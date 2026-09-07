<?php
	if (isset($_GET['Product'])) 
		$Product = $_GET['Product'];
	else
		$Product = "gWClient";
	
	//Refresh Results
	if ($Product <> 'gWClient')
	{
		echo "<a href=\"index.php?LogVersion=".$LogVersion."&Testtype=".$Testtype."&Product=".$ProductFilter."&Filter=no&Refresh=yes \"class=\"btn btn-warning btn-big\" role=\"button\"><span title=\"Update all failed testcases\" class=\"glyphicon glyphicon-refresh\"></span> Reload</a> ";		
	}
	
	//Only Failed
	if($FilterResults == 'yes')
	{
		echo "<a href=\"index.php?LogVersion=".$LogVersion."&Testtype=".$Testtype."&Product=".$ProductFilter."&tag=".$tag."\" class=\"btn btn-danger btn-big\" role=\"button\"><span title=\"View all testcases\" class=\"glyphicon glyphicon-remove\"></span> Failed</a> ";
	}
	else
	{
		echo "<a href=\"index.php?LogVersion=".$LogVersion."&Filter=yes&Testtype=".$Testtype."&Product=".$ProductFilter."&tag=".$tag."\" class=\"btn btn-info btn-big\" role=\"button\"><span title=\"View only failed testcases\" class=\"glyphicon glyphicon-flash\"></span> Filter</a> ";
	}	
	
	//RerunOnlyFailed
	if ($Product <> 'gWClient')
	{		
		echo "<a href=\"index.php?LogVersion=".$LogVersion."&Testtype=".$Testtype."&Product=".$ProductFilter."&Filter=yes&ReRun=yes \"class=\"btn btn-danger btn-big\" role=\"button\"><span title=\"Run each failed of each Testset (only Release Branch)\" class=\"glyphicon glyphicon-forward\"></span></a> ";
	}
	
	//Search field
	if($ProductFilter <> 'gWClient')
	{
		echo "<form action=details.php class=\"form-inline\" role=\"form\" style=\"display:inline\">";	
			
		if (isset($_GET['tag']) && $_GET['tag'] <> '') 
		{
			$tag = $_GET['tag'];
			echo "<input type=\"search\" name=\"tag\" id=\"mySearch\" value=".$tag.">";
		}
		else
		{
			echo "<input type=\"search\" name=\"tag\" id=\"mySearch\" placeholder=\"Search test or tag ...\">";
		}
		
		?>
			<input type="hidden" name="LogVersion" value="<?php echo $LogVersion; ?>">
			<input type="hidden" name="Testtype" value="<?php echo $Testtype; ?>">
			<input type="hidden" name="Product" value="<?php echo $ProductFilter; ?>">
			<input type="hidden" name="Filter" value="<?php echo $FilterResults; ?>">
			<input type="hidden" name="TestBrowser" value="<?php echo $TestBrowser; ?>">				
			<button type="submit" class="btn btn-default btn-big">Search</button>
		</form>	
		<?php
		
	}
	
	// Chrome Or Firefox
	/*echo "<a href=\"index.php?LogVersion=".$LogVersion."&Filter=".$FilterResults."&Testtype=".$Testtype."&Product=".$ProductFilter."&TestBrowser=chrome\" class=\"btn btn-info btn-sm\" role=\"button\"><span class=\"glyphicon glyphicon-repeat\"></span> Chrome</a> ";
	echo "<a href=\"index.php?LogVersion=".$LogVersion."&Filter=".$FilterResults."&Testtype=".$Testtype."&Product=".$ProductFilter."&TestBrowser=firefox\" class=\"btn btn-warning btn-sm\" role=\"button\"><span class=\"glyphicon glyphicon-repeat\"></span> Firefox</a> ";
	*/
	
	//Last Successfull Build value
	$formatLastBuild="green";
	if(isDeployed_Equal_LastBuild($LogVersion,$ProductFilter) == "false")
		$formatLastBuild="red";
	
	if ($Testtype == "hf_x16")
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		if ($Product <> 'gWClient')
		echo "<a href=\"https://sqs-sel-x16hf.cas-software.dev/smartdesign/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">gwWeb HF SERVER</b></a> ";
	}
	if ($Testtype == "hf_x17")
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		if ($Product <> 'gWClient')
		echo "<a href=\"https://sqs-sel-x17hf.cas-software.dev/smartdesign/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">gwWeb HF SERVER</b></a> ";
	}

	if ($Testtype == "rc_x14")
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		if ($Product <> 'gWClient')
		echo "<a href=\"https://sqs-sel-x14rc.cas-software.dev/smartdesign/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">gwWeb RC SERVER</b></a> ";
	}
	if ($Testtype == "rc_x15")
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		if ($Product <> 'gWClient')
		echo "<a href=\"https://sqs-sel-x15rc.cas-software.dev/smartdesign/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">gwWeb RC SERVER</b></a> ";
	}	
    if ($Testtype == "rc_x16")
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		if ($Product <> 'gWClient')
		echo "<a href=\"https://sqs-sel-x16rc.cas-software.dev/smartdesign/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">gwWeb RC SERVER</b></a> ";
	}
	if ($Testtype == "rc_x17")
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		if ($Product <> 'gWClient')
		echo "<a href=\"https://sqs-sel-x17rc.cas-software.dev/smartdesign/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">gwWeb RC SERVER</b></a> ";
	}	

	if (($Testtype == "dev_x14"))
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		if ($Product <> 'gWClient')
		echo "<a href=\"https://sqs-sel-x14dev.cas-software.dev/smartdesign/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">gwWeb DEV SERVER</b></a> ";
	}
	if (($Testtype == "dev_x15"))
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		if ($Product <> 'gWClient')
		echo "<a href=\"https://sqs-sel-x15dev.cas-software.dev/smartdesign/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">gwWeb DEV SERVER</b></a> ";
	}	
    if (($Testtype == "dev_x16"))
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		if ($Product <> 'gWClient')
		echo "<a href=\"https://sqs-sel-x16dev.cas-software.dev/smartdesign/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">gwWeb DEV SERVER</b></a> ";
	}
	if (($Testtype == "dev_x17"))
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		if ($Product <> 'gWClient')
		echo "<a href=\"https://sqs-sel-x17dev.cas-software.dev/smartdesign/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">gwWeb DEV SERVER</b></a> ";
	}
	if (($Testtype == "dev_x18"))
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		if ($Product <> 'gWClient')
		echo "<a href=\"https://sqs-sel-x18dev.cas-software.dev/smartdesign/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">gwWeb DEV SERVER</b></a> ";
	}	
	
	//SmartWe
	if ($Testtype == "we_rc")
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";
		echo "<a href=\"https://sqs-smartwe-rc.internalk8s.home.cas.de/SmartWe/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\"> WeRC SERVER</b></a> ";
		//echo "<a href=\"https://dcs-versiontool.internalk8s.home.cas.de/smartwe-versions/list\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">  (SmartWe version overview)</b></a> ";
	}
	if ($Testtype == "we_hf")
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";	
		echo "<a href=\"https://sqs-smartwe-hotfix.internalk8s.home.cas.de/SmartWe/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\"> WeHF SERVER</b></a> ";
		//echo "<a href=\"https://dcs-versiontool.internalk8s.home.cas.de/smartwe-versions/list\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">  (SmartWe version overview)</b></a> ";
	}					
	if ($Testtype == "we_dev")
	{
		echo "Deployed Build: <b><span style='color: ".$formatLastBuild.";'>" . $LastBuildNum . "</span></b> ";	
		echo "<a href=\"https://sqs-smartwe-dev.internalk8s.home.cas.de/\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\"> WeDEV SERVER</b></a> ";
		//echo "<a href=\"https://dcs-versiontool.internalk8s.home.cas.de/smartwe-versions/list\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">  (SmartWe version overview)</b></a> ";
	}	

	/*if ($Product <> 'gWClient')
	echo "<a href=\"https://dcs-lx-bdmaster/smartdesign-versions\" target=\"_blank\" class=\"btn btn-link btn-sm\" role=\"button\">  (Versions overview)</b></a> ";
	*/
		
	//echo "<a href=\"config.php\" class=\"btn btn-dark\" role=\"button\"><span class=\"glyphicon glyphicon-wrench\"></span> SQS Config</a> ";	
	
	function isDeployed_Equal_LastBuild($logVersion, $product) {
		
		$buildPrefix="last".str_replace("_", "", $logVersion);
		$buildFile = "builds/{$buildPrefix}Build.txt";
		
		if($product == "gWWebSel")
		{
			$deployPrefix="lastSel";
			$parts = explode("_", $logVersion);
			$suffi = $parts[1] . $parts[0];
			$suffix = str_replace("x", "", $suffi);
		}
		else
		{
			$deployPrefix="lastWe";
			$buildFile = strtolower($buildFile);
			
			$parts = explode("_", $logVersion);
			$suffix = $parts[0] . $parts[1];
		}
		
		$deployFile = "deployedVM/{$deployPrefix}{$suffix}Deploy.txt";
		
		$buildValue = file_exists($buildFile) ? trim(file_get_contents($buildFile)) : "N/A";
		$deployValue = file_exists($deployFile) ? trim(file_get_contents($deployFile)) : "N/A";
		
		if($buildValue == $deployValue)
			return "true";
		else
			return "false";		
	}		
	
	?>