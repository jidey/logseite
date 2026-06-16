<?php
/**
 * UPDATE_MANUAL.PHP
 * Met à jour le champ "manual" d'un Scénario dans la BD
 * 
 * Paramètres GET:
 * - AutoID: ID du Scénario
 * - Manual: 1 ou 0
 * - Testtype: Type de test (rc_x17, etc.)
 * - Product: Produit (gWWebSel, etc.)
 */

require_once '../config/config.php';
require_once '../src/TestLogRepository.php';

header('Content-Type: application/json');

try {
    // Valider les paramètres
    $autoID = isset($_GET['AutoID']) ? intval($_GET['AutoID']) : null;
    $manual = isset($_GET['Manual']) ? intval($_GET['Manual']) : null;
    $testType = isset($_GET['Testtype']) ? $_GET['Testtype'] : null;
    $product = isset($_GET['Product']) ? $_GET['Product'] : null;
    
    // Vérifications
    if ($autoID === null || $autoID <= 0) {
        throw new Exception("Invalid AutoID");
    }
    
    if ($manual === null || ($manual !== 0 && $manual !== 1)) {
        throw new Exception("Invalid Manual value");
    }
    
    if (empty($testType) || empty($product)) {
        throw new Exception("Missing Testtype or Product");
    }
    
    // Créer une instance du Repository
    $repo = new TestLogRepository($pdo);
    
    // Obtenir le nom de la table
    $tableName = $repo->getTableForTestType($testType, $product);
    
    // TODO: Remplacer 'manual' par le nom correct du champ dans votre table
    // Vérifier la structure de votre table pour le nom du champ exact
    // Options possibles: manual_test, is_manual, ManualTest, etc.
    
    // Mettre à jour le champ checked
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
