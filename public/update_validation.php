<?php
/**
 * UPDATE_VALIDATION.PHP
 * Met à jour la validation (checked) d'un scénario et retourne le résultat
 */
header('Content-Type: application/json; charset=utf-8');
try {
    require_once __DIR__ . '/../config/config.php';

    $autoID   = $_POST['AutoID'] ?? null;
    $checked  = isset($_POST['Checked']) ? intval($_POST['Checked']) : 0;
    $testType = $_POST['TestType'] ?? null;
    $product  = $_POST['Product'] ?? '';

    if (!$autoID || !$testType) {
        throw new Exception('Paramètres manquants: AutoID=' . $autoID . ', TestType=' . $testType);
    }
    if (!isset($pdo)) {
        throw new Exception('Connexion PDO non disponible');
    }

    // Construire le nom de la table à partir de testType + product
    $tableName = null;
    $isSmartWe = (strpos($product, 'weWebSel') !== false || strpos($product, 'weClient') !== false ||
                  strpos($product, 'smartWe') !== false || strpos($product, 'SmartWe') !== false);
    $isGwDesktop = (strpos($product, 'gWClient') !== false);

    if ($isSmartWe) {
        // SmartWe : table we_* selon la branche, indépendamment de la version x
        if (stripos($testType, 'hf') !== false)      $tableName = 'we_hf';
        elseif (stripos($testType, 'rc') !== false)  $tableName = 'we_rc';
        elseif (stripos($testType, 'dev') !== false) $tableName = 'we_dev';
    } elseif ($isGwDesktop) {
        $gwClientMap = [
            'hf_x14'=>'x14_gwhf','rc_x14'=>'x14_gwrc',
            'hf_x15'=>'x15_gwhf','rc_x15'=>'x15_gwrc',
            'hf_x16'=>'x16_gwhf','rc_x16'=>'x16_gwrc','dev_x16'=>'x16_gwdev',
            'hf_x17'=>'x17_gwhf','rc_x17'=>'x17_gwrc','dev_x17'=>'x17_gwdev',
            'hf_x18'=>'x18_gwhf','rc_x18'=>'x18_gwrc','dev_x18'=>'x18_gwdev',
        ];
        $tableName = $gwClientMap[$testType] ?? null;
    } else {
        // gW Web : rc_x17 -> x17_rc
        preg_match('/x\d+/', $testType, $versionMatch);
        $version = $versionMatch[0] ?? 'x17';
        $branch  = explode('_', $testType)[0] ?? 'rc';
        $tableName = $version . '_' . $branch;
    }

    // Validation du nom de table (sécurité)
    if (!$tableName || !preg_match('/^[a-z0-9_]+$/i', $tableName)) {
        throw new Exception('Impossible de résoudre la table pour Product=' . $product . ' TestType=' . $testType);
    }

    // Mettre à jour la validation dans la base de données
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
        throw new Exception('Erreur lors de la mise à jour: ' . implode(', ', $stmt->errorInfo()));
    }

    echo json_encode([
        'success'       => true,
        'message'       => 'Validation mise à jour avec succès',
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