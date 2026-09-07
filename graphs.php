<!DOCTYPE html>
<html lang="en">
<head>
  <title>Autotests</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Latest compiled and minified CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
  <link rel="stylesheet" type="text/css" title="design" href="index.css"/>
  <!-- jQuery library -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
  <!-- Latest compiled JavaScript -->
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>  
  
</head>
<body>
	<?php
	include_once("inc/db_connect.php");

	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "x8";}

	if (isset($_GET['Product'])) 
	{$ProductFilter = $_GET['Product'];}
	else{$ProductFilter = "";}
	
	if (isset($_GET['Filter'])) 
	{$FilterResults = $_GET['Filter'];}
	else{$FilterResults = "no";}
	
	if (isset($_GET['Versiontype'])) 
	{$Versiontype = $_GET['Versiontype'];}
	else{$Versiontype = "RC";}
	
	if (isset($_GET['Testtype'])) 
	{$Testtype = $_GET['Testtype'];}
	else{$Testtype = "";}
	
	$LastBuildNum = "";
	//include('lastbuild.php');
	
	echo "<img src=Titel.jpg style=\"width:250px;height:60px;\"align=\"right\">";
	
	//Search ALL different Jenkins Jobs (JJobs)
	if ($ProductFilter != "")
	{
		$selectJobs = "SELECT DISTINCT JJob FROM `".$LogVersion."` WHERE (Version='".$LogVersion."' AND Product='".$ProductFilter."' AND TestLogTyp='Main' AND JJob != '' and Testtype = '".$Testtype.")";
	}
	else
	{
		if ($Testtype != '')
		{
			$selectJobs = "SELECT DISTINCT JJob FROM `".$LogVersion."` WHERE (Version='".$LogVersion."' AND TestLogTyp='Main' AND JJob != '' and Testtype = '".$Testtype."')";
		}
		else
		{
			$selectJobs = "SELECT DISTINCT JJob FROM `".$LogVersion."` WHERE (Version='".$LogVersion."' AND TestLogTyp='Main' AND JJob != '')";
		}
	}
	
	$selectJobs = $selectJobs." ORDER BY `".$LogVersion."`.`JJob` ASC";
	
	$JobsList = $conn->query($selectJobs);	
	
	//DEBUG
	//echo $selectJobs;
	
	?>
	<div class="container-fluid">
	<br>
		<div class="dropdown">
		  <?php		
			include('mainmenu.php');				  			
		  ?>
		</div>		
	</div>	
	<br>
	<div class="container-fluid">	
		<div class="panel-group">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h4 class="panel-title">
					<?php
					
					echo '<button type="button" class="btn btn-primary btn-block btn-lg" data-toggle="collapse" data-target="#collapsex16">x16 Measurements</a>';					
					?>
					</h4>
					<div id="collapsex16" class="panel-collapse collapse">
						<div class="panel-heading">
							<h4 class="panel-title">
							<?php
							echo '<button type="button" class="btn btn-block btn-lg" data-toggle="collapse" data-target="#collapsex161"><b>Datatypes</b> opening time measurements (gW x16)</a>';					
							?>
							</h4>
						</div>
						<div id="collapsex161" class="panel-collapse collapse in">
							<div align=center>
							<img src="Graphs_x16-Dateien/Graphs_X16_Graphs_X8_3865_image001.png" alt=" in progess...">
							</div>
							<br>
						</div>
						<div class="panel-heading">
							<h4 class="panel-title">
							<?php
							echo '<button type="button" class="btn btn-block btn-lg" data-toggle="collapse" data-target="#collapsex162"><b>Lists</b> loading time measurements (gW x16)</a>';					
							?>
							</h4>
						</div>
						<div id="collapsex162" class="panel-collapse collapse in">
							<div align=center>
							<img src="Graphs_Listenx16-Dateien/Graphs_Listenx16_Graphs_Listenx15_3515_image001.png" alt=" in progess...">
							</div>
						</div>	
						<br>
					</div>
					<br>
                    <h4 class="panel-title">
					<?php
					echo '<button type="button" class="btn btn-primary btn-block btn-lg" data-toggle="collapse" data-target="#collapsex15">x15 Measurements</a>';					
					?>
					</h4>
					<div id="collapsex15" class="panel-collapse collapse">
						<div class="panel-heading">
							<h4 class="panel-title">
							<?php
							echo '<button type="button" class="btn btn-block btn-lg" data-toggle="collapse" data-target="#collapsex151"><b>Datatypes</b> opening time measurements (gW x15)</a>';					
							?>
							</h4>
						</div>
						<div id="collapsex151" class="panel-collapse collapse in">
							<div align=center>
							<img src="Graphs_x15-Dateien/Graphs_X15_Graphs_X8_3865_image001.png" alt=" in progess...">
							</div>
							<br>
						</div>
						<div class="panel-heading">
							<h4 class="panel-title">
							<?php
							echo '<button type="button" class="btn btn-block btn-lg" data-toggle="collapse" data-target="#collapsex152"><b>Lists</b> loading time measurements (gW x15)</a>';					
							?>
							</h4>
						</div>
						<div id="collapsex152" class="panel-collapse collapse in">
							<div align=center>
							<img src="Graphs_Listenx15-Dateien/Graphs_Listenx15_Graphs_Listenx15_3515_image001.png" alt=" in progess...">
							</div>
						</div>	
						<br>
					</div>
					<br>
					<h4 class="panel-title">
					<?php
					echo '<button type="button" class="btn btn-primary btn-block btn-lg" data-toggle="collapse" data-target="#collapsex14">x14 Measurements</a>';					
					?>
					</h4>
					<div id="collapsex14" class="panel-collapse collapse">
						<div class="panel-heading">
							<h4 class="panel-title">
							<?php
							echo '<button type="button" class="btn btn-block btn-lg" data-toggle="collapse" data-target="#collapsex141"><b>Datatypes</b> opening time measurements (gW x14)</a>';					
							?>
							</h4>
						</div>
						<div id="collapsex141" class="panel-collapse collapse in">
							<div align=center>
							<img src="Graphs_x14-Dateien/Graphs_x14_Graphs_X8_3865_image001.png" alt=" in progess...">
							</div>
							<br>
						</div>
						<div class="panel-heading">
							<h4 class="panel-title">
							<?php
							echo '<button type="button" class="btn btn-block btn-lg" data-toggle="collapse" data-target="#collapsex142"><b>Lists</b> loading time measurements (gW x14)</a>';					
							?>
							</h4>
						</div>
						<div id="collapsex142" class="panel-collapse collapse in">
							<div align=center>
							<img src="Graphs_Listenx14-Dateien/Graphs_Listenx14_3350_image001.png" alt=" in progess...">
							</div>
						</div>	
						<br>
					</div>
					<br>
					<h4 class="panel-title">
					<?php
					/*echo '<button type="button" class="btn btn-primary btn-block btn-lg" data-toggle="collapse" data-target="#collapsex13">x13 Measurements</a>';					
					?>
					</h4>
					<div id="collapsex13" class="panel-collapse collapse">
						<div class="panel-heading">
							<h4 class="panel-title">
							<?php
							echo '<button type="button" class="btn btn-block btn-lg" data-toggle="collapse" data-target="#collapsex131"><b>Datatypes</b> opening time measurements (gW x13)</a>';					
							?>
							</h4>
						</div>
						<div id="collapsex131" class="panel-collapse collapse in">
							<div align=center>
							<img src="Graphs_x13-Dateien/Graphs_X13_Graphs_X8_3865_image001.png" alt=" in progess...">
							</div>
							<br>
						</div>
						<div class="panel-heading">
							<h4 class="panel-title">
							<?php
							echo '<button type="button" class="btn btn-block btn-lg" data-toggle="collapse" data-target="#collapsex132"><b>Lists</b> loading time measurements (gW x13)</a>';					
							?>
							</h4>
						</div>
						<div id="collapsex132" class="panel-collapse collapse in">
							<div align=center>
							<img src="Graphs_Listenx13-Dateien/Graphs_Listen_7682_image001.png" alt=" in progess...">
							</div>
						</div>	
						<br>
						*/
						?>
					</div>					
				</div>
			</div>
		</div>
	</div>
</body>
</html>