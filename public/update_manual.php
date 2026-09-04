<?php
/**
 * UPDATE_MANUAL.PHP
 * Updates the "manual" field of a Scenario in the DB
 *
 * GET parameters:
 * - AutoID: Scenario ID
 * - Manual: 1 or 0
 * - Testtype: Test type (rc_x17, etc.)
 * - Product: Product (gWWebSel, etc.)
 */

require_once '../../_config/config.php';
require_once '../src/TestLogRepository.php';

header('Content-Type: application/json');

try {
    // Validate the parameters
    $autoID = isset($_GET['AutoID']) ? intval($_GET['AutoID']) : null;
    $manual = isset($_GET['Manual']) ? intval($_GET['Manual']) : null;
    $testType = isset($_GET['Testtype']) ? $_GET['Testtype'] : null;
    $product = isset($_GET['Product']) ? $_GET['Product'] : null;
    
    // Checks
    if ($autoID === null || $autoID <= 0) {
        throw new Exception("Invalid AutoID");
    }
    
    if ($manual === null || ($manual !== 0 && $manual !== 1)) {
        throw new Exception("Invalid Manual value");
    }
    
    if (empty($testType) || empty($product)) {
        throw new Exception("Missing Testtype or Product");
    }
    
    // Create a Repository instance
    $repo = new TestLogRepository($pdo);

    // Get the table name
    $tableName = $repo->getTableForTestType($testType, $product);

    // TODO: Replace 'manual' with the correct field name in your table
    // Check your table structure for the exact field name
    // Possible options: manual_test, is_manual, ManualTest, etc.

    // Update the checked field
    $query = "UPDATE `$tableName` SET checked = :manual WHERE AutoID = :autoid";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':manual', $manual, PDO::PARAM_INT);
    $stmt->bindValue(':autoid', $autoID, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Manual flag updated successfully'
        ]);
    } else {
        throw new Exception("Failed to update manual flag");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => htmlspecialchars($e->getMessage())
    ]);
    error_log("update_manual.php error: " . $e->getMessage());
}

?>
