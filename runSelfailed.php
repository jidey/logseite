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
	{$logurl = $_GET['url'];}
	else{$logurl = "https://sqs-sel-cent1.cas-software.dev/logs/index.php";}
	
	if (isset($_GET['JJob'])) 
	{$JJob = $_GET['JJob'];}
	else{$JJob = "";}

	if (isset($_GET['Product'])) 
	{$Product = $_GET['Product'];}
	else{$Product = "error";}
	
	if (isset($_GET['Filter'])) 
	{$FilterResults = $_GET['Filter'];}
	else{$FilterResults = "no";}
	
	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	
	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	
	if (isset($_GET['Testtype'])) 
	{$Testtype = $_GET['Testtype'];}
	else{
		if (isset($_GET['branch'])) 
		{$Testtype = $_GET['branch'];}
		else{$Testtype = "";}
	}
	
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
	if (isset($_GET['localrun'])) 
	{
		$localrun = $_GET['localrun'];
	}
	else
	{
		$localrun = "grid";
	}	
	if (isset($_GET['parallel'])) 
	{
		$parallel = $_GET['parallel'];
	}
	else
	{
		$parallel = "no";
	}	
	if (isset($_GET['retry'])) 
	{$retry = $_GET['retry'];}
	else{$retry = "no";}	
	
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
	else if ($Product == "gWWebSel")
	{
		$test = $test."&Product=Web";
	}
	else
	{
		$test = $test."&Product=error";
	}
	
	if (isset($_GET['Hub'])) 
	{
		$Hub = urlencode($_GET['Hub']);
	}
	else
	{
		$Hub = urlencode('https://sqs-sel-cent1.cas-software.dev');
	}
	
	$runn="https://sqs-sel-cent1.cas-software.dev/logs/check.php?value=2&LogVersion=".$LogVersion."&autoid=";
	$stop="https://sqs-sel-cent1.cas-software.dev/logs/check.php?value=0&LogVersion=".$LogVersion."&autoid=";
	
	echo "<br><center>Please confirm the test EXECUTION";
	echo "<h4>".$JJob."<br> ---- <br><p><font color=\"red\"><b>ALL FAILED</b></font></h4><br>";
	echo "Testbranch: <b>".$Testtype."</b><br>";
	$execute = false;
	if(isset($_GET['Confirm']) OR isset($_GET['Abort']))
	{
		$execute = true;		
	}
	
	if ($execute == false)
	{
		echo "<form action=runSelfailed.php?JJob=".$JJob."&LogVersion=".$LogVersion."&Testtype=".$Testtype." class=\"form-inline\" role=\"form\">";	
			include('runsystems.php');
			?>
			<?php
			/*
			<br>Test Browser:
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
			</div>
			<br>Run localy:
				<div class="form-group">
					<input type="checkbox" id="localrun" name="localrun" value="local">
				</div>
				*/?>
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
			<div class="form-group">
				<input type="checkbox" name="versioncheck" value=true> Check to Run old "Version Tested"<br>
			</div>
			<br><br>
			<input type="submit" name="Confirm" value="Confirm" class="btn btn-success" onclick="RunTest()" />
			<input type="submit" name="Abort" value="Abort" class="btn btn-danger" onclick="Abort()" />		
			<input type="submit" name="Confirm" value="Stop" class="btn btn-warning" onclick="RunTest()" />
			<input type="hidden" name="test" value="<?php echo $test; ?>">
			<input type="hidden" name="runn" value="<?php echo $runn; ?>">
			<input type="hidden" name="stop" value="<?php echo $stop; ?>">
			<input type="hidden" name="JJob" value="<?php echo $JJob; ?>">
			<input type="hidden" name="url" value="<?php echo $logurl; ?>">
			<input type="hidden" name="LogVersion" value="<?php echo $LogVersion; ?>">		
			<input type="hidden" name="Product" value="<?php echo $Product; ?>">		
			<input type="hidden" name="branch" value="<?php echo $Testtype; ?>">	

			<input type="hidden" name="Hub" value="<?php echo $Hub; ?>">
			<input type="hidden" name="localrun" value="local">
			<input type="hidden" name="RunBrowser" value="chrome">
			</div>
		</form>
	</center>
	<?php	
	}
	
	if(isset($_GET['Confirm']))
	{
		$LastBuildNum = "0";
		$Testtype = $_GET['branch'];
		
		if($_GET['Confirm'] == 'Confirm')
		{
			$action = $runn;
		}
		else
		{
			$action = $stop;
		}
		
		//echo "DEBUG ".$action."<br>";		
		//echo "DEBUG '".$Testtype."'<br>";
		//echo "DEBUG '".$TestBrowser."'<br>";
		
		$LogVersion = $_GET['LogVersion'];
		$JJob=$_GET['JJob'];
		if(!isset($_GET['versioncheck']))
		{
			 $oldversion = false;
		} else {
			 $oldversion = $_GET['versioncheck'];
		}
		include('lastbuild.php');
		
		//DEBUG
		//echo "LastBuildNum='".$LastBuildNum."'<br>";
		$selectLastRuns = "SELECT l1.* FROM `".$LogVersion."` as l1 LEFT JOIN `".$LogVersion."` as l2 
			ON (l1.JParam = l2.JParam and l1.jjob = l2.jjob AND l1.TestLogTyp = l2.TestLogTyp AND l1.Browser = l2.Browser AND l1.Testtype = l2.Testtype AND l1.AutoID < l2.AutoID)
			WHERE ((l1.JJob = '".$JJob."' AND l1.Testtype = '".$Testtype."' AND l1.TestLogTyp = 'Main' AND l2.AutoID is NULL)"; 
			
		if($JJob == 'Autotests-x12-gW-WebSelenium' or $JJob == 'Autotests-x11-gW-WebSelenium' or $JJob=='Autotests-We')
		{
			if($TestBrowser != "")
			{
				$selectLastRuns = $selectLastRuns . " AND l1.Browser = '".$TestBrowser."')";
			}
			else
			{
				$selectLastRuns = $selectLastRuns . " AND l1.Browser = 'chrome')";
			}
		}
		//echo "DEBUG Versioncheck= ".$oldversion."<br>";
		//echo "DEBUG LastBuildNum= ".$LastBuildNum."<br>";
		
		if(isset($_GET['Confirm']))
		{	
			if(!$oldversion)
			{
				//Without Version check only failed Tests
				$selectLastRuns = $selectLastRuns . " AND (l1.TearDownFailed != \"0\") AND (l1.running != 2 AND l1.checked != 1";	
			}
			else
			{
				//With Version Check
				$selectLastRuns = $selectLastRuns . " AND (l1.TearDownFailed != \"0\" OR l1.Build != \"".trim($LastBuildNum)."\") AND (l1.running != 2 AND l1.checked != 1";						
			}
		}
			
		if($JJob == 'Autotests-x12-gW-WebSelenium' or $JJob == 'Autotests-x11-gW-WebSelenium' or $JJob=='Autotests-We')
		{
			$selectLastRuns = $selectLastRuns . ") ORDER BY l1.RunDate DESC, l1.JBuild DESC";
		}
		else
		{
			$selectLastRuns = $selectLastRuns . ")) ORDER BY l1.RunDate DESC, l1.JBuild DESC";
		}
		
		//echo "<br>".$selectLastRuns."<br><br>";
		
		$sqlTestsList = $conn->query($selectLastRuns);			
		if (mysqli_num_rows($sqlTestsList) > 0)
		{
			while($row = mysqli_fetch_assoc($sqlTestsList))
			{
				$JParam=$row["JParam"];
				$ID=$row["AutoID"];
				//echo $LogVersion."<br>";
				if (substr($LogVersion,0,3) == "x15")
				{
					RunTest($_GET['test'],$action,$_GET['url'],$_GET['branch'],$_GET['Test_x15'],$_GET['LogVersion'],$JParam,$ID,$_GET['RunBrowser'],$_GET['Confirm'],$_GET['Hub'],$localrun,$parallel,$retry);
				}
                if (substr($LogVersion,0,3) == "x16")
				{
					RunTest($_GET['test'],$action,$_GET['url'],$_GET['branch'],$_GET['Test_x16'],$_GET['LogVersion'],$JParam,$ID,$_GET['RunBrowser'],$_GET['Confirm'],$_GET['Hub'],$localrun,$parallel,$retry);
				}
				if (substr($LogVersion,0,3) == "x17")
				{
					RunTest($_GET['test'],$action,$_GET['url'],$_GET['branch'],$_GET['Test_x17'],$_GET['LogVersion'],$JParam,$ID,$_GET['RunBrowser'],$_GET['Confirm'],$_GET['Hub'],$localrun,$parallel,$retry);
				}
				if (substr($LogVersion,0,2) == "we")
				{
					RunTest($_GET['test'],$action,$_GET['url'],$_GET['branch'],$_GET['Test_Node'],$_GET['LogVersion'],$JParam,$ID,$_GET['RunBrowser'],$_GET['Confirm'],$_GET['Hub'],$localrun,$parallel,$retry);
				}
			}
			header( "Location: $logurl" );
		}
	}
	elseif(isset($_GET['Abort']))
	{
		Abort($logurl);
	}	

    function Abort($originurl)
    {        
		header( "Location: $originurl" );
    }
	
    function RunTest($test,$execute,$url,$branch,$Test_x,$LogVersion,$JParam,$ID,$Browser,$action,$hub,$localrun,$parallel,$retry)
    {
		if (substr($LogVersion, 0, 3) == "x17")
		{
			if($branch == "dev_x17")
				$branch = "dev/13.x";
			if($branch == "rc_x17")
				$branch = "rc/13.x";			
			if($branch == "hf_x17")
				$branch = "hotfix/13.x";
			$test = $test."&Testset=".$JParam."&Test_Version=".$branch."&TestBrowser=".$Browser."&Test_Node=".$Test_x."&Hub=".$hub;
		}
		else if (substr($LogVersion, 0, 3) == "x16")
		{
			if($branch == "dev_x16")
				$branch = "dev/12.x";
			if($branch == "rc_x16")
				$branch = "rc/12.x";			
			if($branch == "hf_x16")
				$branch = "hotfix/12.x";
			$test = $test."&Testset=".$JParam."&Test_Version=".$branch."&TestBrowser=".$Browser."&Test_Node=".$Test_x."&Hub=".$hub;
		}
        else if (substr($LogVersion, 0, 3) == "x15")
		{
			if($branch == "hf_x15")
				$branch = "hotfix/11.x";
			$test = $test."&Testset=".$JParam."&Test_Version=".$branch."&TestBrowser=".$Browser."&Test_Node=".$Test_x."&Hub=".$hub;
		}
		else			
		{
			if($LogVersion == "we_dev")
				$branch = "dev/13.x";
			else if($LogVersion == "we_rc")
				$branch = "rc/13.x";
			else
				$branch = "hotfix/13.x";	
			$test = $test."&Testset=".$JParam."&Test_Version=".$branch."&TestBrowser=".$Browser."&Test_Node=".$Test_x."&Hub=".$hub;
		}
		
		if($localrun == "local")
		$test = $test."&LocalBrower=true";
	
		if($parallel == "parallel")
		$test = $test."&ParallelRun=false";
	
		if ($retry == 'retry')
			$test = $test."&RETRY_FAILED=true";
	
		$execute = $execute.$ID;
		$streamContext = stream_context_create([
			'ssl' => [
			'verify_peer'      => false,
			'verify_peer_name' => false,
			'method'  => 'POST'
			]
		]);
								
		if ($action == "Confirm")
		{								
			
			/*echo $JParam.'  --  '.$ID.'<br>';
			echo $test.'<br>';
			echo $execute.'<br>';
			echo '<br>';
			*/
			file_get_contents($test, false, $streamContext);		
			file_get_contents($execute, false, $streamContext);				
		}
		
		if ($action == "Stop")
		{
			echo "STOP<br>";
			echo $execute.'<br>';
			file_get_contents($execute, false, $streamContext);	
		}			
    }	
	?>	
</body>
</html>