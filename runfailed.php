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

	if (isset($_GET['Product'])) 
	{$Product = $_GET['Product'];}
	else{$Product = "";}
	
	if (isset($_GET['Filter'])) 
	{$FilterResults = $_GET['Filter'];}
	else{$FilterResults = "no";}
	
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
	
	if($Testtype == 'hf_x14')
	{
		$LogVersion='x14_gwhf';
	}
	else if($Testtype == 'rc_x14')
	{
		$LogVersion='x14_gwrc';
	}
	else if($Testtype == 'hf_x15')
	{
		$LogVersion='x15_gwhf';
	}
	else if($Testtype == 'rc_x15')
	{
		$LogVersion='x15_gwrc';
	}
    else if($Testtype == 'hf_x16')
	{
		$LogVersion='x16_gwhf';
	}
	else if($Testtype == 'rc_x16')
	{
		$LogVersion='x16_gwrc';
	}
	
	//echo '<br>'.$LastBuildNum.'<br>';
	$test="https://dcs-19-cis1:8181/view/TC_Testcomplete/job/".$JJob."/buildWithParameters?token=TCAUTO&delay=2sec";
	$runn="https://sqs-autotest-gw-8.cas-software.dev/check.php?value=2&LogVersion=".$LogVersion."&autoid=";
	
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
		echo "<form action=runfailed.php?JJob=".$JJob."&Testtype=".$Testtype." class=\"form-inline\" role=\"form\">";	
		include('rungwsystems.php');
		?>
		<br>
			<div class="form-group">
				<input type="checkbox" name="versioncheck" value=true> Check to Run old "Version Tested"<br>
			</div>
		<br><br>
		<input type="submit" name="Confirm" value="Confirm" class="btn btn-success" onclick="RunTest()" />
		<input type="submit" name="Abort" value="Abort" class="btn btn-danger" onclick="Abort()" />		
		<input type="hidden" name="test" value="<?php echo $test; ?>">
		<input type="hidden" name="runn" value="<?php echo $runn; ?>">
		<input type="hidden" name="JJob" value="<?php echo $JJob; ?>">
		<input type="hidden" name="jenkins" value="<?php echo $jenkins; ?>">
		<input type="hidden" name="LogVersion" value="<?php echo $LogVersion; ?>">		
		<input type="hidden" name="Product" value="<?php echo $Product; ?>">		
		<input type="hidden" name="branch" value="<?php echo $Testtype; ?>">		
		</div>
	</form>
	</center>
	<?php	
	}	
	
	if(isset($_GET['Confirm']))
	{
		$LastBuildNum = "0";
		$Testtype = $_GET['branch'];
		$LogVersion = $_GET['LogVersion'];
		$JJob=$_GET['JJob'];
		if(!isset($_GET['versioncheck']))
		{
			 $oldversion = false;
		} else {
			 $oldversion = $_GET['versioncheck'];
		}
		
		//include('lastbuild.php');
		
		//DEBUG
		//echo "LastBuildNum='".$LastBuildNum."'<br>";			
		$selectLastRuns = "SELECT l1.* FROM `".$LogVersion."` as l1 LEFT JOIN `".$LogVersion."` as l2 
		ON (l1.JParam = l2.JParam and l1.jjob = l2.jjob AND l1.TestLogTyp = l2.TestLogTyp AND l1.Testtype = l2.Testtype AND l1.AutoID < l2.AutoID)
		WHERE ((l1.JJob = '".$JJob."' AND l1.Testtype = '".$Testtype."' AND l1.TestLogTyp = 'Main' AND l2.AutoID is NULL)"; 

		//echo "DEBUG Versioncheck= ".$oldversion."<br>";
		if(!$oldversion)
		{
			//Without Version check only failed Tests
			$selectLastRuns = $selectLastRuns . " AND (l1.TearDownFailed != \"0\") AND (l1.running != 2) AND (l1.running != 2) AND (l1.checked != 1)";	
		}
		else
		{
			//With Version Check
			$selectLastRuns = $selectLastRuns . " AND (l1.TearDownFailed != \"0\" OR l1.Build != \"".$LastBuildNum."\") AND (l1.running != 2) AND (l1.checked != 1)";		
		}
	
		$selectLastRuns = $selectLastRuns . ") ORDER BY l1.RunDate DESC, l1.JBuild DESC";			
		//echo $selectLastRuns."<br><br>";
		
		$sqlTestsList = $conn->query($selectLastRuns);			
		if (mysqli_num_rows($sqlTestsList) > 0)
		{
			while($row = mysqli_fetch_assoc($sqlTestsList))
			{
				$JParam=$row["JParam"];
				$ID=$row["AutoID"];
				
				if (substr($LogVersion,0,3) == "x14")
				{
					RunTest($_GET['test'],$_GET['runn'],$_GET['jenkins'],$_GET['branch'],$_GET['Test_x14'],$LogVersion,$JParam,$ID);
				}
				if (substr($LogVersion,0,3) == "x15")
				{
					RunTest($_GET['test'],$_GET['runn'],$_GET['jenkins'],$_GET['branch'],$_GET['Test_x15'],$LogVersion,$JParam,$ID);
				}	
                if (substr($LogVersion,0,3) == "x16")
				{
					RunTest($_GET['test'],$_GET['runn'],$_GET['jenkins'],$_GET['branch'],$_GET['Test_x16'],$LogVersion,$JParam,$ID);
				}
				if (substr($LogVersion,0,3) == "x17")
				{
					RunTest($_GET['test'],$_GET['runn'],$_GET['jenkins'],$_GET['branch'],$_GET['Test_x17'],$LogVersion,$JParam,$ID);
				}				
			}
			header( "Location: $jenkins" );				
		}
	}
	elseif(isset($_GET['Abort'])){
		Abort($jenkins);
	}

    function Abort($jenkins)
    {        
		 header( "Location: $jenkins" );
    }
	
    function RunTest($test,$runn,$jenkins,$Test_Version,$Test_x,$LogVersion,$JParam,$ID)
    {
        	if (substr($LogVersion,0,3) == "x17")
			{
				$test = $test."&TestedProject=".$JParam."&Test_Version=".$Test_Version."&Test_x17=".$Test_x;
				$runn = $runn.$ID;
			}
			if (substr($LogVersion,0,3) == "x16")
			{
				$test = $test."&TestedProject=".$JParam."&Test_Version=".$Test_Version."&Test_x16=".$Test_x;
				$runn = $runn.$ID;
			}
            if (substr($LogVersion,0,3) == "x15")
			{
				$test = $test."&TestedProject=".$JParam."&Test_Version=".$Test_Version."&Test_x15=".$Test_x;
				$runn = $runn.$ID;
			}
			if (substr($LogVersion,0,3) == "x14")
			{
				$test = $test."&TestedProject=".$JParam."&Test_Version=".$Test_Version."&Test_x14=".$Test_x;
				$runn = $runn.$ID;
			}			
			
			//echo $test.'<br>';
			//echo $runn.'<br>';
			$streamContext = stream_context_create([
			'ssl' => [
			'verify_peer'      => false,
			'verify_peer_name' => false
			]
			]);

			file_get_contents($runn, false, $streamContext);		
			file_get_contents($test, false, $streamContext);		
    }
	?>	
</body>
</html>