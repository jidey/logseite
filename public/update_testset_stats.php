<?php
/**
 * UPDATE_TESTSET_STATS.PHP
 * Met à jour les totaux Passed/Flaky/Failed d'un TestSet
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/config.php';

    $autoID = $_POST['AutoID'] ?? null;
    // Ne plus recevoir Passed - elle sera calculée
    $flaky = isset($_POST['Flaky']) ? intval($_POST['Flaky']) : 0;
    $failed = isset($_POST['Failed']) ? intval($_POST['Failed']) : 0;

    if (!$autoID) {
        throw new Exception('AutoID manquant');
    }

    if (!isset($pdo)) {
        throw new Exception('Connexion PDO non disponible');
    }

    // Chercher le TestSet pour obtenir ses détails (TestType, Product, etc.)
    // Chercher dans toutes les tables possibles
    $testset = null;
    $tableName = null;
    
    // Chercher d'abord dans les tables du testcomplete
    $tables = ['x17_rc', 'x17_dev', 'x17_hf', 'x16_rc', 'x16_dev', 'x16_hf', 'x15_rc', 'x15_dev', 'x15_hf'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `" . $table . "` WHERE AutoID = :autoID LIMIT 1");
            $stmt->execute([':autoID' => $autoID]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $testset = $result;
                $tableName = $table;
                break;
            }
        } catch (Exception $e) {
            // Table n'existe pas, continuer
            continue;
        }
    }
    
    if (!$testset || !$tableName) {
        throw new Exception('TestSet avec AutoID ' . $autoID . ' non trouvé');
    }

    // Mettre à jour seulement Flaky et Failed, PAS Passed
    $updateStmt = $pdo->prepare("
        UPDATE `" . $tableName . "` 
        SET `TearDownWarning` = :flaky,
            `TearDownFailed` = :failed
        WHERE AutoID = :autoID
    ");
    
    $result = $updateStmt->execute([
        ':flaky' => $flaky,
        ':failed' => $failed,
        ':autoID' => $autoID
    ]);

    if (!$result) {
        throw new Exception('Erreur lors de la mise à jour: ' . implode(', ', $updateStmt->errorInfo()));
    }

    // Retourner le succès
    echo json_encode([
        'success' => true,
        'message' => 'Stats du TestSet mises à jour avec succès',
        'table' => $tableName,
        'rows_affected' => $updateStmt->rowCount(),
        'stats' => [
            'flaky' => $flaky,
            'failed' => $failed
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>