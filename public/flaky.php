<?php
/**
 * FLAKY.PHP
 * Called by Jenkins at the end of a test: marks the last run of a TCProj as Flaky
 * (successful retry). Sets TearDownWarning=1, TearDownFailed=0.
 */
header('Content-Type: application/json; charset=utf-8');

function respond($success, $message, $extra = []) {
    http_response_code($success ? 200 : 400);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

try {
    require_once __DIR__ . '/../config/config.php';
} catch (Throwable $e) {
    respond(false, 'Config error: ' . $e->getMessage());
}

if (!isset($pdo)) {
    respond(false, 'PDO connection not available');
}

$LogVersion = $_GET['LogVersion'] ?? '';
$TCProj     = $_GET['TCProj'] ?? '';

// Jenkins/TestComplete sometimes sends values surrounded by single quotes
// e.g. 'User Validates...' -> strip the surrounding pair of quotes
$LogVersion = trim($LogVersion);
if (strlen($LogVersion) >= 2 && $LogVersion[0] === "'" && substr($LogVersion, -1) === "'") {
    $LogVersion = substr($LogVersion, 1, -1);
}
$TCProj = trim($TCProj);
if (strlen($TCProj) >= 2 && $TCProj[0] === "'" && substr($TCProj, -1) === "'") {
    $TCProj = substr($TCProj, 1, -1);
}

// Validate the table name (security - no backtick/injection)
if (!preg_match('/^[a-z0-9_]+$/i', $LogVersion)) {
    respond(false, 'Invalid table name: ' . $LogVersion);
}
if ($TCProj === '') {
    respond(false, 'Missing TCProj');
}

try {
    // Find the last run of this TCProj (+ JJob/JParam to find the parent TestSet)
    $sel = $pdo->prepare("SELECT AutoID, JJob, JParam FROM `$LogVersion` WHERE TCProj = :tcproj ORDER BY AutoID DESC LIMIT 1");
    $sel->execute([':tcproj' => $TCProj]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        respond(false, 'No run found for TCProj=' . $TCProj . ' in ' . $LogVersion);
    }
    $id     = $row['AutoID'];
    $jjob   = $row['JJob'];
    $jparam = $row['JParam'];

    // Mark as Flaky: Warning=1 AND Failed=0 (otherwise the scenario counts twice)
    $upd = $pdo->prepare("
        UPDATE `$LogVersion`
        SET `TearDownWarning` = 1,
            `TearDownFailed`  = 0
        WHERE AutoID = :autoid
    ");
    $upd->execute([':autoid' => $id]);

	// ===== Recalculate the TestSet totals (Main row) =====
    // Get the last scenario (Single) of each TCProj for this TestSet
    $scenSql = "SELECT s.TearDownFailed, s.TearDownWarning, s.checked
                FROM `$LogVersion` s
                INNER JOIN (
                    SELECT TCProj, MAX(AutoID) AS maxid
                    FROM `$LogVersion`
                    WHERE JJob = :jjob AND JParam = :jparam AND TestLogTyp = 'Single'
                    GROUP BY TCProj
                ) latest ON s.AutoID = latest.maxid";
    $scenStmt = $pdo->prepare($scenSql);
    $scenStmt->execute([':jjob' => $jjob, ':jparam' => $jparam]);
    $scenarios = $scenStmt->fetchAll(PDO::FETCH_ASSOC);

    $calcPassed = 0; $calcFlaky = 0; $calcFailed = 0;
    foreach ($scenarios as $sc) {
        if (!empty($sc['checked'])) {
            $calcPassed++;                          // validated => Passed
        } elseif (($sc['TearDownFailed'] ?? 0) > 0) {
            $calcFailed++;
        } elseif (($sc['TearDownWarning'] ?? 0) > 0) {
            $calcFlaky++;                           // Flaky counted
        } else {
            $calcPassed++;
        }
    }

    // Find the AutoID of the MOST RECENT Main row (the latest TestSet)
    $mainSel = $pdo->prepare("
        SELECT AutoID FROM `$LogVersion`
        WHERE JJob = :jjob AND JParam = :jparam AND TestLogTyp = 'Main'
        ORDER BY AutoID DESC LIMIT 1
    ");
    $mainSel->execute([':jjob' => $jjob, ':jparam' => $jparam]);
    $mainRow = $mainSel->fetch(PDO::FETCH_ASSOC);

    $mainRows = 0;
    if ($mainRow) {
        $mainAutoID = $mainRow['AutoID'];

        // Update ONLY this TestSet (the latest one)
        $updMain = $pdo->prepare("
            UPDATE `$LogVersion`
            SET `TearDownPassed`  = :passed,
                `TearDownWarning` = :flaky,
                `TearDownFailed`  = :failed
            WHERE AutoID = :autoid
        ");
        $updMain->execute([
            ':passed' => $calcPassed,
            ':flaky'  => $calcFlaky,
            ':failed' => $calcFailed,
            ':autoid' => $mainAutoID
        ]);
        $mainRows = $updMain->rowCount();
    }

    respond(true, 'Updated successfully', [
        'rowsAffected'    => $upd->rowCount(),
        'table'           => $LogVersion,
        'AutoID'          => $id,
        'mainAutoID'      => $mainRow['AutoID'] ?? null,
        'testsetStats'    => ['passed' => $calcPassed, 'flaky' => $calcFlaky, 'failed' => $calcFailed],
        'testsetRows'     => $mainRows
    ]);
} catch (PDOException $e) {
    respond(false, 'Database error: ' . $e->getMessage());
}
?>
