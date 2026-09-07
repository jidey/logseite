<!DOCTYPE html>
<html lang="en">
<head>
  <title>Autotests</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Latest compiled and minified CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
  <!-- jQuery library -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
  <!-- Latest compiled JavaScript -->
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>    
</head>
	
<body>
	<?php
	include_once("inc/db_connect.php");
		
	if (isset($_GET['url'])) 
	{$jenkins = $_GET['url'];}
	else{$jenkins = "https://sqs-sel-cent1.cas-software.dev/logs/index.php";}
	
	if (isset($_GET['JJob'])) 
	{$JJob = $_GET['JJob'];}
	else{$JJob = "";}
	
	if (isset($_GET['JParam'])) 
	{$JParam = $_GET['JParam'];}
	else{$JParam = "";}
	
	if (isset($_GET['TestProject'])) 
	{$TestProject = $_GET['TestProject'];}
	else{$TestProject = "";}
	
	if (isset($_GET['Build'])) 
	{$Build = urldecode($_GET['Build']);}
	else{$Build = "Last";}
	
	if (isset($_GET['Product'])) 
	{$Product = $_GET['Product'];}
	else{$Product = "";}
	
	if (isset($_GET['Filter'])) 
	{$FilterResults = $_GET['Filter'];}
	else{$FilterResults = "no";}
	
	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	
	if (isset($_GET['Testset'])) 
	{$Testset = $_GET['Testset'];}
	else{$Testset = "";}
	
	if (isset($_GET['MainID'])) 
	{$MainID = $_GET['MainID'];}
	else{$MainID = "";}
	
	if (isset($_GET['TCProj'])) 
	{$TCProj = $_GET['TCProj'];}
	else{$TCProj = "";}
	
	if (isset($_GET['Testtype'])) 
	{$Testtype = $_GET['Testtype'];}
	else{$Testtype = "";}
	
	if (isset($_GET['TestBrowser'])) 
	{$TestBrowser = $_GET['TestBrowser'];}
	else
	{if (isset($_GET['RunBrowser']))
		{
			$TestBrowser = $_GET['RunBrowser'];
		}
		else
			$TestBrowser = "chrome";
	} 
	
	$JJobJenkins = "SQS_Web_TestPipe";
	$ForDebug = 'false';
	
	//Run Jenkins with new Jobs
	if ($ForDebug == 'true')	
		$test="https://build-sqs.cas-software.dev/view/gWWeb/job/".$JJobJenkins."/buildWithParameters?token=TCAUTO&delay=2sec&DebugFeature=".$ForDebug."&Feature=".$Feature;
	else
		$test="https://build-sqs.cas-software.dev/view/gWWeb/job/".$JJobJenkins."/buildWithParameters?token=TCAUTO&delay=2sec&DebugFeature=".$ForDebug;

	if ($Product == "weWebSel")
	{
		$test = $test."&Product=We";
	}
	else
	{
		$test = $test."&Product=Web";
	}
	$runn="https://sqs-sel-cent1.cas-software.dev/logs/check.php?value=2&LogVersion=".$LogVersion."&autoid=";
	$stop="https://sqs-sel-cent1.cas-software.dev/logs/check.php?value=0&LogVersion=".$LogVersion."&autoid=";
	
	echo "<br><center>Please confirm the test EXECUTION";
	echo "<h4>".$JJob."<br>".$TestProject." ---- ".$Build."<br> ---- <br><p><font color=\"red\"><b>ALL FAILED</b></font></h4><br>";
	echo "Testbranch: <b>".$Testtype."</b><br>";
	echo "MainID: <b>".$MainID."</b><br>";
	
	$TestBrowser = "chrome";
	$Test_Node = "Grid";
			
	//DEBUG
	$allTestCases = "SELECT DISTINCT TCProj,Build FROM ".$LogVersion." WHERE JParam='".$TestProject."' AND TCProj <> '".$TestProject."' AND TearDownFailed <> '0' AND TCProj <> 'Debug' AND TestLogTyp = 'Single' AND Build='".$Build."' AND TestType='".$Testtype."'";
	//echo "allTestCases='".$allTestCases."'<br>";
			
	$sqlTestsList = $conn->query($allTestCases);				
	while($TestListrunrow = mysqli_fetch_assoc($sqlTestsList)) 
	{			
		//Search lastRun for each Single TestCase/KeywordTest
		$selectLastTestRun = "SELECT * FROM `".$LogVersion."` WHERE (JJob = '".$JJob."' AND Testtype = '".$Testtype."' AND TCProj = '".$TestListrunrow["TCProj"]."' AND JParam = '".$TestProject."' AND Build = '".$Build."' AND TestLogTyp = 'Single')"; 
		//echo $selectLastTestRun."<br><br>";
		
		$sqlLastTestRun = $conn->query($selectLastTestRun);		
		$row = mysqli_fetch_assoc($sqlLastTestRun);
		
		$TCProj=$row["TCProj"];
		$ID=$row["AutoID"];
						
		if($ID != '' && $row["TearDownFailed"] != '0' && $row["checked"] != '1' && $row["running"] != '2')
		{	
			$Hub='';
			RunTest($test,$runn,$Testtype,$Test_Node,$LogVersion,$TestProject,$row["TCProj"],$ID,$TestBrowser,$Hub,$JJob,$Build,$MainID);			
		}			
		else
		{
			if ($row["running"] == '2')
				echo "Test already running";
		}
	}
	
	function RunTest($test,$execute,$branch,$Test_x,$LogVersion,$testset,$JParam,$ID,$Browser,$Hub,$JJob,$Build,$MainID)
    {        	
			echo "Triggering testcase<br>";
			
			if (substr($LogVersion, 0, 3) == "x17")
			{
				if($branch == "dev_x17")
				$branch = "dev/13.x";
				if($branch == "rc_x17")
				$branch = "rc/13.x";
				if($branch == "hf_x17")
				$branch = "hotfix/13.x";				
			}
			else if (substr($LogVersion, 0, 3) == "x16")
			{
				if($branch == "dev_x16")
				$branch = "dev/12.x";
				if($branch == "rc_x16")
				$branch = "rc/12.x";
				if($branch == "hf_x16")
				$branch = "hotfix/12.x";				
			}
            else if (substr($LogVersion, 0, 3) == "x15")
			{
				if($branch == "dev_x15")
				$branch = "dev/11.x";
				if($branch == "rc_x15")
				$branch = "rc/11.x";
				if($branch == "hf_x15")
				$branch = "hotfix/11.x";				
			}								
			else			
			{
				if($LogVersion == "we_dev")
					$branch = "dev/13.x";
				else if($LogVersion == "we_rc")
					$branch = "rc/13.x";
				else
					$branch = "hotfix/13.x";				
			}
			
			$test = $test."&TestName=".urlencode($JParam)."&Test_Version=".$branch."&TestBrowser=".$Browser."&Test_Node=".$Test_x."&Testset=".$testset."&TestedBuild=".urlencode($Build);
			$executeSingle = $execute.$ID;
			$executeMain = $execute.$MainID;					
			
			/*echo $test.'<br>';
			echo $executeSingle.'<br>';
			echo $executeMain.'<br>';
			echo '<br>';			
			*/
			$streamContext = stream_context_create([
			'ssl' => [
			'verify_peer'      => false,
			'verify_peer_name' => false
			]
			]);

			file_get_contents($test, false, $streamContext);		
			file_get_contents($executeSingle, false, $streamContext);
			file_get_contents($executeMain, false, $streamContext);					
    }	
	?>	
</body>
</html>