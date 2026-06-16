<?php
/**
 * UPDATE_VALIDATION.PHP
 * Met à jour la validation (checked) d'un scénario et retourne le résultat
 */

header('Content-Type: application/json; charset=utf-8');

try {
    // Charger la configuration
    require_once __DIR__ . '/../config/config.php';

    $autoID = $_POST['AutoID'] ?? null;
    $checked = isset($_POST['Checked']) ? intval($_POST['Checked']) : 0;
    $testType = $_POST['TestType'] ?? null;
    $product = $_POST['Product'] ?? null;

    if (!$autoID || !$testType) {
        throw new Exception('Paramètres manquants: AutoID=' . $autoID . ', TestType=' . $testType);
    }

    if (!isset($pdo)) {
        throw new Exception('Connexion PDO non disponible');
    }

    // Construire le nom de la table à partir de testType
    // Format: {branch}_{version} (ex: "rc_x17" -> "x17_rc")
    $tableName = null;
    
    // Extraire la version (x17, x16, x15, etc.)
    preg_match('/x\d+/', $testType, $versionMatch);
    $version = $versionMatch[0] ?? 'x17';
    
    // Extraire la branche (dev, rc, hf, etc.)
    $parts = explode('_', $testType);
    $branch = $parts[0] ?? 'rc';
    
    // Construire le nom de la table
    $tableName = $version . '_' . $branch;

    // Mettre à jour la validation dans la base de données
    $stmt = $pdo->prepare("
        UPDATE `" . $tableName . "` 
        SET `checked` = :checked 
        WHERE AutoID = :autoID
    ");
    
    $result = $stmt->execute([
        ':checked' => $checked,
        ':autoID' => $autoID
    ]);

    if (!$result) {
        throw new Exception('Erreur lors de la mise à jour: ' . implode(', ', $stmt->errorInfo()));
    }

    // Retourner le succès
    echo json_encode([
        'success' => true,
        'message' => 'Validation mise à jour avec succès',
        'rows_affected' => $stmt->rowCount(),
        'table' => $tableName
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>