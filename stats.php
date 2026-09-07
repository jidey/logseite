<!DOCTYPE html>
<html lang="en">
<head>
  <title>Autotests</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Latest compiled and minified CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
  <!-- jQuery library -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
  <!-- Latest compiled JavaScript -->
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>  
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
	
	if (isset($_GET['LimitPlus'])) 
	{$LimitPlus = $_GET['LimitPlus'];}
	else{$LimitPlus = 5;}
	
	if (isset($_GET['TestProject'])) 
	{$TestProject = $_GET['TestProject'];}
	else{$TestProject = "";}
	
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
	
	if (isset($_GET['TestBrowser'])) 
	{$TestBrowser = $_GET['TestBrowser'];}
	else{
			$TestBrowser = "chrome";
	}
	
	$LastBuildNum = "";
	//echo $Testtype;
	echo "<img src=Titel.jpg style=\"width:250px;height:60px;\"align=\"right\">";
	
	//Search ALL different Testcomplete Projects
	if(substr($LogVersion, 0, 2) <> "we")
		$LogVersionTrimmed = substr($LogVersion, 0, 3);
	else
		$LogVersionTrimmed = $LogVersion;
	
	if ($ProductFilter == 'gWClient')
	{
		if($Testtype == 'hf_x14')
		{
			$LogVersion='x14_gwhf';
		}
		else if($Testtype == 'rc_x14')
		{
			$LogVersion='x14_gwrc';
		}
		else if($Testtype == 'hf_x15')
		{
			$LogVersion='x15_gwhf';
		}
		else if($Testtype == 'rc_x15')
		{
			$LogVersion='x15_gwrc';
		}
        else if($Testtype == 'hf_x16')
		{
			$LogVersion='x16_gwhf';
		}
		else if($Testtype == 'rc_x16')
		{
			$LogVersion='x16_gwrc';
		}
	}
	
	$selectJobs = "SELECT DISTINCT JJob FROM `".$LogVersion."` WHERE (JJob ='".$JJob."' AND Version='".$LogVersionTrimmed."' AND TestLogTyp='Main' AND JJob != '') ORDER BY `".$LogVersion."`.`JJob` ASC";
	$JobsList = $conn->query($selectJobs);	
	
	?>
	<div class="container-fluid">
	<br>
		<div class="dropdown">
		  <?php		
			include('mainmenu.php');				  			
		  ?>
		</div>		
	</div>
	<div class="container-fluid">	
	<center><h3>		
	<?php	
	$LineJob = 0;
	$Lines = 0;
	echo "<a href=\"\logs\index.php?LogVersion=".$LogVersion."&Product=".$ProductFilter."&Testtype=".$Testtype."&Filter=".$FilterResults."\"><< Teststatus</a></h3></center>";
	if (mysqli_num_rows($JobsList) > 0) 
	{						
		while($JobsListrow = mysqli_fetch_assoc($JobsList)) 
		{			
			$LineJob = $LineJob + 1;
			//Search ALL Run TCProj For JJob
			if ($TestProject != "")
			{
				$search = " AND JParam = '".$TestProject."' AND JParam != 'Alle' ORDER BY `".$LogVersion."`.`JParam` ASC";
			}
			else
			{
				$search = " AND JParam != 'Alle' ORDER BY `".$LogVersion."`.`JParam` ASC";
			}
			
			$Test = "SELECT DISTINCT JParam FROM `".$LogVersion."` 
			WHERE (Version='".$LogVersionTrimmed."' AND TestLogTyp='Main') AND JJob='".$JobsListrow["JJob"]."'".$search; 
			
			$sqlProjectsList = $conn->query($Test);
						
			if ($sqlProjectsList->num_rows > 0)			
			{
			?>
			<div class="panel-primary">
				<?php
					echo "<h3>".$JobsListrow["JJob"]."</h3>";
				?>
			</div>
			
			<?php			
			while($rowProject = mysqli_fetch_assoc($sqlProjectsList)) 
			{
			$Lines=$Lines+1;
			$Lines=$LineJob+$Lines;			
			
			//$selectAllRuns = "SELECT * FROM `".$LogVersion."` WHERE JJob='".$JobsListrow["JJob"]."' AND TestLogTyp='Main' AND Testtype='".$Testtype."' AND Browser='".$TestBrowser."' AND JParam='".$rowProject["JParam"]."' ORDER BY AutoID DESC LIMIT ".$LimitPlus;
			
			$selectAllRuns = "SELECT * FROM `".$LogVersion."` WHERE JJob='".$JobsListrow["JJob"]."' AND TestLogTyp='Main' AND Testtype='".$Testtype."' AND JParam='".$rowProject["JParam"]."' ORDER BY AutoID DESC LIMIT ".$LimitPlus;
			
			//echo $selectAllRuns;
			$sqlTestsList = $conn->query($selectAllRuns);
			?>										
					<div class="panel panel-primary">
						<?php
						echo "<div class=\"panel-group\" id=\"accordion".$Lines.">";
						?>	
							<div class="panel-heading">	
								<!-- Jenkins Jobs -->
								<?php
								echo "<h5><a data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"\logs\#collapse".$Lines."\">".$rowProject["JParam"]."</a></h5>";
								?>																	
							</div>					
							<div class="panel-boby">
							<?php
							if ($TestProject != "")
							{
								echo "<div id=\"collapse".$Lines."\" class=\"panel-collapse collapse in\">";
							}
							else
							{
								echo "<div id=\"collapse".$Lines."\" class=\"panel-collapse collapse\">";
							}
							
							if ($sqlTestsList->num_rows == 0)
							{
								echo "No results <a href=\"\logs\details.php?LogVersion=".$LogVersion."\" > >> Display ALL </a>";
								echo "<br>";
							}
							else
							{
							?>
							<table class="table table-bordered table-condensed">
									<thead>
									  <tr>							
										<th style="text-align:center" class="col-lg-1">TestNode</th>
										<th style="text-align:center" class="col-lg-1">Passed</th>
										<th style="text-align:center" class="col-lg-1">Verified</th>
										<th style="text-align:center" class="col-lg-1">Failed</th>
										<th style="text-align:center" class="col-lg-1">Tested Build</th>
										<th style="text-align:center" class="col-lg-1">Delete Log</th>
										<th style="text-align:center" class="col-lg-2">RunDate</th>						
										<th style="text-align:center" class="col-lg-2">Duration</th>	
										<th style="text-align:center" class="col-lg-1">Log</th>										
										<th style="text-align:center" class="col-lg-1">TestBranch</th>										
										<th style="text-align:center" class="col-lg-1">ID</th>										
										
									  </tr>
									</thead>
									<?php
									while($row = mysqli_fetch_assoc($sqlTestsList)) 
									{								
									?>		
									<tbody>
										<?php
										echo "<tr>";											
											echo "<td style=\"text-align:center\">".$row["TestNode"]."</td>";
											echo "<td style=\"text-align:center\" class=\"success\"><b>".$row["TearDownPassed"]."</b></td>";
											if ($row["TearDownWarning"] != "0")
											{
												echo "<td style=\"text-align:center\" class=\"warning\"><font color=\"orange\"><b>".$row["TearDownWarning"]."</b></font></td>";
											}
											else
											{
												echo "<td style=\"text-align:center\" class=\"success\">".$row["TearDownWarning"]."</td>";
											}									
											if ($row["TearDownFailed"] != "0")
											{
												echo "<td style=\"text-align:center\" class=\"danger\"><font color=\"red\"><b>".$row["TearDownFailed"]."</b></font></td>";
											}
											else
											{
												echo "<td style=\"text-align:center\" class=\"success\">".$row["TearDownFailed"]."</td>";
											}
											
											echo "<td style=\"text-align:center\">". trim($row["Build"]) ."</td>";		
											//Delete Log entry
											$test = "delete.php?AutoID=".$row["AutoID"]."&Version=".$LogVersion."&TestProject=".$row["JParam"]."&JJob=".$row["JJob"]."&Testtype=".$row["Testtype"]."&TestLogType=".$row["TestLogTyp"]."&Build=".urlencode($row["Build"]);
											echo "<td style=\"text-align:center\"><a href=".$test." target=\"_self\" >";
											echo "<img src=\"delete.png\" alt=\"\" style=\"width:24px;height:24px;border:0\"></a></td>";
											
											echo "<td style=\"text-align:center\">".$row["RunDate"]."</td>";
											echo "<td style=\"text-align:center\">".$row["RunDuration"]."</td>";

											$old = array("http:", "8080");
											$new   = array("https:", "8181");
											$LogLink = str_replace($old, $new, $row["LogLink"]);
											if($row["RunDate"] > "2023-05-11 15:00:00")
											{
												$old = array("Autotests-Web-Grid-x.7", "Autotests-We-Grid-x.7");
												$new = array("SQS_Web_TestPipe", "SQS_Web_TestPipe");
												$LogLink = str_replace($old, $new, $LogLink);
											}
											if($row["Testtype"]=="we_feat" || $row["Testtype"]=="web_feat")
											{
												$old = array("SQS_Web_TestPipe");
												$new = array("SQS_Web_FeatureTests");
												$LogLink = str_replace($old, $new, $LogLink);
											}
											echo "<td style=\"text-align:center\"><a href=\"".$LogLink."\" target=\"_blank\">Test Log</a></td>";
											echo "<td style=\"text-align:center\">".$row["Testtype"]."</td>";
											echo "<td style=\"text-align:center\">".$row["AutoID"]."</td>";									
										echo "</tr>
									</tbody>";								
									}									
									?>																																			
							</table>
							<?php
							echo "<center><a href=\"\logs\stats.php?&Product=".$ProductFilter."&JJob=".$JJob."&Filter=".$FilterResults."&LogVersion=".$LogVersion."&Testtype=".$Testtype."&TestProject=".$TestProject."&LimitPlus=".($LimitPlus+5)."\">More...</a></center>";
							}					
							?>
							</div>														
						</div>
					</div>							
			<?php
			}			
			echo "</div>";
			}
		}
	}
	else
	{
		echo "<center>No Test Data available</center>";
	}
	$conn->close();?>
	</div>
</body>
</html>