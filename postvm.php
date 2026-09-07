<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>VM Manage Post</title>

    <!-- Bootstrap -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>
	<?php
	$servername = "localhost";
	$username = "root";
	$password = "cascas";
	$dbname = "vmpowermngt";

	// Create connection
	$conn = new mysqli($servername, $username, $password, $dbname);
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	} 
	
	if (isset($_GET['vmname'])) 
	{$vmname = $_GET['vmname'];}
	else{$vmname = "";}
	
	if (isset($_GET['managable'])) 
	{$managable = $_GET['managable'];}
	else{$managable = "0";}
	
	if (isset($_GET['OFF_Evening'])) 
	{$OFF_Evening = $_GET['OFF_Evening'];}
	else{$OFF_Evening = "0";}
	if (isset($_GET['ON_Morning'])) 
	{$ON_Morning = $_GET['ON_Morning'];}
	else{$ON_Morning = "0";}
	if (isset($_GET['OFF_Morning'])) 
	{$OFF_Morning = $_GET['OFF_Morning'];}
	else{$OFF_Morning = "0";}
	if (isset($_GET['ON_Evening'])) 
	{$ON_Evening = $_GET['ON_Evening'];}
	else{$ON_Evening = "0";}
		
	if (isset($_GET['addvm'])) {
        $sql = "INSERT `vmpower` (`VMName`, `Managable`, `Off_evening`, `On_morning`, `Off_morning`, `On_evening`) VALUES ('$vmname','$managable','$OFF_Evening','$ON_Morning','$OFF_Morning','$ON_Evening')";
    }
    elseif (isset($_GET['delvm'])) {
        $sql = "DELETE FROM `vmpower` WHERE VMName='$vmname'";
    }
	else {
        $sql = "UPDATE `vmpower` SET `Managable`=$managable,`Off_evening`=$OFF_Evening,`ON_Morning`=$ON_Morning,`OFF_Morning`=$OFF_Morning,`ON_Evening`=$ON_Evening where VMName='$vmname'";
    }
	
	$jenkins = "https://sqs-autotest-gw-8.cas-software.dev/config.php";
	
	if ($conn->query($sql) === TRUE) {
		echo "Record updated successfully";
		
	} 
	else {
		echo "Error: " . $sql . "<br>" . $conn->error;
	}
	header( "Location: $jenkins" );
	$conn->close();	

	?>
	
    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="js/bootstrap.min.js"></script>
  </body>
</html>