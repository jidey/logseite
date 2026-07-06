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
	 * Enregistre les résultats de tests envoyés par Jenkins/TestComplete
	 * Version PDO (config.php) avec support retry Maven :
	 * - Single : UPDATE si JJob+JParam+Build+TCProj existe déjà (retry), INSERT sinon
	 * - Main   : DELETE restreint au Main + INSERT (historique Single préservé)
	 * - Historique préservé : chaque JParam distinct = exécution distincte
																				  
	 */

	require_once '../config/config.php';

	// Helper : retirer les quotes simples englobantes éventuelles
	function unquote($value) {
		if ($value === null) return '';
		$value = trim($value);
													
		if (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") {
			$value = substr($value, 1, -1);
		}
		return $value;
	}

	// Récupérer et nettoyer les paramètres GET
	$JJob             = unquote($_GET['JJob'] ?? '');
	$LogVersion       = $_GET['LogVersion'] ?? '';   // nom de table, pas de quotes
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

	// Valeurs par défaut pour tag/teamtag
	if ($tag === '')     $tag = '-';
	if ($teamtag === '') $teamtag = '-';

	// Décoder le TCProj selon le produit/version (Web/SmartWe)
	$isWebOrWe = ($Product === 'gWWebSel' || $Product === 'weWebSel');
	$webVersions = ['x17', 'x16', 'x15', 'x14', 'x13', 'x12', 'x11', 'we'];

	if ($isWebOrWe && in_array($Version, $webVersions)) {
		$TCProj = urldecode($TCProj);
	}
	
	// gWClient : pas de browser
	if ($Product === 'gWClient') {
		$Browser = '';
	}

	// Remplacer Grid par Grid-x.7 dans le LogLink
	$LogLink = str_replace('Grid', 'Grid-x.7', $LogLink);

	// Pour gWClient : déterminer la table (LogVersion) selon le Testtype
	if ($Product === 'gWClient') {
		$gwClientMap = [
			'hf_x14'  => 'x14_gwhf',
			'rc_x14'  => 'x14_gwrc',
			'hf_x15'  => 'x15_gwhf',
			'rc_x15'  => 'x15_gwrc',
			'hf_x16'  => 'x16_gwhf',
			'rc_x16'  => 'x16_gwrc',
			'dev_x16' => 'x16_gwdev',
			'hf_x17'  => 'x17_gwhf',
			'rc_x17'  => 'x17_gwrc',
			'dev_x17' => 'x17_gwdev',
			'hf_x18'  => 'x18_gwhf',
			'rc_x18'  => 'x18_gwrc',
			'dev_x18' => 'x18_gwdev',
		];
		if (isset($gwClientMap[$Testtype])) {
			$LogVersion = $gwClientMap[$Testtype];
		}
	}

	// Valider le nom de table (sécurité : seuls lettres, chiffres, underscore)
	if (!preg_match('/^[a-z0-9_]+$/i', $LogVersion)) {
		echo "Error: Invalid table name '" . htmlspecialchars($LogVersion) . "'";
		exit;
	}

	try {
														   
		if ($Product === 'gWClient') {
			// gWClient : INSERT direct (pas de retry géré, pas de tag/teamtag)
			$stmt = $pdo->prepare(
				"INSERT INTO `$LogVersion`
				 (JJob, JBuild, JParam, TCProj, Version, Product, gWVersion, TestNode, Build,
				  TearDownFailed, TearDownCanceled, TearDownWarning, TearDownPassed,
				  RunDate, RunDuration, LogLink, TestLogTyp, Testtype, Browser)
				 VALUES
				 (:jjob, :jbuild, :jparam, :tcproj, :version, :product, :gwversion, :testnode, :build,
				  :failed, :canceled, :warning, :passed,
				  :rundate, :runduration, :loglink, :testlogtyp, :testtype, :browser)"
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
			]);

																				 
		} elseif ($TestLogTyp === 'Single') {
			// Scénario individuel : UPDATE si même exécution (retry), INSERT sinon
			// Clé d'unicité : JJob + JParam + Build + TCProj
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
				// Retry : mettre à jour uniquement les colonnes résultat
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
				// Nouveau run : INSERT normal
				$stmt = $pdo->prepare(
					"INSERT INTO `$LogVersion`
					 (JJob, JBuild, JParam, TCProj, Version, Product, gWVersion, TestNode, Build,
					  TearDownFailed, TearDownCanceled, TearDownWarning, TearDownPassed,
					  RunDate, RunDuration, LogLink, TestLogTyp, Testtype, Browser, tag, teamtag)
					 VALUES
					 (:jjob, :jbuild, :jparam, :tcproj, :version, :product, :gwversion, :testnode, :build,
					  :failed, :canceled, :warning, :passed,
					  :rundate, :runduration, :loglink, :testlogtyp, :testtype, :browser, :tag, :teamtag)"
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
					':tag'       => $tag,       ':teamtag'     => $teamtag,
				]);
			}

		} else {
			// TestLogTyp = Main : DELETE restreint au Main + INSERT
			// (le DELETE original sans TestLogTyp écrasait aussi les Single — corrigé)
			$stmtDel = $pdo->prepare(
				"DELETE FROM `$LogVersion`
				 WHERE TCProj = :tcproj AND Build = :build AND TestLogTyp = 'Main'"
			);
			$stmtDel->execute([':tcproj' => $TCProj, ':build' => $Build]);

			$stmt = $pdo->prepare(
				"INSERT INTO `$LogVersion`
				 (JJob, JBuild, JParam, TCProj, Version, Product, gWVersion, TestNode, Build,
				  TearDownFailed, TearDownCanceled, TearDownWarning, TearDownPassed,
				  RunDate, RunDuration, LogLink, TestLogTyp, Testtype, Browser, tag, teamtag)
				 VALUES
				 (:jjob, :jbuild, :jparam, :tcproj, :version, :product, :gwversion, :testnode, :build,
				  :failed, :canceled, :warning, :passed,
				  :rundate, :runduration, :loglink, :testlogtyp, :testtype, :browser, :tag, :teamtag)"
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
				':tag'       => $tag,       ':teamtag'     => $teamtag,
			]);
		}

		// Succès (silencieux)
		// echo "New record added successfully";

	} catch (PDOException $e) {
		echo "Error: " . htmlspecialchars($e->getMessage());
		error_log("post.php error: " . $e->getMessage());
	}
	?>
  </body>
</html>