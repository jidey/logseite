<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQS Dashboard</title>
    <!-- Theme init (avant CSS pour éviter le flash) -->
    <script src="js/theme.js"></script>
    <!-- Inclure Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/theme.css" rel="stylesheet">
	<style type="text/css">
		.tg  {border-collapse:collapse;border-spacing:0;margin:0px auto;}
		
		.tg td{border-color:black;border-style:solid;border-width:3px;font-family:Arial, sans-serif;font-size:18px;
		  overflow:hidden;padding:14px 18px;word-break:normal;}
		.tg th{border-color:black;border-style:solid;border-width:3px;font-family:Arial, sans-serif;font-size:18px;
		  font-weight:normal;overflow:hidden;padding:14px 18px;word-break:normal;}

		.tg .testfail{background-color:#FFCCC9;border-color:#000000;color:#ffffff;font-weight:bold;text-align:center;vertical-align:top}
		.tg .testwarn{background-color:#FFE787;border-color:#000000;color:#ffffff;font-weight:bold;text-align:center;vertical-align:top}
		.tg .testok{background-color:#4CFF00;border-color:#000000;color:#ffffff;font-weight:bold;text-align:center;vertical-align:top}
		.tg .analyse{background-color:#FFFFFF;border-color:#000000;color:#ffffff;font-weight:bold;text-align:center;vertical-align:top}

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
		$sql="SELECT timestamp FROM `we_dev_daily` ORDER BY `index` DESC LIMIT 1";
		$results = $pdo->query($sql);
		
		while ($row = $results->fetch(PDO::FETCH_ASSOC)) {
			$timestamp=$row["timestamp"];
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
		$jsonData = file_get_contents($url, false, $context);

		$data = json_decode($jsonData, true);

		if ($data !== null) {
			$version = $data['_embedded']['versions'][0]['smartDesignVersion'];
			$sdversion = explode("-", $version);
			$branch = $data['_embedded']['versions'][0]['branch'];
			$commitId = $data['_embedded']['versions'][0]['commitId'];
			$wecommit = substr($commitId, 0, 6); // start from position 6
			
			return [$branch, $sdversion[0], $wecommit];		
		} else {
			http_response_code(400);
			echo "Invalid JSON data for tag: $tag";
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
			echo "File not found: " . htmlspecialchars($filePath);
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
		// Ajouter ErrorOnly=1 pour filtrer directement les tests en échec
		$separator = (strpos($url, '?') !== false) ? '&' : '?';
		$urlWithFilter = $url . $separator . 'ErrorOnly=1';
		echo "<td class=".$color."><a href=\"".$urlWithFilter."\" style=\"display:block;\">".$failed."</a></td>";
	}
	
	function generateAnalyseCell($url, $analyse) {
		$color = "analyse";		
		if ($analyse > 0)
		{
			echo "<td class=\"tg-warn\">".$analyse."</td>";			
		}
		else {
			echo "<td class=\"tg-green\" ><center>0</center></td>";	
		}
		
	}
			
	function readLastRunResults($table) {	
		global $pdo;
		$sql="SELECT * FROM `".$table."_daily` ORDER BY `index` DESC LIMIT 1";
		$results = $pdo->query($sql);
		
		$passed="";
		$failed="";
		$analyse="";
		
		while ($row = $results->fetch(PDO::FETCH_ASSOC)) {
			$passed=$row["passed"];
			$failed=$row["failed"];
			$analyse=$row["analyse"];
		}
		return[$passed,$failed,$analyse];
	}

	function getLastResults($table, $testType, $product, $job, $Branch) {
		global $pdo;
		$query = "SELECT l1.* FROM `$table` as l1 
				  LEFT JOIN `$table` as l2 ON (l1.JParam = l2.JParam AND l1.jjob = l2.jjob 
				  AND l1.TestLogTyp = l2.TestLogTyp AND l1.Testtype = l2.Testtype AND l1.AutoID < l2.AutoID) 
				  WHERE ((l1.JJob = 'Autotests-$product-$job' 
				  AND l1.Testtype = '$testType' AND l1.TestLogTyp = 'Main' AND l2.AutoID is NULL)) 
				  ORDER BY l1.RunDate DESC, l1.JBuild DESC";
		
		$stmt = $pdo->query($query);
		$sqlTestsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (!empty($sqlTestsList)) {
			$passed = 0;
			$failed = 0;
			$analyse = 0;
			foreach ($sqlTestsList as $row) {	
				
				if($row["checked"] == "1"){
					$passed += intval($row["TearDownPassed"]) + intval($row["TearDownWarning"]) + intval($row["TearDownFailed"]);				
				}
				else{
					$passed += intval($row["TearDownPassed"]) + intval($row["TearDownWarning"]);
					$failed += intval($row["TearDownFailed"]);
				}		
			
				
				//Check if failed with empty notes
				if(intval($row["TearDownFailed"]) > 0 && $row["checked"] <> "1")
				{
					//Read Notes
					$notesql="SELECT testnotiz FROM `".$table."_tags` WHERE JParam='".$row["JParam"]."'";			
					$notestmt = $pdo->query($notesql);
					$noterows = $notestmt->fetchAll(PDO::FETCH_ASSOC);
					if (empty($noterows))
					{
						$analyse += intval($row["TearDownFailed"]);
					}
				}			
			}
					
			$total = $passed + $failed;
			$failedPercentage = round($failed / $total * 100, 0);
			
			$runn="https://sqs-sel-cent1.cas-software.dev/logg/public/dailyStats.php?passed=".$passed."&failed=".$failed."&percent=".$failedPercentage."&Branch=".urlencode($Branch)."&table=".$table."_daily&analyse=".$analyse;
			$streamContext = stream_context_create([
				'ssl' => [
				'verify_peer'      => false,
				'verify_peer_name' => false,
				'method'  => 'POST'
				]
			]);
			
			//echo $runn."<br>";					
			file_get_contents($runn, false, $streamContext);
			return [$passed,$failed,$analyse];
		}
		return null;
	}			
	
	//Get Build Version Number
	$WeDevVersion = getBranchVersion("Wewedev");		
	$WeRCVersion = getBranchVersion("Wewerc");
	$WeHFVersion = getBranchVersion("Wewehf");
	
	[$WeDevVersion, $sdweDEV, $commitIdDEV] = getBranchVersionWe("dev");						
	[$WeRCVersion, $sdweRC, $commitIdRC] = getBranchVersionWe("rc");
	[$WeHFVersion, $sdweHF, $commitIdHF] = getBranchVersionWe("hotfix");
	
	
	$WebDevVersion = getBranchVersion("Seldev18");
	//$WebRCVersion = getBranchVersion("Selrc15");
	//$WebHFVersion = getBranchVersion("Selhf15");
	$Web1DevVersion = getBranchVersion("Seldev16");
	$Web1RCVersion = getBranchVersion("Selrc16");
	$Web1HFVersion = getBranchVersion("Selhf16");
	$Web2DevVersion = getBranchVersion("Seldev17");
	$Web2RCVersion = getBranchVersion("Selrc17");
	$Web2HFVersion = getBranchVersion("Selhf17");
	
	/*$gWRCVersion = $WebRCVersion;
	$gWHFVersion = $WebHFVersion;
	$gW1RCVersion = $Web1RCVersion;
	$gW1HFVersion = $Web1HFVersion;
	*/
	
	$WebDevVersion = str_replace("28.", "x18.", $WebDevVersion);
	//$WebRCVersion = str_replace("25.", "x15.", $WebRCVersion);
	//$WebHFVersion = str_replace("25.", "x15.", $WebHFVersion);
	$Web1DevVersion = str_replace("26.", "x16.", $Web1DevVersion);
	$Web1RCVersion = str_replace("26.", "x16.", $Web1RCVersion);
	$Web1HFVersion = str_replace("26.", "x16.", $Web1HFVersion);
	$Web2DevVersion = str_replace("27.", "x17.", $Web2DevVersion);
	$Web2RCVersion = str_replace("27.", "x17.", $Web2RCVersion);
	$Web2HFVersion = str_replace("27.", "x17.", $Web2HFVersion);
	
	if(isItTimeToGetLastRuns() || $refresh == "true"){	
		[$we_dev_passed,$we_dev_failed,$we_dev_analyse] = getLastResults("we_dev","we_dev","We","Grid",$WeDevVersion);
		[$we_rc_passed,$we_rc_failed,$we_rc_analyse] = getLastResults("we_rc","we_rc","We","Grid",$WeRCVersion);
		[$we_hf_passed,$we_hf_failed,$we_hf_analyse] = getLastResults("we_hf","we_hf","We","Grid",$WeHFVersion);

		[$web_dev14_passed,$web_dev14_failed,$web_dev14_analyse] = getLastResults("x18_dev","dev_x18","Web","Grid",$WebDevVersion);
		//[$web_rc14_passed,$web_rc14_failed,$web_rc14_analyse] = getLastResults("x18_rc","rc_x18","Web","Grid",$WebRCVersion);
		//[$web_hf14_passed,$web_hf14_failed,$web_hf14_analyse] = getLastResults("x18_hf","hf_x18","Web","Grid",$WebHFVersion);
		
		[$web_dev13_passed,$web_dev13_failed,$web_dev13_analyse] = getLastResults("x17_dev","dev_x17","Web","Grid",$Web2DevVersion);
		[$web_rc13_passed,$web_rc13_failed,$web_rc13_analyse] = getLastResults("x17_rc","rc_x17","Web","Grid",$Web2RCVersion);
		[$web_hf13_passed,$web_hf13_failed,$web_hf13_analyse] = getLastResults("x17_hf","hf_x17","Web","Grid",$Web2HFVersion);
		
		[$web_dev12_passed,$web_dev12_failed,$web_dev12_analyse] = getLastResults("x16_dev","dev_x16","Web","Grid",$Web1DevVersion);
		[$web_rc12_passed,$web_rc12_failed,$web_rc12_analyse] = getLastResults("x16_rc","rc_x16","Web","Grid",$Web1RCVersion);
		[$web_hf12_passed,$web_hf12_failed,$web_hf12_analyse] = getLastResults("x16_hf","hf_x16","Web","Grid",$Web1HFVersion);
		
		/*[$gw_x15hf_passed,$gw_x15hf_failed,$gw_x15hf_analyse] = getLastResults("x15_gwhf","hf_x15","x15","gW",$gWHFVersion);
		[$gw_x15rc_passed,$gw_x15rc_failed,$gw_x15rc_analyse] = getLastResults("x15_gwrc","rc_x15","x15","gW",$gWRCVersion);
		[$gw_x16hf_passed,$gw_x16hf_failed,$gw_x16hf_analyse] = getLastResults("x16_gwhf","hf_x16","x16","gW",$gW1HFVersion);
		[$gw_x16rc_passed,$gw_x16rc_failed,$gw_x16rc_analyse] = getLastResults("x16_gwrc","rc_x16","x16","gW",$gW1RCVersion);
		*/
		// Pas besoin de rafraîchir, on utilise directement la base de données
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
			echo "<th class=\"tg-simple\" colspan=\"7\"><img src=\"icons\\sd.png\"></th>";
			//echo "<th class=\"tg-simple\" colspan=\"4\"><img src=\"icons\\gW.png\"></th>";
			?>
		  </tr>
		</thead>
		<tbody>
			<?php			
			// Get Values from last backup (Quick)
			[$we_dev_passed,$we_dev_failed,$we_dev_analyse] = readLastRunResults("we_dev");
			[$we_rc_passed,$we_rc_failed,$we_rc_analyse] = readLastRunResults("we_rc");
			[$we_hf_passed,$we_hf_failed,$we_hf_analyse] = readLastRunResults("we_hf");

			
			[$web_dev12_passed,$web_dev12_failed,$web_dev12_analyse] = readLastRunResults("x16_dev");
			[$web_rc12_passed,$web_rc12_failed,$web_rc12_analyse] = readLastRunResults("x16_rc");
			[$web_hf12_passed,$web_hf12_failed,$web_hf12_analyse] = readLastRunResults("x16_hf");
			
			[$web_dev13_passed,$web_dev13_failed,$web_dev13_analyse] = readLastRunResults("x17_dev");
			[$web_rc13_passed,$web_rc13_failed,$web_rc13_analyse] = readLastRunResults("x17_rc");
			[$web_hf13_passed,$web_hf13_failed,$web_hf13_analyse] = readLastRunResults("x17_hf");
			
			//[$web_dev14_passed,$web_dev14_failed,$web_dev14_analyse] = readLastRunResults("x18_dev");
			//[$web_rc14_passed,$web_rc14_failed,$web_rc14_analyse] = readLastRunResults("x18_rc");
			[$web_dev14_passed,$web_dev14_failed,$web_dev14_analyse] = readLastRunResults("x18_dev");
			
			/*[$gw_x15rc_passed,$gw_x15rc_failed,$gw_x15rc_analyse] = readLastRunResults("x15_gwrc");
			[$gw_x15hf_passed,$gw_x15hf_failed,$gw_x15hf_analyse] = readLastRunResults("x15_gwhf");
			[$gw_x16rc_passed,$gw_x16rc_failed,$gw_x16rc_analyse] = readLastRunResults("x16_gwrc");
			[$gw_x16hf_passed,$gw_x16hf_failed,$gw_x16hf_analyse] = readLastRunResults("x16_gwhf");
			*/
			//Write results in table
			for ($row = 1; $row <= 4; $row++) {
				echo '<tr>';
					if ($row == 1) {
						echo "<td class=\"tg-simple\" rowspan='1' >Branch</td>";
						echo "<td class=\"tg-simple1\">$WeHFVersion (hf) $sdweHF <font size=\"1\">(#$commitIdHF)</font></td>";
						echo "<td class=\"tg-simple1\">$WeRCVersion (rc) $sdweRC <font size=\"1\">(#$commitIdRC)</font></td>";
						echo "<td class=\"tg-simple1\">$WeDevVersion (dev) $sdweDEV <font size=\"1\">(#$commitIdDEV)</font></td>";
						
						echo "<td class=\"tg-simple1\">$Web1HFVersion (hf)</td>";
						echo "<td class=\"tg-simple1\">$Web1RCVersion (rc)</td>";
						echo "<td class=\"tg-simple1\">$Web1DevVersion (dev)</td>";
						echo "<td class=\"tg-simple1\">$Web2HFVersion (hf)</td>";
						echo "<td class=\"tg-simple1\">$Web2RCVersion (rc)</td>";
						echo "<td class=\"tg-simple1\">$Web2DevVersion (dev)</td>";
						//echo "<td class=\"tg-simple1\">$WebHFVersion (hf)</td>";
						//echo "<td class=\"tg-simple1\">$WebRCVersion (rc)</td>";
						echo "<td class=\"tg-simple1\">$WebDevVersion (dev)</td>";
						/*echo "<td class=\"tg-simple1\">$gWHFVersion (hf)</td>";
						echo "<td class=\"tg-simple1\">$gWRCVersion (rc)</td>";
						echo "<td class=\"tg-simple1\">$gW1HFVersion (hf)</td>";
						echo "<td class=\"tg-simple1\">$gW1RCVersion (rc)</td>";*/
					}
					
					//PASSED
					if ($row == 2) {
						echo "<td class=\"tg-green\" rowspan='1' >Passed</td>";
						echo "<td class=\"tg-green\">$we_hf_passed</td>";
						echo "<td class=\"tg-green\">$we_rc_passed</td>";
						echo "<td class=\"tg-green\">$we_dev_passed</td>";
						echo "<td class=\"tg-green\">$web_hf12_passed</td>";
						echo "<td class=\"tg-green\">$web_rc12_passed</td>";
						echo "<td class=\"tg-green\">$web_dev12_passed</td>";
						echo "<td class=\"tg-green\">$web_hf13_passed</td>";
						echo "<td class=\"tg-green\">$web_rc13_passed</td>";
						echo "<td class=\"tg-green\">$web_dev13_passed</td>";
						//echo "<td class=\"tg-green\">$web_hf14_passed</td>";
						//echo "<td class=\"tg-green\">$web_rc14_passed</td>";
						echo "<td class=\"tg-green\">$web_dev14_passed</td>";
						/*echo "<td class=\"tg-disabled\">0</td>";
						echo "<td class=\"tg-disabled\">0</td>";
						echo "<td class=\"tg-disabled\">0</td>";
						echo "<td class=\"tg-disabled\">0</td>";	*/					
					}
					
					if ($row == 3) {
						//FAILED			
						echo "<td class=\"tg-casred\" rowspan='1' >Failed</td>";
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=weWebSel&Testtype=hf_x17&TestBrowser=chrome", $we_hf_failed);
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=weWebSel&Testtype=rc_x17&TestBrowser=chrome", $we_rc_failed);
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=weWebSel&Testtype=dev_x18&TestBrowser=chrome", $we_dev_failed);
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=hf_x16&TestBrowser=chrome", $web_hf12_failed);
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=rc_x16&TestBrowser=chrome", $web_rc12_failed);
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=dev_x16&TestBrowser=chrome", $web_dev12_failed);
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=hf_x17&TestBrowser=chrome", $web_hf13_failed);
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=rc_x17&TestBrowser=chrome", $web_rc13_failed);
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=dev_x17&TestBrowser=chrome", $web_dev13_failed);
						//generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=hf_x18&TestBrowser=chrome", $web_hf14_failed);
						//generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=rc_x18&TestBrowser=chrome", $web_rc14_failed);
						generateResultCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=dev_x18&TestBrowser=chrome", $web_dev14_failed);
						/*echo "<td class=\"tg-disabled\">0</td>";
						echo "<td class=\"tg-disabled\">0</td>";
						echo "<td class=\"tg-disabled\">0</td>";
						echo "<td class=\"tg-disabled\">0</td>";*/
					}
							
					if ($row == 4) {
						//To check
						echo "<td class=\"tg-warn\" rowspan='1' >To check</td>";
						generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=weWebSel&Testtype=hf_x17&TestBrowser=chrome", $we_hf_analyse);
						generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=weWebSel&Testtype=rc_x17&TestBrowser=chrome", $we_rc_analyse);
						generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=weWebSel&Testtype=dev_x18&TestBrowser=chrome", $we_dev_analyse);
						generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=hf_x16&TestBrowser=chrome", $web_hf12_analyse);
						generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=rc_x16&TestBrowser=chrome", $web_rc12_analyse);
						generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=dev_x16&TestBrowser=chrome", $web_dev12_analyse);
						generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=hf_x17&TestBrowser=chrome", $web_hf13_analyse);
						generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=rc_x17&TestBrowser=chrome", $web_rc13_analyse);
						generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=dev_x17&TestBrowser=chrome", $web_dev13_analyse);
						//generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=hf_x18&TestBrowser=chrome", $web_hf14_analyse);
						//generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=rc_x18&TestBrowser=chrome", $web_rc14_analyse);
						generateAnalyseCell("https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?Product=gWWebSel&Testtype=dev_x18&TestBrowser=chrome", $web_dev14_analyse);
						/*echo "<td class=\"tg-disabled\">0</td>";
						echo "<td class=\"tg-disabled\">0</td>";
						echo "<td class=\"tg-disabled\">0</td>";
						echo "<td class=\"tg-disabled\">0</td>";	*/					
					}								
				echo '</tr>';				
			}
			?>
		</tbody>
    </table>
    

    <!-- Inclure Bootstrap JS et ses dépendances -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</tbody>
</html>