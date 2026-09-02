<?php
/**
 * UPDATE_VALIDATION.PHP
 * Updates the validation (checked) of a scenario and returns the result
 */
header('Content-Type: application/json; charset=utf-8');
try {
    require_once __DIR__ . '/../config/config.php';

    $autoID   = $_POST['AutoID'] ?? null;
    $checked  = isset($_POST['Checked']) ? intval($_POST['Checked']) : 0;
    $testType = $_POST['TestType'] ?? null;
    $product  = $_POST['Product'] ?? '';

    if (!$autoID || !$testType) {
        throw new Exception('Missing parameters: AutoID=' . $autoID . ', TestType=' . $testType);
    }
    if (!isset($pdo)) {
        throw new Exception('PDO connection not available');
    }

    // Build the table name from testType + product
    $tableName = null;
    $isSmartWe = (strpos($product, 'weWebSel') !== false || strpos($product, 'weClient') !== false ||
                  strpos($product, 'smartWe') !== false || strpos($product, 'SmartWe') !== false);
    $isGwDesktop = (strpos($product, 'gWClient') !== false);

    // Centralized mapping in config/versions_config.php (loaded via config.php)
    if ($isSmartWe) {
        // SmartWe: we_* table depending on the branch, regardless of the x version
        if (stripos($testType, 'hf') !== false)      $tableName = 'we_hf';
        elseif (stripos($testType, 'rc') !== false)  $tableName = 'we_rc';
        elseif (stripos($testType, 'dev') !== false) $tableName = 'we_dev';
    } elseif ($isGwDesktop) {
        $tableName = $LOGG_GWCLIENT_MAP[$testType] ?? null;
    } else {
        // gW Web: rc_x17 -> x17_rc
        $tableName = $product_table_map[$testType]['gWWebSel'] ?? null;
        if (!$tableName) {
            // Fallback if the testType is not (yet) in the central mapping
            preg_match('/x\d+/', $testType, $versionMatch);
            $version = $versionMatch[0] ?? 'x17';
            $branch  = explode('_', $testType)[0] ?? 'rc';
            $tableName = $version . '_' . $branch;
        }
    }

    // Table name validation (security)
    if (!$tableName || !preg_match('/^[a-z0-9_]+$/i', $tableName)) {
        throw new Exception('Unable to resolve the table for Product=' . $product . ' TestType=' . $testType);
    }

    // Update the validation in the database
    $stmt = $pdo->prepare("
        UPDATE `" . $tableName . "`
        SET `checked` = :checked
        WHERE AutoID = :autoID
    ");
    $result = $stmt->execute([
        ':checked' => $checked,
        ':autoID'  => $autoID
    ]);

    if (!$result) {
        throw new Exception('Error while updating: ' . implode(', ', $stmt->errorInfo()));
    }

    echo json_encode([
        'success'       => true,
        'message'       => 'Validation updated successfully',
        'rows_affected' => $stmt->rowCount(),
        'table'         => $tableName
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
?>