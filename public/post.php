<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autotests</title>
  </head>
  <body>
	<?php
	/**
	 * POST.PHP
	 * Stores test results sent by Jenkins/TestComplete
	 * PDO version (config.php) with Maven retry support:
	 * - Single : UPDATE if JJob+JParam+Build+TCProj already exists (retry), INSERT otherwise
	 * - Main   : DELETE restricted to Main + INSERT (Single history preserved)
	 * - History preserved: each distinct JParam = distinct execution
	 *
	 * Running flag: once a new result has been stored, the previous runs of the
	 * same TestSet / Scenario are reset (running = 0) so a row left in "Running"
	 * state by a rerun does not stay stuck forever (see resetPreviousRunning()).
	 */

	require_once '../../_config/config.php';

	// Helper: strip surrounding single quotes if present
	function unquote($value) {
		if ($value === null) return '';
		$value = trim($value);

		if (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") {
			$value = substr($value, 1, -1);
		}
		return $value;
	}

	/**
	 * Reset the "running" flag on the PREVIOUS runs of the same test.
	 *
	 * Called right after a new result has been stored. Scope of the reset:
	 *  - Main   : same TestSet name (TCProj) + same Jenkins job + same Browser
	 *  - Single : same Scenario name (TCProj) + same Jenkins job + same Browser
	 * Only older rows are touched (AutoID < the row just written), so the
	 * current run is never affected.
	 *
	 * Fails silently (error_log only): a problem here must never break the
	 * result storage nor pollute the response sent back to Jenkins.
	 */
	function resetPreviousRunning(PDO $pdo, $table, $testLogTyp, $tcproj, $jjob, $browser, $currentAutoID) {
		if (!$currentAutoID) return 0;

		try {
			$stmt = $pdo->prepare(
				"UPDATE `$table`
				 SET `running` = 0
				 WHERE TestLogTyp = :typ
				   AND TCProj     = :tcproj
				   AND JJob       = :jjob
				   AND Browser    = :browser
				   AND `running` <> 0
				   AND AutoID     < :autoid"
			);
			$stmt->execute([
				':typ'     => $testLogTyp,
				':tcproj'  => $tcproj,
				':jjob'    => $jjob,
				':browser' => $browser,
				':autoid'  => $currentAutoID,
			]);
			return $stmt->rowCount();
		} catch (PDOException $e) {
			error_log("post.php resetPreviousRunning error: " . $e->getMessage());
			return 0;
		}
	}

	/**
	 * Reset the "running" flag on the scenarios (Single) of the PREVIOUS
	 * executions of a TestSet.
	 *
	 * Called only when the Main row is stored (= end of the run), so scenarios
	 * of the run currently in progress are never reset too early.
	 * Previous executions are identified by the JJob+JParam pairs of the older
	 * Main rows of the same TestSet; the current execution (:jparam) is excluded.
	 *
	 * Useful for a scenario left in "Running" state that was not part of the
	 * new run (individual rerun that never posted a result, aborted job, ...).
	 */
	function resetPreviousScenariosRunning(PDO $pdo, $table, $tcproj, $jparam) {
		try {
			$stmt = $pdo->prepare(
				"UPDATE `$table` AS s
				 INNER JOIN (
				     SELECT DISTINCT JJob, JParam
				     FROM `$table`
				     WHERE TestLogTyp = 'Main' AND TCProj = :tcproj
				 ) AS m ON s.JJob = m.JJob AND s.JParam = m.JParam
				 SET s.`running` = 0
				 WHERE s.TestLogTyp = 'Single'
				   AND s.`running` <> 0
				   AND s.JParam <> :jparam"
			);
			$stmt->execute([':tcproj' => $tcproj, ':jparam' => $jparam]);
			return $stmt->rowCount();
		} catch (PDOException $e) {
			error_log("post.php resetPreviousScenariosRunning error: " . $e->getMessage());
			return 0;
		}
	}

	/**
	 * Parse a RunDuration value into seconds + its format, so that two
	 * durations can be added and written back in the format Jenkins sent.
	 * Supported: "754" / "754.2" (numeric, unit kept as-is), "H:MM:SS", "MM:SS",
	 * and labelled text such as "1 hr 2 min 3 sec", "12 min 34 sec", "1h 2m 3s".
	 * Returns null when the value cannot be parsed.
	 */
	function parseDuration($value) {
		$v = trim((string)$value);
		if ($v === '') return null;

		if (is_numeric($v)) {
			return ['seconds' => (float)$v, 'format' => 'numeric'];
		}
		if (preg_match('/^(\d+):(\d{1,2}):(\d{1,2})$/', $v, $m)) {
			return ['seconds' => $m[1] * 3600 + $m[2] * 60 + $m[3], 'format' => 'hms'];
		}
		if (preg_match('/^(\d+):(\d{1,2})$/', $v, $m)) {
			return ['seconds' => $m[1] * 60 + $m[2], 'format' => 'ms'];
		}

		// Labelled text: every token must be "<number> <unit>"
		$pattern = '/(\d+(?:[.,]\d+)?)\s*(ms|milliseconds?|h|hrs?|hours?|m|mins?|minutes?|s|secs?|seconds?)\b/i';
		if (preg_match_all($pattern, $v, $all, PREG_SET_ORDER)) {
			$rest = trim(preg_replace($pattern, '', $v), " \t,;");
			if ($rest !== '') return null;   // unknown content -> do not guess

			$seconds = 0.0;
			$labels  = [];
			foreach ($all as $tok) {
				$num  = (float)str_replace(',', '.', $tok[1]);
				$unit = strtolower($tok[2]);
				if (preg_match('/^(ms|millisecond)/', $unit))      { $seconds += $num / 1000; $labels['ms'] = $tok[2]; }
				elseif (preg_match('/^h/', $unit))                  { $seconds += $num * 3600; $labels['h'] = $tok[2]; }
				elseif (preg_match('/^m/', $unit))                  { $seconds += $num * 60;   $labels['m'] = $tok[2]; }
				else                                                { $seconds += $num;        $labels['s'] = $tok[2]; }
			}
			// Keep the input's spacing style ("12 min" vs "12m")
			$sep = preg_match('/\d\s+[a-z]/i', $v) ? ' ' : '';
			return ['seconds' => $seconds, 'format' => 'text', 'labels' => $labels, 'sep' => $sep];
		}
		return null;
	}

	/** Format a number of seconds using the format descriptor from parseDuration(). */
	function formatDuration($seconds, array $fmt) {
		switch ($fmt['format']) {
			case 'numeric':
				return (floor($seconds) == $seconds) ? (string)(int)$seconds : (string)round($seconds, 3);
			case 'hms':
				$s = (int)round($seconds);
				return sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
			case 'ms':
				$s = (int)round($seconds);
				return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
			default: // text
				$l   = $fmt['labels'] + ['h' => 'hr', 'm' => 'min', 's' => 'sec'];
				$sep = $fmt['sep'];
				$s   = (int)round($seconds);
				$h   = intdiv($s, 3600);
				$m   = intdiv($s % 3600, 60);
				$sec = $s % 60;
				$parts = [];
				if ($h > 0)                          $parts[] = $h . $sep . $l['h'];
				if ($m > 0 || ($h > 0 && $sec > 0))  $parts[] = $m . $sep . $l['m'];
				if ($sec > 0 || empty($parts))       $parts[] = $sec . $sep . $l['s'];
				return implode(' ', $parts);
		}
	}

	/**
	 * Add two RunDuration values. The result uses the format of $new.
	 * If either value cannot be parsed, $new is returned unchanged
	 * (= previous behavior, never worse than before).
	 */
	function addDurations($previous, $new) {
		$p = parseDuration($previous);
		$n = parseDuration($new);
		if ($p === null || $n === null) {
			error_log("post.php addDurations: cannot parse '$previous' + '$new', keeping '$new'");
			return $new;
		}
		// Numeric values are unit-less: only add them together
		if (($p['format'] === 'numeric') !== ($n['format'] === 'numeric')) {
			error_log("post.php addDurations: mixed formats '$previous' + '$new', keeping '$new'");
			return $new;
		}
		if ($n['format'] === 'text' && $p['format'] === 'text') {
			$n['labels'] += $p['labels'];   // reuse unit labels seen in either value
		}
		return formatDuration($p['seconds'] + $n['seconds'], $n);
	}

	// Read and clean GET parameters
	$JJob             = unquote($_GET['JJob'] ?? '');
	$LogVersion       = $_GET['LogVersion'] ?? '';   // table name, no quotes
	$JBuild           = unquote($_GET['JBuild'] ?? '');
	$JParam           = unquote($_GET['JParam'] ?? '');
	$TestNode         = unquote($_GET['TestNode'] ?? '');
	$TCProj           = unquote($_GET['TCProj'] ?? '');
	$Build            = unquote($_GET['Build'] ?? '');
	$TearDownFailed   = unquote($_GET['TearDownFailed'] ?? '');
	$TearDownCanceled = unquote($_GET['TearDownCanceled'] ?? '');
	$TearDownWarning  = unquote($_GET['TearDownWarning'] ?? '');
	$TearDownPassed   = unquote($_GET['TearDownPassed'] ?? '');
	$LogLink          = unquote($_GET['LogLink'] ?? '');
	$RunDate          = unquote($_GET['RunDate'] ?? '');
	$RunDuration      = unquote($_GET['RunDuration'] ?? '');
	$Version          = unquote($_GET['Version'] ?? '');
	$Product          = unquote($_GET['Product'] ?? '');
	$gWVersion        = unquote($_GET['gWVersion'] ?? '');
	$TestLogTyp       = unquote($_GET['TestLogTyp'] ?? '');
	$Testtype         = unquote($_GET['Testtype'] ?? '');
	$Browser          = unquote($_GET['Browser'] ?? '');
	$tag              = unquote($_GET['tag'] ?? '');
	$teamtag          = unquote($_GET['teamtag'] ?? '');
	$DBServer 		  = unquote($_GET['DBServer'] ?? '');
	
	// Default values for tag/teamtag
	if ($tag === '')     $tag = '-';
	if ($teamtag === '') $teamtag = '-';
	if ($DBServer === '') $DBServer = 'SQL';

	// Retry flag: sent by the Selenium project on the 2nd post (retry of failed scenarios)
	$isRetry = in_array(strtolower(unquote($_GET['Retry'] ?? '')), ['1', 'true', 'yes'], true);

	// Decode TCProj depending on product/version (Web/SmartWe)
	$isWebOrWe = ($Product === 'gWWebSel' || $Product === 'weWebSel');
	$webVersions = ['x18', 'x17', 'x16', 'we'];

	if ($isWebOrWe && in_array($Version, $webVersions)) {
		$TCProj = urldecode($TCProj);
	}

	// gWClient: no browser
	if ($Product === 'gWClient') {
		$Browser = '';
	}

	// Replace Grid with Grid-x.7 in the LogLink
	$LogLink = str_replace('Grid', 'Grid-x.7', $LogLink);

	// For gWClient: resolve the table (LogVersion) from the Testtype
	// Centralized mapping in _config/versions_config.php ($LOGG_GWCLIENT_MAP)
	if ($Product === 'gWClient') {
		if (isset($LOGG_GWCLIENT_MAP[$Testtype])) {
			$LogVersion = $LOGG_GWCLIENT_MAP[$Testtype];
		}
	}

	// Validate the table name (security: letters, digits and underscore only)
	if (!preg_match('/^[a-z0-9_]+$/i', $LogVersion)) {
		echo "Error: Invalid table name '" . htmlspecialchars($LogVersion) . "'";
		exit;
	}

	try {

		if ($Product === 'gWClient') {
			// gWClient: direct INSERT (no retry handling, no tag/teamtag)
			$stmt = $pdo->prepare(
				"INSERT INTO `$LogVersion`
				 (JJob, JBuild, JParam, TCProj, Version, Product, gWVersion, TestNode, Build,
				  TearDownFailed, TearDownCanceled, TearDownWarning, TearDownPassed,
				  RunDate, RunDuration, LogLink, TestLogTyp, Testtype, Browser, tag, teamtag, DBServer)
				 VALUES
				 (:jjob, :jbuild, :jparam, :tcproj, :version, :product, :gwversion, :testnode, :build,
				  :failed, :canceled, :warning, :passed,
				  :rundate, :runduration, :loglink, :testlogtyp, :testtype, :browser, :tag, :teamtag, :dbserver)"
			);
			$stmt->execute([
				':jjob'      => $JJob,      ':jbuild'     => $JBuild,    ':jparam'    => $JParam,
				':tcproj'    => $TCProj,    ':version'    => $Version,   ':product'   => $Product,
				':gwversion' => $gWVersion, ':testnode'   => $TestNode,  ':build'     => $Build,
				':failed'    => $TearDownFailed,   ':canceled'   => $TearDownCanceled,
				':warning'   => $TearDownWarning,  ':passed'     => $TearDownPassed,
				':rundate'   => $RunDate,   ':runduration' => $RunDuration,
				':loglink'   => $LogLink,   ':testlogtyp' => $TestLogTyp,
				':testtype'  => $Testtype,  ':browser'    => $Browser,
				':tag' => $tag, ':teamtag' => $teamtag, ':dbserver' => $DBServer,
			]);

			// New result stored -> the previous runs are no longer "Running"
			resetPreviousRunning($pdo, $LogVersion, $TestLogTyp, $TCProj, $JJob, $Browser, $pdo->lastInsertId());

		} elseif ($TestLogTyp === 'Single') {
			// Individual scenario: UPDATE if same execution (retry), INSERT otherwise
			// Uniqueness key: JJob + JParam + Build + TCProj
			$checkStmt = $pdo->prepare(
				"SELECT AutoID FROM `$LogVersion`
				 WHERE JJob = :jjob AND JParam = :jparam AND Build = :build
				 AND TCProj = :tcproj AND TestLogTyp = 'Single'
				 LIMIT 1"
			);
			$checkStmt->execute([
				':jjob'   => $JJob,
				':jparam' => $JParam,
				':build'  => $Build,
				':tcproj' => $TCProj,
			]);
			$existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

			if ($existing) {
				// Retry: update only the result columns
				$updStmt = $pdo->prepare(
					"UPDATE `$LogVersion`
					 SET TearDownFailed   = :failed,
					     TearDownCanceled = :canceled,
					     TearDownWarning  = :warning,
					     TearDownPassed   = :passed,
					     RunDate          = :rundate,
					     RunDuration      = :runduration,
					     LogLink          = :loglink,
					     `running`        = 0
					 WHERE AutoID = :autoid"
				);
				$updStmt->execute([
					':failed'      => $TearDownFailed,
					':canceled'    => $TearDownCanceled,
					':warning'     => $TearDownWarning,
					':passed'      => $TearDownPassed,
					':rundate'     => $RunDate,
					':runduration' => $RunDuration,
					':loglink'     => $LogLink,
					':autoid'      => $existing['AutoID'],
				]);

				// Retry finished -> older runs of this scenario are no longer "Running"
				resetPreviousRunning($pdo, $LogVersion, 'Single', $TCProj, $JJob, $Browser, $existing['AutoID']);
			} else {
				// New run: normal INSERT
				$stmt = $pdo->prepare(
					"INSERT INTO `$LogVersion`
					 (JJob, JBuild, JParam, TCProj, Version, Product, gWVersion, TestNode, Build,
					  TearDownFailed, TearDownCanceled, TearDownWarning, TearDownPassed,
					  RunDate, RunDuration, LogLink, TestLogTyp, Testtype, Browser, tag, teamtag, DBServer)
					 VALUES
					 (:jjob, :jbuild, :jparam, :tcproj, :version, :product, :gwversion, :testnode, :build,
					  :failed, :canceled, :warning, :passed,
					  :rundate, :runduration, :loglink, :testlogtyp, :testtype, :browser, :tag, :teamtag, :dbserver)"
				);
				$stmt->execute([
					':jjob'      => $JJob,      ':jbuild'      => $JBuild,    ':jparam'    => $JParam,
					':tcproj'    => $TCProj,    ':version'     => $Version,   ':product'   => $Product,
					':gwversion' => $gWVersion, ':testnode'    => $TestNode,  ':build'     => $Build,
					':failed'    => $TearDownFailed,   ':canceled'    => $TearDownCanceled,
					':warning'   => $TearDownWarning,  ':passed'      => $TearDownPassed,
					':rundate'   => $RunDate,   ':runduration' => $RunDuration,
					':loglink'   => $LogLink,   ':testlogtyp'  => $TestLogTyp,
					':testtype'  => $Testtype,  ':browser'     => $Browser,
					':tag' => $tag, ':teamtag' => $teamtag, ':dbserver' => $DBServer,
				]);

				// New scenario result -> older runs of this scenario are no longer "Running"
				resetPreviousRunning($pdo, $LogVersion, 'Single', $TCProj, $JJob, $Browser, $pdo->lastInsertId());
			}

		} else {
			// TestLogTyp = Main: restricted DELETE on Main + INSERT
			// (the original DELETE without TestLogTyp also wiped Single rows - fixed)

			// Duration of a run = first pass + retry of the failed scenarios.
			// The Selenium project posts the Main row twice per execution:
			//   1st call (no Retry param) -> first pass: its duration REPLACES the
			//                                stored one (new execution, even when
			//                                the same build is relaunched)
			//   2nd call (&Retry=1)       -> retry pass: its duration is ADDED to the
			//                                duration of the Main row it replaces
			// The row read here is the one the DELETE below removes (same key).
			if ($isRetry) {
				$stmtPrev = $pdo->prepare(
					"SELECT RunDuration FROM `$LogVersion`
					 WHERE TCProj = :tcproj AND Build = :build AND TestLogTyp = 'Main'
					 ORDER BY AutoID DESC
					 LIMIT 1"
				);
				$stmtPrev->execute([':tcproj' => $TCProj, ':build' => $Build]);
				$prevMain = $stmtPrev->fetch(PDO::FETCH_ASSOC);
				if ($prevMain && trim((string)$prevMain['RunDuration']) !== '') {
					$RunDuration = addDurations($prevMain['RunDuration'], $RunDuration);
				}
			}

			$stmtDel = $pdo->prepare(
				"DELETE FROM `$LogVersion`
				 WHERE TCProj = :tcproj AND Build = :build AND TestLogTyp = 'Main'"
			);
			$stmtDel->execute([':tcproj' => $TCProj, ':build' => $Build]);

			$stmt = $pdo->prepare(
				"INSERT INTO `$LogVersion`
				 (JJob, JBuild, JParam, TCProj, Version, Product, gWVersion, TestNode, Build,
				  TearDownFailed, TearDownCanceled, TearDownWarning, TearDownPassed,
				  RunDate, RunDuration, LogLink, TestLogTyp, Testtype, Browser, tag, teamtag, DBServer)
				 VALUES
				 (:jjob, :jbuild, :jparam, :tcproj, :version, :product, :gwversion, :testnode, :build,
				  :failed, :canceled, :warning, :passed,
				  :rundate, :runduration, :loglink, :testlogtyp, :testtype, :browser, :tag, :teamtag, :dbserver)"
			);
			$stmt->execute([
				':jjob'      => $JJob,      ':jbuild'      => $JBuild,    ':jparam'    => $JParam,
				':tcproj'    => $TCProj,    ':version'     => $Version,   ':product'   => $Product,
				':gwversion' => $gWVersion, ':testnode'    => $TestNode,  ':build'     => $Build,
				':failed'    => $TearDownFailed,   ':canceled'    => $TearDownCanceled,
				':warning'   => $TearDownWarning,  ':passed'      => $TearDownPassed,
				':rundate'   => $RunDate,   ':runduration' => $RunDuration,
				':loglink'   => $LogLink,   ':testlogtyp'  => $TestLogTyp,
				':testtype'  => $Testtype,  ':browser'     => $Browser,
				':tag' => $tag, ':teamtag' => $teamtag, ':dbserver' => $DBServer,
			]);

			// End of the run -> previous TestSet runs are no longer "Running"
			resetPreviousRunning($pdo, $LogVersion, 'Main', $TCProj, $JJob, $Browser, $pdo->lastInsertId());

			// ... and their scenarios left in "Running" state (aborted job,
			// individual rerun that never posted a result, ...).
			// Comment out this line to keep the reset strictly TestSet-level.
			resetPreviousScenariosRunning($pdo, $LogVersion, $TCProj, $JParam);
		}

		// Success (silent)
		// echo "New record added successfully";

	} catch (PDOException $e) {
		echo "Error: " . htmlspecialchars($e->getMessage());
		error_log("post.php error: " . $e->getMessage());
	}
	?>
  </body>
</html>