<?php
/**
 * SYNC_MAIN.PHP
 * Recalcule et persiste les totaux (Passed/Flaky/Failed) d'un TestSet (Main)
 * à partir de ses scénarios (Single) en base.
 *
 * Appelé par Jenkins après le retry, sans connaître l'AutoID :
 *   sync_main.php?Testtype=dev_x16&Product=gWWebSel&JJob=Autotests-Web-Grid&JParam=lists&Build=26.3.0.16660 #3040
 *
 * Réponse JSON : { "success": true, "AutoID": 123, "passed": 10, "flaky": 2, "failed": 0 }
 */

header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../src/TestLogRepository.php';

// --- Paramètres ---
$testType = $_GET['Testtype'] ?? '';
$product  = $_GET['Product']  ?? '';
$jJob     = $_GET['JJob']     ?? '';
$jParam   = $_GET['JParam']   ?? '';
$build    = $_GET['Build']    ?? '';
$testname = $_GET['TestName'] ?? '';

// --- Mode Debug ---
// Ajouter &Debug=1 à l'URL pour voir le détail des scénarios trouvés
// sans écrire en base (aucun UPDATE n'est exécuté en mode debug)
$debug = isset($_GET['Debug']) && $_GET['Debug'] == '1';

// --- Normalisation ---
$isSmartWe = (strpos($product, 'weWebSel') !== false ||
              strpos($product, 'weClient') !== false ||
              strpos($product, 'smartWe')  !== false ||
              strpos($product, 'SmartWe')  !== false);

// Normaliser x16_dev → dev_x16
if (preg_match('/^(x\d+)_(dev|rc|hf)$/', $testType, $m)) {
    $testType = $m[2] . '_' . $m[1];
}

// Normaliser we_dev / we_rc / we_hf (SmartWe)
if ($isSmartWe) {
    if ($testType === 'we_dev')     $testType = 'dev_x18';
    elseif ($testType === 'we_rc')  $testType = 'rc_x17';
    elseif ($testType === 'we_hf')  $testType = 'hf_x17';
}

// Normalisation SmartWe hf/rc → forcer version
if ($isSmartWe) {
    if (stripos($testType, 'hf') !== false) $testType = 'hf_x17';
    elseif (stripos($testType, 'rc') !== false) $testType = 'rc_x17';
}

if (!$testType || !$jJob || !$jParam || !$build) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters (Testtype, JJob, JParam, Build)']);
    exit;
}

// --- Normalisation SmartWe (cohérent avec details.php) ---
$isSmartWe = (strpos($product, 'weWebSel') !== false ||
              strpos($product, 'weClient') !== false ||
              strpos($product, 'smartWe')  !== false ||
              strpos($product, 'SmartWe')  !== false);

if ($isSmartWe) {
    if (stripos($testType, 'hf') !== false) {
        $testType = 'hf_x17';
    } elseif (stripos($testType, 'rc') !== false) {
        $testType = 'rc_x17';
    }
}

try {
    $repo      = new TestLogRepository($pdo);
    $tableName = $repo->getTableForTestType($testType, $product);

    // --- 1. Trouver l'AutoID de la ligne Main ---
    $stmtMain = $pdo->prepare(
        "SELECT AutoID FROM `$tableName`
         WHERE JJob  = :jjob
         AND   JParam = :jparam
         AND   Build  = :build
         AND   TestLogTyp = 'Main'
         ORDER BY AutoID DESC
         LIMIT 1"
    );
    $stmtMain->execute([':jjob' => $jJob, ':jparam' => $jParam, ':build' => $build]);
    $mainRow = $stmtMain->fetch(PDO::FETCH_ASSOC);

    if (!$mainRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Main TestSet not found for given JJob/JParam/Build']);
        exit;
    }
    $autoID = (int)$mainRow['AutoID'];

	// Reset Single Running State
	if ($testname <> "@Dummy")
	{
		function debugQuery($query, $params) {
			foreach ($params as $key => $value) {
				$value = is_numeric($value) ? $value : "'" . addslashes($value) . "'";
				$query = str_replace($key, $value, $query);
			}
			return $query;
		}

		$sql = "UPDATE `$tableName`
				SET `running` = 0
				WHERE JJob   = :jjob
				AND   JParam = :jparam
				AND   Build  = :build
				AND   TestLogTyp = 'Single'
				AND   TCProj LIKE :testname";

		$params = [
			':jjob'     => $jJob,
			':jparam'   => $jParam,
			':build'    => $build,
			':testname' => $testname."%"
		];

		// Affichage debug
		//echo debugQuery($sql, $params);

		$stmtReset = $pdo->prepare($sql);
		$stmtReset->execute($params);

		// Bonus : vérifier combien de lignes ont été modifiées
		//echo "Lignes affectées : " . $stmtReset->rowCount();
	}
	
    // --- 2. Récupérer tous les scénarios (Single) de cette exécution ---
    $stmtScen = $pdo->prepare(
        "SELECT DISTINCT TCProj, TearDownFailed, TearDownWarning, TearDownPassed, checked, RunDate
         FROM `$tableName`
         WHERE JJob  = :jjob
         AND   JParam = :jparam
         AND   Build  = :build
         AND   TestLogTyp = 'Single'
         ORDER BY RunDate DESC"
    );
    $stmtScen->execute([':jjob' => $jJob, ':jparam' => $jParam, ':build' => $build]);
    $allScenarios = $stmtScen->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. Dédoublonner : garder le plus récent par TCProj ---
    $scenarioMap = [];
    foreach ($allScenarios as $sc) {
        $key = $sc['TCProj'];
        if (!isset($scenarioMap[$key]) ||
            strtotime($sc['RunDate']) > strtotime($scenarioMap[$key]['RunDate'])) {
            $scenarioMap[$key] = $sc;
        }
    }

    // --- 4. Recalculer Passed / Flaky / Failed ---
    $calcPassed = 0;
    $calcFlaky  = 0;
    $calcFailed = 0;
    $debugDecisions = [];   // détail TCProj => raison de la décision (mode debug uniquement)
    foreach ($scenarioMap as $sc) {
        $reason = '';
        if (!empty($sc['checked'])) {
            $calcPassed++;                          // Validé => Passed
            $reason = 'checked=true => Passed';
        } elseif (($sc['TearDownFailed'] ?? 0) > 0) {
            $calcFailed++;
            $reason = 'TearDownFailed>0 => Failed';
        } elseif (($sc['TearDownWarning'] ?? 0) > 0) {
            $calcFlaky++;
            $reason = 'TearDownWarning>0 => Flaky';
        } else {
            $calcPassed++;
            $reason = 'default => Passed';
        }

        if ($debug) {
            $debugDecisions[] = [
                'TCProj'          => $sc['TCProj'],
                'RunDate'         => $sc['RunDate'],
                'checked'         => $sc['checked'],
                'TearDownPassed'  => $sc['TearDownPassed'],
                'TearDownWarning' => $sc['TearDownWarning'],
                'TearDownFailed'  => $sc['TearDownFailed'],
                'decision'        => $reason,
            ];
        }
    }

    // --- Mode Debug : retourner le détail sans écrire en base ---
    if ($debug) {
        echo json_encode([
            'success'  => true,
            'debug'    => true,
            'note'     => 'Mode debug actif : aucune écriture en base (UPDATE non exécuté)',
            'AutoID'   => $autoID,
            'table'    => $tableName,
            'testType' => $testType,
			'testname' => $testname,
            'params'   => [
                'JJob'   => $jJob,
                'JParam' => $jParam,
                'Build'  => $build,
                'Product'=> $product,
            ],
            'raw_scenarios_count'       => count($allScenarios),
            'raw_scenarios'             => $allScenarios,
            'deduplicated_count'        => count($scenarioMap),
            'deduplicated_decisions'    => $debugDecisions,
            'calculated_totals' => [
                'passed' => $calcPassed,
                'flaky'  => $calcFlaky,
                'failed' => $calcFailed,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- 5. Persister dans la ligne Main ---
    $stmtUpd = $pdo->prepare(
        "UPDATE `$tableName`
         SET TearDownPassed  = :passed,
             TearDownWarning = :flaky,
             TearDownFailed  = :failed
         WHERE AutoID = :autoID"
    );
    $stmtUpd->execute([
        ':passed' => $calcPassed,
        ':flaky'  => $calcFlaky,
        ':failed' => $calcFailed,
        ':autoID' => $autoID,
    ]);

    echo json_encode([
        'success' => true,
        'AutoID'  => $autoID,
        'table'   => $tableName,
        'passed'  => $calcPassed,
        'flaky'   => $calcFlaky,
        'failed'  => $calcFailed,
        'scenarios_count' => count($scenarioMap),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("sync_main.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
}