<?php
require_once __DIR__ . '/../config/config.php';

$passed  = isset($_GET['passed'])  ? $_GET['passed']  : '';
$failed  = isset($_GET['failed'])  ? $_GET['failed']  : '';
$percent = isset($_GET['percent']) ? $_GET['percent'] : '';
$Branch  = isset($_GET['Branch'])  ? $_GET['Branch']  : '';
$table   = isset($_GET['table'])   ? $_GET['table']   : '';
$analyse = isset($_GET['analyse']) ? $_GET['analyse'] : '';

// Validate the table name (security: it cannot be parameterized in a prepared statement)
if (!preg_match('/^[a-z0-9_]+$/i', $table)) {
    echo "Error: invalid table name";
    exit;
}

if (!isset($pdo)) {
    echo "Error: PDO connection not available";
    exit;
}

$sql = "INSERT INTO `" . $table . "` (`passed`, `failed`, `percent`, `Branch`, `analyse`)
        VALUES (:passed, :failed, :percent, :branch, :analyse)";

echo htmlspecialchars($sql) . "<br>";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':passed'  => $passed,
        ':failed'  => $failed,
        ':percent' => $percent,
        ':branch'  => $Branch,
        ':analyse' => $analyse,
    ]);
    echo "New record added successfully";
} catch (PDOException $e) {
    error_log("dailyStats.php error: " . $e->getMessage());
    echo "Error: " . htmlspecialchars($e->getMessage());
}
?>
