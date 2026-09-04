<?php
/**
 * CHECK.PHP
 * Updates the "running" or "checked" state of a test
 * LOGG version (PDO + per-product table resolution)
 *
 * GET parameters:
 * - value: value to write
 * - field: 'running' to force updating running (otherwise value<2 → checked, value>=2 → running)
 * - autoid: AutoID of the scenario/testset
 * - Testtype + Product: used to resolve the table (handles SmartWe -> we_rc)
 * - LogVersion: direct table name (fallback)
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
    require_once __DIR__ . '/../../_config/config.php';
} catch (Throwable $e) {
    respond(false, 'Config error: ' . $e->getMessage());
}

// Get the parameters
$value      = isset($_GET['value'])      ? intval($_GET['value'])   : 0;
$autoid     = isset($_GET['autoid'])     ? $_GET['autoid']          : "";
$JJob       = isset($_GET['JJob'])       ? $_GET['JJob']            : "";
$JParam     = isset($_GET['JParam'])     ? $_GET['JParam']          : "";
$Testtype   = isset($_GET['Testtype'])   ? $_GET['Testtype']        : "";
$LogVersion = isset($_GET['LogVersion']) ? $_GET['LogVersion']      : "";
$Product    = isset($_GET['Product'])    ? $_GET['Product']         : "";
$field      = isset($_GET['field'])      ? $_GET['field']           : "";

// Table mapping (Testtype + Product -> table)
// Centralized in _config/versions_config.php ($product_table_map, loaded via config.php)
$tableMap = $product_table_map ?? [];

// Determine the table name
$tableName = "";
if (!empty($Testtype) && !empty($Product) && isset($tableMap[$Testtype][$Product])) {
    $tableName = $tableMap[$Testtype][$Product];
} elseif (!empty($LogVersion)) {
    $tableName = $LogVersion;
}

// Validate the table name (security)
if (empty($tableName) || !preg_match('/^[a-z0-9_]+$/i', $tableName)) {
    respond(false, 'Invalid or missing table name', ['testtype' => $Testtype, 'product' => $Product]);
}

// Build the UPDATE query
$sqlcheck = "";
$params = [];

if ($autoid !== "") {
    if ($field === 'running' || $value >= 2) {
        $sqlcheck = "UPDATE `$tableName` SET `running` = :value WHERE AutoID = :autoid";
        $params = [':value' => $value, ':autoid' => $autoid];
    } else {
        $sqlcheck = "UPDATE `$tableName` SET `checked` = :value WHERE AutoID = :autoid";
        $params = [':value' => $value, ':autoid' => $autoid];
    }
} else {
    respond(false, 'Missing autoid');
}

// Execute
try {
    $stmt = $pdo->prepare($sqlcheck);
    $stmt->execute($params);
    respond(true, 'Updated successfully', [
        'rowsAffected' => $stmt->rowCount(),
        'table' => $tableName,
        'field' => ($field === 'running' || $value >= 2) ? 'running' : 'checked',
        'value' => $value
    ]);
} catch (PDOException $e) {
    respond(false, 'Database error: ' . $e->getMessage(), ['query' => $sqlcheck]);
}
?>
