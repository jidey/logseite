<!DOCTYPE html>
<html lang="en">
<body>
	<?php
	include_once("inc/db_connect.php");


	//flaky.php?LogVersion=x15_dev&TCProj=Failed test1
	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	
	if (isset($_GET['TCProj'])) 
	{$TCProj = $_GET['TCProj'];}
	else{$TCProj = "";}
	
	//$TCProj='Failed test1';
	//$LogVersion='x15_dev';
	
	//Find autoid of Last Run
	$lastRun = "SELECT AutoID FROM ".$LogVersion." WHERE TCProj=".$TCProj." ORDER BY AutoID DESC LIMIT 1";	
	echo $lastRun."<br>";
	
	if ($lastRun <> "")	
	{
		$autoid = $conn->query($lastRun);			
		$runID = mysqli_fetch_assoc($autoid);
		$id=$runID["AutoID"];
	}
	
	$sqlupdate ="UPDATE `".$LogVersion."` SET `TearDownWarning`=1 WHERE AutoID=".$id;	
	
	//echo $sqlupdate."<br>";
	$updatecheck = $conn->query($sqlupdate);	
	$conn->close();
	
	//echo "Update ok";
	?>
</body>
</html>