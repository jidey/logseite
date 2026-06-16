<?php
/**
 * DETAILS PAGE - SCENARIOS FOR A TESTSET
 * Display one line per DISTINCT Scenario (TestLogType = Single)
 * 
 * Structure : Testset | FeatureTag | TeamTag | ID | Scenario name | Tested Build | Result | Trigger | Manual | Log | Last Run Date | Delete
 */

// Load configuration and Repository
require_once '../config/config.php';
require_once '../src/TestLogRepository.php';

// Get parameters
$testType = $_GET['Testtype'] ?? 'rc_x17';
$product = $_GET['Product'] ?? 'gWWebSel';
$autoID = $_GET['AutoID'] ?? null;
$onlyFailed = isset($_GET['OnlyFailed']) && $_GET['OnlyFailed'] === '1';  // Show only failed scenarios

// Normaliser le testType pour SmartWe (cohérent avec index.php)
// SmartWe : rc → rc_x17, hf → hf_x17 (forcés), dev → garde sa version
$isSmartWe = (strpos($product, 'weWebSel') !== false || 
              strpos($product, 'weClient') !== false || 
              strpos($product, 'smartWe') !== false || 
              strpos($product, 'SmartWe') !== false);

if ($isSmartWe) {
    if (stripos($testType, 'hf') !== false) {
        $testType = 'hf_x17';  // Forcer hf_x17
    } elseif (stripos($testType, 'rc') !== false) {
        $testType = 'rc_x17';  // Forcer rc_x17
    }
    // dev garde sa version (dev_x18, etc.)
}

// Create a Repository instance
$repo = new TestLogRepository($pdo);

// Get the TestSet details (Main)
$error = null;
$testset = null;
$scenarios = [];

try {
    if (!$autoID) {
        throw new Exception("Missing AutoID parameter");
    }
    
    // Get the TestSet (Main) details
    $testset = $repo->getRunDetails($testType, (int)$autoID, $product);
    
    if (!$testset) {
        throw new Exception("TestSet not found");
    }
    
    // Get all Scenarios (Single) for this TestSet
    // Query the database for scenarios with same JJob and JParam
    $tableName = $repo->getTableForTestType($testType, $product);
    
    $query = "SELECT * FROM `$tableName` 
              WHERE JJob = :jjob 
              AND JParam = :jparam 
              AND TestLogTyp = 'Single'
              ORDER BY RunDate DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':jjob', $testset['JJob'], PDO::PARAM_STR);
    $stmt->bindValue(':jparam', $testset['JParam'], PDO::PARAM_STR);
    $stmt->execute();
    
    // Group scenarios by ScenarioName (TCProj) and keep latest
    $scenarioMap = [];
    $allScenarios = $stmt->fetchAll();
    
    // Debug: vérifier les colonnes disponibles
    if (!empty($allScenarios)) {
        error_log("Available columns: " . implode(', ', array_keys($allScenarios[0])));
    }
    
    foreach ($allScenarios as $scenario) {
        $scenarioName = $scenario['TCProj'] ?? 'Unknown'; // Using TCProj as Scenario name key
        
        // Normaliser les colonnes - chercher teamtag indépendamment de la casse
        $normalizedScenario = [];
        foreach ($scenario as $key => $value) {
            $lowerKey = strtolower($key);
            $normalizedScenario[$lowerKey] = $value;
        }
        // Garder aussi les clés originales pour la compatibilité
        $normalizedScenario = array_merge($scenario, $normalizedScenario);
        
        // Keep only latest execution of each scenario
        if (!isset($scenarioMap[$scenarioName]) || strtotime($scenario['RunDate'] ?? 0) > strtotime($scenarioMap[$scenarioName]['RunDate'] ?? 0)) {
            $scenarioMap[$scenarioName] = $normalizedScenario;
        }
    }
    
    // Convert to array and sort by date
    $scenarios = array_values($scenarioMap);
    
    // Apply filter: only failed scenarios
    if ($onlyFailed) {
        $scenarios = array_filter($scenarios, function($scenario) {
            return ($scenario['TearDownFailed'] ?? 0) > 0;
        });
    }
    
    usort($scenarios, function($a, $b) {
        return strtotime($b['RunDate'] ?? 0) - strtotime($a['RunDate'] ?? 0);
    });
    
} catch (Exception $e) {
    $error = "Error: " . htmlspecialchars($e->getMessage());
    error_log("Error in details.php: " . $e->getMessage());
}

// Get versions and products for filters
$versions = $repo->getAvailableVersions();
$productsForVersion = $repo->getProductsForVersion($testType);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Details - Test Scenarios</title>
    
    <!-- Theme Initialization Script (must be FIRST - before CSS) -->
    <script>
        (function() {
            // Déterminer le thème avant que le CSS ne charge
            const savedTheme = localStorage.getItem('logg-theme');
            let appliedTheme = 'light'; // Défaut
            
            if (savedTheme === 'dark') {
                // Mode sombre sauvegardé
                appliedTheme = 'dark';
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (savedTheme === 'light') {
                // Mode clair sauvegardé
                appliedTheme = 'light';
                document.documentElement.setAttribute('data-theme', 'light');
            } else {
                // Pas de préférence sauvegardée, utiliser la préférence système
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    appliedTheme = 'dark';
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    appliedTheme = 'light';
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            }
            
            // Stocker le thème appliqué pour utilisation après le chargement du DOM
            window.initialTheme = appliedTheme;
        })();
    </script>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- LOGG Custom CSS (minified) -->
    <link href="css/styles.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        
        <!-- Header -->
        <div class="header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 style="margin: 0;">🧪 Test Scenarios - Details</h1>
                </div>
                <div class="col-md-4 text-end">
                    <div style="margin-bottom: 10px; display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                        <button id="themeToggle" class="btn btn-sm btn-outline-secondary" title="Toggle Dark/Light Mode" onclick="toggleTheme()">
                            🌙 Dark Mode
                        </button>
                        
                        <!-- Text Size Slider -->
                        <input type="range" id="textSizeSlider" min="80" max="150" value="100" style="width: 70px; cursor: pointer; height: 5px;" title="Adjust text size">
                    </div>

                </div>
            </div>
        </div>

        <!-- Back button -->
        <div class="btn-back">
            <a href="index.php?Testtype=<?php echo urlencode($testType); ?>&Product=<?php echo urlencode($product); ?>&ErrorOnly=<?php echo $onlyFailed ? '1' : '0'; ?>" 
               class="btn btn-secondary">
                ← Back to TestSets
            </a>
        </div>

        <!-- Error display -->
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>❌ Error:</strong> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- TestSet Info - Compact -->
        <?php if ($testset): ?>
        <div class="testset-info" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; background: var(--bg-tertiary); border-radius: 6px; margin-bottom: 15px; gap: 20px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 25px;">
                <div>
                    <span style="color: var(--text-secondary); font-size: 12px;">Summary</span><br>
                    <strong style="font-size: 14px;"><?php echo htmlspecialchars($testset['JParam'] ?? '-'); ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-size: 12px;">Version</span><br>
                    <span style="font-size: 13px;"><?php echo htmlspecialchars($testset['Build'] ?? '-'); ?></span>
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-size: 12px;">Test Type</span><br>
                    <span style="font-size: 13px;"><?php echo htmlspecialchars($testset['Testtype'] ?? '-'); ?></span>
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-size: 12px; display: block;">Passed</span>
                    <span class="status-passed" style="font-weight: bold; font-size: 14px;"><?php echo $testset['TearDownPassed'] ?? 0; ?></span>
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-size: 12px; display: block;">Flaky</span>
                    <span class="status-flaky" style="font-weight: bold; font-size: 14px;"><?php echo $testset['TearDownWarning'] ?? 0; ?></span>
                </div>
                <div>
                    <span style="color: var(--text-secondary); font-size: 12px; display: block;">Failed</span>
                    <span class="status-failed" style="font-weight: bold; font-size: 14px;"><?php echo $testset['TearDownFailed'] ?? 0; ?></span>
                </div>
            </div>
			<!-- Filter Section -->			
				<form method="GET" class="filter-group">
					<input type="hidden" name="Testtype" value="<?php echo urlencode($testType); ?>">
					<input type="hidden" name="Product" value="<?php echo urlencode($product); ?>">
					<input type="hidden" name="AutoID" value="<?php echo urlencode($autoID); ?>">
					
					<!-- Only Failed Toggle Button -->
					<div class="btn-group" role="group">
						<input type="radio" class="btn-check" name="OnlyFailed" id="allResultsBtn" value="0" 
							   <?php echo !$onlyFailed ? 'checked' : ''; ?> onchange="this.form.submit()">
						<label class="btn btn-outline-primary" for="allResultsBtn">
							All Scenarios
						</label>
						
						<input type="radio" class="btn-check" name="OnlyFailed" id="onlyFailedBtn" value="1" 
							   <?php echo $onlyFailed ? 'checked' : ''; ?> onchange="this.form.submit()">
						<label class="btn btn-outline-danger" for="onlyFailedBtn">
							❌ Failed Only
						</label>
					</div>
				</form>			
        </div>
        <?php endif; ?>

        <!-- Scenarios Table -->
        <h4 style="margin-bottom: 20px;">Scenarios (<?php echo count($scenarios); ?>)</h4>
        
        <div class="results-table">
            <div style="overflow-x: auto;">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 100px;" class="sortable" data-sort="featuretag">FeatureTag <span class="sort-indicator"></span></th>
                            <th style="width: 100px;" class="sortable" data-sort="teamtag">TeamTag <span class="sort-indicator"></span></th>
                            <th style="width: 200px;">Scenario Name</th>
                            <th style="width: 100px;">Tested Build</th>
                            <th style="width: 80px;" class="sortable" data-sort="result">Result <span class="sort-indicator"></span></th>
                            <th style="width: 70px;">Run</th>
                            <th style="width: 70px;">Log</th>
                            <th style="width: 80px; text-align: center;">Last Run Date</th>
                            <th style="width: 70px;">Validated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($scenarios)): ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">
                                No scenarios found for this TestSet
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($scenarios as $scenario): ?>
                            <tr data-autoid="<?php echo $scenario['AutoID']; ?>" data-failed="<?php echo $scenario['TearDownFailed'] ?? 0; ?>" data-warning="<?php echo $scenario['TearDownWarning'] ?? 0; ?>">
                                <!-- FeatureTag -->
                                <td data-sort-value="<?php echo htmlspecialchars($scenario['tag'] ?? '-'); ?>">
                                    <small><?php echo htmlspecialchars($scenario['tag'] ?? '-'); ?></small>
                                </td>
                                
                                <!-- TeamTag -->
                                <td data-sort-value="<?php echo htmlspecialchars($scenario['teamtag'] ?? '-'); ?>">
                                    <small><?php echo htmlspecialchars($scenario['teamtag'] ?? '-'); ?></small>
                                </td>
                                
                                <!-- Scenario Name (TCProj) -->
                                <td>
                                    <small class="scenario-name">
                                        <?php echo htmlspecialchars($scenario['TCProj'] ?? '-'); ?>
                                    </small>
                                </td>
                                
                                <!-- Tested Build -->
                                <td>
                                    <small><?php echo htmlspecialchars($scenario['Build'] ?? '-'); ?></small>
                                </td>
                                
                                <!-- Result -->
                                <td class="result-cell" data-sort-value="<?php 
                                    // Valeur pour le tri
                                    if ($scenario['checked'] ?? 0) {
                                        echo 'flaky';
                                    } elseif ($scenario['TearDownFailed'] > 0) {
                                        echo 'failed';
                                    } else {
                                        echo 'passed';
                                    }
                                ?>">
                                    <?php 
                                    // Si Validated est coché (checked = 1), afficher Flaky
                                    if ($scenario['checked'] ?? 0) {
                                        echo '<span class="result-badge result-flaky">⚠️ Flaky</span>';
                                    } else {
                                        // Sinon, afficher le résultat réel
                                        if ($scenario['TearDownFailed'] > 0) {
                                            echo '<span class="result-badge result-failed">❌ Failed</span>';
                                        } elseif ($scenario['TearDownWarning'] > 0) {
                                            echo '<span class="result-badge result-flaky">⚠️ Flaky</span>';
                                        } else {
                                            echo '<span class="result-badge result-passed">✅ Passed</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                
                                <!-- Trigger -->
                                <td class="text-center">
                                    <?php 
                                    // Construire le lien rerun avec tous les paramètres
                                    $triggerLink = "rerun.php?" .
                                                   "JJob=" . urlencode($testset['JJob'] ?? 'CI') .
                                                   "&JParam=" . urlencode($testset['JParam'] ?? '') .
                                                   "&Testset=" . urlencode($testset['JParam'] ?? '') .
                                                   "&AutoID=" . urlencode($scenario['AutoID']) .
                                                   "&TCProj=" . urlencode($scenario['TCProj'] ?? '') .
                                                   "&Build=" . urlencode($testset['Build'] ?? 'Last') .
                                                   "&Testtype=" . urlencode($testType) .
                                                   "&LogVersion=" . urlencode($testType) .
                                                   "&Product=" . urlencode($product) .
                                                   "&TestBrowser=" . urlencode($browser ?? 'chrome') .
                                                   "&url=" . urlencode('https://sqs-sel-cent1.cas-software.dev/logg/public/details.php?' .
                                                       'Testtype=' . urlencode($testType) . 
                                                       '&Product=' . urlencode($product) . 
                                                       '&AutoID=' . urlencode($testset['AutoID'] ?? ''));
                                    
                                    $runningStatus = $scenario['running'] ?? $scenario['Running'] ?? 0;
                                    ?>
                                    <?php if ($runningStatus == 2): ?>
                                        <!-- Test en cours - bouton cliquable pour réinitialiser -->
                                        <button type="button" 
                                             class="btn btn-sm btn-warning reset-running-scenario-btn"
                                             title="Test is running - Click to reset"
                                             onclick="resetScenarioRunning('<?php echo htmlspecialchars($scenario['AutoID']); ?>', '<?php echo htmlspecialchars($testType); ?>', '<?php echo htmlspecialchars($product); ?>', this)"
                                             data-jjob="<?php echo htmlspecialchars($testset['JJob'] ?? 'CI'); ?>"
                                             data-jparam="<?php echo htmlspecialchars($scenario['JParam'] ?? ''); ?>"
                                             data-testtype="<?php echo htmlspecialchars($testType); ?>"
                                             data-product="<?php echo htmlspecialchars($product); ?>"
                                             data-autoid="<?php echo htmlspecialchars($scenario['AutoID']); ?>">
                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Running
                                        </button>
                                    <?php else: ?>
                                        <!-- Test prêt à relancer -->
                                        <a href="<?php echo htmlspecialchars($triggerLink); ?>" 
                                           target="_self" 
                                           title="Run this scenario"
                                           class="btn btn-sm btn-success trigger-link"
                                           data-jjob="<?php echo htmlspecialchars($testset['JJob'] ?? 'CI'); ?>"
                                           data-jparam="<?php echo htmlspecialchars($scenario['JParam'] ?? ''); ?>"
                                           data-testtype="<?php echo htmlspecialchars($testType); ?>"
                                           data-product="<?php echo htmlspecialchars($product); ?>">
                                            ▶ Run
                                        </a>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Log -->
                                <td class="text-center">
                                    <?php 
                                    $logLink = $scenario['LogLink'] ?? null;
                                    
                                    if (!empty($logLink)) {
                                        echo '<a href="' . htmlspecialchars($logLink) . '" target="_blank" class="btn btn-sm btn-info" title="View Allure report">';
                                        echo '📋 Log';
                                        echo '</a>';
                                    } else {
                                        echo '<button type="button" class="btn btn-sm btn-secondary" disabled title="No log available">';
                                        echo '📋 N/A';
                                        echo '</button>';
                                    }
                                    ?>
                                </td>
                                
                                <!-- Last Run Date -->
                                <td>
                                    <small>
                                        <?php 
                                        if (!empty($scenario['RunDate'])) {
                                            echo date('d/m/Y H:i', strtotime($scenario['RunDate']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </small>
                                </td>
                                
                                <!-- Validated (Checkbox) -->
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input" 
                                           <?php echo ($scenario['checked'] ?? 0) ? 'checked' : ''; ?>
                                           onchange="updateScenarioValidation(<?php echo $scenario['AutoID']; ?>, this.checked, '<?php echo htmlspecialchars($testType); ?>', '<?php echo htmlspecialchars($product); ?>')">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- LOGG App JS -->
    <script src="js/app.js" defer></script>

    <!-- Theme Toggle Script -->
    <script>
        // Calculer et ajuster les positions sticky
        function updateStickyPositions() {
            const header = document.querySelector('.header');
            const filters = document.querySelector('.filters');
            const stats = document.querySelector('.stats');
            const thead = document.querySelector('.results-table thead');
            
            if (header) {
                const headerHeight = header.offsetHeight;
                
                if (filters) {
                    filters.style.top = headerHeight + 'px';
                }
                
                if (stats) {
                    const filterHeight = filters ? filters.offsetHeight : 0;
                    stats.style.top = (headerHeight + filterHeight) + 'px';
                }
                
                if (thead) {
                    // Ne pas ajouter de top au thead pour details.php
                    // const filterHeight = filters ? filters.offsetHeight : 0;
                    // const statsHeight = stats ? stats.offsetHeight : 0;
                    // thead.style.top = (headerHeight + filterHeight + statsHeight) + 'px';
                }
            }
        }
        
        // Exécuter au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Appliquer les classes de thème basées sur le thème initial déterminé
            if (window.initialTheme === 'dark') {
                document.body.classList.add('dark-mode');
                document.body.classList.remove('light-mode');
            } else {
                document.body.classList.add('light-mode');
                document.body.classList.remove('dark-mode');
            }
            
            updateThemeButton();
            setTimeout(updateStickyPositions, 100);
            
            // Restaurer la préférence "All Scenarios / Failed Only" depuis localStorage
            const savedErrorOnly = localStorage.getItem('logg-error-only');
            if (savedErrorOnly !== null) {
                const btnId = savedErrorOnly === '1' ? 'onlyFailedBtn' : 'allResultsBtn';
                const targetBtn = document.getElementById(btnId);
                
                if (targetBtn) {
                    const isCurrentlyChecked = targetBtn.checked;
                    targetBtn.checked = true;
                    
                    // Si la valeur sauvegardée est différente de celle actuellement cochée, soumettre le formulaire
                    if (!isCurrentlyChecked) {
                        targetBtn.form.submit();
                    }
                }
            }
            
            // Sauvegarder le choix dans localStorage quand on change
            const allResultsBtn = document.getElementById('allResultsBtn');
            const onlyFailedBtn = document.getElementById('onlyFailedBtn');
            
            if (allResultsBtn) {
                allResultsBtn.addEventListener('change', function() {
                    if (this.checked) {
                        localStorage.setItem('logg-error-only', '0');
                    }
                });
            }
            
            if (onlyFailedBtn) {
                onlyFailedBtn.addEventListener('change', function() {
                    if (this.checked) {
                        localStorage.setItem('logg-error-only', '1');
                    }
                });
            }
            
            // Gérer le clic sur les boutons Run pour les transformer en "Running"
            document.querySelectorAll('.trigger-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    // Ne pas empêcher la navigation, juste transformer le bouton
                    this.classList.remove('btn-success');
                    this.classList.add('btn-warning');
                    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Running';
                    this.title = 'Test is running';
                    this.style.pointerEvents = 'none';
                });
            });
        });
        
        // Gérer le clic sur le bouton "Running" - fonction globale appelée par onclick inline
        function resetScenarioRunning(autoID, testType, product, btnElement) {
            console.log('resetScenarioRunning called - AutoID:', autoID, 'TestType:', testType, 'Product:', product);
            
            // Feedback visuel
            btnElement.style.opacity = '0.5';
            btnElement.style.pointerEvents = 'none';
            
            // Appeler check.php pour remettre running=0
            const url = 'check.php?value=0&field=running' + 
                  '&autoid=' + encodeURIComponent(autoID) + 
                  '&LogVersion=' + encodeURIComponent(testType) +
                  '&Testtype=' + encodeURIComponent(testType) + 
                  '&Product=' + encodeURIComponent(product);
            
            console.log('Calling:', url);
            
            fetch(url)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        btnElement.style.opacity = '1';
                        setTimeout(() => {
                            location.reload();
                        }, 300);
                    } else {
                        btnElement.style.opacity = '1';
                        btnElement.style.pointerEvents = 'auto';
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    btnElement.style.opacity = '1';
                    btnElement.style.pointerEvents = 'auto';
                    alert('Error resetting running status: ' + error.message);
                });
        }
        
        // Recalculer au redimensionnement
        window.addEventListener('resize', updateStickyPositions);

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
                // Passer en mode clair
                document.body.classList.remove('dark-mode');
                document.body.classList.add('light-mode');
                savePreferences('light', textSizeSlider.value);
            } else {
                // Passer en mode sombre
                document.body.classList.add('dark-mode');
                document.body.classList.remove('light-mode');
                savePreferences('dark', textSizeSlider.value);
            }
            
            updateThemeButton();
        }
        
        // Apply text size changes
        function applyTextSize(percentage) {
            const baseFontSize = 14; // Base font size in pixels
            const newFontSize = (baseFontSize * percentage) / 100;
            
            // Create or update style tag
            let styleTag = document.getElementById('text-size-style');
            if (!styleTag) {
                styleTag = document.createElement('style');
                styleTag.id = 'text-size-style';
                document.head.appendChild(styleTag);
            }
            
            // Apply CSS rule to override all table text sizes
            styleTag.innerHTML = `
                .results-table,
                table {
                    font-size: ${newFontSize}px !important;
                }
                
                .results-table td,
                .results-table th,
                table td,
                table th {
                    font-size: ${newFontSize}px !important;
                }
                
                .results-table small {
                    font-size: ${newFontSize * 0.85}px !important;
                }
            `;
        }
        
        // Text size slider - Déclarer avant loadUserPreferences
        const textSizeSlider = document.getElementById('textSizeSlider');
        
        // Load user preferences from local cache on page load
        function loadUserPreferences() {
            const cached = localStorage.getItem('logg-prefs');
            const prefs = cached ? JSON.parse(cached) : {};
            
            // Apply theme
            const theme = prefs.theme || 'light';
            if (theme === 'dark') {
                document.body.classList.add('dark-mode');
                document.body.classList.remove('light-mode');
            } else {
                document.body.classList.remove('dark-mode');
                document.body.classList.add('light-mode');
            }
            updateThemeButton();
            
            // Apply text size
            if (textSizeSlider) {
                const textSize = prefs.text_size || 100;
                textSizeSlider.value = textSize;
                applyTextSize(parseInt(textSize));
            }
        }
        
        // Save preferences to local cache
        function savePreferences() {
            const textSizeSlider = document.getElementById('textSizeSlider');
            const prefs = {
                theme: document.body.classList.contains('dark-mode') ? 'dark' : 'light',
                text_size: textSizeSlider ? parseInt(textSizeSlider.value) : 100,
                error_only: 0,
                product: '',
                testtype: '',
                test_browser: '',
                team_tag: ''
            };
            
            localStorage.setItem('logg-prefs', JSON.stringify(prefs));
        }
        
        // Load preferences on page load
        loadUserPreferences();
        
        // Text size slider (textSizeSlider déjà déclaré plus haut)
        if (textSizeSlider) {
            // Handle slider input
            textSizeSlider.addEventListener('input', function() {
                const size = this.value;
                applyTextSize(parseInt(size));
                savePreferences(document.body.classList.contains('dark-mode') ? 'dark' : 'light', size);
            });
        }
        
        // Mettre à jour la validation d'un scénario et rafraîchir les totaux
        function updateScenarioValidation(autoID, isChecked, testType, product) {
            console.log('updateScenarioValidation called:', {autoID, isChecked, testType, product});
            
            // Trouver la ligne du scénario par data-autoid
            const scenarioRow = document.querySelector(`tr[data-autoid="${autoID}"]`);
            
            if (!scenarioRow) {
                console.error('Ligne du scénario non trouvée pour AutoID:', autoID);
                return;
            }
            
            // Trouver la colonne Result (5ème colonne, index 4)
            const resultCell = scenarioRow.querySelector('td:nth-child(5)');
            
            // Mettre à jour le Result visuellement
            if (resultCell) {
                if (isChecked) {
                    // Coché = Flaky
                    resultCell.innerHTML = '<span class="result-badge result-flaky">⚠️ Flaky</span>';
                    console.log('Result changé en Flaky');
                } else {
                    // Décoché = Failed (par défaut)
                    resultCell.innerHTML = '<span class="result-badge result-failed">❌ Failed</span>';
                    console.log('Result changé en Failed');
                }
            } else {
                console.error('Colonne Result non trouvée');
            }
            
            // Envoyer la mise à jour au serveur
            fetch('update_validation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'AutoID=' + encodeURIComponent(autoID) + 
                      '&Checked=' + (isChecked ? 1 : 0) +
                      '&TestType=' + encodeURIComponent(testType) +
                      '&Product=' + encodeURIComponent(product)
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        updateTestSetStats();
                        console.log('Validation mise à jour avec succès');
                    } else {
                        console.error('Erreur serveur:', data.error);
                        alert('Erreur: ' + (data.error || 'Impossible de mettre à jour'));
                    }
                } catch (e) {
                    console.error('Erreur JSON parse:', e);
                    console.error('Contenu reçu:', text);
                    alert('Erreur de format serveur');
                }
            })
            .catch(error => {
                console.error('Erreur fetch:', error);
                alert('Erreur réseau: ' + error.message);
            });
        }
        
        // Rafraîchir les totaux des statistiques du TestSet
        function updateTestSetStats() {
            // Récalculer les totaux en fonction des résultats visibles
            let passed = 0;
            let flaky = 0;
            let failed = 0;
            
            const resultBadges = document.querySelectorAll('.result-badge');
            for (let badge of resultBadges) {
                if (badge.classList.contains('result-passed')) {
                    passed++;
                } else if (badge.classList.contains('result-flaky')) {
                    flaky++;
                } else if (badge.classList.contains('result-failed')) {
                    failed++;
                }
            }
            
            // Mettre à jour l'affichage des totaux
            const statsCards = document.querySelectorAll('.stat-card');
            for (let card of statsCards) {
                if (card.classList.contains('stat-passed')) {
                    const countElement = card.querySelector('.stat-count');
                    if (countElement) {
                        countElement.textContent = passed;
                    }
                } else if (card.classList.contains('stat-flaky')) {
                    const countElement = card.querySelector('.stat-count');
                    if (countElement) {
                        countElement.textContent = flaky;
                    }
                } else if (card.classList.contains('stat-failed')) {
                    const countElement = card.querySelector('.stat-count');
                    if (countElement) {
                        countElement.textContent = failed;
                    }
                }
            }
        }
        
    </script>
</body>
</html>