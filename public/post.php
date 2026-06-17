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
	include_once("inc/db_connect.php");

	if (isset($_GET['JJob'])) 
	{$JJob = $_GET['JJob'];}
	else{$JJob = "";}
	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	if (isset($_GET['JBuild'])) 
	{$JBuild = $_GET['JBuild'];}
	else{$JBuild = "";}
	if (isset($_GET['JParam'])) 
	{$JParam = $_GET['JParam'];}
	else{$JParam = "";}
	if (isset($_GET['TestNode'])) 
	{$TestNode = $_GET['TestNode'];}
	else{$TestNode = "";}
	if (isset($_GET['TCProj'])) 
	{$TCProj = $_GET['TCProj'];}
	else{$TCProj = "";}
	if (isset($_GET['Build'])) 
	{$Build = $_GET['Build'];}
	else{$Build = "";}
	if (isset($_GET['TearDownFailed'])) 
	{$TearDownFailed = $_GET['TearDownFailed'];}
	else{$TearDownFailed = "";}
	if (isset($_GET['TearDownCanceled'])) 
	{$TearDownCanceled = $_GET['TearDownCanceled'];}
	else{$TearDownCanceled = "";}
	if (isset($_GET['TearDownWarning'])) 
	{$TearDownWarning = $_GET['TearDownWarning'];}
	else{$TearDownWarning = "";}
	if (isset($_GET['TearDownPassed'])) 
	{$TearDownPassed = $_GET['TearDownPassed'];}
	else{$TearDownPassed = "";}
	if (isset($_GET['LogLink'])) 
	{$LogLink = $_GET['LogLink'];}
	else{$LogLink = "";}
	if (isset($_GET['RunDate'])) 
	{$RunDate = $_GET['RunDate'];}
	else{$RunDate = "";}
	if (isset($_GET['RunDuration'])) 
	{$RunDuration = $_GET['RunDuration'];}
	else{$RunDuration = "";}
	if (isset($_GET['Version'])) 
	{$Version = $_GET['Version'];}
	else{$Version = "";}
	if (isset($_GET['Product'])) 
	{$Product = $_GET['Product'];}
	else{$Product = "";}
	if (isset($_GET['gWVersion'])) 
	{$gWVersion = $_GET['gWVersion'];}
	else{$gWVersion = "";}
	if (isset($_GET['TestLogTyp'])) 
	{$TestLogTyp = $_GET['TestLogTyp'];}
	else{$TestLogTyp = "";}	
	if (isset($_GET['Testtype'])) 
	{$Testtype = $_GET['Testtype'];}
	else{$Testtype = "";}
	if (isset($_GET['Browser'])) 
	{$Browser = $_GET['Browser'];}
	else{$Browser = '';}
	if (isset($_GET['tag'])) 
	{$tag = $_GET['tag'];}
	else{$tag = "'-'";}
	if (isset($_GET['teamtag'])) 
	{$teamtag = $_GET['teamtag'];}
	else{$teamtag = "'-'";}
	
	if ($tag == '')
	$tag = "'-'";
	else
	$tag = "'".$tag."'";
	
	if ($teamtag == '')
	$teamtag = "'-'";
	else
	$teamtag = "'".$teamtag."'";
	
	/*
	echo "JJob=".$JJob."<br>";
	echo "JParam=".$JParam."<br>";
	echo "Product=".$Product."<br>";
	echo "Build=".$Build."<br>";
	echo "TCProj=".$TCProj."<br>";
	echo "TestNode=".$TestNode."<br>";
	echo "Version=".$Version."<br>";
	echo "Testtype=".$Testtype."<br>";
	echo "TestLogTyp=".$TestLogTyp."<br>";
	echo "teamtag=".$teamtag."<br>";
	echo "tag=".$tag."<br>";
	*/
	
	if ((($Product == "'gWWebSel'") OR ($Product == "'weWebSel'")) AND (($Version=="'x17'") or ($Version=="'x16'") or ($Version=="'x15'") or ($Version=="'x14'") or ($Version=="'x13'") or ($Version=="'x12'") or ($Version=="'x11'") or ($Version=="'we'")))
	{
		//echo "DECODE<br>";
		$TCProj="'".urldecode($TCProj)."'";
	}
	if ((($Product == "'gWWebSel'") OR ($Product == "'weWebSel'")) AND ($Version=="'x10'"))
	{
		$TCProj=trim($TCProj, '"');
		$TCProj=trim($TCProj, '\'');
		$TCProj= "'".$TCProj."'";
	}
	
	//echo "TCProj=".$TCProj."<br>";	
	if ($Product == "'gWClient'")
	{
		$Browser = "''";
	}
	else
	{
		echo "Browser=".$Browser."<br>";
	}
	
	$LogLink  = str_replace('Grid', 'Grid-x.7', $LogLink);		
	//echo $LogLink."<br>";
	
	if ($Product == "'gWClient'" or $Product == "gWClient")
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
		else if($Testtype == "'dev_x16'")
		{
			$LogVersion='x16_gwdev';
		}
		else if($Testtype == "'hf_x17'")
		{
			$LogVersion='x17_gwhf';
		}
		else if($Testtype == "'rc_x17'")
		{
			$LogVersion='x17_gwrc';
		}
		else if($Testtype == "'dev_x17'")
		{
			$LogVersion='x17_gwdev';
		}
					
		$sql = "INSERT INTO `".$LogVersion."`(`JJob`, `JBuild`, `JParam`, `TCProj`, `Version`, `Product`, `gWVersion`, `TestNode`, `Build`, `TearDownFailed`, `TearDownCanceled`, `TearDownWarning`, `TearDownPassed`, `RunDate`, `RunDuration`, `LogLink`, `TestLogTyp`, `Testtype`, `Browser`)";
		$val = " VALUES ($JJob,$JBuild,$JParam,$TCProj,$Version,$Product,$gWVersion,$TestNode,$Build,$TearDownFailed,$TearDownCanceled,$TearDownWarning,$TearDownPassed,$RunDate,$RunDuration,$LogLink,$TestLogTyp,$Testtype,$Browser)";	
	}
	else
	{
		if ($TestLogTyp == "Main")
		{
			//Delete old runs for same Build
			$sqlFirst="DELETE FROM `".$LogVersion."` WHERE TCProj=".$TCProj." AND Build=".$Build;
			$conn->query($sqlFirst);
			
			$sql = "INSERT INTO `".$LogVersion."`(`JJob`, `JBuild`, `JParam`, `TCProj`, `Version`, `Product`, `gWVersion`, `TestNode`, `Build`, `TearDownFailed`, `TearDownCanceled`, `TearDownWarning`, `TearDownPassed`, `RunDate`, `RunDuration`, `LogLink`, `TestLogTyp`, `Testtype`, `Browser`,`tag`,`teamtag`)";
			$val = " VALUES ($JJob,$JBuild,$JParam,$TCProj,$Version,$Product,$gWVersion,$TestNode,$Build,$TearDownFailed,$TearDownCanceled,$TearDownWarning,$TearDownPassed,$RunDate,$RunDuration,$LogLink,$TestLogTyp,$Testtype,$Browser,$tag,$teamtag)";	
		}
		else
		{
			//Delete old runs for same Build
			$sqlFirst="DELETE FROM `".$LogVersion."` WHERE TCProj=".$TCProj." AND Build=".$Build;
			$conn->query($sqlFirst);
			
			//Create new run for this build
			$sql = "INSERT INTO `".$LogVersion."`(`JJob`, `JBuild`, `JParam`, `TCProj`, `Version`, `Product`, `gWVersion`, `TestNode`, `Build`, `TearDownFailed`, `TearDownCanceled`, `TearDownWarning`, `TearDownPassed`, `RunDate`, `RunDuration`, `LogLink`, `TestLogTyp`, `Testtype`, `Browser`,`tag`,`teamtag`)";
			$val = " VALUES ($JJob,$JBuild,$JParam,$TCProj,$Version,$Product,$gWVersion,$TestNode,$Build,$TearDownFailed,$TearDownCanceled,$TearDownWarning,$TearDownPassed,$RunDate,$RunDuration,$LogLink,$TestLogTyp,$Testtype,$Browser,$tag,$teamtag)";						
		}
	}
	
	$sql = $sql . $val;
	
	
	/*echo "##############################";
	echo "<br><br>".$sql."<br><br>";	
	echo "##############################";
	*/
	
	if ($conn->query($sql) === TRUE) {
    //echo "New record added successfully";
	} 
	else {
    echo "Error: " . $sql . "<br>" . $conn->error;
	}
	$conn->close();

	?>
	
    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="js/bootstrap.min.js"></script>
  </body>
</html>