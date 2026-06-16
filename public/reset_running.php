<?php
/**
 * RESET_RUNNING.PHP
 * Réinitialise le champ "running" d'un testset ou d'un scénario à 0
 * 
 * Paramètres GET:
 * - JJob: Identifiant du Job
 * - JParam: Paramètre du Job
 * - Testtype: Type de test (rc_x17, etc.)
 * - Product: Produit (gWWebSel, etc.)
 * - AutoID: (Optionnel) AutoID du scénario spécifique - si fourni, ne cibler que ce scénario
 */

require_once '../config/config.php';
require_once '../src/TestLogRepository.php';

header('Content-Type: application/json');

// Log des paramètres reçus
error_log("reset_running.php called with: " . json_encode($_GET));

try {
    // Valider les paramètres
    $jJob = isset($_GET['JJob']) ? $_GET['JJob'] : null;
    $jParam = isset($_GET['JParam']) ? $_GET['JParam'] : null;
    $testType = isset($_GET['Testtype']) ? $_GET['Testtype'] : null;
    $product = isset($_GET['Product']) ? $_GET['Product'] : null;
    $autoID = isset($_GET['AutoID']) ? intval($_GET['AutoID']) : null;
    
    error_log("Parsed values - JJob: $jJob, JParam: $jParam, TestType: $testType, Product: $product, AutoID: $autoID");
    
    // Vérifications
    // Si AutoID est fourni, on cible par AutoID (JJob/JParam optionnels)
    // Sinon, on a besoin de JJob + JParam pour cibler le testset
    if (empty($testType) || empty($product)) {
        throw new Exception("Missing required parameters: Testtype and Product");
    }
    if (empty($autoID) && (empty($jJob) || empty($jParam))) {
        throw new Exception("Missing required parameters: AutoID or (JJob + JParam)");
    }
    
    // Créer une instance du Repository
    $repo = new TestLogRepository($pdo);
    
    // Obtenir le nom de la table
    $tableName = $repo->getTableForTestType($testType, $product);
    error_log("Table name: $tableName");
    
    // Construire la requête UPDATE
    if (!empty($autoID)) {
        // Cas scénario spécifique : cibler par AutoID
        $query = "UPDATE `$tableName` SET `running` = 0 WHERE AutoID = :autoid";
        error_log("Executing query (by AutoID): $query with AutoID=$autoID");
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':autoid', $autoID, PDO::PARAM_INT);
    } else {
        // Cas testset : cibler par JJob et JParam
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