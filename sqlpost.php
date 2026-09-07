<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Autotests</title>
    <!-- Bootstrap -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body>
	<?php
	include_once("inc/db_connect.php");
	?>
	<form method="post"> 
		<input type="text" name="sqlquerry"><br/>
		<button type="submit" name="save">save</button>
    </form>
	
	<?
	if(isset($_POST['save'])){
        $sql = $sqlquerry;
	}
	echo $sqlquerry;
	if ($sql != "")	
	{
		//echo $sql;
		if ($conn->query($sql) === TRUE) {
			echo "sql executed successfully";
		} 
		else {
			echo "Error: " . $sql . "<br>" . $conn->error;
		}
		$conn->close();
	}
	?>
	
	
	
    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="js/bootstrap.min.js"></script>
  </body>
</html>