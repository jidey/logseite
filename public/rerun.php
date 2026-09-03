<?php
/**
 * RERUN.PHP
 * Confirmation form used to re-run tests
 * Modernized version with Bootstrap 5 and LOGG styles
 */

// Central configuration file for versions/branches (no need for the
// DB connection here, so only versions_config.php is loaded)
require_once __DIR__ . '/../config/versions_config.php';

// Read the GET parameters
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
// Batch mode: multiple TCProj separated by "|||" (from details.php "Run All Failed")
$TCProjList = isset($_GET['TCProjList']) ? explode('|||', $_GET['TCProjList']) : [];
$isBatch = !empty($TCProjList) && $_GET['TCProjList'] !== '';
// AutoID of each failed scenario, aligned by index with $TCProjList
$AutoIDList = isset($_GET['AutoIDList']) ? explode('|||', $_GET['AutoIDList']) : [];
$TestName = $_GET['TestName'] ?? "";
// An unchecked checkbox sends NOTHING in the query string.
// The "checked by default" value must therefore only apply on the initial load,
// not when the form comes back with Confirm/Abort.
$formSubmitted = isset($_GET['Confirm']) || isset($_GET['Abort']);
$localrun = $_GET['localrun'] ?? ($formSubmitted ? 'no' : 'localrun');  // ✅ Checked by default
$parallel = $_GET['parallel'] ?? 'no';                                  // ✅ Unchecked by default
$retry    = $_GET['retry']    ?? ($formSubmitted ? 'no' : 'retry');     // ✅ Checked by default
$DBServer = $_GET['DBServer'] ?? "SQL";  // SQL by default (SQL | PGS)
// Debug mode: when &DryRun=1 is present, ConfirmAndRun DISPLAYS the URLs
// instead of sending them to Jenkins/check.php (nothing is executed).
$dryRun = 0; //1 = active DryRun
// Determine the calling page (index.php or details.php)
// If TCProj is present, the call comes from details.php (ReRun Testcase)
// Otherwise, it comes from index.php (Run complete TestSet)
$pageTitle = $isBatch ? "ReRun All Failed (" . count($TCProjList) . ")" : (!empty($TCProj) ? "ReRun Testcase" : "Run complete TestSet");

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

// Helper converting the testType into the correct LogVersion format
// Examples: "dev_x17" -> "x17_dev", "rc_x17" -> "x17_rc"
function formatLogVersion($input) {
    if (empty($input)) {
        return "x17_dev";
    }
    
    // If it already has the right shape (contains "_"), check and fix it if needed
    if (strpos($input, '_') !== false) {
        $parts = explode('_', $input);
        if (count($parts) === 2) {
            // Check whether it is {version}_{branch} (good) or {branch}_{version} (bad)
            if (preg_match('/^x\d+/', $parts[0])) {
                // Good format: x17_dev
                return $input;
            } else if (preg_match('/^x\d+/', $parts[1])) {
                // Bad format: dev_x17, swap the parts
                return $parts[1] . '_' . $parts[0];
            }
        }
    }
    
    return $input;
}

// Determine the LogVersion when it is not provided
if (empty($LogVersion)) {
    if (!empty($Testtype)) {
        $LogVersion = formatLogVersion($Testtype);
    } else {
        $LogVersion = "x17_dev";
    }
} else {
    // LogVersion is provided, make sure it has the right format
    $LogVersion = formatLogVersion($LogVersion);
}

// Jenkins configuration
$JJobJenkins = "SQS_Web_TestPipe";
$ForDebug = 'false';

// Build the test URL
if ($Testset == $JParam && empty($TCProj)) {
	$JParam = "@dummy";
    $test = "https://build-sqs.cas-software.dev/view/gWWeb/job/" . $JJobJenkins . 
            "/buildWithParameters?token=TCAUTO&delay=4sec&TestName=" . $JParam . 
            "&Testset=" . $Testset . "&DebugFeature=" . $ForDebug;
} else {
	if (!empty($TCProj)) {
		 $TCProjOutline = explode(" outline", $TCProj);
        $TCProj = $TCProjOutline[0];
		$test = "https://build-sqs.cas-software.dev/view/gWWeb/job/" . $JJobJenkins . 
            "/buildWithParameters?token=TCAUTO&delay=4sec&TestName=" . urlencode($TCProj) . 
            "&Testset=" . $Testset . "&DebugFeature=" . $ForDebug;
    }
	else {
		$test = "https://build-sqs.cas-software.dev/view/gWWeb/job/" . $JJobJenkins . 
            "/buildWithParameters?token=TCAUTO&delay=4sec&TestName=@dummy&Testset=" . $Testset . "&DebugFeature=" . $ForDebug;		
	}
    
}

// Append the Product
if ($Product == "weWebSel") {
    $test = $test . "&Product=We";
} elseif ($Product == "gWWebSel") {
    $test = $test . "&Product=Web";
} else {
    $test = $test . "&Product=error";
}

// Build the test check URL
// check.php resolves the table from Testtype + Product (handles SmartWe -> we_rc)
// check.php lives locally in logg/public/
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'sqs-sel-cent1.cas-software.dev';
$basePath = dirname($_SERVER['PHP_SELF'] ?? '/logg/public/rerun.php');
$runn = $protocol . "://" . $host . $basePath . "/check.php?value=2" .
        "&autoid=" . urlencode($AutoID) .
        "&LogVersion=" . urlencode($LogVersion) .
        "&Testtype=" . urlencode($Testtype) .
        "&Product=" . urlencode($Product);

$execute = $formSubmitted;

// Handle the actions
if ($execute) {
    if (isset($_GET['Confirm'])) {
        // Determine the test node depending on the version.
        // Each version has its own parameter (Test_Node for smartWe,
        // Test_x17, Test_x18, ... for gW Web/Desktop). The parameter name
        // is dynamically derived from the version number present in
        // $LogVersion (e.g. "x19_dev" -> "Test_x19"): no change is
        // needed here when a new version is added, see runsystems.php
        // which generates the corresponding field.
        $Test_x = 'Grid'; // Default

        if (substr($LogVersion, 0, 2) == "we") {
            $Test_x = $_GET['Test_Node'] ?? 'Grid';
        } elseif (preg_match('/x\d+/', $LogVersion, $verMatch)) {
            $Test_x = $_GET['Test_' . $verMatch[0]] ?? 'Grid';
        }

		if ($isBatch) {
			// Batch: run the same parameters for every failed TCProj
			foreach ($TCProjList as $idx => $oneTCProj) {
				$oneTCProj = explode(" outline", $oneTCProj)[0];
				// Rebuild the test URL for this specific scenario
				$testOne = "https://build-sqs.cas-software.dev/view/gWWeb/job/" . $JJobJenkins .
						   "/buildWithParameters?token=TCAUTO&delay=4sec&TestName=" . urlencode($oneTCProj) .
						   "&Testset=" . $Testset . "&DebugFeature=" . $ForDebug;
				if ($Product == "weWebSel")      $testOne .= "&Product=We";
				elseif ($Product == "gWWebSel")  $testOne .= "&Product=Web";
				else                             $testOne .= "&Product=error";

				// Rebuild the check.php URL for THIS scenario (its own AutoID)
				// so each failed scenario gets running=2 in the details table.
				$oneAutoID = $AutoIDList[$idx] ?? '';
				$runnOne = $protocol . "://" . $host . $basePath . "/check.php?value=2" .
						   "&autoid=" . urlencode($oneAutoID) .
						   "&LogVersion=" . urlencode($LogVersion) .
						   "&Testtype=" . urlencode($Testtype) .
						   "&Product=" . urlencode($Product);

				ConfirmAndRun($testOne, $runnOne, $logurl, $Testtype, $Test_x, $LogVersion, $TestBrowser, $JJob, $Hub, $ForDebug,
							 $localrun, $parallel, $Build, $retry, $DBServer, false, $dryRun); // false = don't redirect yet
			}
			//header("Location: " . $logurl);
			exit;
		} else {
		    ConfirmAndRun($test, $runn, $logurl, $Testtype, $Test_x, $LogVersion, $TestBrowser, $JJob, $Hub, $ForDebug,
		                 $localrun, $parallel, $Build, $retry, $DBServer, true, $dryRun);
		}			 
    } elseif (isset($_GET['Abort'])) {
        header("Location: " . $logurl);
        exit;
    }
} else {
    // Display the confirmation form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Theme Init Script -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('logg-theme');
            let isDark;
            if (savedTheme === 'dark') {
                isDark = true;
            } else if (savedTheme === 'light') {
                isDark = false;
            } else {
                isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            }
            // Set data-theme on <html> (it already exists, so no flash)
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            window.initialTheme = isDark ? 'dark' : 'light';
            // Apply the class on body as soon as it is available
            document.addEventListener('DOMContentLoaded', function() {
                if (isDark) {
                    document.body.classList.add('dark-mode');
                    document.body.classList.remove('light-mode');
                } else {
                    document.body.classList.add('light-mode');
                    document.body.classList.remove('dark-mode');
                }
                if (typeof updateThemeButton === 'function') updateThemeButton();
            });
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
            
			/* Dark variables applied early via data-theme (prevents the flash) */
			html[data-theme="dark"] {
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
            html[data-theme="dark"] body {
                background: var(--bg-primary) !important;
                color: var(--text-primary) !important;
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
            }
            
            /* Transitions only kick in after the first paint (no load flash) */
            body.theme-ready {
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
            .rerun-container { max-width: 700px; margin: 40px auto; }
            .rerun-card { 
                background: var(--bg-secondary); 
                border-radius: 8px; 
                box-shadow: var(--card-shadow);
                padding: 30px;
            }
            .rerun-card h2 { 
                color: #007bff; 
                margin-bottom: 30px;
                font-size: 1.8rem;
            }
            .rerun-info {
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
            
            /* Light green when checked */
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
        <!-- Header with the Theme Toggle -->
        <div class="rerun-container">
            <div class="rerun-card">
                <h2>🔄 <?php echo $pageTitle; ?></h2>
                
                <div class="rerun-info">
                    <div><strong>Test Type:</strong> <?php echo htmlspecialchars($Testtype); ?></div>
                    <div><strong>TestSet:</strong> <?php echo htmlspecialchars($Testset); ?></div>
                    <div><strong>Build:</strong> <?php echo htmlspecialchars($Build); ?></div>
                    <div><strong>Product:</strong> <?php echo htmlspecialchars($Product); ?></div>
                    <div><strong>Browser:</strong> <?php echo htmlspecialchars($TestBrowser); ?></div>
					<?php if ($isBatch): ?>
                        <div><strong>Scenarios:</strong> <?php echo count($TCProjList); ?> failed test(s)</div>
                    <?php elseif (!empty($TCProj)): ?>
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
						<div class="form-check" style="grid-column: 1 / -1;">
                            <label class="form-check-label" for="DBServer" style="width:100%;">
                                <strong>DB Server</strong><br>
                                <small>Database backend</small>
                            </label>
                            <select class="form-select" id="DBServer" name="DBServer" style="margin-top:8px; max-width:200px;">
                                <option value="SQL" <?php echo ($DBServer === 'SQL') ? 'selected' : ''; ?>>SQL</option>
                                <option value="PGS" <?php echo ($DBServer === 'PGS') ? 'selected' : ''; ?>>PGS</option>
                            </select>
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
					<input type="hidden" name="TCProjList" value="<?php echo htmlspecialchars($_GET['TCProjList'] ?? ''); ?>">
					<input type="hidden" name="AutoIDList" value="<?php echo htmlspecialchars($_GET['AutoIDList'] ?? ''); ?>">
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
            // Update the colors on page load
            document.addEventListener('DOMContentLoaded', function() {
                updateCheckboxColors();
                
                // Add the event listeners for every checkbox
                document.querySelectorAll('.form-check input[type="checkbox"]').forEach(checkbox => {
                    checkbox.addEventListener('change', updateCheckboxColors);
                });
            });
            
            // Update the checkbox colors
            function updateCheckboxColors() {
                document.querySelectorAll('.form-check').forEach(formCheck => {
                    const checkbox = formCheck.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        if (checkbox.checked) {
                            formCheck.style.backgroundColor = '#d4edda';  // Light green
                            formCheck.style.borderColor = '#28a745';
                        } else {
                            formCheck.style.backgroundColor = '#f5f5f5';  // Light grey
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
                
                // Enable transitions AFTER the first paint (avoids the load flash)
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        document.body.classList.add('theme-ready');
                    });
                });
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
            }
        </script>
    </body>
    </html>
    <?php
}

/**
 * Confirm and launch the test
 */
/**
 * Helper sending a GET request (declared once at file scope so it can be
 * called repeatedly, including from ConfirmAndRun in batch mode).
 */
function sendGetRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);          // GET method
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);    // Follow the redirects
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return ['code' => $httpCode, 'response' => $response, 'error' => $error];
}

function ConfirmAndRun($test, $runn, $logurl, $branch, $Test_x, $LogVersion, $Browser, $JJob, $Hub, $forDebug, $localrun, $parallel, $Build, $retry, $DBServer, $doRedirect = true, $dryRun) {
    $Test_y = "&Test_Node=" . $Test_x;

    // Map the branches (testType -> Jenkins Git branch)
    // Centralized mapping in config/versions_config.php ($LOGG_JENKINS_BRANCH_MAP)
    global $LOGG_JENKINS_BRANCH_MAP;
    $branch = $LOGG_JENKINS_BRANCH_MAP[$branch] ?? $branch;
    
    // Build the test URL with its parameters
    $test = $test . "&Test_Version=" . $branch . $Test_y . "&TestBrowser=" . $Browser;
    
    if (!empty($Build)) {
        $test = $test . "&TestedBuild=" . urlencode($Build);
    }
    
	if (!empty($DBServer)) {
        $test = $test . "&DBServer=" . urlencode($DBServer);
    }
	
    if ($forDebug === 'true') {
        $feature = substr($JJob, 14, strlen($JJob));
        $test = $test . "&Feature=" . $feature;
    }
    
    // Select the hub
    if ($Test_x !== "JDF" && $Test_x !== "SV" && $Test_x !== "OG" && $Test_x !== "AS" && $Test_x !== "Grid") {
        $Hub = "http://sqs-gridhub1";
    } else {
        $Hub = "https://sqs-sel-cent1.cas-software.dev";
    }
    
    $test = $test . "&Hub=" . $Hub;
    
	// Execution options
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
    
    // Send the requests via GET (Jenkins reads the URL/query string parameters)
    // Just like pasting the URL directly into the browser
    // (sendGetRequest is declared once at file scope so it stays safe to call
    //  ConfirmAndRun multiple times in batch mode)

    if ($dryRun) {
        // DEBUG: display the URLs instead of sending them (nothing executed)
        echo '<div style="font-family:monospace; font-size:13px; margin:10px; padding:12px; border:1px solid #ccc; background:#f8f8f8; word-break:break-all;">';
        echo '<strong style="color:#d63384;">🐞 DRY RUN (no request sent)</strong><br><br>';
        echo '<strong>check.php URL:</strong><br>' . htmlspecialchars($runn) . '<br><br>';
        echo '<strong>Jenkins URL:</strong><br>' . htmlspecialchars($test) . '<br>';
        echo '</div>';
        return; // do NOT send, do NOT redirect
    }

    // Send the check request (check.php: sets running=2)
    @sendGetRequest($runn);
    
    // Send the test request to Jenkins
    @sendGetRequest($test);
    
    // Redirect back to the log (skipped in batch mode until the last scenario)
	if ($doRedirect) {
        header("Location: " . $logurl);
        exit;
    }
}
?>