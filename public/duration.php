<!DOCTYPE html>
<html lang="en">
<body>
	<?php
	try {
		require_once __DIR__ . '/../config/config.php';
	} catch (Throwable $e) {
		respond(false, 'Config error: ' . $e->getMessage());
	}

	if (isset($_GET['LogVersion']))
	{$LogVersion = $_GET['LogVersion'];}
	else{$LogVersion = "";}

	if (isset($_GET['TestProject']))
	{$TCProj = $_GET['TestProject'];}
	else{$TCProj = "";}

	if (isset($_GET['TestProject']))
	{$TCProj = $_GET['TestProject'];}
	else{$TCProj = "";}

	if (isset($_GET['Build']))
	{$Build = urldecode($_GET['Build']);}
	else{$Build = "";}

	if (isset($_GET['Duration']))
	{$Duration = $_GET['Duration'];}
	else{$Duration = "";}

	$sqlupdate ="UPDATE `".$LogVersion."` SET `RunDuration`='".$Duration."' WHERE TCProj='".$TCProj."' AND Build='".$Build."' AND JParam='".$TCProj."'";

	// Execute
	try {
		$stmt->execute($sqlupdate);
		respond(true, 'Updated successfully', [
			'rowsAffected' => $stmt->rowCount(),
			'table' => $LogVersion,
			'value' => $Duration
		]);
	} catch (PDOException $e) {
		respond(false, 'Database error: ' . $e->getMessage(), ['query' => $sqlupdate]);
	}
	//echo "Update ok";
	?>
</body>
</html>
