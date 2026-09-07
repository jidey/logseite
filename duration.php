<!DOCTYPE html>
<html lang="en">
<body>
	<?php
	include_once("inc/db_connect.php");


	//flaky.php?LogVersion=x15_dev&TCProj=Failed test1
	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	
	if (isset($_GET['TestProject'])) 
	{$TCProj = $_GET['TestProject'];}
	else{$TCProj = "";}
	
	if (isset($_GET['TestProject'])) 
	{$TCProj = $_GET['TestProject'];}
	else{$TCProj = "";}
	
	if (isset($_GET['Build']))
	{$Build = urldecode($_GET['Build']);}
	else{$Build = "";}
	
	if (isset($_GET['Duration'])) 
	{$Duration = $_GET['Duration'];}
	else{$Duration = "";}
	
	$sqlupdate ="UPDATE `".$LogVersion."` SET `RunDuration`='".$Duration."' WHERE TCProj='".$TCProj."' AND Build='".$Build."' AND JParam='".$TCProj."'";
	//echo $sqlupdate."<br>";
	
	$updatecheck = $conn->query($sqlupdate);	
	$conn->close();
		//echo "Update ok";
	?>
</body>
</html>