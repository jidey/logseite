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
	else{$jenkins = "https://build-sqs.cas-software.dev/view/gWWeb/job/SQS_Web_TestPipe/";}
	
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
	
	if (isset($_GET['TCProj'])) 
	{$TCProj = $_GET['TCProj'];}
	else{$TCProj = "";}
	
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
	
	if (isset($_GET['Hub'])) 
	{
		$Hub = urlencode($_GET['Hub']);
	}
	else
	{
		$Hub = urlencode('https://sqs-sel-cent1.cas-software.dev');
	}
	
	if (isset($_GET['localrun'])) 
	{$localrun = $_GET['localrun'];}
	else{$localrun = "local";}
	
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
	else
	{
		$test = $test."&Product=Web";
	}
	$runn="https://sqs-sel-cent1.cas-software.dev/logs/check.php?value=2&LogVersion=".$LogVersion."&autoid=";
	$stop="https://sqs-sel-cent1.cas-software.dev/logs/check.php?value=0&LogVersion=".$LogVersion."&autoid=";
	
	echo "<br><center>Please confirm the test EXECUTION";
	echo "<h4>".$JJob."<br>".$TestProject." ---- ".$Build."<br> ---- <br><p><font color=\"red\"><b>ALL FAILED</b></font></h4><br>";
	echo "Testbranch: <b>".$Testtype."</b><br>";
	//echo $jenkins."<br>";
	
	$execute = false;
	if(isset($_GET['Confirm']) OR isset($_GET['Abort']))
	{
		$execute = true;		
	}
	
	if ($execute == false)
	{
		echo "<form action=runGridfailed.php?&url=".urlencode($jenkins)."&Build=".urldecode($Build)."&TestProject=".$TestProject."&JJob=".$JJob."&LogVersion=".$LogVersion."&Testtype=".$Testtype." class=\"form-inline\" role=\"form\">";	
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
				</div>
			<br>		
			Retry failing Tests:
			<div class="form-group">
				<input type="checkbox" checked id="retry" name="retry" value="retry">
			</div>
			<br>
			<input type="submit" name="Confirm" value="Confirm" class="btn btn-success" onclick="RunTest()" />
			<input type="submit" name="Abort" value="Abort" class="btn btn-danger" onclick="Abort()" />		
			<input type="submit" name="Confirm" value="Stop" class="btn btn-warning" onclick="RunTest()" />
			<input type="hidden" name="test" value="<?php echo $test; ?>">
			<input type="hidden" name="runn" value="<?php echo $runn; ?>">
			<input type="hidden" name="stop" value="<?php echo $stop; ?>">
			<input type="hidden" name="JJob" value="<?php echo $JJob; ?>">
			<input type="hidden" name="TestProject" value="<?php echo $TestProject; ?>">
			<input type="hidden" name="Build" value="<?php echo $Build; ?>">
			<input type="hidden" name="url" value="<?php echo $jenkins; ?>">
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
	
	if($_GET){
		if(isset($_GET['Confirm']))
		{
			//$LastBuildNum = $Build;
			$Testtype = $_GET['branch'];
			if($_GET['Confirm'] == 'Confirm')
			{
				$action = $runn;
			}
			else
			{
				$action = $stop;
			}
			
			/*echo "DEBUG action ".$action."<br>";		
			echo "DEBUG Testtype'".$Testtype."'<br>";
			echo "DEBUG TestBrowser'".$TestBrowser."'<br>";
			*/
			$LogVersion = $_GET['LogVersion'];
			$JJob=$_GET['JJob'];
			////include('lastbuild.php');
			
			//DEBUG
			//echo "LastBuildNum='".$LastBuildNum."'<br>";			
			$allTestCases = "SELECT DISTINCT TCProj,Build FROM ".$LogVersion." WHERE JParam='".$TestProject."' AND TCProj <> '".$TestProject."' AND TearDownFailed <> '0' AND TCProj <> 'Debug' AND TestLogTyp = 'Single' AND Build='".$Build."' AND TestType='".$Testtype."'";
			//echo "allTestCases='".$allTestCases."'<br>";
			
			$sqlTestsList = $conn->query($allTestCases);				
			
			while($TestListrunrow = mysqli_fetch_assoc($sqlTestsList)) 
			{			
				//Search lastRun for each Single TestCase/KeywordTest
				$selectLastTestRun = "SELECT * FROM `".$LogVersion."` WHERE (JJob = '".$JJob."' AND Testtype = '".$Testtype."' AND TCProj = '".$TestListrunrow["TCProj"]."' AND JParam = '".$TestProject."' AND Build = '".$Build."' AND TestLogTyp = 'Single')"; 
				
				$sqlLastTestRun = $conn->query($selectLastTestRun);		
				$row = mysqli_fetch_assoc($sqlLastTestRun);
				
				$TCProj=$row["TCProj"];
				$ID=$row["AutoID"];
								
				if($ID != '' && $row["TearDownFailed"] != '0' && $row["checked"] != '1')
				{	
					/*echo $TCProj."<br>";
					echo $LogVersion."<br>";
					echo "## ".$ID." ##<br><br>";
					
					echo "Failed= ".$row["TearDownFailed"]."<br>";
					echo "checked= ".$row["checked"]."<br>";*/
					
					if (substr($LogVersion, 0, 3) == "x15")
					{						
						RunTest($_GET['test'],$action,$_GET['branch'],$_GET['Test_x15'],$_GET['LogVersion'],$TestProject,$row["TCProj"],$ID,$_GET['RunBrowser'],$_GET['Confirm'],$_GET['Hub'],$_GET['JJob'],$localrun,$parallel,$Build,$retry);
					}
                    else if (substr($LogVersion, 0, 3) == "x16")
					{						
						RunTest($_GET['test'],$action,$_GET['branch'],$_GET['Test_x16'],$_GET['LogVersion'],$TestProject,$row["TCProj"],$ID,$_GET['RunBrowser'],$_GET['Confirm'],$_GET['Hub'],$_GET['JJob'],$localrun,$parallel,$Build,$retry);
					}
					else if (substr($LogVersion, 0, 3) == "x17")
					{						
						RunTest($_GET['test'],$action,$_GET['branch'],$_GET['Test_x17'],$_GET['LogVersion'],$TestProject,$row["TCProj"],$ID,$_GET['RunBrowser'],$_GET['Confirm'],$_GET['Hub'],$_GET['JJob'],$localrun,$parallel,$Build,$retry);
					}
					else
					{
						RunTest($_GET['test'],$action,$_GET['branch'],$_GET['Test_Node'],$_GET['LogVersion'],$TestProject,$row["TCProj"],$ID,$_GET['RunBrowser'],$_GET['Confirm'],$_GET['Hub'],$_GET['JJob'],$localrun,$parallel,$Build,$retry);
					}						
				}				
			}
			//echo "JENKINS ".$jenkins."<br>";
			header("Location: $jenkins");			
		}
		elseif(isset($_GET['Abort']))
		{
			Abort($jenkins);
		}
	}

    function Abort($jenkins)
    {        
		header( "Location: $jenkins" );
    }
	
    function RunTest($test,$execute,$branch,$Test_x,$LogVersion,$testset,$JParam,$ID,$Browser,$action,$Hub,$JJob,$localrun,$parallel,$Build,$retry)
    {
        if (true)
		{										
			if (substr($LogVersion, 0, 3) == "x17")
			{
				if($branch == "dev_x17")
				$branch = "dev/13.x";
				if($branch == "rc_x17")
				$branch = "rc/13.x";
				if($branch == "hf_x17")
				$branch = "hotfix/13.x";
				$test = $test."&TestName=".urlencode($JParam)."&Test_Version=".$branch."&TestBrowser=".$Browser."&Test_Node=".$Test_x."&Testset=".$testset."&Hub=".$Hub."&TestedBuild=".urlencode($Build);				
			}
			else if (substr($LogVersion, 0, 3) == "x16")
			{
				if($branch == "dev_x16")
				$branch = "dev/12.x";
				if($branch == "rc_x16")
				$branch = "rc/12.x";
				if($branch == "hf_x16")
				$branch = "hotfix/12.x";
				$test = $test."&TestName=".urlencode($JParam)."&Test_Version=".$branch."&TestBrowser=".$Browser."&Test_Node=".$Test_x."&Testset=".$testset."&Hub=".$Hub."&TestedBuild=".urlencode($Build);				
			}
            else if (substr($LogVersion, 0, 3) == "x15")
			{
				if($branch == "hf_x15")
				$branch = "hotfix/11.x";
				$test = $test."&TestName=".urlencode($JParam)."&Test_Version=".$branch."&TestBrowser=".$Browser."&Test_Node=".$Test_x."&Testset=".$testset."&Hub=".$Hub."&TestedBuild=".urlencode($Build);				
			}			
			else			
			{
				if($LogVersion == "we_dev")
					$branch = "dev/13.x";
				else if($LogVersion == "we_rc")
					$branch = "rc/13.x";
				else
					$branch = "hotfix/13.x";	
				$test = $test."&TestName=".urlencode($JParam)."&Test_Version=".$branch."&TestBrowser=".$Browser."&Test_Node=".$Test_x."&Testset=".$testset."&Hub=".$Hub."&TestedBuild=".urlencode($Build);					
			}
			$execute = $execute.$ID;
		
			if($localrun == "local")
			$test = $test."&LocalBrower=true";
			if($parallel == "parallel")
			$test = $test."&ParallelRun=false";
			if ($retry == 'retry')
			$test = $test."&RETRY_FAILED=true";
		
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
				file_get_contents($execute, false, $streamContext);
				file_get_contents($test, false, $streamContext);
				
				/*?>
				<script>
					window.onload = function() {
					var url = '<?php echo $test; ?>'; // Insert the PHP variable into the JavaScript
					var iframe = document.createElement('iframe');
					iframe.style.display = 'none'; // Hide the iframe
					iframe.src = url;
					document.body.appendChild(iframe);										
				};
				</script>
				
				<?php */
			}
			
			if ($action == "Stop")
			{
				echo "STOP<br>";
				echo $execute.'<br>';
				file_get_contents($execute, false, $streamContext);	
			}	
		}
		else
		{
			echo "<a href=\"\logs\javascript:history.go(-1)\">Retry</a>";
		}
    }	
	?>	
</body>
</html>