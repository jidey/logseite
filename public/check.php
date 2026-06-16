<?php
/**
 * CHECK.PHP
 * Met à jour l'état "running" ou "checked" d'un test
 * Version adaptée pour LOGG (PDO + résolution de table par produit)
 *
 * Paramètres GET:
 * - value: valeur à écrire
 * - field: 'running' pour forcer la MAJ de running (sinon value<2 → checked, value>=2 → running)
 * - autoid: AutoID du scénario/testset
 * - Testtype + Product: pour résoudre la table (gère SmartWe → we_rc)
 * - LogVersion: nom de table direct (fallback)
 */

// Empêcher tout affichage d'erreur qui corromprait le JSON
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json');

// Fonction de réponse JSON propre
function respond($success, $message, $extra = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

try {
    require_once __DIR__ . '/../config/config.php';
} catch (Throwable $e) {
    respond(false, 'Config error: ' . $e->getMessage());
}

// Recuperer les parametres
$value      = isset($_GET['value'])      ? intval($_GET['value'])   : 0;
$autoid     = isset($_GET['autoid'])     ? $_GET['autoid']          : "";
$JJob       = isset($_GET['JJob'])       ? $_GET['JJob']            : "";
$JParam     = isset($_GET['JParam'])     ? $_GET['JParam']          : "";
$Testtype   = isset($_GET['Testtype'])   ? $_GET['Testtype']        : "";
$LogVersion = isset($_GET['LogVersion']) ? $_GET['LogVersion']      : "";
$Product    = isset($_GET['Product'])    ? $_GET['Product']         : "";
$field      = isset($_GET['field'])      ? $_GET['field']           : "";

// Mapping des tables (Testtype + Product -> table)
$tableMap = [
    'rc_x18'  => ['gWWebSel' => 'x18_rc',  'weWebSel' => 'we_rc',  'gWClient' => 'x18_gwrc'],
    'hf_x18'  => ['gWWebSel' => 'x18_hf',  'weWebSel' => 'we_hf',  'gWClient' => 'x18_gwhf'],
    'dev_x18' => ['gWWebSel' => 'x18_dev', 'weWebSel' => 'we_dev', 'gWClient' => 'x18_gwdev'],
    'rc_x17'  => ['gWWebSel' => 'x17_rc',  'weWebSel' => 'we_rc',  'gWClient' => 'x17_gwrc'],
    'hf_x17'  => ['gWWebSel' => 'x17_hf',  'weWebSel' => 'we_hf',  'gWClient' => 'x17_gwhf'],
    'dev_x17' => ['gWWebSel' => 'x17_dev', 'weWebSel' => 'we_dev', 'gWClient' => 'x17_gwdev'],
    'rc_x16'  => ['gWWebSel' => 'x16_rc',  'weWebSel' => 'we_rc',  'gWClient' => 'x16_gwrc'],
    'hf_x16'  => ['gWWebSel' => 'x16_hf',  'weWebSel' => 'we_hf',  'gWClient' => 'x16_gwhf'],
    'dev_x16' => ['gWWebSel' => 'x16_dev', 'weWebSel' => 'we_dev', 'gWClient' => 'x16_gwdev'],
];

// Determiner le nom de la table
$tableName = "";
if (!empty($Testtype) && !empty($Product) && isset($tableMap[$Testtype][$Product])) {
    $tableName = $tableMap[$Testtype][$Product];
} elseif (!empty($LogVersion)) {
    $tableName = $LogVersion;
}

// Valider le nom de table (securite)
if (empty($tableName) || !preg_match('/^[a-z0-9_]+$/i', $tableName)) {
    respond(false, 'Invalid or missing table name', ['testtype' => $Testtype, 'product' => $Product]);
}

// Construire la requete UPDATE
$sqlcheck = "";
$params = [];

if ($autoid !== "") {
    if ($field === 'running' || $value >= 2) {
        $sqlcheck = "UPDATE `$tableName` SET `running` = :value WHERE AutoID = :autoid";
        $params = [':value' => $value, ':autoid' => $autoid];
    } else {
        $sqlcheck = "UPDATE `$tableName` SET `checked` = :value WHERE AutoID = :autoid";
        $params = [':value' => $value, ':autoid' => $autoid];
    }
} else {
    respond(false, 'Missing autoid');
}

// Executer
try {
    $stmt = $pdo->prepare($sqlcheck);
    $stmt->execute($params);
    respond(true, 'Updated successfully', [
        'rowsAffected' => $stmt->rowCount(),
        'table' => $tableName,
        'field' => ($field === 'running' || $value >= 2) ? 'running' : 'checked',
        'value' => $value
    ]);
} catch (PDOException $e) {
    respond(false, 'Database error: ' . $e->getMessage(), ['query' => $sqlcheck]);
}
?>