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
					     LogLink          = :loglink
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