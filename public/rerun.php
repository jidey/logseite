<?php
/**
 * RERUN.PHP
 * Formulaire de confirmation pour relancer des tests
 * Version modernisée avec Bootstrap 5 et styles LOGG
 */

// Récupérer les paramètres GET
$logurl = $_GET['url'] ?? "https://sqs-sel-cent1.cas-software.dev/logg/public/index.php";
$JJob = $_GET['JJob'] ?? "";
$JParam = $_GET['JParam'] ?? "";
$Build = isset($_GET['Build']) ? urldecode($_GET['Build']) : "Last";
$Product = $_GET['Product'] ?? "error";
$LogVersion = $_GET['LogVersion'] ?? "";
$AutoID = $_GET['AutoID'] ?? "";
$FilterResults = $_GET['Filter'] ?? "no";
$Testtype = $_GET['Testtype'] ?? "";
$Testset = $_GET['Testset'] ?? "";
$TCProj = $_GET['TCProj'] ?? "";
$TestName = $_GET['TestName'] ?? "";
$localrun = $_GET['localrun'] ?? "localrun";  // ✅ Coché par défaut
$parallel = $_GET['parallel'] ?? "no";  // ✅ Décoché par défaut
$retry = $_GET['retry'] ?? "retry";  // ✅ Coché par défaut

// Déterminer la source d'appel (index.php ou details.php)
// Si TCProj est présent, c'est depuis details.php (ReRun Testcase)
// Sinon, c'est depuis index.php (Run complete TestSet)
$pageTitle = !empty($TCProj) ? "ReRun Testcase" : "Run complete TestSet";

// Browser
if (isset($_GET['TestBrowser'])) {
    $TestBrowser = $_GET['TestBrowser'];
} elseif (isset($_GET['RunBrowser'])) {
    $TestBrowser = $_GET['RunBrowser'];
} else {
    $TestBrowser = "chrome";
}

// Hub
$Hub = isset($_GET['Hub']) ? urlencode($_GET['Hub']) : urlencode('https://sqs-sel-cent1.cas-software.dev');

// Fonction pour convertir le testType au format LogVersion correct
// Exemples: "dev_x17" -> "x17_dev", "rc_x17" -> "x17_rc"
function formatLogVersion($input) {
    if (empty($input)) {
        return "x17_dev";
    }
    
    // Si c'est déjà au bon format (contient "_"), vérifier et corriger si nécessaire
    if (strpos($input, '_') !== false) {
        $parts = explode('_', $input);
        if (count($parts) === 2) {
            // Vérifier si c'est au format {version}_{branch} (bon) ou {branch}_{version} (mauvais)
            if (preg_match('/^x\d+/', $parts[0])) {
                // Bon format: x17_dev
                return $input;
            } else if (preg_match('/^x\d+/', $parts[1])) {
                // Mauvais format: dev_x17, il faut inverser
                return $parts[1] . '_' . $parts[0];
            }
        }
    }
    
    return $input;
}

// Déterminer le LogVersion s'il n'est pas fourni
if (empty($LogVersion)) {
    if (!empty($Testtype)) {
        $LogVersion = formatLogVersion($Testtype);
    } else {
        $LogVersion = "x17_dev";
    }
} else {
    // LogVersion est fourni, s'assurer qu'il est au bon format
    $LogVersion = formatLogVersion($LogVersion);
}

// Jenkins configuration
$JJobJenkins = "SQS_Web_TestPipe";
$ForDebug = 'false';

// Construire l'URL de test
if ($Testset == $JParam && empty($TCProj)) {
    $JParam = "@dummy";
    $test = "https://build-sqs.cas-software.dev/view/gWWeb/job/" . $JJobJenkins . 
            "/buildWithParameters?token=TCAUTO&delay=2sec&TestName=" . $JParam . 
            "&Testset=" . $Testset . "&DebugFeature=" . $ForDebug;
} else {
    if (!empty($TCProj)) {
        $TCProjOutline = explode(" outline", $TCProj);
        $TCProj = $TCProjOutline[0];
    }
    $test = "https://build-sqs.cas-software.dev/view/gWWeb/job/" . $JJobJenkins . 
            "/buildWithParameters?token=TCAUTO&delay=2sec&TestName=" . urlencode($TCProj) . 
            "&Testset=" . $Testset . "&DebugFeature=" . $ForDebug;
}

// Ajouter le Product
if ($Product == "weWebSel") {
    $test = $test . "&Product=We";
} elseif ($Product == "gWWebSel") {
    $test = $test . "&Product=Web";
} else {
    $test = $test . "&Product=error";
}

// Construire l'URL de vérification du test
// check.php résout la table via Testtype + Product (gère SmartWe → we_rc)
// check.php est en local dans logg/public/
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'sqs-sel-cent1.cas-software.dev';
$basePath = dirname($_SERVER['PHP_SELF'] ?? '/logg/public/rerun.php');
$runn = $protocol . "://" . $host . $basePath . "/check.php?value=2" .
        "&autoid=" . urlencode($AutoID) .
        "&LogVersion=" . urlencode($LogVersion) .
        "&Testtype=" . urlencode($Testtype) .
        "&Product=" . urlencode($Product);

$execute = isset($_GET['Confirm']) || isset($_GET['Abort']);

// Gérer les actions
if ($execute) {
    if (isset($_GET['Confirm'])) {
        // Déterminer le nœud de test selon la version
        // Chaque version a son propre paramètre (Test_Node, Test_x17, Test_x16, Test_x15, Test_x14)
        $Test_x = 'Grid'; // Défaut
        
		if (substr($LogVersion, 0, 2) == "we") {
            $Test_x = $_GET['Test_Node'] ?? 'Grid';
        } elseif (substr($LogVersion, 0, 3) == "x18") {
            $Test_x = $_GET['Test_x18'] ?? 'Grid';
		} elseif (substr($LogVersion, 0, 3) == "x17") {
            $Test_x = $_GET['Test_x17'] ?? 'Grid';
        } elseif (substr($LogVersion, 0, 3) == "x16") {
            $Test_x = $_GET['Test_x16'] ?? 'Grid';
        } elseif (substr($LogVersion, 0, 3) == "x15") {
            $Test_x = $_GET['Test_x15'] ?? 'Grid';
        } elseif (substr($LogVersion, 0, 3) == "x14") {
            $Test_x = $_GET['Test_x14'] ?? 'Grid';
        }
        
		ConfirmAndRun($test, $runn, $logurl, $Testtype, $Test_x, $LogVersion, $TestBrowser, $JJob, $Hub, $ForDebug, 
                     $localrun, $parallel, $Build, $retry);
    } elseif (isset($_GET['Abort'])) {
        header("Location: " . $logurl);
        exit;
    }
} else {
    // Afficher le formulaire de confirmation
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Theme Init Script -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('logg-theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                document.body.classList.remove('light-mode');
            } else if (savedTheme === 'light') {
                document.body.classList.add('light-mode');
                document.body.classList.remove('dark-mode');
            } else {
                if (!window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.body.classList.add('light-mode');
                }
            }
        })();
    </script>
        <title><?php echo $pageTitle; ?> - LOGG</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="css/styles.min.css" rel="stylesheet">
        <style>
            /* Theme CSS Variables */
            :root {
                --bg-primary: #f8f9fa;
                --bg-secondary: #fff;
                --bg-tertiary: #f5f5f5;
                --text-primary: #212529;
                --text-secondary: #666;
                --text-muted: #999;
                --border-color: #dee2e6;
                --border-light: rgba(0,0,0,.08);
                --input-bg: #fff;
                --input-border: #dee2e6;
                --input-focus-border: #007bff;
                --card-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            body.dark-mode {
                --bg-primary: #1a1a1a;
                --bg-secondary: #2d2d2d;
                --bg-tertiary: #3a3a3a;
                --text-primary: #e4e4e4;
                --text-secondary: #b0b0b0;
                --text-muted: #808080;
                --border-color: #444;
                --border-light: rgba(255,255,255,.1);
                --input-bg: #3a3a3a;
                --input-border: #555;
                --input-focus-border: #4a9eff;
                --card-shadow: 0 2px 4px rgba(0,0,0,0.3);
            }
            
            body {
                background: var(--bg-primary) !important;
                color: var(--text-primary) !important;
                transition: background-color 0.3s ease, color 0.3s ease;
            }
            
            .container {
                background: var(--bg-secondary) !important;
                border-radius: 6px;
                box-shadow: var(--card-shadow);
                margin-top: 20px;
                padding: 30px !important;
            }
            
            h1 {
                color: var(--text-primary) !important;
            }
            
            .form-group label, .form-check label {
                color: var(--text-primary) !important;
            }
            
            .form-control, select {
                background: var(--input-bg) !important;
                color: var(--text-primary) !important;
                border: 1px solid var(--input-border) !important;
            }
            
            .form-control:focus, select:focus {
                background: var(--input-bg) !important;
                color: var(--text-primary) !important;
                border-color: var(--input-focus-border) !important;
                box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25) !important;
            }
            
            .rerun-info {
                background: var(--bg-tertiary) !important;
                border-left: 4px solid #007bff;
                color: var(--text-primary) !important;
            }
            
            .rerun-info strong { 
                color: #007bff; 
            }
            
            .btn-outline-secondary {
                color: var(--text-primary) !important;
                border-color: var(--border-color) !important;
            }
            
            .btn-outline-secondary:hover {
                background: var(--bg-tertiary) !important;
            }
            body { background: #f8f9fa; }
            .rerun-container { max-width: 700px; margin: 40px auto; }
            .rerun-card { 
                background: white; 
                border-radius: 8px; 
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                padding: 30px;
            }
            .rerun-card h2 { 
                color: #007bff; 
                margin-bottom: 30px;
                font-size: 1.8rem;
            }
            .rerun-info {
                background: #f8f9fa;
                border-left: 4px solid #007bff;
                padding: 20px;
                margin: 20px 0;
                border-radius: 4px;
                font-size: 14px;
                line-height: 1.8;
            }
            .rerun-info strong { 
                color: #007bff; 
                min-width: 120px;
                display: inline-block;
            }
            .rerun-info div {
                margin-bottom: 8px;
            }
            .checkbox-group { 
                display: grid; 
                grid-template-columns: 1fr 1fr 1fr; 
                gap: 15px;
                margin: 20px 0;
            }
            .form-check { 
                padding: 15px;
                border-radius: 6px;
                border: 1px solid var(--border-color);
                background: var(--bg-tertiary);
                transition: background-color 0.3s ease;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                cursor: pointer;
                min-height: 100px;
            }
            
            .form-check input[type="checkbox"] {
                margin: 0 0 8px 0 !important;
                cursor: pointer;
                width: 20px;
                height: 20px;
            }
            
            .form-check label {
                margin: 0 !important;
                cursor: pointer;
            }
            
            .form-check label strong {
                display: block;
                margin-bottom: 4px;
                font-size: 15px;
            }
            
            .form-check label small {
                display: block;
                color: var(--text-secondary);
                font-size: 12px;
            }
            
            /* Vert clair quand coché */
            .form-check input[type="checkbox"]:checked {
                background-color: #28a745 !important;
            }
            
            .form-check input[type="checkbox"]:checked ~ label {
                color: #155724;
            }
            
            .form-check:has(input[type="checkbox"]:checked) {
                background-color: #d4edda;
                border-color: #28a745;
            }
            
            .form-check:hover {
                border-color: #007bff;
                box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            }
            .button-group { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 10px;
                margin-top: 30px;
            }
        </style>
    </head>
    <body>
        <!-- Header avec Theme Toggle -->
        <div style="padding: 15px; text-align: right; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color);">
            <button id="themeToggle" class="btn btn-sm btn-outline-secondary" title="Toggle Dark/Light Mode" onclick="toggleTheme()">
                🌙 Dark Mode
            </button>
        </div>
        
        <div class="rerun-container">
            <div class="rerun-card">
                <h2>🔄 <?php echo $pageTitle; ?></h2>
                
                <div class="rerun-info">
                    <div><strong>Test Type:</strong> <?php echo htmlspecialchars($Testtype); ?></div>
                    <div><strong>TestSet:</strong> <?php echo htmlspecialchars($Testset); ?></div>
                    <div><strong>Build:</strong> <?php echo htmlspecialchars($Build); ?></div>
                    <div><strong>Product:</strong> <?php echo htmlspecialchars($Product); ?></div>
                    <div><strong>Browser:</strong> <?php echo htmlspecialchars($TestBrowser); ?></div>
                    <?php if (!empty($TCProj)): ?>
                        <div><strong>Scenario:</strong> <?php echo htmlspecialchars($TCProj); ?></div>
                    <?php endif; ?>
                </div>

                <form method="GET" action="rerun.php" id="rerunForm">
                    <!-- Jenkins Node Selection -->
                    <div style="margin: 20px 0; padding: 15px; background: var(--bg-tertiary); border-radius: 6px;">
                        <label for="jenkins-node" style="display: block; margin-bottom: 10px; font-weight: bold; color: var(--text-primary);">
                            Jenkins Node:
                        </label>
                        <?php include('runsystems.php'); ?>
                    </div>

                    <!-- Options -->
                    <div class="checkbox-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="localrun" name="localrun" value="localrun" 
                                   <?php echo ($localrun === 'localrun') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="localrun">
                                <strong>Run Locally</strong><br>
                                <small>No Grid</small>
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="parallel" name="parallel" value="parallel"
                                   <?php echo ($parallel === 'parallel') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="parallel">
                                <strong>Sequential</strong><br>
                                <small>Force sequential tests</small>
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="retry" name="retry" value="retry"
                                   <?php echo ($retry === 'retry') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="retry">
                                <strong>Retry</strong><br>
                                <small>Retry failing tests</small>
                            </label>
                        </div>
                    </div>					
                    <!-- Hidden fields -->
                    <input type="hidden" name="JJob" value="<?php echo htmlspecialchars($JJob); ?>">
                    <input type="hidden" name="JParam" value="<?php echo htmlspecialchars($JParam); ?>">
                    <input type="hidden" name="Build" value="<?php echo htmlspecialchars($Build); ?>">
                    <input type="hidden" name="Product" value="<?php echo htmlspecialchars($Product); ?>">
                    <input type="hidden" name="LogVersion" value="<?php echo htmlspecialchars($LogVersion); ?>">
                    <input type="hidden" name="AutoID" value="<?php echo htmlspecialchars($AutoID); ?>">
                    <input type="hidden" name="Filter" value="<?php echo htmlspecialchars($FilterResults); ?>">
                    <input type="hidden" name="Testtype" value="<?php echo htmlspecialchars($Testtype); ?>">
                    <input type="hidden" name="Testset" value="<?php echo htmlspecialchars($Testset); ?>">
                    <input type="hidden" name="TCProj" value="<?php echo htmlspecialchars($TCProj); ?>">
                    <input type="hidden" name="TestName" value="<?php echo htmlspecialchars($TestName); ?>">
                    <input type="hidden" name="TestBrowser" value="<?php echo htmlspecialchars($TestBrowser); ?>">
                    <input type="hidden" name="Hub" value="<?php echo htmlspecialchars($Hub); ?>">
                    <input type="hidden" name="url" value="<?php echo htmlspecialchars($logurl); ?>">

                    <!-- Buttons -->
                    <div class="button-group">
                        <button type="submit" name="Confirm" value="1" class="btn btn-success btn-lg">
                            ✅ Confirm & Run
                        </button>
                        <button type="submit" name="Abort" value="1" class="btn btn-danger btn-lg">
                            ❌ Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <script>
            // Mettre à jour les couleurs au chargement
            document.addEventListener('DOMContentLoaded', function() {
                updateCheckboxColors();
                
                // Ajouter les event listeners pour tous les checkboxes
                document.querySelectorAll('.form-check input[type="checkbox"]').forEach(checkbox => {
                    checkbox.addEventListener('change', updateCheckboxColors);
                });
            });
            
            // Fonction pour mettre à jour les couleurs
            function updateCheckboxColors() {
                document.querySelectorAll('.form-check').forEach(formCheck => {
                    const checkbox = formCheck.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        if (checkbox.checked) {
                            formCheck.style.backgroundColor = '#d4edda';  // Vert clair
                            formCheck.style.borderColor = '#28a745';
                        } else {
                            formCheck.style.backgroundColor = '#f5f5f5';  // Gris clair
                            formCheck.style.borderColor = '#dee2e6';
                        }
                    }
                });
            }
        </script>
        
        <!-- Theme Toggle Script -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                updateThemeButton();
            });

            function updateThemeButton() {
                const themeToggle = document.getElementById('themeToggle');
                if (!themeToggle) return;

                const isDarkMode = document.body.classList.contains('dark-mode');
                
                if (isDarkMode) {
                    themeToggle.textContent = '🌙 Dark Mode';
                    themeToggle.title = 'Currently in Dark Mode';
                } else {
                    themeToggle.textContent = '☀️ Light Mode';
                    themeToggle.title = 'Currently in Light Mode';
                }
            }

            function toggleTheme() {
                const isDarkMode = document.body.classList.contains('dark-mode');
                
                if (isDarkMode) {
                    document.body.classList.remove('dark-mode');
                    document.body.classList.add('light-mode');
                    localStorage.setItem('logg-theme', 'light');
                } else {
                    document.body.classList.add('dark-mode');
                    document.body.classList.remove('light-mode');
                    localStorage.setItem('logg-theme', 'dark');
                }
                
                updateThemeButton();
            }
        </script>
    </body>
    </html>
    <?php
}

/**
 * Confirmer et lancer le test
 */
function ConfirmAndRun($test, $runn, $logurl, $branch, $Test_x, $LogVersion, $Browser, $JJob, $Hub, $forDebug, $localrun, $parallel, $Build, $retry) {
    $Test_y = "&Test_Node=" . $Test_x;
    
    // Mapper les branches
    $branchMap = [
        'hf_x15' => 'hotfix/11.x',
        'dev_x16' => 'dev/12.x',
        'rc_x16' => 'rc/12.x',
        'hf_x16' => 'hotfix/12.x',
        'dev_x17' => 'dev/13.x',
        'rc_x17' => 'rc/13.x',
        'hf_x17' => 'hotfix/13.x',
        'dev_x18' => 'dev/14.x',
        'rc_x18' => 'rc/14.x',
        'hf_x18' => 'hotfix/14.x',
        'we_dev' => 'dev/14.x',
        'we_rc' => 'rc/13.x',
        'we_hf' => 'hotfix/13.x',
    ];
    
    $branch = $branchMap[$branch] ?? $branch;
    
    // Construire l'URL de test avec les paramètres
    $test = $test . "&Test_Version=" . $branch . $Test_y . "&TestBrowser=" . $Browser;
    
    if (!empty($Build)) {
        $test = $test . "&TestedBuild=" . urlencode($Build);
    }
    
    if ($forDebug === 'true') {
        $feature = substr($JJob, 14, strlen($JJob));
        $test = $test . "&Feature=" . $feature;
    }
    
    // Sélectionner le hub
    if ($Test_x !== "JDF" && $Test_x !== "SV" && $Test_x !== "OG" && $Test_x !== "AS" && $Test_x !== "Grid") {
        $Hub = "http://sqs-gridhub1";
    } else {
        $Hub = "https://sqs-sel-cent1.cas-software.dev";
    }
    
    $test = $test . "&Hub=" . $Hub;
    
    // Options d'exécution
    if ($localrun === 'localrun') {
        $test = $test . "&LocalBrower=true";
    } else {
        $test = $test . "&LocalBrower=false";
    }
    
    if ($parallel === 'parallel') {
        $test = $test . "&ParallelRun=false";
    }
    
    if ($retry === 'retry') {
        $test = $test . "&RETRY_FAILED=true";
    }
    
    // Envoyer les requêtes via POST (Jenkins nécessite POST, pas GET)
    
    // Fonction pour envoyer une requête POST
    function sendPostRequest($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ['code' => $httpCode, 'response' => $response];
    }
    
    // Envoyer la requête de vérification
    @sendPostRequest($runn);
    
    // Envoyer la requête de test à Jenkins
    @sendPostRequest($test);
    
    // Rediriger vers le log
    header("Location: " . $logurl);
    exit;
}
?>