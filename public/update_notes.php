<?php
/**
 * UPDATE_NOTES.PHP
 * AJAX endpoint to edit notes inline in the table
 * Saves into the *_tags table
 */

require_once '../../_config/config.php';
require_once '../src/TestLogRepository.php';

header('Content-Type: application/json');

// Get the POST parameters
$autoID = $_POST['AutoID'] ?? null;
$testType = $_POST['Testtype'] ?? null;
$product = $_POST['Product'] ?? null;
$notes = $_POST['Notes'] ?? '';

// Validation
if (!$autoID || !$testType || !$product) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $repo = new TestLogRepository($pdo);
    
    // Get the TestSet detail to obtain JJob and JParam
    $runDetails = $repo->getRunDetails($testType, $autoID, $product);
    
    if (!$runDetails) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'TestSet not found']);
        exit;
    }
    
    $jJob = $runDetails['JJob'];
    $jParam = $runDetails['JParam'];
    
    // Get the table name for the TestType
    $tableName = $repo->getTableForTestType($testType, $product);
    $tagsTable = $tableName . '_tags';

    // Check whether the row exists in _tags
    $query = "SELECT * FROM `$tagsTable` 
              WHERE JJob = :jjob 
              AND JParam = :jparam 
              LIMIT 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':jjob', $jJob, PDO::PARAM_STR);
    $stmt->bindValue(':jparam', $jParam, PDO::PARAM_STR);
    $stmt->execute();
    
    $existingRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingRow) {
        // UPDATE
        $updateQuery = "UPDATE `$tagsTable` 
                       SET testnotiz = :notes
                       WHERE JJob = :jjob 
                       AND JParam = :jparam";
        
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindValue(':notes', $notes, PDO::PARAM_STR);
        $updateStmt->bindValue(':jjob', $jJob, PDO::PARAM_STR);
        $updateStmt->bindValue(':jparam', $jParam, PDO::PARAM_STR);
        $updateStmt->execute();
    } else {
        // INSERT
        $insertQuery = "INSERT INTO `$tagsTable` (JJob, JParam, testnotiz) 
                       VALUES (:jjob, :jparam, :notes)";
        
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->bindValue(':jjob', $jJob, PDO::PARAM_STR);
        $insertStmt->bindValue(':jparam', $jParam, PDO::PARAM_STR);
        $insertStmt->bindValue(':notes', $notes, PDO::PARAM_STR);
        $insertStmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notes saved successfully',
        'notes' => $notes,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

?>
