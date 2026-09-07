<?php
include("inc/db_connect.php");

if (isset($_GET['passed'])) 
{$passed = $_GET['passed'];}
else{$passed = "";}
if (isset($_GET['failed'])) 
{$failed = $_GET['failed'];}
else{$failed = "";}
if (isset($_GET['percent'])) 
{$percent = $_GET['percent'];}
else{$percent = "";}
if (isset($_GET['Branch'])) 
{$Branch = $_GET['Branch'];}
else{$Branch = "";}
if (isset($_GET['table'])) 
{$table = $_GET['table'];}
else{$table = "";}
if (isset($_GET['analyse'])) 
{$analyse = $_GET['analyse'];}
else{$analyse = "";}


$sql="INSERT INTO `".$table."`(`passed`, `failed`, `percent`, `Branch`, `analyse`) VALUES ('".$passed."','".$failed."','".$percent."','".$Branch."','".$analyse."')";

//echo $sql."<br>";

if ($conn->query($sql) === TRUE) {
    echo "New record added successfully";
} 
else {
	echo "Error: " . $sql . "<br>" . $conn->error;
}	
$conn->close();
?>

