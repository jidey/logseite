<?php
$ctx = stream_context_create([
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false
                ]
            ]);

if($LogVersion == "")
$LogVersion = "x17";

$position = strpos($Testtype, "we_");

if ($position !== false) {	
	//smartWe
	$branch = str_replace('_', '', $Testtype);
	$filePath = "deployedVM/lastWe" . $branch . "Deploy.txt";	
} else {
	//gWWeb
	$branch = str_replace('_x', '', $Testtype);
	$filePath = "deployedVM/lastSel" . $branch . "Deploy.txt";
}

// Check if the file exists
if (file_exists($filePath)) {
    // Read the content of the file
	$LastBuildNum = file_get_contents($filePath);   
	if ($position !== false) {	
		//smartWe
		$wecommit = substr($LastBuildNum, 0, 6); // start from position 6
		$LastBuildNum = "we " . strtoupper(substr($branch, 2)) . " #".$wecommit; 
	}
	
	$BuildVersion=$Testtype;
} else {
    echo "File not found: " . htmlspecialchars($filePath);
}	
?>