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
	
	if (isset($_GET['LogVersion'])) 
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}
	
	if (isset($_GET['Testtype'])) 
	{$Testtype = $_GET['Testtype'];}
	else{$Testtype = "";}
		
	if (isset($_GET['FeatureBranch'])) 
	{$FeatureBranch = $_GET['FeatureBranch'];}
	else{$FeatureBranch = "";}
	
	if (isset($_GET['Test_Version'])) 
	{$Test_Version = $_GET['Test_Version'];}
	else{$Test_Version = "";}
	
	if (isset($_GET['Testset'])) 
	{$Testset = $_GET['Testset'];}
	else{$Testset = "";}
	
	if (isset($_GET['ServerURL'])) 
	{$ServerURL = $_GET['ServerURL'];}
	else{$ServerURL = "";}
	
	if (isset($_GET['ServerPort'])) 
	{$ServerPort = $_GET['ServerPort'];}
	else{$ServerPort = "9090";}
	
	if (isset($_GET['RestPort'])) 
	{$RestPort = $_GET['RestPort'];}
	else{$RestPort = "8080";}
	
	if (isset($_GET['Test_Node'])) 
	{$Test_Node = $_GET['Test_Node'];}
	else{$Test_Node = "";}
	
	if (isset($_GET['Product'])) 
	{$Product = $_GET['Product'];}
	else{$Product = "";}
	
	$test="https://dcs-19-cis1:8181/view/TC_Testcomplete/job/SQS_Web_TestPipe/buildWithParameters?token=TCAUTO&delay=2sec&DebugFeature=true";
	
	echo "<br><center><h1>Test Run on Feature Branch</h1>";
	echo "(Central <a href=\"\logs\http://172.24.100.86:4444/ui\" target=\"_blank\">Grid</a> Hub)<br><br>";
	
	$execute = false;
	if(isset($_GET['Confirm']))
	{
		$execute = true;		
	}
	
	if ($execute == false)
	{
		echo "<form action=runfeature.php class=\"form-inline\" role=\"form\">";	
	?>		
		<table class="tg">
		<style type="text/css">
		.tg  {border-collapse:collapse;border-spacing:0;}
		.tg td{border-color:black;border-style:solid;border-width:1px;font-family:Arial, sans-serif;font-size:14px;
		  overflow:hidden;padding:10px 5px;word-break:normal;}
		.tg th{border-color:black;border-style:solid;border-width:1px;font-family:Arial, sans-serif;font-size:14px;
		  font-weight:normal;overflow:hidden;padding:10px 5px;word-break:normal;}
		.tg .tg-de2y{border-color:#333333;text-align:left;vertical-align:top}
		.tg .tg-0lax{text-align:left;vertical-align:top}
		</style>
		<table class="tg">
		<tbody>
		  <tr>
			<td class="tg-de2y"><b>Product</b></td>
			<td class="tg-de2y">
			<select name="Product">
			<?php
			if($Productlabel <> '')
				echo "<option value=".$Productlabel.">".$Productlabel."</option>";
			else {
				echo "<option value=\"Web\">Web</option>";
				echo "<option value=\"We\">We</option>";
			}
			?>				
			</select>
			</td>
		  </tr>
		  <tr>
			<td class="tg-de2y">Build_Node</td>
			<td class="tg-de2y">
				<select name="Test_Node">
					<option value='TC-Autotest'>TC-Autotest (Sequential)</option>
					<option value='Grid'>Grid (Multiple parallel)</option>
					<option value='TC-x16-dev'>TC-x16-dev (Sequential)</option>
                    <option value='TC-x15-dev'>TC-x15-dev (Sequential)</option>
					<option value='TC-x15-rc'>TC-x15-rc (Sequential)</option>
					<option value='TC-x15-hf'>TC-x15-hf (Sequential)</option>
				</select>
				 (Jenkins Node where Build is done)
			</td>
		  </tr>
		  <tr>
			<td class="tg-de2y">Feature Branch *</td>
			<td class="tg-de2y">
				<?php
				if($FeatureBranch <> '')
					echo "<input type=\"label\" id=\"FeatureBranch\" name=\"FeatureBranch\" value=".$FeatureBranch.">";
				else {
					echo "<input type=\"label\" id=\"FeatureBranch\" name=\"FeatureBranch\" value=\"\">";
				}
				?>				
				 (Branch checked out for Testing Code)
			</td>
		  </tr>
		  <tr>
			<td class="tg-de2y">Test Server *</td>
			<td class="tg-de2y">
				<select name="Test_Version">
				<?php
				if($Test_Version <> '')
					echo "<option value=".$Test_Version.">".$Test_Version."</option>";
				else if($Productlabel == "We") {
					echo "					
					<option value=''></option>
					<option value='dev/13.x'>dev/13.x</option>
					<option value='rc/13.x'>rc/13.x</option>
					<option value='hotfix/13.x'>hotfix/13.x</option>";					
				}
				else {
					echo "					
					<option value=''></option>
                    <option value='dev/12.x'>dev/12.x</option>
					<option value='rc/12.x'>rc/12.x</option>
					<option value='hotfix/12.x'>hotfix/12.x</option>
					<option value='dev/11.x'>dev/11.x</option>
					<option value='rc/11.x'>rc/11.x</option>
					<option value='hotfix/11.x'>hotfix/11.x</option>
					<option value='hotfix/10.x'>hotfix/10.x</option>";
				}
				?>				
				</select>
				 (Version of TestServer)
			</td>
		  </tr>
		  <tr>
			<td class="tg-0lax">Testset *</td>
			<td class="tg-0lax">
				<select name="Testset">
					<?php
					if($Testset <> '')
						echo "<option value=".$Testset.">".$Testset."</option>";
					else {
						echo "
						<option value=''></option>
						<option value='@nightly'>@nightly</option>
						<option value='@smokeTest'>@smokeTest</option>
						<option value='@UVZ'>@UVZ</option>
						<option value='addressidentities'>addressidentities</option>
						<option value='addressrelation'>addressrelation</option>
						<option value='administration'>administration</option>
						<option value='appdesigner'>appdesigner</option>
						<option value='appsmanagement'>appsmanagement</option>
						<option value='appstests'>appstests</option>
						<option value='appstore'>appstore</option>
						<option value='basicfeatures'>basicfeatures</option>
						<option value='calendar'>calendar</option>
						<option value='charts'>charts</option>
						<option value='deeplinking'>deeplinking</option>
						<option value='distributionlist'>distributionlist</option>
						<option value='documentcreation'>documentcreation</option>
						<option value='emailcampaign'>emailcampaign</option>
						<option value='event'>event</option>
						<option value='fieldgroups'>fieldgroups</option>
						<option value='filter'>filter</option>
						<option value='importing'>importing</option>
						<option value='linking'>linking</option>
						<option value='lists'>lists</option>
						<option value='nestededit'>nestededit</option>
						<option value='opportunities'>opportunities</option>
						<option value='radialmenu'>radialmenu</option>
						<option value='receipts'>receipts</option>
						<option value='recurrringappointments'>recurrringappointments</option>
						<option value='scriptedcommands'>scriptedcommands</option>
						<option value='search'>search</option>
						<option value='settings'>settings</option>
						<option value='smartactions'>smartactions</option>
						<option value='smartroutines'>smartroutines</option>
						<option value='templates'>templates</option>
						<option value='viewmanagement'>viewmanagement</option>
						<option value='webwidgets'>webwidgets</option>";
					}
					?>						
				</select>
			 (TestSet to be executed)
			</td>
		  </tr>
		  <tr>
			<td class="tg-0lax">Custom Server URL</td>
			<td class="tg-0lax">
			  <input type="label" id="ServerURL" name="ServerURL" value=""> (Full Custom Server Url:port)<br>			  
			</td>
		  </tr>
		</tbody>
		</table>

		<input type="hidden" name="test" value="<?php echo $test; ?>">			
		<input type="submit" name="Confirm" value="Execute" class="btn btn-success" onclick="Confirm()" />		
	</form>
	</center>
	
	<?php	
	}	
	if(isset($_GET['Confirm'])){
		Confirm($_GET['test'],$_GET['Testset'],$_GET['Product'],$_GET['Test_Version'],$_GET['FeatureBranch'],$_GET['Test_Node'],$_GET['ServerURL'],$ServerPort,$RestPort);
	}	

    function Confirm($test,$Testset,$Product,$Test_Version,$FeatureBranch,$Test_Node,$ServerURL,$ServerPort,$RestPort)
    {
	
		$test=$test."&Testset=".$Testset."&Product=".$Product."&Test_Version=".$Test_Version."&FeatureBranch=".$FeatureBranch."&Test_Node=".$Test_Node;
		
		if ($ServerURL <> '') {
			$test=$test."&CustomServerURL=".$ServerURL;
		}
		
		$streamContext = stream_context_create([
			'ssl' => [
			'verify_peer'      => false,
			'verify_peer_name' => false,
			'method'  => 'POST'
			]
	    ]);
		
		if($Product=="Web"){
			$LogVersion="web_feat";
			$Testtype="web_feat";
			$Productlabel="gWWebSel";	
			$LogUrl="https://sqs-sel-cent1.cas-software.dev/logs/index.php?LogVersion=web_feat&Testtype=web_feat&Product=gWWebSel";
		}
		else {
			$LogVersion="we_feat";
			$Testtype="we_feat";
			$Productlabel="weWebSel";	
			$LogUrl="https://sqs-sel-cent1.cas-software.dev/logs/index.php?LogVersion=we_feat&Testtype=we_feat&Product=weWebSel";
		}
		
		$JenkinsJobUrl="https://dcs-19-cis1:8181/view/TC_Testcomplete/job/SQS_Web_TestPipe/";
		
		if($FeatureBranch <> ''){		
			if($LogVersion <> ''){		
				if($Testset <> '') {
						file_get_contents($test, false, $streamContext);	
						/*echo $test."<br>";
						echo $LogUrl."<br>";
						*/
						echo "<br><h1><a href=$JenkinsJobUrl target=”_blank”>1. Wait until test build is finished</a></h1>";
						echo "<h1><a href=$LogUrl target=”_blank”>2. See results here in the Log</a></h1>";
				}
				else {			
					echo "<font color=\"red\"><b>Please specify a Testset</b></font>";			
				}	
			}	
			else	
			{
				echo "<font color=\"red\"><b>Please verify 'Test Server' value (Only 11.x for We)</b></font>";
			}
		}
		else {
			echo "<font color=\"red\"><b>Feature Branch value must be set</b></font>";
		}
	}
	?>	
</body>
</html>