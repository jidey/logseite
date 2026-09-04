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

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>
	<?php
	require_once '../../_config/config.php';

	if (isset($_GET['JJob']))
	{$JJob = $_GET['JJob'];}
	else{$JJob = "";}
	if (isset($_GET['Version']))
	{$Version = $_GET['Version'];}
	else{$Version = '';}
	if (isset($_GET['AutoID']))
	{$AutoID = $_GET['AutoID'];}
	else{$AutoID = '';}
	if (isset($_GET['TestProject']))
	{$TestProject = $_GET['TestProject'];}
	else{$TestProject = "";}
	if (isset($_GET['Testtype']))
	{$Testtype = $_GET['Testtype'];}
	else{$Testtype = "";}
	if (isset($_GET['TestLogType']))
	{$TestLogType = $_GET['TestLogType'];}
	else{$TestLogType = "";}
	if (isset($_GET['Build']))
	{$Build = $_GET['Build'];}
	else{$Build = "";}

	echo $Version."<br>";
	echo $AutoID."<br>";
	echo $TestProject."<br>";
	echo $Testtype."<br>";
	echo $TestLogType."<br>";
	echo $Build."<br>";

	if($TestLogType <> "Main")
	{
		$sql = "DELETE FROM `".$Version."` WHERE `AutoID` = ".$AutoID." AND JJob ='".$JJob."'";
	}
	else
	{
		$sql = "DELETE FROM `".$Version."` WHERE `Build` = '".$Build."' AND `JParam` = '".$TestProject."' AND JJob ='".$JJob."'";
	}

	echo "<br>".$sql."<br><br>";

	$stmtJobs = $pdo->prepare($sql);
    $stmtJobs->execute();

	header('Location: ' . $_SERVER['HTTP_REFERER']);
	?>

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="js/bootstrap.min.js"></script>
  </body>
</html>
