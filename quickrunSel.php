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
	if (isset($_GET['url'])) 
	{$logurl = $_GET['url'];}
	else{$logurl = "https://sqs-sel-cent1.cas-software.dev/logs/index.php";}
	
	if (isset($_GET['JJob'])) 
	{$JJob = $_GET['JJob'];}
	else{$JJob = "";}
	
	if (isset($_GET['JParam'])) 
	{$JParam = $_GET['JParam'];}
	else{$JParam = "";}
	
	if (isset($_GET['Build'])) 
	{$Build = urldecode($_GET['Build']);}
	else{$Build = "Last";}
	
	if (isset($_GET['Product'])) 
	{$Product = $_GET['Product'];}
	else{$Product = "";}
	
	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	
	if (isset($_GET['AutoID'])) 
	{$AutoID = $_GET['AutoID'];}
	else{$AutoID = "";}
	
	if (isset($_GET['Filter'])) 
	{$FilterResults = $_GET['Filter'];}
	else{$FilterResults = "no";}
	
	if (isset($_GET['Testtype'])) 
	{$Testtype = $_GET['Testtype'];}
	else{$Testtype = "";}
	
	if (isset($_GET['Testset'])) 
	{$Testset = $_GET['Testset'];}
	else{$Testset = "";}
	
	if (isset($_GET['TCProj'])) 
	{$TCProj = $_GET['TCProj'];}
	else{$TCProj = "";}
	
	if (isset($_GET['TestName'])) 
	{$TestName = $_GET['TestName'];}
	else{$TestName = "";}
	
	/*if (isset($_GET['localrun'])) 
	{$localrun = $_GET['localrun'];}
	else{$localrun = "local";}
	
	if (isset($_GET['parallel'])) 
	{$parallel = $_GET['parallel'];}
	else{$parallel = "no";}	
	
	if (isset($_GET['TestBrowser'])) 
	{
		$TestBrowser = $_GET['TestBrowser'];
	}
	else
	{
		if (isset($_GET['RunBrowser']))
		{
			$TestBrowser = $_GET['RunBrowser'];
		}
		else
			$TestBrowser = "chrome";
	} 
	
	if (isset($_GET['Hub'])) 
	{
		$Hub = urlencode($_GET['Hub']);
	}
	else
	{
		$Hub = urlencode('https://sqs-sel-cent1.cas-software.dev');
	}*/
		
	//Run logurl with new Jobs
	$JJobJenkins = "SQS_Web_TestPipe";
	$ForDebug = 'false';
	
	if($Testset == $JParam && $TCProj == "")
	{
		$JParam = "@dummy";
		$test="https://build-sqs.cas-software.dev/view/gWWeb/job/".$JJobJenkins."/buildWithParameters?token=TCAUTO&delay=2sec&TestName=".$JParam."&Testset=".$Testset."&DebugFeature=".$ForDebug;
	}
	else
	{
		if ($TCProj <> "")
		{
			$TCProjOutline=explode(" outline",$TCProj);
			$TCProj=$TCProjOutline[0];
		}	
		$test="https://build-sqs.cas-software.dev/view/gWWeb/job/".$JJobJenkins."/buildWithParameters?token=TCAUTO&delay=2sec&TestName=".urlencode($TCProj)."&Testset=".$Testset."&DebugFeature=".$ForDebug;
	}
	
	if ($Product == "weWebSel")
	{
		$test = $test."&Product=We";
	}
	else if ($Product == "gWWebSel")
	{
		$test = $test."&Product=Web";
	}
	else
	{
		$test = $test."&Product=NA";
	}
	
	$runn="https://sqs-sel-cent1.cas-software.dev/logs/check.php?value=2&autoid=".$AutoID."&LogVersion=".$LogVersion;	
	
	//DEBUG
	echo "Testype:".$Testtype."<br>";
	echo "<h4>Jenkins Job: ".$JJobJenkins."<br> ---- <br><b>Testset: ".$Testset."</b><br>---- <br>LogVersion: ".$LogVersion."<br>---- <br><b>Scenario: ".$TCProj."</h4></b><br><br>";
		
	$Browser="chrome";
	$Hub="";
	$localrun="local";
	$parallel = "";
	
	//Set QuickRun BuildNode - java21 for x16+
	$Test_x = "&Test_Node=Grid";
		
    if ((substr($LogVersion, 0, 3) == "x17") or(substr($LogVersion, 0, 3) == "x16") or (substr($LogVersion, 0, 3) == "x15") or (substr($LogVersion, 0, 3) == "x14") or (substr($LogVersion, 0, 3) == "x13"))
	{									
		if($Testtype == "hf_x15")
			$Testtype = "hotfix/11.x";
        if($Testtype == "dev_x16")
			$Testtype = "dev/12.x";
		if($Testtype == "rc_x16")
			$Testtype = "rc/12.x";
		if($Testtype == "hf_x16")
			$Testtype = "hotfix/12.x";
		if($Testtype == "dev_x17")
			$Testtype = "dev/13.x";
		if($Testtype == "rc_x17")
			$Testtype = "rc/13.x";
		if($Testtype == "hf_x17")
			$Testtype = "hotfix/13.x";
		
		$test = $test."&Test_Version=".$Testtype.$Test_x."&TestBrowser=".$Browser."&TestedBuild=".urlencode($Build);				
	}
	else if (($LogVersion == "we_hf") OR ($LogVersion == "we_rc") OR ($LogVersion == "we_dev"))
	{
		if($Testtype == "we_dev")
			$Testtype = "dev/13.x";					
		if($Testtype == "we_rc")
			$Testtype = "rc/13.x";
		if($Testtype == "we_hf")
			$Testtype = "hotfix/13.x";
			
		$test = $test."&Test_Version=".$Testtype.$Test_x."&TestBrowser=".$Browser."&TestedBuild=".urlencode($Build);				
	}
		
	if($localrun == "local")
		$test = $test."&LocalBrower=true";
	if($parallel == "parallel")
		$test = $test."&ParallelRun=false";
			
	echo $LogVersion.'<br>';
	echo "## ".$test." ##";
	echo '<br>';
	
	echo "## ".$runn." ##";
	echo '<br>';
	
	$streamContext = stream_context_create([
			'ssl' => [
			'verify_peer'      => false,
			'verify_peer_name' => false,
			'method'  => 'POST'
			]
	]);
	
	file_get_contents($runn, false, $streamContext);
	file_get_contents($test, false, $streamContext);
		
	?>
	<script>
		window.onload = function() {
        var url = '<?php echo $test; ?>'; // Insert the PHP variable into the JavaScript
        var iframe = document.createElement('iframe');
        iframe.style.display = 'none'; // Hide the iframe
        iframe.src = url;
        document.body.appendChild(iframe);
     };
	 </script>
	<?php
	echo '<br>';
	echo "Testcase triggerd";
	?>	
</body>
</html>