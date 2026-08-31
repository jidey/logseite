<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQS Dashboard</title>
    <!-- Theme init (before CSS to avoid the flash) -->
    <script src="js/theme.js"></script>
    <!-- Include Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/theme.css" rel="stylesheet">
	<style type="text/css">
		.tg  {
		  border-collapse:collapse;
		  border-spacing:0;
		  margin:0 auto;
		  width:100%;
		  max-width:100%;
		  table-layout:fixed;   /* columns share the width evenly */
		}
		
		.tg td{
		  border-color:black;border-style:solid;border-width:2px;
		  font-family:Arial, sans-serif;
		  font-size:clamp(11px, 1.1vw, 18px);   /* shrinks with the viewport */
		  overflow:hidden;
		  padding:clamp(4px, 0.6vw, 14px) clamp(2px, 0.5vw, 18px);
		  word-break:break-word;
		}
		.tg th{
		  border-color:black;border-style:solid;border-width:2px;
		  font-family:Arial, sans-serif;
		  font-size:clamp(11px, 1.1vw, 18px);
		  font-weight:normal;
		  overflow:hidden;
		  padding:clamp(4px, 0.6vw, 14px) clamp(2px, 0.5vw, 18px);
		  word-break:break-word;
		}
		
		/* First column (Branch / Passed / Failed labels) stays narrow */
		.tg td:first-child, .tg th:first-child { width:90px; }
		
		/* Images in the header scale down too */
		.tg th img { max-width:100%; height:auto; }
		
		.tg .testfail{background-color:#FFCCC9;border-color:#000000;color:#ffffff;font-weight:bold;text-align:center;vertical-align:top}
		.tg .testwarn{background-color:#FFE787;border-color:#000000;color:#ffffff;font-weight:bold;text-align:center;vertical-align:top}
		.tg .testok{background-color:#4CFF00;border-color:#000000;color:#ffffff;font-weight:bold;text-align:center;vertical-align:top}
		
		.tg .tg-simple1{border-color:black;color:#808080;text-align:center;vertical-align:center}
		.tg .tg-simple{border-color:black;font-weight:bold;text-align:center;vertical-align:center}

		.tg .tg-disabled{background-color:#5B5B5B;border-color:black;font-weight:bold;text-align:center;vertical-align:top}

		.tg .tg-green{background-color:#9aff99;border-color:black;font-weight:bold;text-align:center;vertical-align:top}
		.tg .tg-red{background-color:#FFCCC9;border-color:black;font-weight:bold;text-align:center;vertical-align:top}
		.tg .tg-casred{background-color:#E72A36;border-color:black;color:#FFFFFF;font-weight:bold;text-align:center;vertical-align:top}
		.tg .tg-warn{background-color:#FFE787;border-color:black;color:#000000;font-weight:bold;text-align:center;vertical-align:top}

		.table a
		{
			display:block;
			text-decoration:none;
		}
		
		/* Version color coding (Branch row) */
		.tg .tg-we   { background-color:#e8f4fd; border-left:3px solid #1e88e5; }
		.tg .tg-x16  { background-color:#f3e5f5; border-left:3px solid #8e24aa; }
		.tg .tg-x17  { background-color:#e8f5e9; border-left:3px solid #43a047; }
		.tg .tg-x18  { background-color:#fff3e0; border-left:3px solid #fb8c00; }
	</style>
</head>

<tbody>
	<?php
	require_once __DIR__ . '/../config/config.php';
	
	if (isset($_GET['refresh']))
	{$refresh = $_GET['refresh'];}
	else{$refresh = "false";}
	
	function isItTimeToGetLastRuns() {
		global $pdo;
		// Protégé : si `we_dev_daily` n'existe pas/plus (table renommée ou pas
		// encore créée), on force un refresh au lieu de faire planter toute la
		// page (voir aussi readLastRunResults/getLastResults ci-dessous).
		try {
			$sql="SELECT timestamp FROM `we_dev_daily` ORDER BY `index` DESC LIMIT 1";
			$results = $pdo->query($sql);

			$timestamp = null;
			while ($row = $results->fetch(PDO::FETCH_ASSOC)) {
				$timestamp=$row["timestamp"];
			}

			if (empty($timestamp)) {
				return true; // pas encore de donnée -> tenter un refresh
			}

			// Current date and time
			$currentDateTime = new DateTime();

			// Convert timestamp to DateTime
			$timestampDateTime = new DateTime();
			$timestampDateTime->setTimestamp(strtotime($timestamp));

			// Calculate the difference
			$interval = $currentDateTime->diff($timestampDateTime);

			// Get the total minutes
			$totalMinutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;

			// Check if the time difference is more than 60 minutes
			if ($totalMinutes > 120) {
				return true;
			} else {
				return false;
			}
		} catch (PDOException $e) {
			error_log("isItTimeToGetLastRuns error: " . $e->getMessage());
			return false; // évite de retenter à chaque chargement si la table est cassée
		}
	}

	function getBranchVersionWe($tag) { //Problem with file_get_contents(): SSL: 
		$url = "https://dcs-versiontool.internalk8s.home.cas.de/smartwe-versions/versions/search/findByTag?tag=$tag";			
		
		$context = stream_context_create([
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true,
				'allow_self_signed' => false,
				'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
			],
		]);
		$jsonData = @file_get_contents($url, false, $context);

		// If the request fails (SSL, network, timeout), return null
		if ($jsonData === false) {
			error_log("file_get_contents failed for tag: $tag (SSL/network issue)");
			return null;
		}

		$data = json_decode($jsonData, true);

		if ($data !== null) {
			// Check that the expected structure exists
			if (!isset($data['_embedded']['versions'][0])) {
				error_log("Unexpected JSON structure for tag: $tag");
				return null;
			}
			
			$versionData = $data['_embedded']['versions'][0];
			$version = $versionData['smartDesignVersion'] ?? '';
			$sdversion = explode("-", $version);
			$branch = $versionData['branch'] ?? '';
			$commitId = $versionData['commitId'] ?? '';
			$wecommit = substr($commitId, 0, 6); // start from position 6
			
			return [$branch, $sdversion[0] ?? '', $wecommit];		
		} else {
			error_log("Invalid JSON data for tag: $tag");
			return null;
		}
	}

	function getBranchVersion($tag){
		// Construct the full path to the file
		$filePath = "deployedVM/last" . $tag . "Deploy.txt";
		// Check if the file exists
		if (file_exists($filePath)) {
			// Read the content of the file
			return file_get_contents($filePath);
		} else {
			// Fichier de déploiement pas encore créé pour cette branche (ex:
			// version toute juste ajoutée dans versions_config.php, nightly
			// pas encore configuré) : on n'affiche plus l'erreur brute dans
			// la page, juste une valeur vide.
			return '';
		}
	}
	
	function getColorClass($failed) {
		if ($failed == 0) {
			return "tg-green";
		} else {
			return "testfail";
		}
	}

	function generateResultCell($url, $failed) {
		$color = getColorClass($failed);
		// Add ErrorOnly=1 to filter the failing tests directly
		$separator = (strpos($url, '?') !== false) ? '&' : '?';
		$urlWithFilter = $url . $separator . 'ErrorOnly=1';
		echo "<td class=".$color."><a href=\"".$urlWithFilter."\" style=\"display:block;\">".$failed."</a></td>";
	}
	
	function readLastRunResults($table) {
		global $pdo;
		// Protégé : une table "..._daily" manquante (version retirée/pas encore
		// créée) affiche des cases vides au lieu de faire planter toute la page.
		try {
			$sql="SELECT * FROM `".$table."_daily` ORDER BY `index` DESC LIMIT 1";
			$results = $pdo->query($sql);

			$passed="";
			$failed="";

			while ($row = $results->fetch(PDO::FETCH_ASSOC)) {
				$passed=$row["passed"];
				$failed=$row["failed"];
			}
			return[$passed,$failed];
		} catch (PDOException $e) {
			error_log("readLastRunResults('$table') error: " . $e->getMessage());
			return ['', ''];
		}
	}

	function getLastResults($table, $testType, $product, $job, $Branch) {
		global $pdo;
		// Protégé : une table de version retirée/pas encore créée renvoie null
		// (comme le cas "aucun résultat" déjà géré ci-dessous) au lieu de faire
		// planter toute la page.
		try {
			$query = "SELECT l1.* FROM `$table` as l1
					  LEFT JOIN `$table` as l2 ON (l1.JParam = l2.JParam AND l1.jjob = l2.jjob
					  AND l1.TestLogTyp = l2.TestLogTyp AND l1.Testtype = l2.Testtype AND l1.AutoID < l2.AutoID)
					  WHERE ((l1.JJob = 'Autotests-$product-$job'
					  AND l1.Testtype = '$testType' AND l1.TestLogTyp = 'Main' AND l2.AutoID is NULL))
					  ORDER BY l1.RunDate DESC, l1.JBuild DESC";

			//echo $query."<br>";

			$stmt = $pdo->query($query);
			$sqlTestsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			error_log("getLastResults('$table') error: " . $e->getMessage());
			return null;
		}

		if (!empty($sqlTestsList)) {
			$passed = 0;
			$failed = 0;

			foreach ($sqlTestsList as $row) {
				$passed += intval($row["TearDownPassed"]) + intval($row["TearDownWarning"]);
				$failed += intval($row["TearDownFailed"]);
			}

			$total = $passed + $failed;
			$failedPercentage = round($failed / $total * 100, 0);

			$runn="https://sqs-sel-cent1.cas-software.dev/logg/public/dailyStats.php?passed=".$passed."&failed=".$failed."&percent=".$failedPercentage."&Branch=".urlencode($Branch)."&table=".$table."_daily";
			$streamContext = stream_context_create([
				'ssl' => [
				'verify_peer'      => false,
				'verify_peer_name' => false,
				'method'  => 'POST'
				]
			]);

			//echo $runn."<br>";
			file_get_contents($runn, false, $streamContext);
			return [$passed,$failed];
		}
		return null;
	}
	
	//Get Build Version Number
	$WeDevVersion = getBranchVersion("Wewedev");		
	$WeRCVersion = getBranchVersion("Wewerc");
	$WeHFVersion = getBranchVersion("Wewehf");
	
	[$WeDevVersion, $sdweDEV, $commitIdDEV] = getBranchVersionWe("dev") ?? ['', '', ''];						
	[$WeRCVersion, $sdweRC, $commitIdRC] = getBranchVersionWe("rc") ?? ['', '', ''];
	[$WeHFVersion, $sdweHF, $commitIdHF] = getBranchVersionWe("hotfix") ?? ['', '', ''];
	
	
	// --------------------------------------------------------------------
	// gW Web (colonnes "sd.png") : construit dynamiquement à partir de
	// config/versions_config.php. Ordre par colonne : HF, RC, DEV, groupé
	// par version croissante (x16, x17, x18, x19...) — identique à l'ordre
	// historique. Ajouter une version dans $LOGG_VERSIONS (versions_config.php)
	// suffit : elle apparaît ici automatiquement, sans toucher à dash.php.
	// --------------------------------------------------------------------
	$webBranchOrder = ['hf', 'rc', 'dev'];
	$webByVersion = [];
	foreach ($LOGG_VM_BRANCHES as $vmTestType) {
		$vmParts = logg_branch_vm_parts($vmTestType);
		$webByVersion[$vmParts['version']][$vmParts['branch']] = $vmTestType;
	}

	// Classes CSS existantes (voir <style> en haut du fichier) pour x16/x17/x18 ;
	// palette de repli générée automatiquement pour x19 et les versions suivantes
	// (pas de classe CSS dédiée à créer à chaque nouvelle version).
	$webVersionCssClass = ['x16' => 'tg-x16', 'x17' => 'tg-x17', 'x18' => 'tg-x18'];
	$webFallbackPalette = [
		['bg' => '#e0f7fa', 'border' => '#00838f'],
		['bg' => '#fce4ec', 'border' => '#c2185b'],
		['bg' => '#f1f8e9', 'border' => '#689f38'],
	];
	$webFallbackIndex = 0;

	$webBranches = [];
	foreach ($webByVersion as $webVersion => $webBranchesForVersion) {
		if (!isset($webVersionCssClass[$webVersion])) {
			$webFallbackColor = $webFallbackPalette[$webFallbackIndex % count($webFallbackPalette)];
			$webFallbackIndex++;
		}
		foreach ($webBranchOrder as $webBranch) {
			if (!isset($webBranchesForVersion[$webBranch])) {
				continue; // branche retirée / pas encore active pour cette version (ex: dev_x16, rc_x16)
			}
			$webTestType = $webBranchesForVersion[$webBranch];
			$webTable = $product_table_map[$webTestType]['gWWebSel'] ?? null;
			if (!$webTable) {
				continue;
			}
			$webPartsFull = logg_branch_vm_parts($webTestType);

			// Même convention de tag que l'ancien code en dur : "Sel" + branche + numéro
			// (ex: "Selhf17" -> deployedVM/lastSelhf17Deploy.txt)
			$webRawVersion = getBranchVersion('Sel' . $webBranch . $webPartsFull['num']);
			$webPrefix = $LOGG_VERSION_LABEL_PREFIX[$webVersion] ?? null;
			$webLabel = $webPrefix ? str_replace($webPrefix, $webVersion . '.', $webRawVersion) : $webRawVersion;

			$webBranches[] = [
				'testType' => $webTestType,
				'branch'   => $webBranch,
				'version'  => $webVersion,
				'table'    => $webTable,
				'label'    => $webLabel,
				'class'    => $webVersionCssClass[$webVersion] ?? '',
				'style'    => isset($webVersionCssClass[$webVersion]) ? '' :
					('background-color:' . $webFallbackColor['bg'] . ';border-left:3px solid ' . $webFallbackColor['border'] . ';'),
			];
		}
	}
	$webResults = [];
	
	if(isItTimeToGetLastRuns() || $refresh == "true"){	
		[$we_dev_passed,$we_dev_failed] = getLastResults("we_dev","we_dev","We","Grid",$WeDevVersion);
		[$we_rc_passed,$we_rc_failed] = getLastResults("we_rc","we_rc","We","Grid",$WeRCVersion);
		[$we_hf_passed,$we_hf_failed] = getLastResults("we_hf","we_hf","We","Grid",$WeHFVersion);

		// gW Web : idem, piloté par $webBranches (voir plus haut). Le retour est
		// écrasé juste en dessous par readLastRunResults() qui relit le cache
		// "_daily" fraîchement mis à jour par le side-effect de getLastResults()
		// (POST vers dailyStats.php) — comportement identique à l'ancien code.
		foreach ($webBranches as $wb) {
			$webResults[$wb['testType']] = getLastResults($wb['table'], $wb['testType'], "Web", "Grid", $wb['label']);
		}
		
		// No refresh needed, the database is used directly
		// $streamContext = stream_context_create([
		// 	'ssl' => [
		// 	'verify_peer'      => false,
		// 	'verify_peer_name' => false,
		// 	'method'  => 'POST'
		// 	]
		// ]);
		// 
		// $url="https://sqs-sel-cent1.cas-software.dev/logg/public/dash.php";
		// $content = file_get_contents($url,false, $streamContext);
		// //header("Location: " . $url);
	}	
	?>
	<!-- Theme Toggle Button -->
	<button id="themeToggle" class="btn btn-sm btn-outline-secondary theme-toggle-btn" title="Toggle Dark/Light Mode" onclick="toggleTheme()">
		🌙 Dark
	</button>
	<center>
	<br><a href="https://sqs-sel-cent1.cas-software.dev/logg/public/index.php"><< UI-TESTS STATUS Summary</a><br><br>
	</center>	
    <table class="tg">
		<thead>
		  <tr >
			<?php	
			echo "<th class=\"tg-simple1\"><a href=\"https://sqs-sel-cent1.cas-software.dev/logg/public/dash.php?refresh=true\" style=\"display:block;\"><img title=\"Refresh results\" src=\"icons\\refresh.png\"><br>Reload</a></th>";  
			echo "<th class=\"tg-simple\" colspan=\"3\"><img src=\"icons\\we.png\"></th>";
			echo "<th class=\"tg-simple\" colspan=\"" . count($webBranches) . "\"><img src=\"icons\\sd.png\"></th>";
			//echo "<th class=\"tg-simple\" colspan=\"4\"><img src=\"icons\\gW.png\"></th>";
			?>
		  </tr>
		</thead>
		<tbody>
			<?php			
			// Get Values from last backup (Quick)
			[$we_dev_passed,$we_dev_failed] = readLastRunResults("we_dev");
			[$we_rc_passed,$we_rc_failed] = readLastRunResults("we_rc");
			[$we_hf_passed,$we_hf_failed] = readLastRunResults("we_hf");
			
			foreach ($webBranches as $wb) {
				$webResults[$wb['testType']] = readLastRunResults($wb['table']);
			}
			
			//Write results in table
			for ($row = 1; $row <= 3; $row++) {
				echo '<tr>';
					if ($row == 1) {
						echo "<td class=\"tg-simple\" rowspan='1' >Branch</td>";
						echo "<td class=\"tg-simple1 tg-we\">$WeHFVersion (hf) $sdweHF <font size=\"1\">(#$commitIdHF)</font></td>";
						echo "<td class=\"tg-simple1 tg-we\">$WeRCVersion (rc) $sdweRC <font size=\"1\">(#$commitIdRC)</font></td>";
						echo "<td class=\"tg-simple1 tg-we\">$WeDevVersion (dev) $sdweDEV <font size=\"1\">(#$commitIdDEV)</font></td>";
						
						foreach ($webBranches as $wb) {
							$classAttr = $wb['class'] !== '' ? 'tg-simple1 ' . $wb['class'] : 'tg-simple1';
							$styleAttr = $wb['style'] !== '' ? ' style="' . $wb['style'] . '"' : '';
							echo "<td class=\"" . $classAttr . "\"" . $styleAttr . ">" . htmlspecialchars((string) $wb['label']) . " ({$wb['branch']})</td>";
						}
					}

					//PASSED
					if ($row == 2) {
						echo "<td class=\"tg-green\" rowspan='1' >Passed</td>";
						echo "<td class=\"tg-green\">$we_hf_passed</td>";
						echo "<td class=\"tg-green\">$we_rc_passed</td>";
						echo "<td class=\"tg-green\">$we_dev_passed</td>";
						foreach ($webBranches as $wb) {
							$passed = $webResults[$wb['testType']][0] ?? '';
							echo "<td class=\"tg-green\">" . htmlspecialchars((string) $passed) . "</td>";
						}
					}

					if ($row == 3) {
						//FAILED
						echo "<td class=\"tg-casred\" rowspan='1' >Failed</td>";
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=weWebSel&Testtype=" . urlencode($LOGG_SMARTWE_HF) . "&TestBrowser=chrome", $we_hf_failed);
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=weWebSel&Testtype=" . urlencode($LOGG_SMARTWE_RC) . "&TestBrowser=chrome", $we_rc_failed);
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=weWebSel&Testtype=" . urlencode('dev_' . $SMARTWE_CURRENT_VERSION) . "&TestBrowser=chrome", $we_dev_failed);
						foreach ($webBranches as $wb) {
							$failed = $webResults[$wb['testType']][1] ?? '';
							generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=" . urlencode($wb['testType']) . "&TestBrowser=chrome", $failed);
						}
					}
				echo '</tr>';				
			}
			?>
		</tbody>
    </table>
    
    <!-- Include Bootstrap JS and its dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</tbody>
</html>