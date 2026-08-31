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

// Get parameters
$testType = $_GET['Testtype'] ?? 'rc_x17';
$product = $_GET['Product'] ?? 'gWWebSel';
$autoID = $_GET['AutoID'] ?? null;
$browser = $_GET['TestBrowser'] ?? 'chrome';
$onlyFailed = isset($_GET['OnlyFailed']) && $_GET['OnlyFailed'] === '1';  // Show only failed scenarios

// Normalize the testType for SmartWe (consistent with index.php)
// SmartWe: rc -> rc_x18, hf -> hf_x18, dev -> dev_x18
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
              ORDER BY AutoID DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':jjob', $testset['JJob'], PDO::PARAM_STR);
    $stmt->bindValue(':jparam', $testset['JParam'], PDO::PARAM_STR);
	$stmt->execute();
    
    // Group scenarios by ScenarioName (TCProj) and keep latest
    $scenarioMap = [];
    $allScenarios = $stmt->fetchAll();
    
    // Debug: check the available columns
    if (!empty($allScenarios)) {
        error_log("Available columns: " . implode(', ', array_keys($allScenarios[0])));
    }
    
    foreach ($allScenarios as $scenario) {
        $scenarioName = $scenario['TCProj'] ?? 'Unknown'; // Using TCProj as Scenario name key
        
        // Normalize the columns - look up teamtag regardless of case
        $normalizedScenario = [];
        foreach ($scenario as $key => $value) {
            $lowerKey = strtolower($key);
            $normalizedScenario[$lowerKey] = $value;
        }
        // Also keep the original keys for compatibility
        $normalizedScenario = array_merge($scenario, $normalizedScenario);
        
        // Keep only latest execution of each scenario (highest AutoID = most recent)
        if (!isset($scenarioMap[$scenarioName]) || ($scenario['AutoID'] ?? 0) > ($scenarioMap[$scenarioName]['AutoID'] ?? 0)) {
            $scenarioMap[$scenarioName] = $normalizedScenario;
        }
    }
    
    // Convert to array and sort by date
    $scenarios = array_values($scenarioMap);

    // Recalculate Passed / Flaky / Failed from the real scenarios (BEFORE any filter)
    // Rule: a validated scenario (checked=1) counts as Passed.
    //       otherwise -> Failed if TearDownFailed>0, Flaky if TearDownWarning>0, Passed otherwise.
    $calcPassed = 0;
    $calcFlaky  = 0;
    $calcFailed = 0;
    foreach ($scenarios as $sc) {
        if (!empty($sc['checked'])) {
            $calcPassed++;                       // validated => Passed
        } elseif (($sc['TearDownFailed'] ?? 0) > 0) {
            $calcFailed++;
        } elseif (($sc['TearDownWarning'] ?? 0) > 0) {
            $calcFlaky++;                        // Flaky stays Flaky and is counted
        } else {
            $calcPassed++;
        }
    }
    // Inject into $testset for the .testset-info display
    $testset['TearDownPassed']  = $calcPassed;
    $testset['TearDownWarning'] = $calcFlaky;
    $testset['TearDownFailed']  = $calcFailed;

    // Apply filter: only failed scenarios (after computing the totals)
    if ($onlyFailed) {
        $scenarios = array_filter($scenarios, function($scenario) {
            return ($scenario['TearDownFailed'] ?? 0) > 0;
        });
    }
	
	// Persist the recalculated totals into the Main (TestSet) row in the database
    // ONLY when all scenarios are present (no Failed Only filter)
    if (!empty($allScenarios) && !empty($testset['AutoID'])) {
        try {
            $updTestset = $pdo->prepare("
                UPDATE `$tableName`
                SET `TearDownPassed`  = :passed,
                    `TearDownWarning` = :flaky,
                    `TearDownFailed`  = :failed
                WHERE AutoID = :autoID
            ");
            $updTestset->execute([
                ':passed' => $calcPassed,
                ':flaky'  => $calcFlaky,
                ':failed' => $calcFailed,
                ':autoID' => $testset['AutoID'],
            ]);
        } catch (Exception $e) {
            error_log("details.php update testset stats error: " . $e->getMessage());
        }
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
            // Determine the theme before the CSS loads
            const savedTheme = localStorage.getItem('logg-theme');
            let appliedTheme = 'light'; // Default
            
            if (savedTheme === 'dark') {
                // Dark mode saved
                appliedTheme = 'dark';
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (savedTheme === 'light') {
                // Light mode saved
                appliedTheme = 'light';
                document.documentElement.setAttribute('data-theme', 'light');
            } else {
                // No saved preference, use the system preference
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    appliedTheme = 'dark';
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    appliedTheme = 'light';
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            }
            
            // Store the applied theme for use after the DOM has loaded
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
        <?php endif; 

		// Build used for the reruns: latest deployed version (fallback on the build of the run)
		$deployedBuild = getDeployedBuild($testType, $product);
		$buildForRun = !empty($deployedBuild) ? trim($deployedBuild) : ($testset['Build'] ?? 'Last');
		// SmartWe: reformat as "we {BRANCH} #{short hash}"
		if ($isSmartWe && !empty($deployedBuild)) {
			$weBranch = strtoupper(explode('_', $testType)[0]); // DEV / RC / HF
			$buildForRun = "we " . $weBranch . " #" . $deployedBuild; // already truncated to 6 chars
		}
		?>

        <!-- Scenarios Table -->
        <h4 style="margin-bottom: 20px;">
            Scenarios (<?php echo count($scenarios); ?>)
            <?php
            // Count the failed scenarios and prepare their rerun URLs
            $failedRerunUrls = [];
            foreach ($scenarios as $sc) {
                if (($sc['TearDownFailed'] ?? 0) > 0) {
                    $failedRerunUrls[] = "rerun.php?" .
                        "JJob="        . urlencode($testset['JJob'] ?? 'CI') .
                        "&JParam="     . urlencode($testset['JParam'] ?? '') .
                        "&Testset="    . urlencode($testset['JParam'] ?? '') .
                        "&AutoID="     . urlencode($sc['AutoID']) .
                        "&TCProj="     . urlencode($sc['TCProj'] ?? '') .
                        "&Build="      . urlencode($buildForRun) .
                        "&Testtype="   . urlencode($testType) .
                        "&LogVersion=" . urlencode($testType) .
                        "&Product="    . urlencode($product) .
                        "&TestBrowser=". urlencode($browser ?? 'chrome') .
                        "&localrun=localrun" .
                        "&retry=retry" .
                        "&Confirm=1";
                }
            }
            if (!empty($failedRerunUrls)):
            ?>
            <button type="button" id="runAllFailedBtn"
                    class="btn btn-sm btn-danger"
                    style="margin-left: 15px; vertical-align: middle;"
                    onclick='runAllFailed(<?php echo json_encode($failedRerunUrls); ?>)'>
                ▶ Run All Failed (<?php echo count($failedRerunUrls); ?>)
            </button>
            <?php endif; ?>
        </h4>
        
        <div class="results-table">
            <div style="overflow-x: auto;">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 100px;" class="sortable" data-sort="featuretag">FeatureTag <span class="sort-indicator"></span></th>
                            <th style="width: 100px;" class="sortable" data-sort="teamtag">TeamTag <span class="sort-indicator"></span></th>
                            <th style="width: 200px;">Scenario Name</th>
                            <th style="width: 100px;">Tested Build</th>
							<th style="width: 60px;">DB</th>
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
                            <td colspan="13" class="text-center text-muted py-4">
                                No scenarios found for this TestSet
                            </td>
                        </tr>
                        <?php else:							
							foreach ($scenarios as $scenario): ?>
                            <tr data-original-result="<?php echo ($scenario['TearDownFailed'] > 0) ? 'Failed' : (($scenario['TearDownWarning'] > 0) ? 'Flaky' : 'Passed');?>" data-autoid="<?php echo $scenario['AutoID']; ?>" data-failed="<?php echo $scenario['TearDownFailed'] ?? 0; ?>" data-warning="<?php echo $scenario['TearDownWarning'] ?? 0; ?>">
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
                                
								<!-- DB Server -->
                                <td>
                                    <small><?php 
                                        $db = trim($scenario['DBServer'] ?? '');
                                        echo htmlspecialchars($db !== '' ? $db : 'SQL');
                                    ?></small>
                                </td>
								
                                <!-- Result -->
                                <td class="result-cell" data-sort-value="<?php 
                                    // Value used for sorting
                                    if ($scenario['checked'] ?? 0) {
                                        echo 'passed';
                                    } elseif ($scenario['TearDownFailed'] > 0) {
                                        echo 'failed';
                                    } else {
                                        echo 'passed';
                                    }
                                ?>">
                                    <?php 
                                    // If Validated is checked (checked = 1), display Passed
                                    if ($scenario['checked'] ?? 0) {
                                        echo '<span class="result-badge result-passed">✅ Passed</span>';
                                    } else {
                                        // Otherwise, display the real result
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
									// Build the rerun link with all the parameters
                                    $triggerLink = "rerun.php?" .
                                                   "JJob=" . urlencode($testset['JJob'] ?? 'CI') .
                                                   "&JParam=" . urlencode($testset['JParam'] ?? '') .
                                                   "&Testset=" . urlencode($testset['JParam'] ?? '') .
                                                   "&AutoID=" . urlencode($scenario['AutoID']) .
                                                   "&TCProj=" . urlencode($scenario['TCProj'] ?? '') .
                                                   "&Build=" . urlencode($buildForRun) .
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
                                        <!-- Test running - clickable button to reset it -->
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
                                        <!-- Test ready to be re-run -->
                                        <a href="<?php echo htmlspecialchars($triggerLink); ?>" 
                                           target="_self" 
                                           title="Run this scenario"
                                           class="btn btn-sm btn-success trigger-link"
                                           data-jjob="<?php echo htmlspecialchars($testset['JJob'] ?? 'CI'); ?>"
                                           data-jparam="<?php echo htmlspecialchars($scenario['JParam'] ?? ''); ?>"
                                           data-testtype="<?php echo htmlspecialchars($testType); ?>"
                                           data-product="<?php echo htmlspecialchars($product); ?>">
                                            Run
                                        </a>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Log -->
                                <td class="text-center">
                                    <?php 
                                    $logLink = $scenario['LogLink'] ?? null;
                                    
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
		// Trigger a run for every failed scenario
        function runAllFailed(urls) {
            if (!urls || urls.length === 0) return;

            const btn = document.getElementById('runAllFailedBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Starting...';
            }

            // Trigger each rerun in the background (fetch, no navigation)
            let done = 0;
            const promises = urls.map(url =>
                fetch(url, { method: 'GET', mode: 'no-cors' })
                    .then(() => { done++; })
                    .catch(err => { console.error('Rerun failed:', url, err); })
            );

            Promise.all(promises).then(() => {
                if (btn) {
                    btn.innerHTML = '✅ ' + done + ' triggered';
                }
                // Reload the page after a short delay to see the "Running" statuses
                setTimeout(() => { window.location.reload(); }, 1500);
            });
        }
		
        // Compute and adjust the sticky positions
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
                    // Do not add a top offset to the thead for details.php
                    // const filterHeight = filters ? filters.offsetHeight : 0;
                    // const statsHeight = stats ? stats.offsetHeight : 0;
                    // thead.style.top = (headerHeight + filterHeight + statsHeight) + 'px';
                }
            }
        }
        
        // Run on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Apply the theme classes based on the initial theme
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

            setTimeout(updateStickyPositions, 100);
            
            // Restore the "All Scenarios / Failed Only" preference from localStorage
            const savedErrorOnly = localStorage.getItem('logg-error-only');
            if (savedErrorOnly !== null) {
                const btnId = savedErrorOnly === '1' ? 'onlyFailedBtn' : 'allResultsBtn';
                const targetBtn = document.getElementById(btnId);
                
                if (targetBtn) {
                    const isCurrentlyChecked = targetBtn.checked;
                    targetBtn.checked = true;
                    
                    // If the saved value differs from the currently checked one, submit the form
                    if (!isCurrentlyChecked) {
                        targetBtn.form.submit();
                    }
                }
            }
            
            // Save the choice in localStorage when it changes
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
            
            // Handle the click on the Run buttons to turn them into "Running"
            document.querySelectorAll('.trigger-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    // Do not prevent navigation, just transform the button
                    this.classList.remove('btn-success');
                    this.classList.add('btn-warning');
                    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Running';
                    this.title = 'Test is running';
                    this.style.pointerEvents = 'none';
                });
            });
        });
        
        // Handle the click on the "Running" button - global function called by the inline onclick
        function resetScenarioRunning(autoID, testType, product, btnElement) {
            console.log('resetScenarioRunning called - AutoID:', autoID, 'TestType:', testType, 'Product:', product);
            
            // Visual feedback
            btnElement.style.opacity = '0.5';
            btnElement.style.pointerEvents = 'none';
            
            // Call check.php to set running back to 0
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
        
        // Recompute on resize
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
        
        // Text size slider - declare it before loadUserPreferences
        const textSizeSlider = document.getElementById('textSizeSlider');
        
        // Load user preferences from local cache on page load
        function loadUserPreferences() {
            const cached = localStorage.getItem('logg-prefs');
            const prefs = cached ? JSON.parse(cached) : {};
            
            // Apply theme - read logg-theme (key shared across all pages)
            const theme = localStorage.getItem('logg-theme') || prefs.theme || 'light';
            if (theme === 'dark') {
                document.body.classList.add('dark-mode');
                document.body.classList.remove('light-mode');
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.body.classList.remove('dark-mode');
                document.body.classList.add('light-mode');
                document.documentElement.setAttribute('data-theme', 'light');
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
            // Preserve the existing preferences (product/testtype/team_tag are managed by index.php)
            let prefs = {};
            try {
                const cached = localStorage.getItem('logg-prefs');
                if (cached) prefs = JSON.parse(cached);
            } catch (e) {}
            // Only update what details.php controls
            prefs.theme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
            prefs.text_size = textSizeSlider ? parseInt(textSizeSlider.value) : 100;
            localStorage.setItem('logg-prefs', JSON.stringify(prefs));
        }
        
        // Load preferences on page load
        loadUserPreferences();
        
        // Text size slider (textSizeSlider already declared above)
        if (textSizeSlider) {
            // Handle slider input
            textSizeSlider.addEventListener('input', function() {
                const size = this.value;
                applyTextSize(parseInt(size));
                savePreferences();
            });
        }
        
        // Update the validation of a scenario and refresh the totals
        function updateScenarioValidation(autoID, isChecked, testType, product) {
            const row = document.querySelector('tr[data-autoid="' + autoID + '"]');
            if (!row) { console.error('Row not found:', autoID); return; }

            const resultCell = row.querySelector('.result-cell');
            const origin = row.getAttribute('data-original-result') || 'Passed';

            // Deltas: validated => Passed
            let dPassed = 0, dFlaky = 0, dFailed = 0;
            if (origin === 'Failed') { dPassed = 1; dFailed = -1; }
            else if (origin === 'Flaky') { dPassed = 1; dFlaky = -1; }

            if (resultCell) {
                if (isChecked) {
                    resultCell.innerHTML = '<span class="result-badge result-passed">✅ Passed</span>';
                    updateCounters(dPassed, dFlaky, dFailed);
                } else {
                    if (origin === 'Failed')      resultCell.innerHTML = '<span class="result-badge result-failed">❌ Failed</span>';
                    else if (origin === 'Flaky')  resultCell.innerHTML = '<span class="result-badge result-flaky">⚠️ Flaky</span>';
                    else                          resultCell.innerHTML = '<span class="result-badge result-passed">✅ Passed</span>';
                    updateCounters(-dPassed, -dFlaky, -dFailed);
                }
            }

            fetch('update_validation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'AutoID=' + encodeURIComponent(autoID) +
                      '&Checked=' + (isChecked ? 1 : 0) +
                      '&TestType=' + encodeURIComponent(testType) +
                      '&Product=' + encodeURIComponent(product)
            })
            .then(r => r.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) { updateTestSetStats(testType, product); }
                    else { console.error('Server error:', data.error); alert('Error: ' + (data.error || '')); }
                } catch (e) { console.error('JSON parse:', e, text); alert('Server format error'); }
            })
            .catch(err => { console.error('fetch:', err); alert('Network error: ' + err.message); });
        }
		
		// Update the Passed/Flaky/Failed counters displayed in .testset-info
		function updateCounters(passedDelta, flakyDelta, failedDelta) {
            const info = document.querySelector('.testset-info');
            if (!info) return;
            const p = info.querySelector('span.status-passed');
            const f = info.querySelector('span.status-flaky');
            const x = info.querySelector('span.status-failed');
            if (p) p.textContent = (parseInt(p.textContent.replace(/\D/g,'')) || 0) + passedDelta;
            if (f) f.textContent = (parseInt(f.textContent.replace(/\D/g,'')) || 0) + flakyDelta;
            if (x) x.textContent = (parseInt(x.textContent.replace(/\D/g,'')) || 0) + failedDelta;
        }
		
        // Persist the TestSet totals in the database
        function updateTestSetStats(testType, product) {
            const urlParams = new URLSearchParams(window.location.search);
            const autoID = urlParams.get('AutoID');
            if (!autoID) return;
            if (!testType) testType = urlParams.get('Testtype') || '';
            if (!product)  product  = urlParams.get('Product')  || '';

            const info = document.querySelector('.testset-info');
            if (!info) return;
            const passed = parseInt((info.querySelector('span.status-passed')?.textContent || '0').replace(/\D/g,'')) || 0;
            const flaky  = parseInt((info.querySelector('span.status-flaky')?.textContent  || '0').replace(/\D/g,'')) || 0;
            const failed = parseInt((info.querySelector('span.status-failed')?.textContent || '0').replace(/\D/g,'')) || 0;

            fetch('update_testset_stats.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'AutoID=' + encodeURIComponent(autoID) +
                      '&Passed=' + encodeURIComponent(passed) +
                      '&Flaky=' + encodeURIComponent(flaky) +
                      '&Failed=' + encodeURIComponent(failed) +
                      '&TestType=' + encodeURIComponent(testType) +
                      '&Product=' + encodeURIComponent(product)
            }).then(r => r.text()).then(t => console.log('stats:', t)).catch(e => console.error(e));
        }
        
    </script>
</body>
</html>