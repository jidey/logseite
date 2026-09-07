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