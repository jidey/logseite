<?php

$directory = "deployedVM";
$files = scandir($directory);
$results = [];

foreach ($files as $file) {
    if (preg_match('/lastSel(.*?)Deploy/', $file, $matches)) {
        $buildNumber = $matches[1];
        $filePath = $directory . DIRECTORY_SEPARATOR . $file;

        if (is_file($filePath)) {
            $fileContent = file_get_contents($filePath);
            // Assume the version is on the first line of the file
            $version = trim(strtok($fileContent, "\n"));

            $results[] = [
                'Build' => $buildNumber,
                'Version' => $version
            ];
        }
    }
	if (preg_match('/lastWe(.*?)Deploy/', $file, $matches)) {
        $buildNumber = $matches[1];
        $filePath = $directory . DIRECTORY_SEPARATOR . $file;

        if (is_file($filePath)) {
            $fileContent = file_get_contents($filePath);
            // Assume the version is on the first line of the file
            $version = trim(strtok($fileContent, "\n"));
			$version = "we " . strtoupper(substr($buildNumber, 2)) . " #".substr($version, 0, 6); 
			
			$results[] = [
                'Build' => $buildNumber,
                'Version' => $version
            ];
        }
    }
}

// Write the results to a JSON file
$jsonFilePath = 'builds_versions.json';
file_put_contents($jsonFilePath, json_encode($results, JSON_PRETTY_PRINT));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deployed Build Number</title>
    <style>
        table {
            width: 50%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 18px;
            text-align: left;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>

<h2>Deployed Build Number</h2>

<table>
    <thead>
        <tr>
            <th>Build</th>
            <th>Version</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($results as $result): ?>
            <tr>
                <td><?php echo htmlspecialchars($result['Build']); ?></td>
                <td><?php echo htmlspecialchars($result['Version']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
