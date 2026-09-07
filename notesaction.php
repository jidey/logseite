<?php
include_once("inc/db_connect.php");
	
if ($_POST['action'] == 'edit' && $_POST['newnote']) {	

	$sqlQuery = "REPLACE INTO `".trim($_POST['jjob'])."_tags` (JParam,JJob,testnotiz) VALUES ('".$_POST['id']."','".$_POST['testset']."','".$_POST['newnote']."')";	
	mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));	
	$data = array(
		"message"	=> "Record Updated",	
		"status" => 1
	);
	echo json_encode($data);		
}

if ($_POST['action'] == 'delete') {
	
	$sqlQuery = "DELETE FROM `".trim($_POST['jjob'])."_tags` WHERE (JParam='".$_POST['id']."' AND JJob='".$_POST['testset']."')";
	$sqlQuery = "DELETE FROM `".trim($_POST['jjob'])."_tags` WHERE JParam='".$_POST['id']."'";	
	mysqli_query($conn, $sqlQuery) or die("database error:". mysqli_error($conn));	
	$data = array(
		"message"	=> "Record Deleted",	
		"status" => 1
	);
	echo json_encode($data);	
}
?>