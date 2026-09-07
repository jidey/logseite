<!DOCTYPE html>
<html lang="en">
<body>
	<?php
	include_once("inc/db_connect.php");

	if (isset($_GET['value'])) 
	{$value = $_GET['value'];}
	else{$value = 0;}

	if (isset($_GET['autoid'])) 
	{$autoid = $_GET['autoid'];}
	else{$autoid = "";}
	
	if (isset($_GET['JJob'])) 
	{$JJob = $_GET['JJob'];}
	else{$JJob = "";}
	
	if (isset($_GET['JParam'])) 
	{$JParam = $_GET['JParam'];}
	else{$JParam = "";}
	
	if (isset($_GET['Testtype'])) 
	{$Testtype = $_GET['Testtype'];}
	else{$Testtype = "";}
	
	if (isset($_GET['running'])) 
	{$running = $_GET['running'];}
	else{$running = "";}
	
	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	
	// Update running state
	$sqlcheck = "";
	
	if ($autoid <> "" AND $LogVersion <> "")
	{
		if ($value < 2){
		
			$sqlcheck ="UPDATE `".$LogVersion."` SET `checked`=".$value." WHERE (AutoID=".$autoid.")";
		}
		else
		{		
			$sqlcheck ="UPDATE `".$LogVersion."` SET `running`=".$value." WHERE (AutoID=".$autoid.")";
		}
	}
	else
	{
		if ($LogVersion <> "")
		{
			if ($autoid == "")
				$sqlcheck ="UPDATE `".$LogVersion."` SET `running`=3 WHERE (gWVersion='".$LogVersion."' AND JJob=".$JJob." AND JParam=".$JParam." AND Testtype=".$Testtype." AND TestLogTyp='Main' AND running=2)";	
			else
				$sqlcheck ="UPDATE `".$LogVersion."` SET `running`=2 WHERE (gWVersion='".$LogVersion."' AND JJob=".$JJob." AND JParam=".$JParam." AND Testtype=".$Testtype." AND TestLogTyp='Main')";	
		}
	}
	echo $sqlcheck;
		
	if ($sqlcheck <> "")	
	{
		$updatecheck = $conn->query($sqlcheck);	
	}
	$conn->close();
	?>
</body>
</html>