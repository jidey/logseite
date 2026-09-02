<?php
/**
 * RESET_RUNNING.PHP
 * Resets the "running" field of a testset or scenario to 0
 *
 * GET parameters:
 * - JJob: Job identifier
 * - JParam: Job parameter
 * - Testtype: Test type (rc_x17, etc.)
 * - Product: Product (gWWebSel, etc.)
 * - AutoID: (Optional) AutoID of a specific scenario - if provided, targets only that scenario
 */

require_once '../config/config.php';
require_once '../src/TestLogRepository.php';

header('Content-Type: application/json');

// Log the received parameters
error_log("reset_running.php called with: " . json_encode($_GET));

try {
    // Validate the parameters
    $jJob = isset($_GET['JJob']) ? $_GET['JJob'] : null;
    $jParam = isset($_GET['JParam']) ? $_GET['JParam'] : null;
    $testType = isset($_GET['Testtype']) ? $_GET['Testtype'] : null;
    $product = isset($_GET['Product']) ? $_GET['Product'] : null;
    $autoID = isset($_GET['AutoID']) ? intval($_GET['AutoID']) : null;
    
    error_log("Parsed values - JJob: $jJob, JParam: $jParam, TestType: $testType, Product: $product, AutoID: $autoID");
    
    // Checks
    // If AutoID is provided, target by AutoID (JJob/JParam optional)
    // Otherwise, JJob + JParam are needed to target the testset
    if (empty($testType) || empty($product)) {
        throw new Exception("Missing required parameters: Testtype and Product");
    }
    if (empty($autoID) && (empty($jJob) || empty($jParam))) {
        throw new Exception("Missing required parameters: AutoID or (JJob + JParam)");
    }
    
    // Create a Repository instance
    $repo = new TestLogRepository($pdo);

    // Get the table name
    $tableName = $repo->getTableForTestType($testType, $product);
    error_log("Table name: $tableName");

    // Build the UPDATE query
    if (!empty($autoID)) {
        // Specific scenario case: target by AutoID
        $query = "UPDATE `$tableName` SET `running` = 0 WHERE AutoID = :autoid";
        error_log("Executing query (by AutoID): $query with AutoID=$autoID");
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':autoid', $autoID, PDO::PARAM_INT);
    } else {
        // Testset case: target by JJob and JParam
        $query = "UPDATE `$tableName` SET `running` = 0 WHERE JJob = :jjob AND JParam = :jparam";
        error_log("Executing query (by JJob/JParam): $query");
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':jjob', $jJob, PDO::PARAM_STR);
        $stmt->bindValue(':jparam', $jParam, PDO::PARAM_STR);
    }
    
    if ($stmt->execute()) {
        error_log("Query executed successfully. Rows affected: " . $stmt->rowCount());
        echo json_encode([
            'success' => true,
            'message' => 'Running status reset to 0',
            'rowsAffected' => $stmt->rowCount()
        ]);
    } else {
        throw new Exception("Failed to reset running status");
    }
    
} catch (Exception $e) {
    error_log("Error in reset_running.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => htmlspecialchars($e->getMessage())
    ]);
}

?>