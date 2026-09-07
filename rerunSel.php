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
	else{$Product = "error";}
	
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
	
	if (isset($_GET['localrun'])) 
	{$localrun = $_GET['localrun'];}
	else{$localrun = "no";}
	
	if (isset($_GET['parallel'])) 
	{$parallel = $_GET['parallel'];}
	else{$parallel = "no";}	
	
	if (isset($_GET['retry'])) 
	{$retry = $_GET['retry'];}
	else{$retry = "no";}	
	
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
	}
	
	
	//Run Jenkins with new Jobs
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
		$test = $test."&Product=error";
	}
	
	$runn="https://sqs-sel-cent1.cas-software.dev/logs/check.php?value=2&autoid=".$AutoID."&LogVersion=".$LogVersion;	
	
	$execute = false;
	if(isset($_GET['Confirm']) OR isset($_GET['Abort']))
	{
		$execute = true;		
	}
	
	if ($execute == false)
	{
		echo "<br><center>Please confirm the test EXECUTION on ".$Testtype;
		if ($JParam == '@dummy')
		{
			echo "<h4>Jenkins Job: ".$JJobJenkins."<br> ---- <br><b>Testset: ".$Testset."</b><br>---- <br>LogVersion: ".$LogVersion."<br></h4><br><br>";
		}
		else
		{
			echo "<h4>Jenkins Job: ".$JJobJenkins."<br> ---- <br><b>Testset: ".$Testset."</b><br>---- <br>LogVersion: ".$LogVersion."<br>---- <br><b>Scenario: ".$TCProj."</h4></b>";
		}
		
		echo "<form action=rerunSel.php?url=".$logurl."&JJob=".$JJob."&Build=".urldecode($Build)."&LogVersion=".$LogVersion."&JParam=".$JParam."&Testtype=".$Testtype."&AutoID=".$AutoID." class=\"form-inline\" role=\"form\">";	
		
		//TestNode
		include('runsystems.php');?>
		
		<?php
		/*<br>Browser:
		<div class="form-group">
		<select name="RunBrowser" size="1">
			<option value="chrome" <?php if ($TestBrowser == "chrome") echo "selected='selected'";?> >chrome</option>
			<option value="firefox" <?php if ($TestBrowser == "firefox") echo "selected='selected'";?> >firefox</option>	
			<option value="chrome:nightly" <?php if ($TestBrowser == "chrome:nightly") echo "selected='selected'";?> >chrome:nightly</option>			
		</select>	
		</div>
		
		
		<br>Grid Hub:
			<div class="form-group">
				<select name="Hub" size="1">
					<?php include('Gridhubs.html');?>
				</select>
			</div>*/
		$Hub="https://sqs-sel-cent1.cas-software.dev";
		?>
		<br>Run localy (No Grid):
			<div class="form-group">
				<input type="checkbox" checked id="localrun" name="localrun" value="localrun">
			</div>	
		<br>
			Force sequential Tests:
			<div class="form-group">
				<input type="checkbox" id="parallel" name="parallel" value="parallel">
			</div>
		<br>		
			Retry failing Tests:
			<div class="form-group">
				<input type="checkbox" checked id="retry" name="retry" value="retry">
			</div>
		<br>
			<input type="submit" name="Confirm" value="Confirm" class="btn btn-success" onclick="Confirm()" />
			<input type="submit" name="Abort" value="Abort" class="btn btn-danger" onclick="Abort()" />		
			<input type="hidden" name="test" value="<?php echo $test; ?>">
			<input type="hidden" name="runn" value="<?php echo $runn; ?>">
			<input type="hidden" name="url" value="<?php echo $logurl; ?>">
			<input type="hidden" name="JJob" value="<?php echo $JJob; ?>">
			<input type="hidden" name="JParam" value="<?php echo $JParam; ?>">
			<input type="hidden" name="Build" value="<?php echo $Build; ?>">
			<input type="hidden" name="AutoID" value="<?php echo $AutoID; ?>">	
			<input type="hidden" name="LogVersion" value="<?php echo $LogVersion; ?>">		
			<input type="hidden" name="Product" value="<?php echo $Product; ?>">		
			<input type="hidden" name="branch" value="<?php echo $Testtype; ?>">	
			<input type="hidden" name="forDebug" value="<?php echo $ForDebug; ?>">		
			<input type="hidden" name="Hub" value="<?php echo $Hub; ?>">
			<input type="hidden" name="RunBrowser" value="chrome">			
		</div>
	</form>
	</center>
	
	<?php	
	}	
	
	if($_GET){
		if(isset($_GET['Confirm'])){
			if (substr($LogVersion, 0, 3) == "x15")
			{
				Confirm($_GET['test'],$_GET['runn'],$_GET['url'],$_GET['branch'],$_GET['Test_x15'],$_GET['LogVersion'],$_GET['RunBrowser'],$_GET['JJob'],$_GET['Hub'],$_GET['forDebug'],$localrun,$parallel,$Build,$retry);
			}
            if (substr($LogVersion, 0, 3) == "x16")
			{
				Confirm($_GET['test'],$_GET['runn'],$_GET['url'],$_GET['branch'],$_GET['Test_x16'],$_GET['LogVersion'],$_GET['RunBrowser'],$_GET['JJob'],$_GET['Hub'],$_GET['forDebug'],$localrun,$parallel,$Build,$retry);
			}
			if (substr($LogVersion, 0, 3) == "x17")
			{
				Confirm($_GET['test'],$_GET['runn'],$_GET['url'],$_GET['branch'],$_GET['Test_x17'],$_GET['LogVersion'],$_GET['RunBrowser'],$_GET['JJob'],$_GET['Hub'],$_GET['forDebug'],$localrun,$parallel,$Build,$retry);
			}
			if (substr($LogVersion, 0, 3) == "x18")
			{
				Confirm($_GET['test'],$_GET['runn'],$_GET['url'],$_GET['branch'],$_GET['Test_x18'],$_GET['LogVersion'],$_GET['RunBrowser'],$_GET['JJob'],$_GET['Hub'],$_GET['forDebug'],$localrun,$parallel,$Build,$retry);
			}
			if (($LogVersion == "we_hf") OR ($LogVersion == "we_rc") OR ($LogVersion == "we_dev"))
			{
				Confirm($_GET['test'],$_GET['runn'],$_GET['url'],$_GET['branch'],$_GET['Test_Node'],$_GET['LogVersion'],$_GET['RunBrowser'],$_GET['JJob'],$_GET['Hub'],$_GET['forDebug'],$localrun,$parallel,$Build,$retry);
			}
		}
		elseif(isset($_GET['Abort'])){
			Abort($logurl);
		}
	}

    function Abort($logurl)
    {        
		header( "Location: $logurl" );
    }
	
    function Confirm($test,$runn,$logurl,$branch,$Test_x,$LogVersion,$Browser,$JJob,$Hub,$forDebug,$localrun,$parallel,$Build,$retry)
    {
		$Test_y = "&Test_Node=".$Test_x;
		if ((substr($LogVersion, 0, 3) == "x18") or (substr($LogVersion, 0, 3) == "x17") or (substr($LogVersion, 0, 3) == "x16") or (substr($LogVersion, 0, 3) == "x15"))
		{				
			if($branch == "hf_x15")
				$branch = "hotfix/11.x";
			if($branch == "dev_x16")
				$branch = "dev/12.x";
			if($branch == "rc_x16")
				$branch = "rc/12.x";
			if($branch == "hf_x16")
				$branch = "hotfix/12.x";
			if($branch == "dev_x17")
				$branch = "dev/13.x";
			if($branch == "rc_x17")
				$branch = "rc/13.x";
			if($branch == "hf_x17")
				$branch = "hotfix/13.x";
			if($branch == "dev_x18")
				$branch = "dev/14.x";
			if($branch == "rc_x18")
				$branch = "rc/14.x";
			if($branch == "hf_x18")
				$branch = "hotfix/14.x";
			
			if ((substr($LogVersion, 0, 3) == "x15") or (substr($LogVersion, 0, 3) == "x16") or (substr($LogVersion, 0, 3) == "x17") or (substr($LogVersion, 0, 3) == "x18"))
			{	
				$test = $test."&Test_Version=".$branch.$Test_y."&TestBrowser=".$Browser."&TestedBuild=".urlencode($Build);
			}
			else
				$test = $test."&Test_Version=".$branch.$Test_y."&TestBrowser=".$Browser;	
			
			if ($forDebug == 'true')
			$test = $test."&Feature=".substr($JJob,14,strlen($JJob));	
		
		}			
		else if (($LogVersion == "we_hf") OR ($LogVersion == "we_rc") OR ($LogVersion == "we_dev"))
		{
			if($branch == "we_dev")
			{
				$branch = "dev/14.x";					
			}
			if($branch == "we_rc")
			{
				$branch = "rc/13.x";
			}
			if($branch == "we_hf")
			{
				$branch = "hotfix/13.x";
			}			
			$test = $test."&Test_Version=".$branch.$Test_y."&TestBrowser=".$Browser."&TestedBuild=".urlencode($Build);
			
			if ($forDebug == 'true')
			$test = $test."&Feature=".substr($JJob,13,strlen($JJob));			
		}
		
		if ($Test_x == "JDF" OR $Test_x == "SV" OR $Test_x == "OG" OR $Test_x == "AS")
		{
			$Hub="https://sqs-sel-cent1.cas-software.dev";
		}
		else
		{
			$Hub="http://sqs-gridhub1";
		}
		$test = $test."&Hub=".$Hub;
		
		if($localrun == "localrun")
			$test = $test."&LocalBrower=true";
		else
			$test = $test."&LocalBrower=false";
		
		if($parallel == "parallel")
			$test = $test."&ParallelRun=false";
								
		if ($retry == 'retry')
			$test = $test."&RETRY_FAILED=true";
		
		/*echo $LogVersion.'<br>';
		echo "##".$test."##<br><br>";
		echo "####".$runn."####";
		echo '<br>';		
		echo '<br>';
		*/
		$streamContext = stream_context_create([
			'ssl' => [
			'verify_peer'      => false,
			'verify_peer_name' => false,
			'method'  => 'POST'
			]
	    ]);
		
		file_get_contents($runn, false, $streamContext);
		file_get_contents($test, false, $streamContext);
		
		header( "Location: $logurl" );
	}
	?>	
</body>
</html>