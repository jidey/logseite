<?php
/**
 * UPDATE_TESTSET_STATS.PHP
 * Updates the Flaky/Failed totals of a TestSet
 */
header('Content-Type: application/json; charset=utf-8');
try {
    require_once __DIR__ . '/../../_config/config.php';

    $autoID   = $_POST['AutoID'] ?? null;
    $passed   = isset($_POST['Passed']) ? intval($_POST['Passed']) : null;
    $flaky    = isset($_POST['Flaky'])  ? intval($_POST['Flaky'])  : 0;
    $failed   = isset($_POST['Failed']) ? intval($_POST['Failed']) : 0;
    $testType = $_POST['TestType'] ?? ($_POST['Testtype'] ?? null);
    $product  = $_POST['Product'] ?? '';

    if (!$autoID) {
        throw new Exception('Missing AutoID');
    }
    if (!$testType) {
        throw new Exception('Missing TestType');
    }
    if (!isset($pdo)) {
        throw new Exception('PDO connection not available');
    }

    // Table resolution (same logic as update_validation.php)
    $tableName = null;
    $isSmartWe = (strpos($product, 'weWebSel') !== false || strpos($product, 'weClient') !== false ||
                  strpos($product, 'smartWe') !== false || strpos($product, 'SmartWe') !== false);
    $isGwDesktop = (strpos($product, 'gWClient') !== false);

    // Centralized mapping in _config/versions_config.php (loaded via config.php)
    if ($isSmartWe) {
        if (stripos($testType, 'hf') !== false)      $tableName = 'we_hf';
        elseif (stripos($testType, 'rc') !== false)  $tableName = 'we_rc';
        elseif (stripos($testType, 'dev') !== false) $tableName = 'we_dev';
    } elseif ($isGwDesktop) {
        $tableName = $LOGG_GWCLIENT_MAP[$testType] ?? null;
    } else {
        $tableName = $product_table_map[$testType]['gWWebSel'] ?? null;
        if (!$tableName) {
            // Fallback if the testType is not (yet) in the central mapping
            preg_match('/x\d+/', $testType, $versionMatch);
            $version = $versionMatch[0] ?? 'x17';
            $branch  = explode('_', $testType)[0] ?? 'rc';
            $tableName = $version . '_' . $branch;
        }
    }

    if (!$tableName || !preg_match('/^[a-z0-9_]+$/i', $tableName)) {
        throw new Exception('Unable to resolve the table for Product=' . $product . ' TestType=' . $testType);
    }

    // Check that the row exists
    $check = $pdo->prepare("SELECT AutoID FROM `" . $tableName . "` WHERE AutoID = :autoID LIMIT 1");
    $check->execute([':autoID' => $autoID]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('TestSet with AutoID ' . $autoID . ' not found in ' . $tableName);
    }

    // Update Passed (TearDownPassed), Flaky (TearDownWarning) and Failed (TearDownFailed)
    // Passed is only updated when provided (otherwise the existing value is kept)
    if ($passed !== null) {
        $updateStmt = $pdo->prepare("
            UPDATE `" . $tableName . "`
            SET `TearDownPassed`  = :passed,
                `TearDownWarning` = :flaky,
                `TearDownFailed`  = :failed
            WHERE AutoID = :autoID
        ");
        $result = $updateStmt->execute([
            ':passed' => $passed,
            ':flaky'  => $flaky,
            ':failed' => $failed,
            ':autoID' => $autoID
        ]);
    } else {
        $updateStmt = $pdo->prepare("
            UPDATE `" . $tableName . "`
            SET `TearDownWarning` = :flaky,
                `TearDownFailed`  = :failed
            WHERE AutoID = :autoID
        ");
        $result = $updateStmt->execute([
            ':flaky'  => $flaky,
            ':failed' => $failed,
            ':autoID' => $autoID
        ]);
    }

    if (!$result) {
        throw new Exception('Error while updating: ' . implode(', ', $updateStmt->errorInfo()));
    }

    echo json_encode([
        'success'       => true,
        'message'       => 'TestSet stats updated successfully',
        'table'         => $tableName,
        'rows_affected' => $updateStmt->rowCount(),
        'stats'         => ['passed' => $passed, 'flaky' => $flaky, 'failed' => $failed]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
?>