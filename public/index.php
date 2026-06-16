<?php
/**
 * MAIN PAGE - TEST LOGS
 * Display one line per DISTINCT TestSet
 * Filters: Product, Test Type, Browser, TestSet Name, Errors Only
 * 
 * Structure : TestSet | Version | Passed | Flaky | Failed | Testcases | Log | Run | Duration | Date | Notes
 */

// Load configuration and Repository
require_once '../config/config.php';
require_once '../src/TestLogRepository.php';

/**
 * Build URL for pagination with all current filters
 */
function buildPaginationUrl($pageNum, $product, $testType, $browser, $testsetFilter, $teamTag, $errorOnly) {
    $params = [
        'Product' => $product,
        'Testtype' => $testType,
        'TestBrowser' => $browser,
        'page' => $pageNum
    ];
    
    if (!empty($testsetFilter)) {
        $params['TestsetFilter'] = $testsetFilter;
    }
    
    if (!empty($teamTag)) {
        $params['TeamTag'] = $teamTag;
    }
    
    if ($errorOnly) {
        $params['ErrorOnly'] = '1';
    }
    
    return 'index.php?' . http_build_query($params);
}

/**
 * Get the deployed build from deployedVM folder
 */
function getDeployedBuild($testType, $product) {
    $deployFile = null;
    
    // Detect product type
    $isSmartWe = (strpos($product, 'weWebSel') !== false || strpos($product, 'weClient') !== false || 
                  strpos($product, 'smartWe') !== false || strpos($product, 'SmartWe') !== false);
    
    if ($isSmartWe) {
        // SmartWe: testtype format is "dev_x17", "rc_x17", "hf_x17"
        // Deploy file format: lastWewercDeploy.txt (lastWe + we + rc)
        $parts = explode("_", $testType);
        if (count($parts) == 2) {
            $branch = $parts[0]; // dev, rc, hf
            $suffix = "we" . strtolower($branch); // wedev, werc, wehf
            $deployFile = __DIR__ . "/deployedVM/lastWe{$suffix}Deploy.txt";
        }
    } else {
        // gWWebSel: testtype format is "rc_x17", "dev_x17", "hf_x17"
        // Deploy file format: lastSelrc17Deploy.txt (branch + version)
        $parts = explode("_", $testType);
        if (count($parts) == 2) {
            $branch = $parts[0];
            $version = str_replace("x", "", $parts[1]);
            $suffix = $branch . $version;
            $deployFile = __DIR__ . "/deployedVM/lastSel{$suffix}Deploy.txt";
        }
    }
    
    // Read the deployed build file
    if ($deployFile && file_exists($deployFile)) {
        return trim(file_get_contents($deployFile));
    }
    
    return null;
}

// Get and validate GET parameters
$product = $_GET['Product'] ?? 'gWWebSel';
$testType = $_GET['Testtype'] ?? 'rc_x17';
$browser = $_GET['TestBrowser'] ?? 'chrome';
$testsetFilter = $_GET['TestsetFilter'] ?? '';  // Filter by TestSet name
$teamTag = $_GET['TeamTag'] ?? '';  // Filter by Team tag
$errorOnly = isset($_GET['ErrorOnly']) && $_GET['ErrorOnly'] === '1';  // Show only errors

// Normaliser le testType pour SmartWe AVANT toute requête BD
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

// Pagination parameters
$perPage = 50;  // Number of TestSets per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Create a Repository instance
$repo = new TestLogRepository($pdo);

// Get unique team tags for filter dropdown
$uniqueTeamTags = [];
try {
    $tableName = $repo->getTableForTestType($testType, $product);
    $query = "SELECT DISTINCT teamtag FROM `$tableName` WHERE teamtag IS NOT NULL AND teamtag != '' AND teamtag != '0' AND teamtag != '1' ORDER BY teamtag ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // Ajouter @team_sqs au début s'il n'existe pas
    $uniqueTeamTags = array_filter($result);
    if (!in_array('@team_sqs', $uniqueTeamTags)) {
        array_unshift($uniqueTeamTags, '@team_sqs');
    }
} catch (Exception $e) {
    error_log("Error fetching team tags: " . $e->getMessage());
    $uniqueTeamTags = ['@team_sqs'];
}

// Get data
$error = null;
$testsets = []; // Array to store distinct TestSets

try {
    // Get ALL jobs for this version/product
    $jobs = $repo->getJobsByTestType($testType, $product);
    
    if (empty($jobs)) {
        $error = "No jobs found for this version and product";
        $totalTestsets = 0;
        $totalPages = 0;
    } else {
        // For each job, get latest executions
        foreach ($jobs as $job) {
            $jobName = $job['JJob'];
            
            // Get latest executions (reduced from 500 to 100 for better performance)
            $runs = $repo->getLatestRunsForJob(
                $testType,
                $jobName,
                $product,
                $browser,
                100  // Reduced from 500 for pagination
            );
            
            if (!empty($runs)) {
                // For each execution, create a TestSet line
                foreach ($runs as $run) {
                    // Unique key for TestSet (Job + JParam = unique TestSet)
                    $testsetKey = $run['JJob'] . '|' . $run['JParam'];
                    
                    // Keep only latest execution of each TestSet
                    if (!isset($testsets[$testsetKey]) || strtotime($run['RunDate'] ?? 0) > strtotime($testsets[$testsetKey]['RunDate'] ?? 0)) {
                        $testsets[$testsetKey] = $run;
                    }
                }
            }
        }
        
        // Apply filters
        $testsets = array_filter($testsets, function($testset) use ($testsetFilter, $teamTag, $errorOnly) {
            // Filter by TestSet name
            if (!empty($testsetFilter)) {
                $jparam = $testset['JParam'] ?? '';
                if (stripos($jparam, $testsetFilter) === false) {
                    return false;
                }
            }
            
            // Filter by Team tag
            if (!empty($teamTag)) {
                $currentTeamTag = $testset['teamtag'] ?? '';
                // Normaliser les valeurs vides/0/1 à @team_sqs
                if (empty($currentTeamTag) || $currentTeamTag === '0' || $currentTeamTag === '1') {
                    $currentTeamTag = '@team_sqs';
                }
                if ($currentTeamTag !== $teamTag) {
                    return false;
                }
            }
            
            // Filter by errors only
            if ($errorOnly) {
                $failed = $testset['TearDownFailed'] ?? 0;
                if ($failed === 0) {
                    return false;
                }
            }
            
            return true;
        });
        
        // Sort by date descending
        usort($testsets, function($a, $b) {
            return strtotime($b['RunDate'] ?? 0) - strtotime($a['RunDate'] ?? 0);
        });
        
        // Apply pagination
        $totalTestsets = count($testsets);
        $totalPages = ceil($totalTestsets / $perPage);
        $offset = ($page - 1) * $perPage;
        $testsets = array_slice($testsets, $offset, $perPage);
    }
    
} catch (Exception $e) {
    $error = "Error: " . htmlspecialchars($e->getMessage());
    error_log("Error in index.php: " . $e->getMessage());
}

// Calculate stats from TestSets
$stats = [
    'total_testsets' => count($testsets),
    'total_tested' => 0,
    'total_passed' => 0,
    'total_warning' => 0,
    'total_failed' => 0,
];

// Iterate through all TestSets to calculate totals
foreach ($testsets as $testset) {
    $stats['total_tested'] += ($testset['TearDownPassed'] ?? 0) + ($testset['TearDownFailed'] ?? 0) + ($testset['TearDownWarning'] ?? 0);
    $stats['total_passed'] += ($testset['TearDownPassed'] ?? 0);
    $stats['total_warning'] += ($testset['TearDownWarning'] ?? 0);
    $stats['total_failed'] += ($testset['TearDownFailed'] ?? 0);
}

// Get other info from DB
$versions = $repo->getAvailableVersions();
$productsForVersion = $repo->getProductsForVersion($testType);

// Get all available products
$allProducts = [];
foreach ($versions as $v) {
    $prods = $repo->getProductsForVersion($v);
    foreach ($prods as $p) {
        if (!in_array($p, $allProducts)) {
            $allProducts[] = $p;
        }
    }
}

// Get test types for current product (based on actual data)
$testTypesForProduct = $repo->getAvailableTestTypesForProduct($product);

// Pour SmartWe, créer un mapping label simplifié → testType réel
// ($isSmartWe et la normalisation de $testType sont déjà faits plus haut)
if ($isSmartWe) {
    $smartWeMapping = []; // ['dev' => 'dev_x18', 'rc' => 'rc_x17', 'hf' => 'hf_x17']
    foreach ($testTypesForProduct as $tt) {
        if (stripos($tt, 'dev') !== false && !isset($smartWeMapping['dev'])) {
            $smartWeMapping['dev'] = $tt;
        }
    }
    // Forcer rc_x17 et hf_x17
    $smartWeMapping['rc'] = 'rc_x17';
    $smartWeMapping['hf'] = 'hf_x17';
    
    // Réordonner: dev, rc, hf
    $ordered = [];
    foreach (['dev', 'rc', 'hf'] as $branch) {
        if (isset($smartWeMapping[$branch])) {
            $ordered[$branch] = $smartWeMapping[$branch];
        }
    }
    $smartWeMapping = $ordered;
}

// Si aucun TestType n'a de données, afficher un message
if (empty($testTypesForProduct)) {
    $error = "No data available for product: " . htmlspecialchars($product);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs - Automated Tests</title>
    
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
        
        <!-- Sticky Header Container -->
        <div class="sticky-header-container">
            <!-- Header -->
            <div class="header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <h1 style="margin: 0;">🧪 Automated Tests - Logs</h1>
                            <?php
                                $deployedBuild = getDeployedBuild($testType, $product);
                                if (!empty($deployedBuild)) {
                                    echo '<div style="font-size: 14px;">';
                                    echo '<strong>Deployed Build:</strong> <span style="color: #28a745; font-weight: bold;">' . htmlspecialchars($deployedBuild) . '</span>';
                                    echo '</div>';
                                    echo '<script>console.log("📦 Deployed Build: ' . htmlspecialchars($deployedBuild) . '");</script>';
                                } else {
                                    echo '<script>console.log("⚠️ No Deployed Build file found for: ' . htmlspecialchars($testType) . ' / ' . htmlspecialchars($product) . '");</script>';
                                }
                            ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div style="margin-bottom: 15px; display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                            <a href="dash.php" class="btn btn-sm btn-primary" target="_blank" title="Global Dashboard">
                                📊 Dashboard
                            </a>
                            <a href="vm_config.php" class="btn btn-sm btn-info" target="_blank" title="VM Nightly Update Configuration">
                                ⚙️ Configuration
                            </a>
                            <a href="cache_management.php" class="btn btn-sm btn-secondary" title="Cache Management">
                                💾 Cache
                            </a>
                            <button id="themeToggle" class="btn btn-sm btn-outline-secondary" title="Toggle Dark/Light Mode" onclick="toggleTheme()">
                                🌙 Dark Mode
                            </button>
                            
                            <!-- Text Size Slider -->
                            <input type="range" id="textSizeSlider" min="80" max="150" value="100" style="width: 70px; cursor: pointer; height: 5px;" title="Adjust text size">
                        </div>
    
                    </div>
                </div>
            </div>

            <!-- Error display -->
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>❌ Error:</strong> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" class="filter-row">
                    <!-- Product FIRST -->
                    <div>
                        <label for="product" class="form-label"><strong>Product</strong></label>
                        <select class="form-select" id="product" name="Product" onchange="this.form.submit()" style="width: 100%;">
                            <?php 
                            // Mapping des noms affichés pour les produits
                            $productMapping = [
                                'gWWebSel' => 'gW Web',
                                'weWebSel' => 'smartWe',
                                'gWClient' => 'gW Desktop'
                            ];
                            
                            foreach ($allProducts as $p): 
                                $displayName = $productMapping[$p] ?? $p;
                            ?>
                                <option value="<?php echo htmlspecialchars($p); ?>" 
                                    <?php echo $product === $p ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($displayName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Branch SECOND -->
                    <div>
                        <label for="testtype" class="form-label"><strong>Branch</strong></label>
                        <select class="form-select" id="testtype" name="Testtype" onchange="this.form.submit()" style="width: 100%;">
                            <?php if ($isSmartWe && !empty($smartWeMapping)): ?>
                                <?php foreach ($smartWeMapping as $label => $realType): ?>
                                    <option value="<?php echo htmlspecialchars($realType); ?>" 
                                        <?php echo $testType === $realType ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($testTypesForProduct as $v): ?>
                                    <option value="<?php echo htmlspecialchars($v); ?>" 
                                        <?php echo $testType === $v ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($v); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Browser -->
                    <div>
                        <label for="browser" class="form-label"><strong>Browser</strong></label>
                        <select class="form-select" id="browser" name="TestBrowser" onchange="this.form.submit()" style="width: 100%;">
                            <option value="chrome" <?php echo $browser === 'chrome' ? 'selected' : ''; ?>>Chrome</option>
                            <option value="firefox" <?php echo $browser === 'firefox' ? 'selected' : ''; ?>>Firefox</option>
                        </select>
                    </div>

                    <!-- Team Filter -->
                    <div>
                        <label for="teamtag" class="form-label"><strong>Team</strong></label>
                        <select class="form-select" id="teamtag" name="TeamTag" onchange="this.form.submit()" style="width: 100%;">
                            <option value="">All Teams</option>
                            <?php foreach ($uniqueTeamTags as $tag): ?>
                                <option value="<?php echo htmlspecialchars($tag); ?>" <?php echo $teamTag === $tag ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tag); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- TestSet Name -->
                    <div>
                        <label for="testsetFilter" class="form-label"><strong>TestSet Name</strong></label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <input type="text" class="form-control" id="testsetFilter" name="TestsetFilter" 
                               value="<?php echo htmlspecialchars($testsetFilter); ?>" 
                               placeholder="Search..." 
                               style="width: 100%;">
                            <?php if (!empty($testsetFilter)): ?>
                                <button type="button" class="btn btn-sm" id="clearTestsetFilter" 
                                        title="Clear TestSet Name filter"
                                        style="position: absolute; right: 10px; border: none; background: transparent; color: #999; font-size: 20px; padding: 0 5px; cursor: pointer; line-height: 1;">
                                    ✕
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- Errors Only Toggle Button -->
                <div class="filter-group">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="ErrorOnly" id="errorsOffBtn" value="0" 
                               <?php echo !$errorOnly ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <label class="btn btn-outline-primary" for="errorsOffBtn">
                            All Results
                        </label>
                        
                        <input type="radio" class="btn-check" name="ErrorOnly" id="errorsOnBtn" value="1" 
                               <?php echo $errorOnly ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <label class="btn btn-outline-danger" for="errorsOnBtn">
                            ❌ Errors Only
                        </label>
                    </div>
                </div>

            </form>
        </div>

        <!-- Statistics -->
        <?php if (!empty($stats)): ?>
        <div class="stats">
            <div class="stat-card stat-total" style="background: #495057 !important; color: white !important;">
                <div class="stat-label" style="color: white !important;">Total TestSets</div>
                <div class="stat-value" style="color: white !important;"><?php echo $stats['total_testsets']; ?></div>
            </div>
            <div class="stat-card stat-total" style="background: #495057 !important; color: white !important;">
                <div class="stat-label" style="color: white !important;">Total Executions</div>
                <div class="stat-value" style="color: white !important;"><?php echo $stats['total_tested']; ?></div>
            </div>
            <div class="stat-card stat-passed" style="background: #28a745 !important; color: white !important;">
                <div class="stat-label" style="color: white !important;">Passed</div>
                <div class="stat-value" style="color: white !important;"><?php echo ($stats['total_passed'] + $stats['total_warning']); ?></div>
            </div>
            <div class="stat-card stat-failed" style="background: #dc3545 !important; color: white !important;">
                <div class="stat-label" style="color: white !important;">Failed</div>
                <div class="stat-value" style="color: white !important;"><?php echo $stats['total_failed']; ?></div>
            </div>
        </div>
        <?php endif; ?>
        </div>
        <!-- End Sticky Header Container -->

        <!-- Results Table -->
        <div class="results-table">
            <div style="overflow-x: auto;">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 200px; text-align: center;" class="sortable" data-sort="testset">TestSet <span class="sort-indicator"></span></th>
                            <th style="width: 80px; text-align: center;" class="sortable" data-sort="version">Tested version <span class="sort-indicator"></span></th>
                            <th style="width: 45px; text-align: center;" class="sortable" data-sort="passed">Passed <span class="sort-indicator"></span></th>
                            <th style="width: 45px; text-align: center;" class="sortable" data-sort="failed">Failed <span class="sort-indicator"></span></th>
                            <th style="width: 55px; text-align: center;">Testcases</th>
                            <th style="width: 70px; text-align: center;">Log</th>
                            <th style="width: 60px; text-align: center;">Run</th>
                            <th style="width: 55px; text-align: center;">Duration</th>
                            <th style="width: 80px; text-align: center;" class="sortable" data-sort="date">Date <span class="sort-indicator"></span></th>
                            <th style="width: 280px; text-align: center;" class="sortable" data-sort="notes">Notes <span class="sort-indicator"></span></th>
                            <th style="width: 80px; text-align: center;" class="sortable" data-sort="team">Team <span class="sort-indicator"></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($testsets)): ?>
                        <tr>
                            <td colspan="13" class="text-center text-muted py-4">
                                No TestSets found for this selection
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($testsets as $testset): ?>
                            <tr>
                                <!-- TestSet -->
                                <td data-sort-value="<?php echo htmlspecialchars($testset['JParam'] ?? 'N/A'); ?>">
                                    <div class="testset-name">
                                        <?php echo htmlspecialchars($testset['JParam'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                
                                <!-- Tested version -->
                                <?php
                                    $testedBuild = trim($testset['Build'] ?? '');
                                    $deployedBuild = getDeployedBuild($testType, $product);
                                    
                                    // DEBUG: Simple logs
                                    $isSmartWe = (strpos($product, 'weWebSel') !== false || strpos($product, 'smartWe') !== false || strpos($product, 'SmartWe') !== false);
                                    echo '<script>console.log("🔧 [' . htmlspecialchars($product) . ' / ' . htmlspecialchars($testType) . '] Deployed Build: " + ((' . json_encode($deployedBuild) . ') ? "✅ Found" : "❌ NULL"));</script>';
                                    
                                    $formatTestedVersion = "gray"; // Default
                                    $isMatch = false;
                                    
                                    if (!empty($testedBuild) && !empty($deployedBuild)) {
                                        $deployedBuildTrimmed = trim($deployedBuild);
                                        
                                        if ($isSmartWe) {
                                            // Extract short hash from tested version (format: "we DEV #1470a9")
                                            if (preg_match('/#([a-f0-9]+)/', $testedBuild, $matches)) {
                                                $testedHash = $matches[1]; // 1470a9
                                                // Compare short hash with beginning of full hash
                                                $isMatch = (strpos($deployedBuildTrimmed, $testedHash) === 0);
                                            }
                                        } else {
                                            // For gWWebSel: exact comparison
                                            $isMatch = ($testedBuild === $deployedBuildTrimmed);
                                        }
                                        
                                        if ($isMatch) {
                                            $formatTestedVersion = "green";
                                        } else {
                                            $formatTestedVersion = "orange";
                                        }
                                    }
                                ?>
                                <td data-sort-value="<?php echo htmlspecialchars($testedBuild); ?>">
                                    <small><b><span style='color: <?php echo $formatTestedVersion; ?>;'><?php echo !empty($testedBuild) ? htmlspecialchars($testedBuild) : '-'; ?></span></b></small>
                                </td>
                                
                                <!-- Passed (incluant Flaky) -->
                                <td class="table-number status-passed" data-sort-value="<?php echo ($testset['TearDownPassed'] + $testset['TearDownWarning']) ?? 0; ?>">
                                    <?php echo ($testset['TearDownPassed'] + $testset['TearDownWarning']) ?? 0; ?>
                                </td>
                                
                                <!-- Failed -->
                                <td class="table-number status-failed" data-sort-value="<?php echo $testset['TearDownFailed'] ?? 0; ?>">
                                    <?php echo $testset['TearDownFailed'] ?? 0; ?>
                                </td>
                                
                                <!-- Details -->
                                <td align='center'>
                                    <a href="details.php?Testtype=<?php echo urlencode($testType); ?>&Product=<?php echo urlencode($product); ?>&AutoID=<?php echo urlencode($testset['AutoID']); ?>&OnlyFailed=<?php echo $errorOnly ? '1' : '0'; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View Testcases details">
                                        🔍 Details
                                    </a>
                                </td>
                                
								<!-- Log -->
                                <td>
                                    <?php 
                                    $logLink = $testset['LogLink'] ?? null;
                                    
                                    if (!empty($logLink)) {
                                        echo '<a href="' . htmlspecialchars($logLink) . '" target="_blank" class="btn btn-sm btn-outline-info" title="View log">';
                                        echo '📋 Allure';
                                        echo '</a>';
                                    } else {
                                        echo '<button class="btn btn-sm btn-outline-info" disabled title="No log available">';
                                        echo '📋';
                                        echo '</button>';
                                    }
                                    ?>
                                </td>
								
                                <!-- Run -->
                                <td class="text-center">
                                    <?php 
                                    // Construire l'URL de retour vers index.php avec les filtres actuels
                                    $returnUrl = "https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?" .
                                                 "Product=" . urlencode($product) . 
                                                 "&Testtype=" . urlencode($testType) . 
                                                 "&TestBrowser=" . urlencode($browser ?? 'chrome') .
                                                 "&ErrorOnly=" . ($errorOnly ? '1' : '0');
                                    
                                    // Lien vers rerun.php
                                    $runLink = "rerun.php?JJob=" . urlencode($testset['JJob']) . 
                                               "&JParam=" . urlencode($testset['JParam']) . 
                                               "&Testset=" . urlencode($testset['JParam']) . 
                                               "&Build=" . urlencode($testset['Build'] ?? 'Last') . 
                                               "&AutoID=" . urlencode($testset['AutoID']) . 
                                               "&Testtype=" . urlencode($testType) . 
                                               "&LogVersion=" . urlencode($testType) . 
                                               "&TestBrowser=" . urlencode($browser ?? 'chrome') . 
                                               "&Product=" . urlencode($product) .
                                               "&url=" . urlencode($returnUrl);
                                    
                                    $runningStatus = $testset['running'] ?? $testset['Running'] ?? 0;
                                    if ($runningStatus == 2) {
                                        // Image cliquable pour réinitialiser le statut "running"
                                        echo '<img src="running.jpg" alt="Running - Click to reset" ' .
                                             'style="width:24px;height:24px;border:0;cursor:pointer;" ' .
                                             'class="reset-running-btn" ' .
                                             'data-jjob="' . htmlspecialchars($testset['JJob']) . '" ' .
                                             'data-jparam="' . htmlspecialchars($testset['JParam']) . '" ' .
                                             'data-testtype="' . htmlspecialchars($testType) . '" ' .
                                             'data-product="' . htmlspecialchars($product) . '" ' .
                                             'title="Click to reset running status">';
                                    } else {
                                        echo '<a href="' . htmlspecialchars($runLink) . '" target="_self" class="run-link" ' .
                                             'data-jjob="' . htmlspecialchars($testset['JJob']) . '" ' .
                                             'data-jparam="' . htmlspecialchars($testset['JParam']) . '" ' .
                                             'data-testtype="' . htmlspecialchars($testType) . '" ' .
                                             'data-product="' . htmlspecialchars($product) . '">';
                                        echo '<img src="clock.png" alt="" style="width:24px;height:24px;border:0; cursor:pointer;" class="run-icon">';
                                        echo '</a>';
                                    }
                                    ?>
                                </td>
                                
                                <!-- Duration -->
                                <td class="duration">
                                    <small>
                                        <?php 
                                        $duration = $testset['RunDuration'] ?? null;
                                        
                                        if (!empty($duration)) {
                                            // Convertir la durée en heures et minutes
                                            // Supposer que RunDuration est en secondes ou en format HH:MM:SS
                                            if (is_numeric($duration)) {
                                                // Si c'est en secondes
                                                $hours = intval($duration / 3600);
                                                $minutes = intval(($duration % 3600) / 60);
                                                echo sprintf("%dh %02dm", $hours, $minutes);
                                            } else {
                                                // Si c'est déjà au format HH:MM:SS
                                                echo htmlspecialchars($duration);
                                            }
                                        } else {
                                            echo "-";
                                        }
                                        ?>
                                    </small>
                                </td>
                                
                                <!-- Date -->
                                <td>
                                    <small>
                                        <?php 
                                        if (!empty($testset['RunDate'])) {
                                            echo date('d/m/Y H:i', strtotime($testset['RunDate']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </small>
                                </td>
                                
                                <!-- Notes -->
                                <?php 
                                // Récupérer les notes depuis la table _tags
                                $testSetNotes = $repo->getTestSetNotes($testType, $testset['JJob'], $testset['JParam'], $product);
                                ?>
                                <td class="notes notes-cell" 
                                    data-autoid="<?php echo htmlspecialchars($testset['AutoID']); ?>"
                                    data-testtype="<?php echo htmlspecialchars($testType); ?>"
                                    data-product="<?php echo htmlspecialchars($product); ?>"
                                    title="<?php echo !empty($testSetNotes) ? htmlspecialchars($testSetNotes) : 'Double-click to edit notes'; ?>">
                                    <small>
                                        <?php 
                                        if (!empty($testSetNotes)) {
                                            echo htmlspecialchars($testSetNotes);
                                        }
                                        ?>
                                    </small>
                                </td>
                                
                                <!-- Team -->
                                <td style="text-align: center;">
                                    <small><?php 
                                        $teamtag = $testset['teamtag'] ?? '';
                                        // Si vide, 0, 1, ou autre valeur invalide, afficher @team_sqs
                                        if (empty($teamtag) || $teamtag === '0' || $teamtag === '1') {
                                            echo '@team_sqs';
                                        } else {
                                            echo htmlspecialchars($teamtag);
                                        }
                                    ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Pagination" style="margin-top: 20px;">
                <ul class="pagination justify-content-center mb-0">
                    <!-- Previous Button -->
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildPaginationUrl(1, $product, $testType, $browser, $testsetFilter, $teamTag, $errorOnly); ?>" tabindex="-1">
                            « First
                        </a>
                    </li>
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildPaginationUrl($page - 1, $product, $testType, $browser, $testsetFilter, $teamTag, $errorOnly); ?>" tabindex="-1">
                            ‹ Previous
                        </a>
                    </li>
                    
                    <!-- Page Numbers (show max 5 pages) -->
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    if ($start > 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for ($i = $start; $i <= $end; $i++) {
                        $active = ($i === $page) ? 'active' : '';
                        echo "<li class=\"page-item $active\">";
                        echo '<a class="page-link" href="' . buildPaginationUrl($i, $product, $testType, $browser, $testsetFilter, $teamTag, $errorOnly) . '">' . $i . '</a>';
                        echo '</li>';
                    }
                    
                    if ($end < $totalPages) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    ?>
                    
                    <!-- Next Button -->
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildPaginationUrl($page + 1, $product, $testType, $browser, $testsetFilter, $teamTag, $errorOnly); ?>">
                            Next ›
                        </a>
                    </li>
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildPaginationUrl($totalPages, $product, $testType, $browser, $testsetFilter, $teamTag, $errorOnly); ?>">
                            Last »
                        </a>
                    </li>
                </ul>
            </nav>
            
            <!-- Page Info -->
            <div class="text-center mt-3">
                <small class="text-muted">
                    Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong> 
                    | Showing <strong><?php echo min($perPage, count($testsets)); ?></strong> of <strong><?php echo $totalTestsets; ?></strong> TestSets
                </small>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- LOGG App JS -->
    <script src="js/app.js" defer></script>

    <!-- Theme Toggle Script -->
    <script>
        // Mettre à jour le bouton au chargement
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
            
            // Restaurer la préférence "All Results / Errors Only" depuis localStorage
            const savedErrorOnly = localStorage.getItem('logg-error-only');
            if (savedErrorOnly !== null) {
                const errorOnlyBtn = document.getElementById(savedErrorOnly === '1' ? 'errorsOnBtn' : 'errorsOffBtn');
                if (errorOnlyBtn) {
                    const isCurrentlyChecked = errorOnlyBtn.checked;
                    errorOnlyBtn.checked = true;
                    
                    // Si la valeur sauvegardée est différente de celle actuellement cochée, soumettre le formulaire
                    if (!isCurrentlyChecked) {
                        errorOnlyBtn.form.submit();
                    }
                }
            }
            
            // Sauvegarder le choix dans localStorage quand on change
            const errorsOffBtn = document.getElementById('errorsOffBtn');
            const errorsOnBtn = document.getElementById('errorsOnBtn');
            
            if (errorsOffBtn) {
                errorsOffBtn.addEventListener('change', function() {
                    if (this.checked) {
                        localStorage.setItem('logg-error-only', '0');
                    }
                });
            }
            
            if (errorsOnBtn) {
                errorsOnBtn.addEventListener('change', function() {
                    if (this.checked) {
                        localStorage.setItem('logg-error-only', '1');
                    }
                });
            }
        });
            
            // Gérer le bouton de suppression du filtre Testset Name
            const clearBtn = document.getElementById('clearTestsetFilter');
            if (clearBtn) {
                clearBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const testsetInput = document.getElementById('testsetFilter');
                    testsetInput.value = '';
                    testsetInput.form.submit();
                });
            }

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
            } else {
                // Passer en mode sombre
                document.body.classList.add('dark-mode');
                document.body.classList.remove('light-mode');
            }
            
            updateThemeButton();
            savePreferences();
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
            
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
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
            
            // Apply filter values - if GET params are not set, use saved preferences
            const productSelect = document.getElementById('product');
            const testtypeSelect = document.getElementById('testtype');
            const browserSelect = document.getElementById('browser');
            const teamtagSelect = document.getElementById('teamtag');
            const errorsOnBtn = document.getElementById('errorsOnBtn');
            const errorsOffBtn = document.getElementById('errorsOffBtn');
            
            if (!urlParams.has('Product') && productSelect && prefs.product) {
                productSelect.value = prefs.product;
            }
            if (!urlParams.has('Testtype') && testtypeSelect && prefs.testtype) {
                testtypeSelect.value = prefs.testtype;
            }
            if (!urlParams.has('TestBrowser') && browserSelect && prefs.test_browser) {
                browserSelect.value = prefs.test_browser;
            }
            if (!urlParams.has('TeamTag') && teamtagSelect && prefs.team_tag) {
                teamtagSelect.value = prefs.team_tag;
            }
            
            // Apply ErrorOnly radio buttons
            if (!urlParams.has('ErrorOnly')) {
                if (prefs.error_only === 1 && errorsOnBtn) {
                    errorsOnBtn.checked = true;
                } else if (errorsOffBtn) {
                    errorsOffBtn.checked = true;
                }
            }
        }
        
        // Save preferences to local cache
        function savePreferences() {
            const prefs = {
                theme: document.body.classList.contains('dark-mode') ? 'dark' : 'light',
                text_size: parseInt(textSizeSlider.value),
                error_only: document.getElementById('errorsOnBtn').checked ? 1 : 0,
                product: document.getElementById('product').value,
                testtype: document.getElementById('testtype').value,
                test_browser: document.getElementById('browser').value,
                team_tag: document.getElementById('teamtag').value
            };
            
            localStorage.setItem('logg-prefs', JSON.stringify(prefs));
        }
        
        // Load preferences on page load
        loadUserPreferences();
        
        // Save preferences on form submit
        const filterForm = document.querySelector('form');
        if (filterForm) {
            filterForm.addEventListener('submit', function() {
                setTimeout(savePreferences, 100);
            });
        }
        
        // Gérer le clic sur l'icône "running" pour la réinitialiser
        document.querySelectorAll('.reset-running-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const jJob = this.dataset.jjob;
                const jParam = this.dataset.jparam;
                const testType = this.dataset.testtype;
                const product = this.dataset.product;
                const imgElement = this;
                
                // Feedback visuel - changement temporaire de couleur
                imgElement.style.opacity = '0.5';
                
                // Appel AJAX pour réinitialiser le statut
                fetch('reset_running.php?JJob=' + encodeURIComponent(jJob) + 
                      '&JParam=' + encodeURIComponent(jParam) + 
                      '&Testtype=' + encodeURIComponent(testType) + 
                      '&Product=' + encodeURIComponent(product))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Feedback: flash vert
                            imgElement.style.opacity = '1';
                            imgElement.style.filter = 'brightness(0.8)';
                            setTimeout(() => {
                                imgElement.style.filter = 'brightness(1)';
                                // Rafraîchir la page après 300ms
                                setTimeout(() => {
                                    location.reload();
                                }, 300);
                            }, 300);
                        } else {
                            imgElement.style.opacity = '1';
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        imgElement.style.opacity = '1';
                        alert('Error resetting running status');
                    });
            });
        });
        
        // Gérer le clic sur les liens Run pour mettre à jour l'icône en running
        // Note: check.php (appelé par rerun.php via $runn) met running=2 côté serveur
        document.querySelectorAll('.run-link').forEach(link => {
            link.addEventListener('click', function(e) {
                // Ne pas empêcher la navigation, juste mettre à jour l'icône
                const img = this.querySelector('.run-icon');
                if (img) {
                    // Changer l'icône en running
                    img.src = 'running.jpg';
                    img.alt = 'Running';
                    img.title = 'Test is running';
                    
                    // Feedback visuel - flash
                    img.style.opacity = '0.6';
                    setTimeout(() => {
                        img.style.opacity = '1';
                        img.style.transition = 'opacity 0.3s ease';
                    }, 100);
                }
            });
        });
        
        // Text size slider event listener
        if (textSizeSlider) {
            textSizeSlider.addEventListener('input', function() {
                const size = this.value;
                applyTextSize(parseInt(size));
                savePreferences();
            });
        }
    </script>
</body>
</html>