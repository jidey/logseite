<?php
/**
 * UPDATE_NOTES.PHP
 * Endpoint AJAX pour éditer les notes inline dans le tableau
 * Sauvegarde dans la table *_tags
 */

require_once '../config/config.php';
require_once '../src/TestLogRepository.php';

header('Content-Type: application/json');

// Récupérer les paramètres POST
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
    
    // Récupérer le détail du TestSet pour obtenir JJob et JParam
    $runDetails = $repo->getRunDetails($testType, $autoID, $product);
    
    if (!$runDetails) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'TestSet not found']);
        exit;
    }
    
    $jJob = $runDetails['JJob'];
    $jParam = $runDetails['JParam'];
    
    // Récupérer le nom de la table pour le TestType
    $tableName = $repo->getTableForTestType($testType, $product);
    $tagsTable = $tableName . '_tags';
    
    // Vérifier si la ligne existe dans _tags
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
