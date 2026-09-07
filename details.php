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
  <script type="Text/JavaScript">
	function VerifyRun(id, value, LogVersion, reloading) 
	{
		$.ajax({
			url: "check.php?autoid=" + id + "&value=" + value + "&LogVersion=" + LogVersion
		})
		
		if(reloading === 1)
		{
			location.reload();			
		}
	}  		
  </script>
  
  <script type="text/javascript">
  function RerunQuick(RerunUrl, id, mainid, logTable)
  {		
		console.log(RerunUrl);
		//ReRunning Test using Defaults GridHub and JenkinsNode
		$.ajax({
		  url: RerunUrl
		});
		
		//Update Running state without refresh
		$('#runningstate-'+id).load("running_state.php");
		//Update running state in DB
		VerifyRun(id,"2",logTable,0);
		//Update Main Running state
		VerifyRun(mainid,"2",logTable,0);		
  }
  </script>
  
  <script type="text/javascript">
  function ResetRun()
  {
	location.reload();
  }
  </script>
  
</head>
<body>
	<?php
	include_once("inc/db_connect.php");

	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	
	if (isset($_GET['TestProject'])) 
	{$TestProject = $_GET['TestProject'];}
	else{$TestProject = "";}
	
	if (isset($_GET['TestProduct'])) 
	{$ProductFilter = $_GET['TestProduct'];}
	else{$ProductFilter = "";}
	
	if (isset($_GET['Filter'])) 
	{$FilterResults = $_GET['Filter'];}
	else{$FilterResults = "no";}
	
	if (isset($_GET['Filterfailed'])) 
	{$Filterfailed = $_GET['Filterfailed'];}
	else{$Filterfailed = $FilterResults;}
	
	if (isset($_GET['Testtype'])) 
	{$Testtype = $_GET['Testtype'];}
	else{$Testtype = "";}
	
	if (isset($_GET['Build'])) 
	{$Build = urldecode($_GET['Build']);}
	else{$Build = "";}
	
	if (isset($_GET['ReRun'])) 
	{$ReRun = $_GET['ReRun'];}
	else{$ReRun = "no";}
	
	if (isset($_GET['Browser'])) 
	{$Browser = $_GET['Browser'];}
	else{$Browser = "";}
	
	if (isset($_GET['JJob'])) 
	{$JJob = $_GET['JJob'];}
	else{$JJob = "";}
	
	if (isset($_GET['TestName'])) 
	{$TestName = $_GET['TestName'];}
	else{$TestName = "";}
	
	if (isset($_GET['Testset'])) 
	{$Testset = $_GET['Testset'];}
	else{$Testset = "";}
	
	if (isset($_GET['MainID'])) 
	{$MainID = $_GET['MainID'];}
	else{$MainID = "";}
	
	if (isset($_GET['Failed'])) 
	{$Failed = $_GET['Failed'];}
	else{$Failed = "";}
	
	if (isset($_GET['Warning'])) 
	{$Warning = $_GET['Warning'];}
	else{$Warning = "";}
	
	if (isset($_GET['Passed'])) 
	{$Passed = $_GET['Passed'];}
	else{$Passed = "";}
	
	if (isset($_GET['tag'])) 
	{$tag = $_GET['tag'];}
	else{$tag = '';}
	
	$LastBuildNum = "";
	include('lastbuild.php');
		
	$TestProject=trim($TestProject, '"');
	$TestProject=trim($TestProject, '\'');
	$_SERVER['HTTP_HOST']='sqs-sel-cent1.cas-software.dev';
	$actual_page = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    
    echo "<img src=Titel.jpg style=\"width:250px;height:60px;\"align=\"right\">";	
	
	if (isset($_GET['tag'])) 
	{		
		$allTestCases = "SELECT DISTINCT TCProj FROM ".$LogVersion." WHERE ((tag LIKE \"%".$tag."%\" OR JParam LIKE \"%".$tag."%\" OR TCProj LIKE \"%".$tag."%\") AND TestLogTyp = 'Single') ORDER BY JParam ASC";		
	}
	else
	{
		$allTestCases = "SELECT DISTINCT TCProj FROM ".$LogVersion." WHERE JJob='".$JJob."' AND JParam='".$TestProject."' AND TCProj <> '".$TestProject."' AND TestLogTyp = 'Single' AND Build='".$Build."' ORDER BY TCProj ASC";
	}

	//DEBUG
	
	/*echo $JJob."<br><br><br>";
	echo $Build."<br><br><br>";
	echo $TestProject."<br><br><br>";
	echo $LogVersion."<br><br><br>";	
	echo "SQL:  ".$allTestCases."<br><br><br>";
	*/
	$actual_link = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
	$TestsList = $conn->query($allTestCases);	
	?>
	<div class="container-fluid">
	<br>
		<div class="dropdown">
		  <?php		
			include('mainmenu.php');				  			
		  ?>
		</div>
		<div class="panel">
			<div class="panel-info">				
				<?php 	
					include('submenu.php');					
				?>					
			</div>
		</div>		
	</div>					
	<div class="container-fluid">	
	<center><h3>
		<?php
		if (isset($_GET['tag'])) 
			echo "<a href=\"\logs\index.php?LogVersion=".$LogVersion."&Filter=".$FilterResults."&Product=".$ProductFilter."&Testtype=".$Testtype."\"><< <b>".$tag." - ".$Testtype."</b></a></h3></center>";
		else
			echo "<a href=\"\logs\index.php?LogVersion=".$LogVersion."&Filter=".$FilterResults."&Product=".$ProductFilter."&Testtype=".$Testtype."\"><< <b>".$TestProject." - ".$Testtype."</b></a></h3></center>";
		?>
	</div>
	<?php
	
	if (mysqli_num_rows($TestsList) > 0)
	{		
	$i=0;		
	?>
		<div class="container-fluid">
			<div class="panel-group">
				<div class="panel">
					<div class="panel-boby">
					<?php					
					//Only Failed
					if($Filterfailed == 'yes')
					{
						echo "<a href=".$actual_page."&Filterfailed=no class=\"btn btn-danger btn-sm\" role=\"button\"><span class=\"glyphicon glyphicon-filter\"></span> Display ALL </a> ";
					}
					else
					{
						echo "<a href=".$actual_page."&Filterfailed=yes class=\"btn btn-info btn-sm\" role=\"button\"><span class=\"glyphicon glyphicon-filter\"></span> Filter Only Failed </a> ";
					}	
					?>
						<br>
						<table class="table table-bordered table-striped table-condensed">
							<thead>
							  <tr>							
								<th style="text-align:center">Num</th>
								<th style="text-align:center" class="col-lg-1">Testset</th>
								<th style="text-align:center" class="col-lg-1">FeatureTag</th>
								<th style="text-align:center" class="col-lg-1">TeamTag</th>
								<th style="text-align:center" class="col-lg-3">Scenario name</th>
								<th style="text-align:center" class="col-lg-2">Tested Build</th>
								<th style="text-align:center" class="col-lg-1">Result</th>			
								<?php
								if (!isset($_GET['tag']))
								{
									echo '<th style=\"text-align:center\" class=\"col-lg-1\">Manual</th>';
									echo '<th style="text-align:center" class="col-lg-1">Log</th>';
								}
								?>
								
								
								<th style="text-align:center" class="col-lg-2">Last Run Date</th>
								<?php
								if (!isset($_GET['tag']))
								{
									echo '<th style=\"text-align:center\" class=\"col-lg-1\">Delete</th>';
								}
								?>
							  </tr>
							</thead>
							<tbody>														
							<?php			
							$TotalFailed = mysqli_num_rows($TestsList);
							//echo $TotalFailed;
							$checkfailed = 0;
							$checkwarning = 0;
							$checkpassed = 0;
							$counter=0;
							$runningTest=false;							
							while($TestListrunrow = mysqli_fetch_assoc($TestsList)) 
							{			
								//Search lastRun for each Single failed TestCase/KeywordTest
								if (!isset($_GET['tag'])) 
								{
									$selectLastTestRun = "SELECT * FROM `".$LogVersion."` WHERE (TCProj='".$TestListrunrow["TCProj"]."' AND Build='".$Build."')  ";									
								}
								else
								{
									$selectLastTestRun = "SELECT * FROM `".$LogVersion."` WHERE (TCProj='".$TestListrunrow["TCProj"]."')"; 
								}
								
								//Debug 
								//echo $selectLastTestRun."<br><br>";												
								$sqlLastTestRun = $conn->query($selectLastTestRun);			
								$runrow = mysqli_fetch_assoc($sqlLastTestRun);																										
								$display = true;
								
								if($runrow != null)
								{	
									//Debug
									//echo $runrow["TearDownFailed"];
									//echo $runrow["checked"];
									//echo $runrow["running"];
									
									if ($runrow["running"] == "2")
										$runningTest = true;																			
									
									if($Filterfailed == 'yes') //Display Only Failed
									{
										if($runrow["TearDownFailed"] == "0" || $runrow["checked"] == "1")
										$display = false;
									}

									if ($display and $runrow != null)	
									{
										$counter++;
										echo "<td><center>".$counter."</center></td>";
										echo "<td><center>".$runrow["JParam"]."</center></td>";
										echo "<td><center>".$runrow["tag"]."</center></td>";
										echo "<td><center>".$runrow["teamtag"]."</center></td>";
										echo "<td>".$runrow["TCProj"]."</td>";
										echo "<td style=\"text-align:center\"><font color=\"black\">". trim($runrow["Build"]) ."</font></td>";
																			
										//Results
										if($runrow["TearDownFailed"] == "0")
										{
											if($runrow["TearDownWarning"] == "1")
											{
												echo "<td style=\"text-align:center\" class=\"warning\">FLAKY</td>";
												$TotalFailed--;
												$checkwarning++;
											}	
											else
											{
												echo "<td style=\"text-align:center\" class=\"success\">PASSED</td>";
												$TotalFailed--;
												$checkpassed++;
											}											
										}
										else
										{										
											if($runrow["checked"] == "1")
											{
												echo "<td style=\"text-align:center\" class=\"warning\">VERIFIED</td>";
												$TotalFailed--;
												$checkwarning++;
											}
											else
											{	
												if (!isset($actual_link))
												{
													$actual_link = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
												}
																								
												$test2 = "https://sqs-sel-cent1.cas-software.dev/logs/quickrunSel.php?JJob=".$JJob."&JParam=".urlencode($runrow["JParam"])."&AutoID=".$runrow["AutoID"]."&Filter=".$FilterResults."&LogVersion=".$LogVersion."&Testtype=".$Testtype."&Build=".urlencode($runrow["Build"])."&Testset=".$TestProject."&Product=".$ProductFilter."&TCProj=".urlencode($runrow["TCProj"]);
												//echo $test2."<br><br>";
												
												if($ReRun == "yes")
												{													
													echo "<td style=\"text-align:center\" class=\"Warning\">ReRunning<br>";																											
														echo '<script type="text/javascript">';
														echo "RerunQuick(".$test2.",".$runrow["AutoID"].",".$MainID.",'".$LogVersion."')";
														echo '</script>';																								
													echo "</td>";
												}
												else
												{
													echo "<td style=\"text-align:center\" class=\"danger\">FAILED ";
													echo "</td>";
												}
												$checkfailed++;
											}
										}
																			
										//Verified
										if (!isset($_GET['tag'])) 
										{
											echo "<td style=\"text-align:center\">";							
												//Debug
												//echo $runrow["checked"];
												if ($runrow["checked"] == 0)
												{
													echo "<input type=\"checkbox\" name=\"testvalidation\" value=\"1\" onclick=\"javascript:VerifyRun(".$runrow["AutoID"].",1,'".$LogVersion."',1)\">";											
												}
												else
												{
													echo "<input type=\"checkbox\" name=\"testvalidation\" value=\"0\" onclick=\"javascript:VerifyRun(".$runrow["AutoID"].",0,'".$LogVersion."',1)\" checked=\"checked\" >";
												}
											echo "</td>";
										}
										
										//Log
										//$old = array("http", "8080");
										//$new   = array("https", "8181");
										//$LogLink = str_replace($old, $new, $runrow["LogLink"]);
										$LogLink = $runrow["LogLink"];
										if($runrow["RunDate"] > "2023-05-11 15:00:00")
										{
											$old = array("Autotests-Web-Grid-x.7", "Autotests-We-Grid-x.7");
											$new = array("SQS_Web_TestPipe", "SQS_Web_TestPipe");
											$LogLink = str_replace($old, $new, $LogLink);
										}
										
										if($runrow["Testtype"]=="we_feat" || $runrow["Testtype"]=="web_feat")
										{
											$old = array("SQS_Web_TestPipe");
											$new = array("SQS_Web_FeatureTests");
											$LogLink = str_replace($old, $new, $LogLink);
										}
											
										echo "<td style=\"text-align:center\"><a href=\"".$LogLink."\" target=\"_blank\"><i>Allure</i></a></td>";
										echo "<td style=\"text-align:center\">".$runrow["RunDate"]."</td>";
										echo "<td style=\"text-align:center\">".$runrow["AutoID"]."</td>";
										
										//Delete Log entry
										if (!isset($_GET['tag'])) 
										{
											$test = "delete.php?AutoID=".$runrow["AutoID"]."&Version=".$LogVersion."&JJob=".$runrow["JJob"]."&TestProject=".$runrow["JParam"]."&Testtype=".$runrow["Testtype"];
											echo "<td style=\"text-align:center\"><a href=".$test." target=\"_self\" >";
											echo "<img src=\"delete.png\" alt=\"\" style=\"width:24px;height:24px;border:0\"></a></td>";
										}
									echo "</tr>";	
									}
								}
							}
							//echo $runningTest;		
							if ($runningTest)
							{
								$url = "https://sqs-sel-cent1.cas-software.dev/logs/check.php?value=2&LogVersion=".$LogVersion."&autoid=".$MainID;								
							}
							else
							{
								$url = "https://sqs-sel-cent1.cas-software.dev/logs/check.php?value=3&LogVersion=".$LogVersion."&autoid=".$MainID;
							}
							
							$streamContext = stream_context_create([
								'ssl' => array(
								'verify_peer'      => false,
								'verify_peer_name' => false,
								'method' => 'POST'
								)
							]);
							
							file_get_contents($url,false,$streamContext);
							
	
							//echo $TotalFailed;
							if($Filterfailed != 'yes')
							{
								//echo "<br>Results after re-runs and verification>> ";	
								if ($Testtype == "hf_x12" or $Testtype == "rc_x12")								
								{
									$Passednew=$Passed;
								}
								else
									$Passednew=$checkpassed;
								
								//($Passed + $Failed + $Warning -$checkwarning -$checkfailed);
								$Warningnew=$checkwarning;
								$Failednew=$checkfailed;
								echo "<a class=\"btn btn-success btn-sm\" role=\"button\">".$Passednew."</a>";
								echo "<a class=\"btn btn-warning btn-sm\" role=\"button\">".$Warningnew."</a>";
								echo "<a class=\"btn btn-danger btn-sm\" role=\"button\">".$Failednew."</a>";
																
								if ($Testtype == "hf_x14")								
									$logTable = "x14_hf";
								else if ($Testtype == "dev_x15")
									$logTable = "x15_dev";	
								else if ($Testtype == "rc_x15")
									$logTable = "x15_rc";	
								else if ($Testtype == "hf_x15")								
									$logTable = "x15_hf";
                                else if ($Testtype == "dev_x16")
									$logTable = "x16_dev";	
								else if ($Testtype == "rc_x16")
									$logTable = "x16_rc";	
								else if ($Testtype == "hf_x16")								
									$logTable = "x16_hf";
								else if ($Testtype == "dev_x17")
									$logTable = "x17_dev";	
								else if ($Testtype == "rc_x17")
									$logTable = "x17_rc";	
								else if ($Testtype == "hf_x17")								
									$logTable = "x17_hf";
								else if ($Testtype == "dev_x18")
									$logTable = "x18_dev";	
								else if ($Testtype == "rc_x18")
									$logTable = "x18_rc";	
								else if ($Testtype == "hf_x18")								
									$logTable = "x18_hf";
								else
									$logTable = $Testtype;	
									
								if (!isset($_GET['tag'])) 
								{
									
									if (isset($_GET['MainID'])) 
									{
										$refreshResultSQL = "UPDATE `".$logTable."` SET `TearDownFailed`=".$Failednew.",`TearDownWarning`=".$Warningnew.",`TearDownPassed`=".$Passednew." WHERE AutoID='".$MainID."'";										
									}
									else{
										$findMainID = "SELECT AUTOID FROM `".$logTable."` WHERE JParam='".$TestProject."' AND TestLogTyp='main' ORDER BY `".$logTable."`.`AutoID` DESC LIMIT 1";
										//echo $findMainID."<br>";
										$newID=$conn->query($findMainID);	
										$TestListrunrow = mysqli_fetch_assoc($newID);
										$foundnewID=$TestListrunrow["AUTOID"];
										
										$refreshResultSQL = "UPDATE `".$logTable."` SET `TearDownFailed`=".$Failednew.",`TearDownWarning`=".$Warningnew.",`TearDownPassed`=".$Passednew." WHERE AutoID='".$foundnewID."'";										
									}
									
									//echo $refreshResultSQL;
									$refreshResult = $conn->query($refreshResultSQL);	
								}
							}							
							?>
							</tbody>
						</table>
						<table class="table table-bordered table-condensed>
								<tbody>
									<div class="row">
										<?php
										if($Browser == "")
										$Browser = "chrome";
										$actual_link = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];									
										//echo $JJob."<br>";
										if($JJob == "Autotests-We-Grid" or $JJob == 'Autotests-Web-Grid')
										{
											$test = "runGridfailed.php?JJob=".$JJob."&Testtype=".$Testtype."&LogVersion=".$LogVersion."&Product=".$ProductFilter."&url=".urlencode($actual_link)."&Build=".urlencode($Build)."&TestBrowser=".$Browser."&TestProject=".$TestProject;
											
										}
										else
										{										
											$test = "runFeatFailed.php?JJob=".$JJob."&Testtype=".$Testtype."&LogVersion=".$LogVersion."&Product=".$ProductFilter."&url=".urlencode($actual_link)."&Build=".urlencode($Build)."&TestBrowser=".$Browser."&TestProject=".$TestProject;
										}
										//echo $test."<br>";												
										echo '<td align="right"><a href='.$test.' target=_self><img title="Run ALL FAILED" src=clock.png></a>';	
										$cancel = "cancel.php?JJob=".$JJob."&Testtype=".$Testtype."&Product=".$ProductFilter."&LogVersion=".$LogVersion;	
										echo '<a href='.$cancel.' target=_self><img title="CLEAR RUNS" src=cancel.png alt= style=width:24px;height:24px;border:0></a></td>';
										?>
									</div>	
								</tbody>		
						</table>
					</div>						
				</div>
			</div>				
		</div>
	<?php			
	}
	else
	{
		echo "<center><h3>No Failed Runs found for \"".$TestProject."\" ".$Testtype."</h3></center>";
	}
	
	$conn->close();?>
	
</body>
</html>