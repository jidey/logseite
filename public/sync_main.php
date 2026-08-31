<?php
/**
 * SYNC_MAIN.PHP
 * Recalculates and persists the totals (Passed/Flaky/Failed) of a TestSet (Main)
 * from its scenarios (Single) stored in the database.
 *
 * Called by Jenkins after the retry, without knowing the AutoID:
 *   sync_main.php?Testtype=dev_x16&Product=gWWebSel&JJob=Autotests-Web-Grid&JParam=lists&Build=26.3.0.16660 #3040
 *
 * JSON response: { "success": true, "AutoID": 123, "passed": 10, "flaky": 2, "failed": 0 }
 */

header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../src/TestLogRepository.php';

// --- Parameters ---
$testType = $_GET['Testtype'] ?? '';
$product  = $_GET['Product']  ?? '';
$jJob     = $_GET['JJob']     ?? '';
$jParam   = $_GET['JParam']   ?? '';
$build    = $_GET['Build']    ?? '';
$testname = $_GET['TestName'] ?? '';

// --- Debug mode ---
// Add &Debug=1 to the URL to see the details of the scenarios found
// without writing to the database (no UPDATE is run in debug mode)
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

if (!$testType || !$jJob || !$jParam || !$build) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters (Testtype, JJob, JParam, Build)']);
    exit;
}

// --- SmartWe normalization (consistent with details.php) ---
$isSmartWe = (strpos($product, 'weWebSel') !== false ||
              strpos($product, 'weClient') !== false ||
              strpos($product, 'smartWe')  !== false ||
              strpos($product, 'SmartWe')  !== false);

// Normalize we_dev / we_rc / we_hf (SmartWe)
// Version smartWe courante centralisée dans config/versions_config.php
// ($SMARTWE_CURRENT_VERSION / $LOGG_SMARTWE_HF / $LOGG_SMARTWE_RC, ex: x18 / hf_x18 / rc_x18)
if ($isSmartWe) {
    if ($testType === 'we_dev')     $testType = 'dev_' . $SMARTWE_CURRENT_VERSION;
    elseif ($testType === 'we_rc')  $testType = $LOGG_SMARTWE_RC;
    elseif ($testType === 'we_hf')  $testType = $LOGG_SMARTWE_HF;
}

try {
    $repo      = new TestLogRepository($pdo);
    $tableName = $repo->getTableForTestType($testType, $product);

    // --- 1. Find the AutoID of the Main row ---
    $stmtMain = $pdo->prepare(
		"SELECT AutoID FROM `$tableName`
		 WHERE JJob  = :jjob
		 AND   JParam = :jparam
		 AND   TestLogTyp = 'Main'
		 ORDER BY AutoID DESC
		 LIMIT 1"
	);
	$stmtMain->execute([':jjob' => $jJob, ':jparam' => $jParam]);
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

		// Bonus: check how many rows were modified
		//echo "Rows affected: " . $stmtReset->rowCount();
	}
	
    // --- 2. Get every scenario (Single) of this execution ---
	$stmtScen = $pdo->prepare(
		"SELECT DISTINCT TCProj, TearDownFailed, TearDownWarning, TearDownPassed, checked, RunDate
		 FROM `$tableName`
		 WHERE JJob  = :jjob
		 AND   JParam = :jparam
		 AND   TestLogTyp = 'Single'
		 ORDER BY RunDate DESC"
	);
	$stmtScen->execute([':jjob' => $jJob, ':jparam' => $jParam]);
    $allScenarios = $stmtScen->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. Deduplicate: keep the most recent per TCProj ---
    $scenarioMap = [];
    foreach ($allScenarios as $sc) {
        $key = $sc['TCProj'];
        if (!isset($scenarioMap[$key]) ||
            strtotime($sc['RunDate']) > strtotime($scenarioMap[$key]['RunDate'])) {
            $scenarioMap[$key] = $sc;
        }
    }

    // --- 4. Recalculate Passed / Flaky / Failed ---
    $calcPassed = 0;
    $calcFlaky  = 0;
    $calcFailed = 0;
    $debugDecisions = [];   // TCProj detail => reason for the decision (debug mode only)
    foreach ($scenarioMap as $sc) {
        $reason = '';
        if (!empty($sc['checked'])) {
            $calcPassed++;                          // Validated => Passed
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

    // --- Debug mode: return the details without writing to the database ---
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

    // --- 5. Persist into the Main row ---
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