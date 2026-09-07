<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autotests</title>    
  </head>
  <body>
	<?php
	include_once("inc/db_connect.php");
 
	if (isset($_GET['JJob'])) 
	{$JJob = $_GET['JJob'];}
	else{$JJob = "";}
	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	
	$sql = "DELETE FROM `".$LogVersion."` WHERE JJob ='".$JJob."'";
	
	//echo "<br>".$sql."<br><br>";
	
	if ($conn->query($sql) === TRUE) {
		echo "Record deleted successfully";
	} 
	else {
    echo "Error: " . $sql . "<br>" . $conn->error;
	}
	$conn->close();
	
	header('Location: ' . $_SERVER['HTTP_REFERER']);
	?>	   
  </body>
</html>