<?php
// Central configuration file for versions/branches (no need for the
// DB connection here, so only versions_config.php is loaded)
require_once __DIR__ . '/../../_config/versions_config.php';
require_once __DIR__ . '/../../_config/config.php';

// Jenkins deploy job triggered by the "Update" buttons (remote trigger token = TCAUTO)
const JENKINS_DEPLOY_JOB_URL = 'https://build-sqs.cas-software.dev/view/Deployments/job/SQS-gWServer-Deploy/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>VM Nightly Update Configuration</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Theme init (before CSS to avoid the flash) -->
  <script src="js/theme.js"></script>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="css/theme.css" rel="stylesheet" />

  <style>
    body {
      padding: 2rem;
      background: var(--bg-primary);
    }
	
    .toast-container {
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 1100;
    }
	
	td {
	  text-align: center;
	  vertical-align: middle;
	}

  </style>
</head>
<body>

  <!-- Theme Toggle Button -->
  <button id="themeToggle" class="btn btn-sm btn-outline-secondary theme-toggle-btn" title="Toggle Dark/Light Mode" onclick="toggleTheme()">
    🌙 Dark
  </button>

  <div class="container">
    <h1 class="mb-4 text-center">VM Nightly Update Configuration</h1>
	<h4 class="mb-2 text-center"><a href="<?php echo JENKINS_DEPLOY_JOB_URL; ?>" target="_blank">Jenkins Deploy</a></h4>
	
    <!-- Nav tabs -->
    <ul class="nav nav-tabs mb-3" id="vmTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="selenium-tab" data-bs-toggle="tab" data-bs-target="#selenium" type="button" role="tab" aria-controls="selenium" aria-selected="true">
          Selenium VMs
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="release-tab" data-bs-toggle="tab" data-bs-target="#release" type="button" role="tab" aria-controls="release" aria-selected="false">
          Release VMs
        </button>
      </li>
	   <li class="nav-item" role="presentation">
        <button class="nav-link" id="smartwe-tab" data-bs-toggle="tab" data-bs-target="#smartwe" type="button" role="tab" aria-controls="smartwe" aria-selected="false">
          smartWe VMs
        </button>
      </li>
	  <li class="nav-item" role="presentation">
        <button class="nav-link" id="testcomplete-tab" data-bs-toggle="tab" data-bs-target="#testcomplete" type="button" role="tab" aria-controls="testcomplete" aria-selected="false">
          Testcomplete VMs
        </button>
      </li>
    </ul>

    <div class="tab-content" id="vmTabsContent">
      <!-- VMs Tab -->
      <div class="tab-pane fade show active" id="selenium" role="tabpanel" aria-labelledby="selenium-tab">
        <?php
          // Derived from _config/versions_config.php ($LOGG_VM_BRANCHES): adding
          // a gW Web version in that file is enough to add it here too.
          $branches = array_map(fn($tt) => logg_branch_vm_parts($tt)['display'], $LOGG_VM_BRANCHES);
          $suffixes = array_map(fn($tt) => logg_branch_vm_parts($tt)['suffix'], $LOGG_VM_BRANCHES);
        ?>
		<!-- Selenium VMs Tab -->
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-light">
              <tr>
				<th>Branch (<a href="<?php echo LOGG_BASE_URL; ?>/index.php" target="_blank">Logs</a>)</th>
                <?php
                foreach ($branches as $branch) {
				$url = "https://sqs-sel-$branch.cas-software.dev/smartdesign/";
				echo "<th><a href='$url'>$branch</a></th>";
				}

                ?>
              </tr>
            </thead>
            <tbody>
              <?php
              renderLastBuildRow('last', $branches);
			  renderDeploymentRowWithComparison('last', 'lastSel', $branches, $suffixes);
			  $checkboxKeys = array_map(fn($b) => $b.'_selenium', $branches);
              renderCheckboxRow($checkboxKeys, "Nightly Update");
              renderUpdateRow($branches, 'Selenium');
              ?>
            </tbody>
          </table>
        </div>
      </div>
	 
      <!-- Release VMs Tab -->
      <div class="tab-pane fade" id="release" role="tabpanel" aria-labelledby="release-tab">
	    <?php
          // Derived from _config/versions_config.php ($LOGG_VM_BRANCHES): adding
          // a gW Web version in that file is enough to add it here too.
          $branches = array_map(fn($tt) => logg_branch_vm_parts($tt)['display'], $LOGG_VM_BRANCHES);
          $suffixes = array_map(fn($tt) => logg_branch_vm_parts($tt)['suffix'], $LOGG_VM_BRANCHES);
        ?>
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-light">
              <tr>
                <th>Branch (<a href="https://application.cas.de/smartdesign/#!app/xcas.bugreport" target="_blank">Bugs</a>)</th>
                <?php
                foreach ($branches as $branch) {
                  echo "<th>$branch</th>";
                }
                ?>
              </tr>
            </thead>
            <tbody>
              <?php
              renderLastBuildRow('last', $branches);
			  renderDeploymentRowWithComparison('last', 'lastRel', $branches, $suffixes);
			  $checkboxKeys = array_map(fn($b) => $b.'_release', $branches);
              renderCheckboxRow($checkboxKeys, "Nightly Update");
              renderUpdateRow($branches, 'Release');
              ?>
            </tbody>
          </table>
        </div>
      </div>
	  
	  <!-- smartWe VMs Tab -->
      <div class="tab-pane fade" id="smartwe" role="tabpanel" aria-labelledby="smartwe-tab">
	    <?php
          $suffixes = ['wedevDeploy', 'wercDeploy', 'wehfDeploy'];
          $branches = ['wedev', 'werc', 'wehf'];
        ?>
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-light">
              <tr>
                <th>Branch (<a href="<?php echo LOGG_BASE_URL; ?>/index.php?Product=weWebSel&Testtype=rc_x18" target="_blank">Logs</a>)</th>
                <?php
                foreach ($branches as $branch) {
					if ($branch === 'wehf') {
						$url = "https://sqs-smartwe-hotfix.internalk8s.home.cas.de/identity/login?ongoing=app";
					} elseif ($branch === 'werc') {
						$url = "https://sqs-smartwe-rc.internalk8s.home.cas.de/identity/login?ongoing=app";
					} elseif ($branch === 'wedev') {
						$url = "https://sqs-smartwe-dev.internalk8s.home.cas.de/identity/select-tenant?ongoing=app";
					}
					echo "<th><a href='$url'>$branch</a></th>";
				}

                ?>
              </tr>
            </thead>
            <tbody>
              <?php
			  
              renderLastBuildRowWe('lastWe', $branches); //lastWedevBuild
			  renderDeploymentRowWithComparison('last', 'lastWe', $branches, $suffixes); //lastWewedevDeploy
			  $checkboxKeys = array_map(fn($b) => $b.'_smartwe', $branches);
              renderCheckboxRow($checkboxKeys, "Nightly Update");
              ?>
            </tbody>
          </table>
        </div>
      </div>

	  <!-- VMs Tab -->
      <div class="tab-pane fade" id="testcomplete" role="tabpanel" aria-labelledby="testcomplete-tab">
        <?php
          $suffixes = ['hf16Deploy1', 'rc17Deploy1', 'rc17Deploy2', 'rc18Deploy1'];
          $branches = ['x16hf_1', 'x17rc_1', 'x17rc_2', 'x18rc_1',];
        ?>
		<!-- testcomplete VMs Tab -->
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-light">
              <tr>
                <th>Branch</th>
                <?php
                foreach ($branches as $branch) {
				echo "<th>$branch</a></th>";
				}

                ?>
              </tr>
            </thead>
            <tbody>
              <?php
              renderLastBuildRowTC('last', $branches);
			  renderDeploymentRowWithComparisonTC('last', 'lastTes', $branches, $suffixes);
			  $checkboxKeys = array_map(fn($b) => $b.'_testcomplete', $branches);
              renderCheckboxRow($checkboxKeys, "Nightly Update");
              renderUpdateRow($branches, 'Testcomplete');
              ?>
            </tbody>
          </table>
        </div>
      </div>	  
    </div>
  </div>

  <!-- Toast container -->
  <div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="saveToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body" id="saveToastBody">
          Settings saved successfully!
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  </div>

  <!-- Modal for error -->
  <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content border-danger">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="errorModalLabel">Error</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="errorModalBody">
          <!-- Error message goes here -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JS Bundle (includes Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Send checkbox state and show toast or error modal on failure
    function sendValue(checkbox, key) {
      const value = checkbox.checked ? "checked" : "unchecked";
      fetch('nightly/save_column.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `column=${encodeURIComponent(key)}&state=${encodeURIComponent(value)}`
      }).then(response => {
        if (!response.ok) {
          throw new Error(`Server error: ${response.statusText}`);
        }
        showToast();
        // Only after a successful save: Jenkins reads the server-side state
        refreshUpdateButtons();
      }).catch(err => {
        showError(err.message);
      });
    }

    // Load saved states and apply
    function applySavedStates() {
      fetch('nightly/load_states.php')
        .then(response => response.json())
        .then(states => {
          for (const [key, state] of Object.entries(states)) {
            const checkbox = document.querySelector(`input[data-key="${key}"]`);
            if (checkbox) {
              checkbox.checked = (state === "checked");
            }
          }
          refreshUpdateButtons();
        })
        .catch(err => {
          showError("Failed to load checkbox states: " + err.message);
        });
    }

    // Show toast success message (default text = checkbox save confirmation)
    function showToast(message) {
      document.getElementById('saveToastBody').textContent = message || 'Settings saved successfully!';
      const toastEl = document.getElementById('saveToast');
      const toast = new bootstrap.Toast(toastEl);
      toast.show();
    }

    // Show error modal
    function showError(message) {
      const modalEl = document.getElementById('errorModal');
      const modalBody = document.getElementById('errorModalBody');
      modalBody.textContent = message;
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }

    // "Update" buttons are only enabled when the "Nightly Update" checkbox of
    // the same column is checked: the Jenkins pipeline skips the deployment
    // ("Update DISABLED") otherwise. Buttons start disabled (server-rendered)
    // until the saved states are loaded.
    function refreshUpdateButton(btn) {
      if (btn.dataset.busy === '1') {
        return; // request in progress / post-trigger lock
      }
      const checkbox = document.querySelector(`input[data-key="${btn.dataset.nightlyKey}"]`);
      const enabled = !!(checkbox && checkbox.checked);
      btn.disabled = !enabled;
      btn.title = enabled
        ? `Trigger Jenkins deploy for ${btn.dataset.label}`
        : 'Enable "Nightly Update" first';
    }

    function refreshUpdateButtons() {
      document.querySelectorAll('button[data-nightly-key]').forEach(refreshUpdateButton);
    }

    // Trigger the Jenkins deploy job directly from the browser (GET on the
    // buildWithParameters URL built server-side in renderUpdateRow()).
    // Jenkins sends no CORS headers: 'no-cors' lets the GET go through, but the
    // response is opaque, so only network errors can be detected here.
    // The build result has to be checked in Jenkins itself.
    function triggerUpdate(btn) {
      const url = btn.dataset.url;
      const label = btn.dataset.label;
      const question = `Launch Jenkins deploy (FORCEUPDATE) for ${label}?\n\n`
                     + `Running sessions on the VM will be killed.`;
      if (!confirm(question)) {
        return;
      }

      const originalHtml = btn.innerHTML;
      btn.dataset.busy = '1';
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

      fetch(url, { method: 'GET', mode: 'no-cors', cache: 'no-store' })
        .then(() => {
          showToast(`Deploy request sent to Jenkins (${label}).`);
          btn.innerHTML = '✓ Sent';
          // Keep the button locked for a while to avoid double triggers
          setTimeout(() => {
            btn.innerHTML = originalHtml;
            delete btn.dataset.busy;
            refreshUpdateButton(btn);
          }, 10000);
        })
        .catch(err => {
          btn.innerHTML = originalHtml;
          delete btn.dataset.busy;
          refreshUpdateButton(btn);
          showError('Could not reach Jenkins: ' + err.message);
        });
    }

    // Initialize on page load
    window.addEventListener('DOMContentLoaded', applySavedStates);
  </script>

</body>
</html>

<?php
function renderLastBuildRowWe($prefix, $suffixes) {
  echo "<tr><th>Last Build</th>";
  foreach ($suffixes as $suffix) {
	$suffixshort = substr($suffix, 2);
	$file = SHARED_DATA_DIR . "_builds/{$prefix}{$suffixshort}Build.txt";
	$content = file_exists($file) ? htmlspecialchars(file_get_contents($file)) : "N/A";
	echo "<td>$content</td>";
  }
  echo "</tr>";
}

function renderLastBuildRow($prefix, $suffixes) {
  echo "<tr><th>Last Build</th>";
  foreach ($suffixes as $suffix) {
	$file = SHARED_DATA_DIR . "_builds/{$prefix}{$suffix}Build.txt";
	$content = file_exists($file) ? htmlspecialchars(file_get_contents($file)) : "N/A";
	echo "<td>$content</td>";
  }
  echo "</tr>";
}

function renderLastBuildRowTC($prefix, $suffixes) {
  echo "<tr><th>Last Build</th>";
  foreach ($suffixes as $suffix) {
	$clean = explode('_', $suffix)[0];
    $file = SHARED_DATA_DIR . "_builds/{$prefix}{$clean}Build.txt";
	$content = file_exists($file) ? htmlspecialchars(file_get_contents($file)) : "N/A";
	echo "<td>$content</td>";
  }
  echo "</tr>";
}

function renderCheckboxRow($keys, $label) {
  echo "<tr><th>$label</th>";
  foreach ($keys as $key) {
	$tooltip = "Enable nightly update for {$key}";
	echo <<<HTML
	  <td>
		<div class="form-check d-flex justify-content-center align-items-center m-0">
		  <input class="form-check-input" type="checkbox" id="chk_$key" data-key="$key" title="$tooltip" onchange="sendValue(this, '$key')">
		  <label class="form-check-label" for="chk_$key"></label>
		</div>
	  </td>
	HTML;
  }
  echo "</tr>";
}

// Maps a vm_config.php column to the Jenkins deploy job parameters:
//   'x18hf'  -> branch 'hotfixx18', 'x18rc' -> 'rcx18', 'x18dev' -> 'devx18'
//   Testcomplete columns carry the VM index: 'x17rc_2' -> 'rcx17' + System 'Testcomplete2'
// Returns null when the column can't be mapped (button is then disabled).
function vmColumnToJenkinsParams($branch, $system) {
  if (!preg_match('/^x(\d{2})(hf|rc|dev)(?:_(\d))?$/', $branch, $m)) {
	return null;
  }
  $prefixMap = ['hf' => 'hotfix', 'rc' => 'rc', 'dev' => 'dev'];
  $vmIndex = $m[3] ?? '';

  if ($system === 'Testcomplete') {
	if ($vmIndex === '') {
	  return null;
	}
	$jenkinsSystem = 'Testcomplete' . $vmIndex;
  } else {
	if ($vmIndex !== '') {
	  return null;
	}
	$jenkinsSystem = $system;
  }

  return [
	'branch' => $prefixMap[$m[2]] . 'x' . $m[1],
	'System' => $jenkinsSystem,
  ];
}

// Row with one "Update" button per VM column. Each button carries the full
// Jenkins URL (GET, token=TCAUTO, FORCEUPDATE=true); the other job parameters
// keep their pipeline defaults.
// Buttons are rendered disabled; JS enables them when the matching
// "Nightly Update" checkbox (key = <column>_<system>) is checked.
// $system: 'Selenium' | 'Release' | 'Testcomplete'
function renderUpdateRow($branches, $system) {
  echo "<tr><th>Manual Update</th>";
  foreach ($branches as $branch) {
	$params = vmColumnToJenkinsParams($branch, $system);
	if ($params === null) {
	  $b = htmlspecialchars($branch, ENT_QUOTES);
	  echo "<td><button type='button' class='btn btn-sm btn-secondary' disabled title='No Jenkins mapping for {$b}'>Update</button></td>";
	  continue;
	}

	$url = JENKINS_DEPLOY_JOB_URL . 'buildWithParameters?' . http_build_query([
	  'token'       => 'TCAUTO',
	  'delay'       => '0sec',
	  'branch'      => $params['branch'],
	  'System'      => $params['System'],
	  'FORCEUPDATE' => 'true',
	]);
	$label = "{$params['branch']} / {$params['System']}";

	// Same key as the "Nightly Update" checkbox of this column (renderCheckboxRow)
	$nightlyKey = $branch . '_' . strtolower($system);

	$safeUrl   = htmlspecialchars($url, ENT_QUOTES);
	$safeLabel = htmlspecialchars($label, ENT_QUOTES);
	$safeKey   = htmlspecialchars($nightlyKey, ENT_QUOTES);
	echo "<td><button type='button' class='btn btn-sm btn-primary' disabled data-url='{$safeUrl}' data-label='{$safeLabel}' data-nightly-key='{$safeKey}' title='Enable &quot;Nightly Update&quot; first' onclick='triggerUpdate(this)'>Update</button></td>";
  }
  echo "</tr>";
}

function renderDeploymentRowWithComparison($buildPrefix, $deployPrefix, $branches, $suffixes) {
  echo "<tr><th>Deployed on VM</th>";

  for ($i = 0; $i < count($branches); $i++) {
	$branch = $branches[$i];
	$suffix = $suffixes[$i];

	$buildFile = SHARED_DATA_DIR . "_builds/{$buildPrefix}{$branch}Build.txt";
	$deployFile = SHARED_DATA_DIR . "_deployedVM/{$deployPrefix}{$suffix}.txt";

	$buildValue = file_exists($buildFile) ? trim(file_get_contents($buildFile)) : "N/A";
	$deployValue = file_exists($deployFile) ? trim(file_get_contents($deployFile)) : "N/A";

	$safeDeploy = htmlspecialchars($deployValue);
	$cellClass = ($buildValue !== $deployValue) ? "bg-warning" : "bg-success";

	$title = ($buildValue !== $deployValue) ? "title='Expected: $buildValue'" : "";
	echo "<td class='$cellClass' $title>$safeDeploy</td>";
  }

  echo "</tr>";
}

function renderDeploymentRowWithComparisonTC($buildPrefix, $deployPrefix, $branches, $suffixes) {
  echo "<tr><th>Deployed on VM</th>";

  for ($i = 0; $i < count($branches); $i++) {
	$branch = $branches[$i];
	$cleanbranch = explode('_', $branch)[0];
	$suffix = $suffixes[$i];
	
	$buildFile = SHARED_DATA_DIR . "_builds/{$buildPrefix}{$cleanbranch}Build.txt";
	$deployFile = SHARED_DATA_DIR . "_deployedVM/{$deployPrefix}{$suffix}.txt";
	
	$buildValue = file_exists($buildFile) ? trim(file_get_contents($buildFile)) : "N/A";
	$deployValue = file_exists($deployFile) ? trim(file_get_contents($deployFile)) : "N/A";

	$safeDeploy = htmlspecialchars($deployValue);
	$cellClass = ($buildValue !== $deployValue) ? "bg-warning" : "bg-success";

	$title = ($buildValue !== $deployValue) ? "title='Expected: $buildValue'" : "";
	echo "<td class='$cellClass' $title>$safeDeploy</td>";
  }

  echo "</tr>";
}
?>