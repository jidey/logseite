<?php
/**
 * UPDATE_TESTSET_STATS.PHP
 * Met à jour les totaux Flaky/Failed d'un TestSet
 */
header('Content-Type: application/json; charset=utf-8');
try {
    require_once __DIR__ . '/../config/config.php';

    $autoID   = $_POST['AutoID'] ?? null;
    $passed   = isset($_POST['Passed']) ? intval($_POST['Passed']) : null;
    $flaky    = isset($_POST['Flaky'])  ? intval($_POST['Flaky'])  : 0;
    $failed   = isset($_POST['Failed']) ? intval($_POST['Failed']) : 0;
    $testType = $_POST['TestType'] ?? ($_POST['Testtype'] ?? null);
    $product  = $_POST['Product'] ?? '';

    if (!$autoID) {
        throw new Exception('AutoID manquant');
    }
    if (!$testType) {
        throw new Exception('TestType manquant');
    }
    if (!isset($pdo)) {
        throw new Exception('Connexion PDO non disponible');
    }

    // Résolution de la table (même logique que update_validation.php)
    $tableName = null;
    $isSmartWe = (strpos($product, 'weWebSel') !== false || strpos($product, 'weClient') !== false ||
                  strpos($product, 'smartWe') !== false || strpos($product, 'SmartWe') !== false);
    $isGwDesktop = (strpos($product, 'gWClient') !== false);

    if ($isSmartWe) {
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
        preg_match('/x\d+/', $testType, $versionMatch);
        $version = $versionMatch[0] ?? 'x17';
        $branch  = explode('_', $testType)[0] ?? 'rc';
        $tableName = $version . '_' . $branch;
    }

    if (!$tableName || !preg_match('/^[a-z0-9_]+$/i', $tableName)) {
        throw new Exception('Impossible de résoudre la table pour Product=' . $product . ' TestType=' . $testType);
    }

    // Vérifier que la ligne existe
    $check = $pdo->prepare("SELECT AutoID FROM `" . $tableName . "` WHERE AutoID = :autoID LIMIT 1");
    $check->execute([':autoID' => $autoID]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('TestSet avec AutoID ' . $autoID . ' non trouvé dans ' . $tableName);
    }

    // Mettre à jour Passed (TearDownPassed), Flaky (TearDownWarning) et Failed (TearDownFailed)
    // Passed n'est mis à jour que s'il a été fourni (sinon on garde la valeur existante)
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
        throw new Exception('Erreur lors de la mise à jour: ' . implode(', ', $updateStmt->errorInfo()));
    }

    echo json_encode([
        'success'       => true,
        'message'       => 'Stats du TestSet mises à jour avec succès',
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