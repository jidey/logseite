<?php
/**
 * DELETE_SCENARIO.PHP
 * Deletes one scenario (Single) row from its result table.
 * LOGG version (PDO + per-product table resolution)
 *
 * POST parameters:
 * - AutoID: AutoID of the scenario row to delete
 * - TestType + Product: used to resolve the table (handles SmartWe -> we_rc)
 */

// Suppress any error output that would corrupt the JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json');

// Clean JSON response helper
function respond($success, $message, $extra = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

try {
    require_once __DIR__ . '/../config/config.php';
} catch (Throwable $e) {
    respond(false, 'Config error: ' . $e->getMessage());
}

// Only allow POST (destructive action)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed');
}

// Get the parameters
$autoid   = isset($_POST['AutoID'])   ? $_POST['AutoID']   : "";
$Testtype = isset($_POST['TestType']) ? $_POST['TestType'] : "";
$Product  = isset($_POST['Product'])  ? $_POST['Product']  : "";

// Table mapping (Testtype + Product -> table)
// Centralized in config/versions_config.php ($product_table_map, loaded via config.php)
$tableMap = $product_table_map ?? [];

// Determine the table name
$tableName = "";
if (!empty($Testtype) && !empty($Product) && isset($tableMap[$Testtype][$Product])) {
    $tableName = $tableMap[$Testtype][$Product];
}

// Validate the table name (security)
if (empty($tableName) || !preg_match('/^[a-z0-9_]+$/i', $tableName)) {
    respond(false, 'Invalid or missing table name', ['testtype' => $Testtype, 'product' => $Product]);
}

// Validate AutoID
if ($autoid === "" || !ctype_digit((string)$autoid)) {
    respond(false, 'Missing or invalid AutoID');
}

// Only allow deleting a Single scenario row (never the TestSet/Main row)
try {
    $checkStmt = $pdo->prepare("SELECT TestLogTyp FROM `$tableName` WHERE AutoID = :autoid");
    $checkStmt->execute([':autoid' => $autoid]);
    $row = $checkStmt->fetch();

    if (!$row) {
        respond(false, 'Scenario not found', ['autoid' => $autoid, 'table' => $tableName]);
    }
    if (($row['TestLogTyp'] ?? '') !== 'Single') {
        respond(false, 'Only a Single scenario row can be deleted', ['autoid' => $autoid, 'table' => $tableName]);
    }

    $delStmt = $pdo->prepare("DELETE FROM `$tableName` WHERE AutoID = :autoid AND TestLogTyp = 'Single'");
    $delStmt->execute([':autoid' => $autoid]);

    respond(true, 'Scenario deleted successfully', [
        'rowsAffected' => $delStmt->rowCount(),
        'table' => $tableName,
        'autoid' => $autoid
    ]);
} catch (PDOException $e) {
    respond(false, 'Database error: ' . $e->getMessage());
}
?>
