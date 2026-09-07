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
	{$jenkins = $_GET['url'];}
	else{$jenkins = "https://sqs-sel-cent1.cas-software.dev/logs/index.php";}
	
	if (isset($_GET['JJob'])) 
	{$JJob = $_GET['JJob'];}
	else{$JJob = "";}

	if (isset($_GET['JParam'])) 
	{$JParam = $_GET['JParam'];}
	else{$JParam = "";}
	
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
	
	if ($Product == "'gWClient'" or $Product == "gWClient")
	{
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
	}
	
	$test="https://dcs-19-cis1:8181/view/TC_Testcomplete/job/".$JJob."/buildWithParameters?token=TCAUTO&delay=2sec&TestedProject=".$JParam;
	$runn="https://sqs-autotest-gw-8.cas-software.dev/check.php?value=2&autoid=".$AutoID."&LogVersion=".$LogVersion;	
	
	
	echo "<br><center>Please confirm the test EXECUTION on ".$Testtype;
	echo "<h4>".$JJob."<br> ---- <br>".$JParam."</h4><br>";
	
	$execute = false;
	if(isset($_GET['Confirm']) OR isset($_GET['Abort']))
	{
		$execute = true;		
	}
	
	if ($execute == false)
	{
		echo "<form action=run.php?JJob=".$JJob."&LogVersion=".$LogVersion."&JParam=".$JParam."&Testtype=".$Testtype."&AutoID=".$AutoID." class=\"form-inline\" role=\"form\">";	
		include('rungwsystems.php');
		?>
		<br><br>
		<input type="submit" name="Confirm" value="Confirm" class="btn btn-success" onclick="Confirm()" />
		<input type="submit" name="Abort" value="Abort" class="btn btn-danger" onclick="Abort()" />		
		<input type="hidden" name="test" value="<?php echo $test; ?>">
		<input type="hidden" name="runn" value="<?php echo $runn; ?>">
		<input type="hidden" name="jenkins" value="<?php echo $jenkins; ?>">
		<input type="hidden" name="JJob" value="<?php echo $JJob; ?>">
		<input type="hidden" name="JParam" value="<?php echo $JParam; ?>">
		<input type="hidden" name="AutoID" value="<?php echo $AutoID; ?>">	
		<input type="hidden" name="LogVersion" value="<?php echo $LogVersion; ?>">		
		<input type="hidden" name="Product" value="<?php echo $Product; ?>">		
		<input type="hidden" name="branch" value="<?php echo $Testtype; ?>">		
		</div>
	</form>
	</center>
	<?php	
	}	
	if($_GET){
		if(isset($_GET['Confirm'])){
			if (substr($LogVersion,0,3) == "x14")
			{
				Confirm($_GET['test'],$_GET['runn'],$_GET['jenkins'],$_GET['branch'],$_GET['Test_x14'],$_GET['LogVersion']);
			}
			if (substr($LogVersion,0,3) == "x15")
			{
				Confirm($_GET['test'],$_GET['runn'],$_GET['jenkins'],$_GET['branch'],$_GET['Test_x15'],$_GET['LogVersion']);
			}
            if (substr($LogVersion,0,3) == "x16")
			{
				Confirm($_GET['test'],$_GET['runn'],$_GET['jenkins'],$_GET['branch'],$_GET['Test_x16'],$_GET['LogVersion']);
			}
			if (substr($LogVersion,0,3) == "x17")
			{
				Confirm($_GET['test'],$_GET['runn'],$_GET['jenkins'],$_GET['branch'],$_GET['Test_x17'],$_GET['LogVersion']);
			}
		}
		elseif(isset($_GET['Abort'])){
			Abort($jenkins);
		}
	}

    function Abort($jenkins)
    {        
		 header( "Location: $jenkins" );
    }
	
    function Confirm($test,$runn,$jenkins,$branch,$Test_x,$LogVersion)
    {
        if (true)
		{			
			if (substr($LogVersion,0,3) == "x16")
			{
				$test = $test."&Test_Version=".$branch."&Test_x16=".$Test_x;
			}
            if (substr($LogVersion,0,3) == "x15")
			{
				$test = $test."&Test_Version=".$branch."&Test_x15=".$Test_x;
			}
			if (substr($LogVersion,0,3) == "x14")
			{
				$test = $test."&Test_Version=".$branch."&Test_x14=".$Test_x;
			}
			
			//echo $test;
			//echo '<br>';
			//echo $runn;
			$streamContext = stream_context_create([
			'ssl' => [
			'verify_peer'      => false,
			'verify_peer_name' => false
			]
			]);

			file_get_contents($runn, false, $streamContext);		
			file_get_contents($test, false, $streamContext);		
			header( "Location: $jenkins" );			
		}
		else
		{
			echo "<a href=\"\logs\javascript:history.go(-1)\">Retry</a>";
		}
    }
	
	?>	
</body>
</html>