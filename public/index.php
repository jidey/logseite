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
        // SmartWe: testtype format is "dev_x18", "rc_x18", "hf_x18"
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
		$content = trim(file_get_contents($deployFile));
		// SmartWe: keep only the short hash (first 6 characters)
		if ($isSmartWe) {
			$content = substr($content, 0, 6);
		}
		return $content;
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

// Normalize the testType for SmartWe BEFORE any DB query
// SmartWe: rc -> rc_x18, hf -> hf_x18 ,dev -> dev_x18
$isSmartWe = (strpos($product, 'weWebSel') !== false || 
              strpos($product, 'weClient') !== false || 
              strpos($product, 'smartWe') !== false || 
              strpos($product, 'SmartWe') !== false);

if ($isSmartWe) {
    if (stripos($testType, 'hf') !== false) {
        $testType = $LOGG_SMARTWE_HF;  // ex: hf_x18 (voir config/versions_config.php)
    } elseif (stripos($testType, 'rc') !== false) {
        $testType = $LOGG_SMARTWE_RC;  // ex: rc_x18 (voir config/versions_config.php)
    }
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
    // Add @team_sqs at the beginning if it does not exist
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
                $jparam = $testset['JParam'] ?? '';
                // @nightly and @smokeTest: teamtag is always @team_sqs (ignore the scenario teamtags)
                if (stripos($jparam, '@nightly') !== false || stripos($jparam, '@smokeTest') !== false) {
                    $currentTeamTag = '@team_sqs';
                } else {
                    $currentTeamTag = $testset['teamtag'] ?? '';
                    // Normalize empty/0/1 values to an empty tag
                    if (empty($currentTeamTag) || $currentTeamTag === '0' || $currentTeamTag === '1') {
                        $currentTeamTag = '';
                    }
                }
                if ($currentTeamTag !== $teamTag) {
                    return false;
                }
            }
            
            // Filter by errors only (also keeps the tests currently "running")
            if ($errorOnly) {
                $failed = $testset['TearDownFailed'] ?? 0;
                $running = $testset['running'] ?? $testset['Running'] ?? 0;
                // Keep if: has failures OR is currently running
                if ($failed == 0 && $running != 2) {
                    return false;
                }
            }
            
            return true;
        });
        
        // Feature mode: group the TestSets by JJob (like stats.php)
        $isFeature = ($testType === 'we_feat' || $testType === 'web_feat');

        if ($isFeature) {
            // Sort by JJob first, then by date descending inside each job
            usort($testsets, function($a, $b) {
                $cmp = strcmp($a['JJob'] ?? '', $b['JJob'] ?? '');
                if ($cmp !== 0) return $cmp;
                return strtotime($b['RunDate'] ?? 0) - strtotime($a['RunDate'] ?? 0);
            });
        } else {
            // Sort by date descending
            usort($testsets, function($a, $b) {
                return strtotime($b['RunDate'] ?? 0) - strtotime($a['RunDate'] ?? 0);
            });
        }
        
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

// Detect gW Desktop (gWClient)
$isGwDesktop = (strpos($product, 'gWClient') !== false);

// For gW Desktop, force the specific branch list
// (liste centralisée dans config/versions_config.php : $LOGG_GW_DESKTOP_LIST)
if ($isGwDesktop) {
    // Display all these branches, whether they have data or not
    $testTypesForProduct = $LOGG_GW_DESKTOP_LIST;
} else {
    // For the other products (gW Web, etc.), obsolete branches are already
    // excluded upstream: TestLogRepository ne renvoie que les branches dont
    // le statut n'est pas 'retired' dans config/versions_config.php.

    // Add the feature branch (gW Web) if missing
    if (!$isSmartWe && !in_array('web_feat', $testTypesForProduct)) {
        $testTypesForProduct[] = 'web_feat';
    }
}

// For SmartWe, build a simplified label -> real testType mapping
// ($isSmartWe and the $testType normalization are already done above)
if ($isSmartWe) {
    $smartWeMapping = []; // ['dev' => 'dev_x18', 'rc' => 'rc_x18', 'hf' => 'hf_x17']
    foreach ($testTypesForProduct as $tt) {
        if (stripos($tt, 'dev') !== false && !isset($smartWeMapping['dev'])) {
            $smartWeMapping['dev'] = $tt;
        }
    }
    
    $smartWeMapping['hf'] = 'hf_x18';
	$smartWeMapping['rc'] = 'rc_x18';
	$smartWeMapping['feature'] = 'we_feat';
    
    // Reorder: dev, rc, hf, feature
    $ordered = [];
    foreach (['dev', 'rc', 'hf', 'feature'] as $branch) {
        if (isset($smartWeMapping[$branch])) {
            $ordered[$branch] = $smartWeMapping[$branch];
        }
    }
    $smartWeMapping = $ordered;
}

// If no TestType has data, display a message
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
            // Determine the theme before the CSS loads
            const savedTheme = localStorage.getItem('logg-theme');
            let appliedTheme = 'light'; // Default
            
            if (savedTheme === 'dark') {
                appliedTheme = 'dark';
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (savedTheme === 'light') {
                appliedTheme = 'light';
                document.documentElement.setAttribute('data-theme', 'light');
            } else {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    appliedTheme = 'dark';
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    appliedTheme = 'light';
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            }
            
            window.initialTheme = appliedTheme;
        })();
    </script>
    
    <!-- Early Filters Redirect: applies ALL cached filters BEFORE rendering -->
    <script>
        (function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            const hasAnyFilter = urlParams.has('Product') || urlParams.has('Testtype') || 
                                 urlParams.has('TeamTag') || urlParams.has('ErrorOnly');
            
            if (!hasAnyFilter) {
                let prefs = {};
                try {
                    const cached = localStorage.getItem('logg-prefs');
                    if (cached) prefs = JSON.parse(cached);
                } catch (e) {}
                
                let needsRedirect = false;
                
                if (prefs.product) {
                    urlParams.set('Product', prefs.product);
                    needsRedirect = true;
                }
                if (prefs.testtype) {
                    urlParams.set('Testtype', prefs.testtype);
                    needsRedirect = true;
                }
                if (prefs.team_tag) {
                    urlParams.set('TeamTag', prefs.team_tag);
                    needsRedirect = true;
                }
                if (prefs.error_only === 1 || prefs.error_only === '1') {
                    urlParams.set('ErrorOnly', '1');
                    needsRedirect = true;
                }
                
                if (needsRedirect) {
                    window.location.replace(window.location.pathname + '?' + urlParams.toString());
                }
            }
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
                            <h1 style="margin: 0; font-size: 25px;">🧪 Automated Tests - Logs</h1>
                            <?php
                                $deployedBuild = getDeployedBuild($testType, $product);
                                if (!empty($deployedBuild)) {
                                    echo '<div style="font-size: 20px;">';
                                    echo '<strong>Deployed Build:</strong> <span style="color: #28a745; font-weight: bold;">' . htmlspecialchars($deployedBuild) . '</span>';
                                    echo '</div>';
                                    //echo '<script>console.log("📦 Deployed Build: ' . htmlspecialchars($deployedBuild) . '");</script>';
                                } else {
                                    echo '<script>console.log("⚠️ No Deployed Build file found for: ' . htmlspecialchars($testType) . ' / ' . htmlspecialchars($product) . '");</script>';
                                }
                            ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div style="margin-bottom: 15px; display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                            <a href="dash.php" class="btn btn-sm btn-primary" target="_blank" title="Global Dashboard">
                                📊 Release Dashboard
                            </a>
                            <a href="vm_config.php" class="btn btn-sm btn-info" target="_blank" title="VM Nightly Deployments Configuration">
                                ⚙️ Deployments
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
                        <select class="form-select" id="product" name="Product" style="width: 100%;">
                            <?php 
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
                        <select class="form-select" id="testtype" name="Testtype" style="width: 100%;">
                            <?php if ($isSmartWe && !empty($smartWeMapping)): ?>
                                <?php foreach ($smartWeMapping as $label => $realType): ?>
                                    <option value="<?php echo htmlspecialchars($realType); ?>" 
                                        <?php echo $testType === $realType ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php
                                // Branches marquées 'future' dans config/versions_config.php
                                // -> affichées entre parenthèses (pas encore disponibles)
                                $notYetAvailable = $LOGG_FUTURE_TESTTYPES;
                                foreach ($testTypesForProduct as $v):
                                    $displayLabel = in_array($v, $notYetAvailable) ? "($v)" : $v;
                                ?>
                                    <option value="<?php echo htmlspecialchars($v); ?>" 
                                        <?php echo $testType === $v ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($displayLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Browser (hidden - always chrome by default) -->
                    <input type="hidden" name="TestBrowser" value="chrome">

                    <!-- Team Filter -->
                    <div>
                        <label for="teamtag" class="form-label"><strong>Team</strong></label>
                        <select class="form-select" id="teamtag" name="TeamTag" style="width: 100%;">
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
                        <label for="testsetFilter" class="form-label"><strong>Search Testset</strong></label>
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
                               <?php echo !$errorOnly ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-primary" for="errorsOffBtn">
                            All Results
                        </label>
                        
                        <input type="radio" class="btn-check" name="ErrorOnly" id="errorsOnBtn" value="1" 
                               <?php echo $errorOnly ? 'checked' : ''; ?>>
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
							<th style="width: 55px; text-align: center;">DB</th>
                            <th style="width: 45px; text-align: center;" class="sortable" data-sort="passed">Passed <span class="sort-indicator"></span></th>
                            <th style="width: 45px; text-align: center;" class="sortable" data-sort="failed">Failed <span class="sort-indicator"></span></th>
                            <th style="width: 55px; text-align: center;">Testcases</th>
                            <th style="width: 70px; text-align: center;">Log</th>
                            <th style="width: 60px; text-align: center;">Run</th>
                            <th style="width: 55px; text-align: center;">Duration</th>
                            <th style="width: 80px; text-align: center;" class="sortable desc-active" data-sort="date" data-default-sort="desc">Date <span class="sort-indicator desc"></span></th>
                            <th style="width: 280px; text-align: center;" class="sortable" data-sort="notes">Notes <span class="sort-indicator"></span></th>
                            <th style="width: 80px; text-align: center;" class="sortable" data-sort="team">Team <span class="sort-indicator"></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($testsets)): ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">
                                No TestSets found for this selection
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php 
                            $currentJJob = null;
                            foreach ($testsets as $testset): 
                                // Feature mode: insert a header row on each JJob change
                                if ($isFeature && ($testset['JJob'] ?? '') !== $currentJJob):
                                    $currentJJob = $testset['JJob'] ?? '';
                            ?>
                            <tr class="job-group-header">
                                <td colspan="12">
                                    <strong>📁 <?php echo htmlspecialchars($currentJJob); ?></strong>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <!-- TestSet -->
                                <?php
									// Build the link to stats.php
                                    // LogVersion = table name (e.g. dev_x17 + gWWebSel -> x17_dev)
                                    $statsLogVersion = $repo->getTableForTestType($testType, $product);
                                    $statsLink = "stats.php?LogVersion=" . urlencode($statsLogVersion) .
                                                 "&JJob=" . urlencode($testset['JJob']) .
                                                 "&Product=" . urlencode($product) .
                                                 "&TestBrowser=" . urlencode($browser ?? 'chrome') .
                                                 "&Testtype=" . urlencode($testType) .
                                                 "&Filter=no" .
                                                 "&TestProject=" . urlencode($testset['JParam'] ?? '');
                                ?>
                                <td data-sort-value="<?php echo htmlspecialchars($testset['JParam'] ?? 'N/A'); ?>">
                                    <div class="testset-name">
                                        <a href="<?php echo htmlspecialchars($statsLink); ?>" title="View statistics for this TestSet">
                                            <?php echo htmlspecialchars($testset['JParam'] ?? 'N/A'); ?>
                                        </a>
                                    </div>
                                </td>
                                
                                <!-- Tested version -->
                                <?php
                                    $testedBuild = trim($testset['Build'] ?? '');
                                    $deployedBuild = getDeployedBuild($testType, $product);
                                    
                                    $isSmartWe = (strpos($product, 'weWebSel') !== false || strpos($product, 'smartWe') !== false || strpos($product, 'SmartWe') !== false);
                                    //echo '<script>console.log("🔧 [' . htmlspecialchars($product) . ' / ' . htmlspecialchars($testType) . '] Deployed Build: " + ((' . json_encode($deployedBuild) . ') ? "✅ Found" : "❌ NULL"));</script>';
                                    
                                    $formatTestedVersion = "gray"; // Default
                                    $isMatch = false;
                                    
                                    if (!empty($testedBuild) && !empty($deployedBuild)) {
                                        $deployedBuildTrimmed = trim($deployedBuild);
                                        
                                        if ($isSmartWe) {
                                            if (preg_match('/#([a-f0-9]+)/', $testedBuild, $matches)) {
                                                $testedHash = $matches[1];
                                                $isMatch = (strpos($deployedBuildTrimmed, $testedHash) === 0);
                                            }
                                        } else {
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
                                <!-- DB Server -->
                                <td style="text-align: center;">
                                    <small><?php 
                                        $db = trim($testset['DBServer'] ?? '');
                                        echo htmlspecialchars($db !== '' ? $db : 'SQL');
                                    ?></small>
                                </td>
                                <!-- Passed (including Flaky) -->
                                <td class="table-number status-passed" data-sort-value="<?php echo ($testset['TearDownPassed'] + $testset['TearDownWarning']) ?? 0; ?>">
                                    <?php echo ($testset['TearDownPassed'] + $testset['TearDownWarning']) ?? 0; ?>
                                </td>
                                
                                <!-- Failed -->
                                <td class="table-number status-failed" data-sort-value="<?php echo $testset['TearDownFailed'] ?? 0; ?>">
                                    <?php echo $testset['TearDownFailed'] ?? 0; ?>
                                </td>
                                
                                <!-- Testcases (Details) -->
                                <td align='center'>
                                    <a href="details.php?Testtype=<?php echo urlencode($testType); ?>&Product=<?php echo urlencode($product); ?>&AutoID=<?php echo urlencode($testset['AutoID']); ?>&OnlyFailed=<?php echo $errorOnly ? '1' : '0'; ?>" 
                                       class="btn btn-sm btn-primary" title="View Testcases details">
                                        Details
                                    </a>
                                </td>
                                
								<!-- Log -->
                                <td class="text-center">
                                    <?php 
                                    $logLink = $testset['LogLink'] ?? null;
                                    
                                    if (!empty($logLink)) {
                                        echo '<a href="' . htmlspecialchars($logLink) . '" target="_blank" class="btn btn-sm btn-info" title="View Allure report">';
                                        echo 'Allure';
                                        echo '</a>';
                                    } else {
                                        echo '<button type="button" class="btn btn-sm btn-secondary" disabled title="No log available">';
                                        echo 'N/A';
                                        echo '</button>';
                                    }
                                    ?>
                                </td>
								
                                <!-- Run -->
                                <td class="text-center">
                                    <?php 
                                    $returnUrl = "https://sqs-sel-cent1.cas-software.dev/logg/public/index.php?" .
                                                 "Product=" . urlencode($product) . 
                                                 "&Testtype=" . urlencode($testType) . 
                                                 "&TestBrowser=" . urlencode($browser ?? 'chrome') .
                                                 "&ErrorOnly=" . ($errorOnly ? '1' : '0');
                                    
                                    // Use the latest deployed version as the Build for the rerun
                                    // (instead of the build of the last run). Fallback to 'Last' if not found.
                                    $deployedBuildForRun = getDeployedBuild($testType, $product);
                                    $buildForRun = !empty($deployedBuildForRun) ? trim($deployedBuildForRun) : ($testset['Build'] ?? 'Last');
									
									// SmartWe: rebuild the Build as "we {BRANCH} #{short hash}"
                                    // e.g. deployed = ecd727eb6105... + branch dev  ->  "we DEV #ecd727"
                                    if ($isSmartWe && !empty($deployedBuildForRun)) {
                                        $shortHash = substr(trim($deployedBuildForRun), 0, 6);
                                        // Extract the branch from the testType (dev_x18 -> dev)
                                        $weBranch = strtoupper(explode('_', $testType)[0]); // DEV / RC / HF
                                        $buildForRun = "we " . $weBranch . " #" . $shortHash;
                                    }
                                    $runLink = "rerun.php?JJob=" . urlencode($testset['JJob']) . 
                                               "&JParam=" . urlencode($testset['JParam']) . 
                                               "&Testset=" . urlencode($testset['JParam']) . 
                                               "&Build=" . urlencode($buildForRun) . 
                                               "&AutoID=" . urlencode($testset['AutoID']) . 
                                               "&Testtype=" . urlencode($testType) . 
                                               "&LogVersion=" . urlencode($testType) . 
                                               "&TestBrowser=" . urlencode($browser ?? 'chrome') . 
                                               "&Product=" . urlencode($product) .
                                               "&url=" . urlencode($returnUrl);
                                    
                                    $runningStatus = $testset['running'] ?? $testset['Running'] ?? 0;
                                    if ($runningStatus == 2) {
                                        echo '<button type="button" class="btn btn-sm btn-warning reset-running-btn" ' .
                                             'data-jjob="' . htmlspecialchars($testset['JJob']) . '" ' .
                                             'data-jparam="' . htmlspecialchars($testset['JParam']) . '" ' .
                                             'data-testtype="' . htmlspecialchars($testType) . '" ' .
                                             'data-product="' . htmlspecialchars($product) . '" ' .
                                             'data-autoid="' . htmlspecialchars($testset['AutoID']) . '" ' .
                                             'title="Test is running - Click to reset">';
                                        echo '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Running';
                                        echo '</button>';
                                    } else {
                                        echo '<a href="' . htmlspecialchars($runLink) . '" target="_self" ' .
                                             'class="btn btn-sm btn-success run-link" ' .
                                             'data-jjob="' . htmlspecialchars($testset['JJob']) . '" ' .
                                             'data-jparam="' . htmlspecialchars($testset['JParam']) . '" ' .
                                             'data-testtype="' . htmlspecialchars($testType) . '" ' .
                                             'data-product="' . htmlspecialchars($product) . '" ' .
                                             'title="Run this TestSet">';
                                        echo 'Run';
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
                                            if (is_numeric($duration)) {
                                                $hours = intval($duration / 3600);
                                                $minutes = intval(($duration % 3600) / 60);
                                                echo sprintf("%dh %02dm", $hours, $minutes);
                                            } else {
                                                echo htmlspecialchars($duration);
                                            }
                                        } else {
                                            echo "-";
                                        }
                                        ?>
                                    </small>
                                </td>
                                
                                <!-- Date -->
                                <td data-sort-value="<?php echo !empty($testset['RunDate']) ? strtotime($testset['RunDate']) : 0; ?>">
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
                                        $jparam = $testset['JParam'] ?? '';
                                        // For @nightly and @smokeTest: always @team_sqs
                                        if (stripos($jparam, '@nightly') !== false || stripos($jparam, '@smokeTest') !== false) {
                                            echo '@team_sqs';
                                        } elseif (empty($teamtag) || $teamtag === '0' || $teamtag === '1') {
                                            // Empty/0/1: leave blank
                                            echo '';
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
        document.addEventListener('DOMContentLoaded', function() {
            if (window.initialTheme === 'dark') {
                document.body.classList.add('dark-mode');
                document.body.classList.remove('light-mode');
            } else {
                document.body.classList.add('light-mode');
                document.body.classList.remove('dark-mode');
            }
            
            updateThemeButton();
            
			// Enable transitions AFTER the first paint (avoids the load flash)
			requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    document.body.classList.add('theme-ready');
                });
            });

            // CENTRALIZED FILTER HANDLING
            ['product', 'testtype', 'teamtag'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('change', function() {
                        savePreferences();
                        this.form.submit();
                    });
                }
            });
            
            const errorsOffBtn = document.getElementById('errorsOffBtn');
            const errorsOnBtn = document.getElementById('errorsOnBtn');
            
            if (errorsOffBtn) {
                errorsOffBtn.addEventListener('change', function() {
                    if (this.checked) {
                        localStorage.setItem('logg-error-only', '0');
                        savePreferences();
                        this.form.submit();
                    }
                });
            }
            
            if (errorsOnBtn) {
                errorsOnBtn.addEventListener('change', function() {
                    if (this.checked) {
                        localStorage.setItem('logg-error-only', '1');
                        savePreferences();
                        this.form.submit();
                    }
                });
            }
            
            const testsetInput = document.getElementById('testsetFilter');
            if (testsetInput) {
                testsetInput.addEventListener('input', function() {
                    savePreferences();
                });
            }
            
            const clearBtn = document.getElementById('clearTestsetFilter');
            if (clearBtn) {
                clearBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const testsetInput = document.getElementById('testsetFilter');
                    testsetInput.value = '';
                    savePreferences();
                    testsetInput.form.submit();
                });
            }
        });

        // Save preferences to local cache
        function savePreferences() {
			const browserEl = document.getElementById('browser');
			const prefs = {
				theme: document.body.classList.contains('dark-mode') ? 'dark' : 'light',
				text_size: parseInt(textSizeSlider.value),
				error_only: document.getElementById('errorsOnBtn').checked ? 1 : 0,
				product: document.getElementById('product').value,
				testtype: document.getElementById('testtype').value,
				test_browser: browserEl ? browserEl.value : 'chrome',
				team_tag: document.getElementById('teamtag').value
			};
			
			localStorage.setItem('logg-prefs', JSON.stringify(prefs));
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
                // Switch to light mode
                document.body.classList.remove('dark-mode');
                document.body.classList.add('light-mode');
                document.documentElement.setAttribute('data-theme', 'light');
                localStorage.setItem('logg-theme', 'light');
            } else {
                // Switch to dark mode
                document.body.classList.add('dark-mode');
                document.body.classList.remove('light-mode');
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('logg-theme', 'dark');
            }            
            updateThemeButton();
            savePreferences();
        }
        
        // Apply text size changes
        function applyTextSize(percentage) {
            const baseFontSize = 14;
            const newFontSize = (baseFontSize * percentage) / 100;
            
            let styleTag = document.getElementById('text-size-style');
            if (!styleTag) {
                styleTag = document.createElement('style');
                styleTag.id = 'text-size-style';
                document.head.appendChild(styleTag);
            }
            
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
        
        const textSizeSlider = document.getElementById('textSizeSlider');
        
        // Load user preferences from local cache on page load
        function loadUserPreferences() {
            const cached = localStorage.getItem('logg-prefs');
            const prefs = cached ? JSON.parse(cached) : {};
            const urlParams = new URLSearchParams(window.location.search);
            // Apply theme - read logg-theme (key shared across all pages)
            const theme = localStorage.getItem('logg-theme') || prefs.theme || 'light';
			
            if (theme === 'dark') {
                document.body.classList.add('dark-mode');
                document.body.classList.remove('light-mode');
            } else {
                document.body.classList.remove('dark-mode');
                document.body.classList.add('light-mode');
            }
            updateThemeButton();
            
            if (textSizeSlider) {
                const textSize = prefs.text_size || 100;
                textSizeSlider.value = textSize;
                applyTextSize(parseInt(textSize));
            }
            
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
            
            if (!urlParams.has('ErrorOnly')) {
                if (prefs.error_only === 1 && errorsOnBtn) {
                    errorsOnBtn.checked = true;
                } else if (errorsOffBtn) {
                    errorsOffBtn.checked = true;
                }
            }
        }
        
        loadUserPreferences();
        
        const filterForm = document.querySelector('form');
        if (filterForm) {
            filterForm.addEventListener('submit', function() {
                savePreferences();
            });
        }
        
        document.querySelectorAll('.reset-running-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const jJob = this.dataset.jjob;
                const jParam = this.dataset.jparam;
                const testType = this.dataset.testtype;
                const product = this.dataset.product;
                const btnElement = this;
                
                btnElement.style.opacity = '0.5';
                btnElement.style.pointerEvents = 'none';
                
                fetch('check.php?value=0&field=running' +
                      '&autoid=' + encodeURIComponent(this.dataset.autoid || '') +
                      '&LogVersion=' + encodeURIComponent(testType) +
                      '&Testtype=' + encodeURIComponent(testType) + 
                      '&Product=' + encodeURIComponent(product) +
                      '&JJob=' + encodeURIComponent(jJob) +
                      '&JParam=' + encodeURIComponent(jParam))
                    .then(response => response.json())
                    .then(data => {
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
                        alert('Error resetting running status');
                    });
            });
        });
        
        document.querySelectorAll('.run-link').forEach(link => {
            link.addEventListener('click', function(e) {
                this.classList.remove('btn-success');
                this.classList.add('btn-warning');
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Running';
                this.title = 'Test is running';
                this.style.pointerEvents = 'none';
            });
        });
        
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