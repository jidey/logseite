<!DOCTYPE html>
<html lang="en">
<body>
	<?php
	include_once("inc/db_connect.php");

	if (isset($_GET['JJob'])) 
	{$JJob = $_GET['JJob'];}
	else{$JJob = "";}
	
	if (isset($_GET['Testtype'])) 
	{$Testtype = $_GET['Testtype'];}
	else{$Testtype = "";}
	
	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	
	if (isset($_GET['Product'])) 
	{$ProductFilter = $_GET['Product'];}
	else{$ProductFilter = "";}
	
	
	// Update running state
	$sqlcheck = "";
	if ($LogVersion <> "")
	{
		if ($ProductFilter == "'gWClient'" or $ProductFilter == "gWClient")
		{
			if($Testtype == "'hf_x14'")
			{
				$LogVersion='x14_gwhf';
			}
			else if($Testtype == "'rc_x14'")
			{
				$LogVersion='x14_gwrc';
			}
			else if($Testtype == "'hf_x15'")
			{
				$LogVersion='x15_gwhf';
			}
			else if($Testtype == "'rc_x15'")
			{
				$LogVersion='x15_gwrc';
			}
			else if($Testtype == "'hf_x16'")
			{
				$LogVersion='x16_gwhf';
			}
			else if($Testtype == "'rc_x16'")
			{
				$LogVersion='x16_gwrc';
			}
		}

		if((substr($LogVersion, 0, 2) == "we"))
			$sqlcheck ="UPDATE `".$LogVersion."` SET `running`=0 WHERE JJob='".$JJob."' AND Testtype='".$Testtype."' AND gWVersion='".$LogVersion."'";
		else
			$sqlcheck ="UPDATE `".$LogVersion."` SET `running`=0 WHERE JJob='".$JJob."' AND Testtype='".$Testtype."' AND gWVersion='".substr($LogVersion, 0, 3)."'";
	}
	//echo $sqlcheck;
	if ($sqlcheck <> "")	
	{
		$updatecheck = $conn->query($sqlcheck);	
	}
	$conn->close();
	$home = "https://sqs-sel-cent1.cas-software.dev/logs/index.php?&Testtype=".$Testtype."&Product=".$ProductFilter."&LogVersion=".$LogVersion;
	header( "Location: $home" );
	?>
</body>
</html>