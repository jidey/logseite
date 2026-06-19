<!DOCTYPE html>
<html lang="en">
<head>
<title>Autotests - Stats</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Theme init (avant CSS pour éviter le flash) -->
<script src="js/theme.js"></script>
<!-- Bootstrap 3 (conservé pour compatibilité avec la mise en page existante) -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css">
<link href="css/theme.css" rel="stylesheet">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.2/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
</head>
<body>
<!-- Theme Toggle Button -->
<button id="themeToggle" class="btn btn-sm btn-default theme-toggle-btn" title="Toggle Dark/Light Mode" onclick="toggleTheme()">
    🌙 Dark
</button>
<?php
/**
 * STATS.PHP
 * Affiche l'historique des runs (statistiques) d'un TestSet/Job
 * Version adaptée pour LOGG (PDO via config.php)
 */

require_once '../config/config.php';

// Récupérer et nettoyer les paramètres GET
$JJob         = $_GET['JJob'] ?? '';
$LogVersion   = $_GET['LogVersion'] ?? '';
$LimitPlus    = isset($_GET['LimitPlus']) ? max(1, intval($_GET['LimitPlus'])) : 5;
$TestProject  = $_GET['TestProject'] ?? '';
$ProductFilter = $_GET['Product'] ?? '';
$FilterResults = $_GET['Filter'] ?? 'no';
$Versiontype  = $_GET['Versiontype'] ?? 'RC';
$Testtype     = $_GET['Testtype'] ?? '';
$TestBrowser  = $_GET['TestBrowser'] ?? 'chrome';

echo "<img src=\"Titel.jpg\" style=\"width:250px;height:60px;\" align=\"right\">";

// Calculer la version "courte" (ex: x17 depuis x17_dev), sauf pour SmartWe (we_*)
if (substr($LogVersion, 0, 2) !== "we") {
    $LogVersionTrimmed = substr($LogVersion, 0, 3);
} else {
    $LogVersionTrimmed = $LogVersion;
}

// SmartWe : la colonne Testtype en base contient le nom de table (we_rc, we_hf, we_dev)
// Convertir rc_x17 -> we_rc, hf_x17 -> we_hf, dev_x18 -> we_dev
$isSmartWe = ($ProductFilter === 'weWebSel' || strpos($ProductFilter, 'smartWe') !== false || 
              strpos($ProductFilter, 'SmartWe') !== false || substr($LogVersion, 0, 2) === 'we');
if ($isSmartWe) {
    if (stripos($Testtype, 'hf') !== false) {
        $Testtype = 'we_hf';
    } elseif (stripos($Testtype, 'rc') !== false) {
        $Testtype = 'we_rc';
    } elseif (stripos($Testtype, 'dev') !== false) {
        $Testtype = 'we_dev';
    }
}

// gWClient : déterminer la table selon le Testtype
if ($ProductFilter === 'gWClient') {
    $gwClientMap = [
        'hf_x14'  => 'x14_gwhf',
        'rc_x14'  => 'x14_gwrc',
        'hf_x15'  => 'x15_gwhf',
        'rc_x15'  => 'x15_gwrc',
        'hf_x16'  => 'x16_gwhf',
        'rc_x16'  => 'x16_gwrc',
        'dev_x16' => 'x16_gwdev',
        'hf_x17'  => 'x17_gwhf',
        'rc_x17'  => 'x17_gwrc',
        'dev_x17' => 'x17_gwdev',
        'hf_x18'  => 'x18_gwhf',
        'rc_x18'  => 'x18_gwrc',
        'dev_x18' => 'x18_gwdev',
    ];
    if (isset($gwClientMap[$Testtype])) {
        $LogVersion = $gwClientMap[$Testtype];
    }
}

// Valider le nom de table (sécurité)
if (!preg_match('/^[a-z0-9_]+$/i', $LogVersion)) {
    echo "<div class='container-fluid'><center>Invalid table name</center></div></body></html>";
    exit;
}

try {
    // Lister les jobs distincts
    $selectJobs = "SELECT DISTINCT JJob FROM `$LogVersion`
                   WHERE JJob = :jjob AND Version = :version AND TestLogTyp = 'Main' AND JJob != ''
                   ORDER BY JJob ASC";
    $stmtJobs = $pdo->prepare($selectJobs);
    $stmtJobs->execute([':jjob' => $JJob, ':version' => $LogVersionTrimmed]);
    $jobsList = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <br>
    <div class="dropdown">
        <?php
        if (file_exists('mainmenu.php')) {
            include('mainmenu.php');
        }
        ?>
    </div>
</div>

<div class="container-fluid">
    <center><h3>
    <?php
    $LineJob = 0;
    $Lines = 0;

    // Lien retour vers index.php (local logg/public)
    $backLink = "index.php?Product=" . urlencode($ProductFilter) .
                "&Testtype=" . urlencode($Testtype) .
                "&Filter=" . urlencode($FilterResults);
    echo "<a href=\"" . htmlspecialchars($backLink) . "\">&lt;&lt; Teststatus</a></h3></center>";

    if (count($jobsList) > 0) {
        foreach ($jobsList as $jobsListrow) {
            $LineJob++;

            // Rechercher tous les TCProj pour ce JJob
            if ($TestProject !== "") {
                $testSql = "SELECT DISTINCT JParam FROM `$LogVersion`
                            WHERE Version = :version AND TestLogTyp = 'Main'
                            AND JJob = :jjob AND JParam = :testproject AND JParam != 'Alle'
                            ORDER BY JParam ASC";
                $stmtProj = $pdo->prepare($testSql);
                $stmtProj->execute([
                    ':version' => $LogVersionTrimmed,
                    ':jjob' => $jobsListrow['JJob'],
                    ':testproject' => $TestProject
                ]);
            } else {
                $testSql = "SELECT DISTINCT JParam FROM `$LogVersion`
                            WHERE Version = :version AND TestLogTyp = 'Main'
                            AND JJob = :jjob AND JParam != 'Alle'
                            ORDER BY JParam ASC";
                $stmtProj = $pdo->prepare($testSql);
                $stmtProj->execute([
                    ':version' => $LogVersionTrimmed,
                    ':jjob' => $jobsListrow['JJob']
                ]);
            }

            $projectsList = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

            if (count($projectsList) > 0) {
                ?>
                <div class="panel-primary">
                    <?php echo "<h3>" . htmlspecialchars($jobsListrow['JJob']) . "</h3>"; ?>
                </div>
                <?php

                foreach ($projectsList as $rowProject) {
                    $Lines++;
                    $Lines = $LineJob + $Lines;

                    // Récupérer les derniers runs
                    $selectAllRuns = "SELECT * FROM `$LogVersion`
                                      WHERE JJob = :jjob AND TestLogTyp = 'Main'
                                      AND Testtype = :testtype AND JParam = :jparam
                                      ORDER BY AutoID DESC LIMIT :limitplus";
                    $stmtRuns = $pdo->prepare($selectAllRuns);
                    $stmtRuns->bindValue(':jjob', $jobsListrow['JJob']);
                    $stmtRuns->bindValue(':testtype', $Testtype);
                    $stmtRuns->bindValue(':jparam', $rowProject['JParam']);
                    $stmtRuns->bindValue(':limitplus', $LimitPlus, PDO::PARAM_INT);

                    // ===== DEBUG : afficher la requête SQL avec les valeurs =====
                    /*$debugSql = $selectAllRuns;
                    $debugSql = str_replace(':jjob', "'" . $jobsListrow['JJob'] . "'", $debugSql);
                    $debugSql = str_replace(':testtype', "'" . $Testtype . "'", $debugSql);
                    $debugSql = str_replace(':jparam', "'" . $rowProject['JParam'] . "'", $debugSql);
                    $debugSql = str_replace(':limitplus', $LimitPlus, $debugSql);
                    echo "<pre style='background:#f5f5f5;color:#333;border:1px solid #ccc;padding:8px;font-size:12px;white-space:pre-wrap;'>"
                         . "🐞 DEBUG SQL stmtRuns:\n" . htmlspecialchars($debugSql) . "</pre>";
                    // ===== FIN DEBUG =====
					*/

                    $stmtRuns->execute();
                    $testsList = $stmtRuns->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="panel panel-primary">
                        <?php echo "<div class=\"panel-group\" id=\"accordion" . $Lines . "\">"; ?>
                        <div class="panel-heading">
                            <!-- Jenkins Jobs -->
                            <?php
                            echo "<h5><a data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapse" . $Lines . "\">" . htmlspecialchars($rowProject['JParam']) . "</a></h5>";
                            ?>
                        </div>
                        <div class="panel-body">
                            <?php
                            if ($TestProject !== "") {
                                echo "<div id=\"collapse" . $Lines . "\" class=\"panel-collapse collapse in\">";
                            } else {
                                echo "<div id=\"collapse" . $Lines . "\" class=\"panel-collapse collapse\">";
                            }

                            if (count($testsList) == 0) {
                                echo "No results <a href=\"details.php?LogVersion=" . urlencode($LogVersion) . "\"> &gt;&gt; Display ALL </a><br>";
                            } else {
                                ?>
                                <table class="table table-bordered table-condensed">
                                    <thead>
                                        <tr>
                                            <th style="text-align:center" class="col-lg-1">TestNode</th>
                                            <th style="text-align:center" class="col-lg-1">Passed</th>
                                            <th style="text-align:center" class="col-lg-1">Verified</th>
                                            <th style="text-align:center" class="col-lg-1">Failed</th>
                                            <th style="text-align:center" class="col-lg-1">Tested Build</th>
                                            <th style="text-align:center" class="col-lg-1">Delete Log</th>
                                            <th style="text-align:center" class="col-lg-2">RunDate</th>
                                            <th style="text-align:center" class="col-lg-2">Duration</th>
                                            <th style="text-align:center" class="col-lg-1">Log</th>
                                            <th style="text-align:center" class="col-lg-1">TestBranch</th>
                                            <th style="text-align:center" class="col-lg-1">ID</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    foreach ($testsList as $row) {
                                        echo "<tr>";
                                        echo "<td style=\"text-align:center\">" . htmlspecialchars($row['TestNode']) . "</td>";
                                        echo "<td style=\"text-align:center\" class=\"success\"><b>" . htmlspecialchars($row['TearDownPassed']) . "</b></td>";

                                        if ($row['TearDownWarning'] != "0") {
                                            echo "<td style=\"text-align:center\" class=\"warning\"><font color=\"orange\"><b>" . htmlspecialchars($row['TearDownWarning']) . "</b></font></td>";
                                        } else {
                                            echo "<td style=\"text-align:center\" class=\"success\">" . htmlspecialchars($row['TearDownWarning']) . "</td>";
                                        }

                                        if ($row['TearDownFailed'] != "0") {
                                            echo "<td style=\"text-align:center\" class=\"danger\"><font color=\"red\"><b>" . htmlspecialchars($row['TearDownFailed']) . "</b></font></td>";
                                        } else {
                                            echo "<td style=\"text-align:center\" class=\"success\">" . htmlspecialchars($row['TearDownFailed']) . "</td>";
                                        }

                                        echo "<td style=\"text-align:center\">" . htmlspecialchars(trim($row['Build'])) . "</td>";

                                        // Lien suppression du log
                                        $deleteLink = "delete.php?AutoID=" . urlencode($row['AutoID']) .
                                                      "&Version=" . urlencode($LogVersion) .
                                                      "&TestProject=" . urlencode($row['JParam']) .
                                                      "&JJob=" . urlencode($row['JJob']) .
                                                      "&Testtype=" . urlencode($row['Testtype']) .
                                                      "&TestLogType=" . urlencode($row['TestLogTyp']) .
                                                      "&Build=" . urlencode($row['Build']);
                                        echo "<td style=\"text-align:center\"><a href=\"" . htmlspecialchars($deleteLink) . "\" target=\"_self\">";
                                        echo "<img src=\"delete.png\" alt=\"Delete\" style=\"width:24px;height:24px;border:0\"></a></td>";

                                        echo "<td style=\"text-align:center\">" . htmlspecialchars($row['RunDate']) . "</td>";
                                        echo "<td style=\"text-align:center\">" . htmlspecialchars($row['RunDuration']) . "</td>";

                                        // Transformer le LogLink (port + pipeline)
                                        $old = array("http:", "8080");
                                        $new = array("https:", "8181");
                                        $LogLink = str_replace($old, $new, $row['LogLink']);

                                        if ($row['RunDate'] > "2023-05-11 15:00:00") {
                                            $old = array("Autotests-Web-Grid-x.7", "Autotests-We-Grid-x.7");
                                            $new = array("SQS_Web_TestPipe", "SQS_Web_TestPipe");
                                            $LogLink = str_replace($old, $new, $LogLink);
                                        }

                                        if ($row['Testtype'] == "we_feat" || $row['Testtype'] == "web_feat") {
                                            $old = array("SQS_Web_TestPipe");
                                            $new = array("SQS_Web_FeatureTests");
                                            $LogLink = str_replace($old, $new, $LogLink);
                                        }

                                        echo "<td style=\"text-align:center\"><a href=\"" . htmlspecialchars($LogLink) . "\" target=\"_blank\">Test Log</a></td>";
                                        echo "<td style=\"text-align:center\">" . htmlspecialchars($row['Testtype']) . "</td>";
                                        echo "<td style=\"text-align:center\">" . htmlspecialchars($row['AutoID']) . "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                    </tbody>
                                </table>
                                <?php
                                // Lien "More..." (augmente la limite)
                                $moreLink = "stats.php?Product=" . urlencode($ProductFilter) .
                                            "&JJob=" . urlencode($JJob) .
                                            "&Filter=" . urlencode($FilterResults) .
                                            "&LogVersion=" . urlencode($LogVersion) .
                                            "&Testtype=" . urlencode($Testtype) .
                                            "&TestProject=" . urlencode($TestProject) .
                                            "&LimitPlus=" . ($LimitPlus + 5);
                                echo "<center><a href=\"" . htmlspecialchars($moreLink) . "\">More...</a></center>";
                            }
                            ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                echo "</div>";
            }
        }
    } else {
        echo "<center>No Test Data available</center>";
    }

} catch (PDOException $e) {
    echo "<center>Error: " . htmlspecialchars($e->getMessage()) . "</center>";
    error_log("stats.php error: " . $e->getMessage());
}
?>
</div>
</body>
</html>