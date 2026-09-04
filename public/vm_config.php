<?php
// Central configuration file for versions/branches (no need for the
// DB connection here, so only versions_config.php is loaded)
require_once __DIR__ . '/../../_config/versions_config.php';
require_once __DIR__ . '/../../_config/config.php';
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
	<h4 class="mb-2 text-center"><a href="https://build-sqs.cas-software.dev/view/Deployments/job/SQS-gWServer-Deploy/" target="_blank">Jenkins Deploy</a></h4>
	
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
        <div class="toast-body">
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
        })
        .catch(err => {
          showError("Failed to load checkbox states: " + err.message);
        });
    }

    // Show toast success message
    function showToast() {
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