<?php
/**
 * ============================================================================
 *  LOGG — CENTRAL VERSION / BRANCH CONFIGURATION FILE
 * ============================================================================
 *
 * THIS IS THE ONLY PLACE WHERE YOU ADD OR REMOVE A VERSION.
 *
 * ----------------------------------------------------------------------------
 * TO QUICKLY ADD A VERSION (e.g. gW Web x19, branches dev/rc/hf)
 * ----------------------------------------------------------------------------
 * 1. Copy an existing block (e.g. the "x18" block) into $LOGG_VERSIONS below,
 *    changing "x18" to "x19" and adapting the MySQL table names.
 *    The tables must already exist in the database (or be created at the
 *    same time).
 * 2. Choose the status:
 *      - 'active'  => branch shown normally in the selectors, immediately,
 *                     even if the database doesn't have any data for it yet.
 *      - 'future'  => branch shown but greyed out / in parentheses
 *                     "(rc_x19)" in the selector (not ready yet).
 *      - 'retired' => branch completely invisible (see next section).
 * 3. Fill in 'jenkins_branch' with the name of the Git branch used by
 *    Jenkins to rerun tests (visible in Jenkins / GitLab).
 *    Pattern observed on x15->x18: Jenkins number = version number - 4
 *    (x17 -> 13.x, x18 -> 14.x), so x19 -> probably 15.x. Verify this
 *    before triggering a real run.
 * 4. If gW Desktop (gWClient) or smartWe (weWebSel) also need to move to
 *    this version, add their table under 'tables' (see the gWClient /
 *    smartWe section below).
 * 5. Nothing else to change. Reload the page — the new version appears in
 *    the selectors of index.php / details.php, on the "Deployments" page
 *    (vm_config.php), and can be rerun via rerun.php.
 *
 * ----------------------------------------------------------------------------
 * TO RETIRE AN OBSOLETE VERSION
 * ----------------------------------------------------------------------------
 * Set its status to 'retired' (the simplest way, keeps the history visible
 * in this file) or delete its block outright. Either way, it instantly
 * disappears from every selector and table mapping.
 *
 * ----------------------------------------------------------------------------
 * smartWe (weWebSel)
 * ----------------------------------------------------------------------------
 * smartWe doesn't follow gW Web's x.. numbering: only one "current" version
 * is active at a time (table we_dev / we_rc / we_hf, regardless of x).
 * To move smartWe to a new x version, change only $SMARTWE_CURRENT_VERSION
 * below.
 * ============================================================================
 */

// x version currently used by smartWe (weWebSel) for hf/rc.
// Change this single line the day smartWe moves to the next version.
$SMARTWE_CURRENT_VERSION = 'x18';

$LOGG_VERSIONS = [

    // ------------------------------------------------------------------
    // x15 — obsolete, kept here just for the record (status 'retired' =
    // no longer appears anywhere). Delete the block if you don't need it
    // anymore.
    // ------------------------------------------------------------------
    'dev_x15' => ['status' => 'retired'],
    'rc_x15'  => ['status' => 'retired'],
    'hf_x15'  => ['status' => 'retired', 'jenkins_branch' => 'hotfix/11.x'],

    // ------------------------------------------------------------------
    // x16 — only the hf branch is still active (dev/rc retired, same
    // behavior as the old code: blocks commented out below)
    // ------------------------------------------------------------------
    // 'dev_x16' => retired (no associated table)
    // 'rc_x16'  => retired (no associated table)
    'hf_x16' => [
        'status'         => 'active',
        'tables'         => [
            'gWWebSel' => 'x16_hf',
            'gWClient' => 'x16_gwhf',
        ],
        'jenkins_branch' => 'hotfix/12.x',
        'gw_desktop_list'=> true,
    ],

    // ------------------------------------------------------------------
    // x17
    // ------------------------------------------------------------------
    'dev_x17' => [
        'status'         => 'active',
        'tables'         => [
            'gWWebSel' => 'x17_dev',
            'gWClient' => 'x17_gwdev',
        ],
        'jenkins_branch' => 'dev/13.x',
        'gw_desktop_list'=> false, // historical behavior: dev is not shown on gW Desktop
    ],
    'rc_x17' => [
        'status'         => 'active',
        'tables'         => [
            'gWWebSel' => 'x17_rc',
            'gWClient' => 'x17_gwrc',
        ],
        'jenkins_branch' => 'rc/13.x',
        'gw_desktop_list'=> true,
    ],
    'hf_x17' => [
        'status'         => 'active',
        'tables'         => [
            'gWWebSel' => 'x17_hf',
            'gWClient' => 'x17_gwhf',
        ],
        'jenkins_branch' => 'hotfix/13.x',
        'gw_desktop_list'=> true,
    ],

    // ------------------------------------------------------------------
    // x18
    // ------------------------------------------------------------------
    'dev_x18' => [
        'status'         => 'active',
        'tables'         => [
            'gWWebSel' => 'x18_dev',
            'weWebSel' => 'we_dev',
            'gWClient' => 'x18_gwdev',
        ],
        'jenkins_branch' => 'dev/14.x',
        'gw_desktop_list'=> false, // historical behavior: dev is not shown on gW Desktop
    ],
    'rc_x18' => [
        'status'         => 'active',
        'tables'         => [
            'gWWebSel' => 'x18_rc',
            'weWebSel' => 'we_rc',
            'gWClient' => 'x18_gwrc',
        ],
        'jenkins_branch' => 'rc/14.x',
        'gw_desktop_list'=> true,
    ],
    'hf_x18' => [
        'status'         => 'active',
        'tables'         => [
            'gWWebSel' => 'x18_hf',
            'weWebSel' => 'we_hf',
            'gWClient' => 'x18_gwhf',
        ],
        'jenkins_branch' => 'hotfix/14.x',
        'gw_desktop_list'=> true,
    ],

    // ------------------------------------------------------------------
    // x19 — ADD YOUR NEW VERSIONS HERE (at the end, at the bottom of the
    // list). Example: gW Web x19, branches dev/rc/hf.
    
	// TO CHECK : verify the Jenkins branch names before the first real run,
    // and create/rename the MySQL tables x19_dev / x19_rc / x19_hf as needed on mysql DB
    // ------------------------------------------------------------------
    'dev_x19' => [
        'status'         => 'retired', 	  //set active when branch exists
        'tables'         => [
            'gWWebSel' => 'x19_dev',
            // 'weWebSel' => 'we_dev',    // uncomment if smartWe moves to x19
            // 'gWClient' => 'x19_gwdev', // uncomment if gW Desktop moves to x19
        ],
        'jenkins_branch' => 'dev/15.x',    
        'gw_desktop_list'=> false,         // true = shown in the fixed "gW Desktop" selector
    ],
    'rc_x19' => [
        'status'         => 'retired',
        'tables'         => [
            'gWWebSel' => 'x19_rc',
        ],
        'jenkins_branch' => 'rc/15.x',     
        'gw_desktop_list'=> false,
    ],
    'hf_x19' => [
        'status'         => 'retired',
        'tables'         => [
            'gWWebSel' => 'x19_hf',
        ],
        'jenkins_branch' => 'hotfix/15.x',
        'gw_desktop_list'=> false,
    ],

];


/**
 * ============================================================================
 *  AUTOMATIC DERIVATION — DO NOT EDIT BY HAND
 *  (everything below is computed from $LOGG_VERSIONS above)
 * ============================================================================
 */

// testType (e.g. 'rc_x17') -> ['gWWebSel' => 'x17_rc', 'weWebSel' => ..., 'gWClient' => ...]
// Consumed by src/TestLogRepository.php (via $GLOBALS['product_table_map'])
$product_table_map = [];

// Ordered list of testTypes marked 'future' (no data expected yet)
// -> shown in parentheses in index.php's selectors
$LOGG_FUTURE_TESTTYPES = [];

// testType -> Jenkins Git branch (consumed by rerun.php / ConfirmAndRun)
$LOGG_JENKINS_BRANCH_MAP = [];

// Fixed ordered list for the "gW Desktop" (gWClient) selector in index.php
$LOGG_GW_DESKTOP_LIST = [];

foreach ($LOGG_VERSIONS as $testType => $def) {
    $status = $def['status'] ?? 'retired';

    if ($status === 'retired') {
        continue; // obsolete version: invisible everywhere
    }

    if (!empty($def['tables'])) {
        $product_table_map[$testType] = $def['tables'];
    }

    if ($status === 'future') {
        $LOGG_FUTURE_TESTTYPES[] = $testType;
    }

    if (!empty($def['jenkins_branch'])) {
        $LOGG_JENKINS_BRANCH_MAP[$testType] = $def['jenkins_branch'];
    }

    if (!empty($def['gw_desktop_list'])) {
        $LOGG_GW_DESKTOP_LIST[] = $testType;
    }
}

// Jenkins branch for smartWe (we_dev / we_rc / we_hf): reuses the one from
// smartWe's current version (avoids a 3rd hardcoded copy of "dev/14.x").
foreach (['dev' => 'we_dev', 'rc' => 'we_rc', 'hf' => 'we_hf'] as $branch => $weKey) {
    $currentKey = $branch . '_' . $SMARTWE_CURRENT_VERSION;
    if (isset($LOGG_JENKINS_BRANCH_MAP[$currentKey])) {
        $LOGG_JENKINS_BRANCH_MAP[$weKey] = $LOGG_JENKINS_BRANCH_MAP[$currentKey];
    }
}

// testType -> gWClient table only (consumed by post.php, check.php,
// update_testset_stats.php, update_validation.php)
$LOGG_GWCLIENT_MAP = [];
foreach ($product_table_map as $testType => $tables) {
    if (!empty($tables['gWClient'])) {
        $LOGG_GWCLIENT_MAP[$testType] = $tables['gWClient'];
    }
}

// Ordered list of active gW Web testTypes (have a 'gWWebSel' table)
// Consumed by vm_config.php (Selenium VMs / Release VMs tabs) and by
// dash.php (gW Web dashboard columns)
$LOGG_VM_BRANCHES = [];
foreach ($product_table_map as $testType => $tables) {
    if (!empty($tables['gWWebSel'])) {
        $LOGG_VM_BRANCHES[] = $testType;
    }
}

// "Short" testType forced for smartWe hf/rc (e.g. 'hf_x18', 'rc_x18')
// Consumed by index.php, details.php, sync_main.php, dash.php
$LOGG_SMARTWE_HF = 'hf_' . $SMARTWE_CURRENT_VERSION;
$LOGG_SMARTWE_RC = 'rc_' . $SMARTWE_CURRENT_VERSION;

/**
 * Splits a testType (e.g. 'hf_x17') into the pieces used for VM display
 * (vm_config.php) and the dashboard (dash.php): displayed branch name
 * ("x17hf") and deployment file suffix ("hf17Deploy").
 *
 * @param string $testType e.g. 'hf_x17' or 'x17_hf'
 * @return array{branch:string, version:string, num:string, display:string, suffix:string}
 */
function logg_branch_vm_parts(string $testType): array {
    $parts = explode('_', $testType);
    if (count($parts) !== 2) {
        return ['branch' => $testType, 'version' => '', 'num' => '', 'display' => $testType, 'suffix' => $testType . 'Deploy'];
    }
    if (preg_match('/^x\d+$/', $parts[0])) {
        [$version, $branch] = $parts;
    } else {
        [$branch, $version] = $parts;
    }
    $num = substr($version, 1);
    return [
        'branch'  => $branch,
        'version' => $version,
        'num'     => $num,
        'display' => $version . $branch,       // e.g. x17hf
        'suffix'  => $branch . $num . 'Deploy', // e.g. hf17Deploy
    ];
}

// ----------------------------------------------------------------------------
// Cosmetic build-number prefix displayed by dash.php
// ----------------------------------------------------------------------------
// The deployedVM/*.txt files contain a raw build number that starts with a
// numeric prefix unrelated to the "x.." version (e.g. version x18 produces
// builds like "28.x.y.z"). dash.php replaces that prefix with "x18." for
// display. Pattern observed on x15->x18: prefix = version number + 10
// (x16->26, x17->27, x18->28). NOT CONFIRMED beyond x18: if an x19 build
// shows up with the wrong prefix (or no replacement at all), just fix the
// value here — dash.php doesn't need to know about it.
// Consumed by public/dash.php.
$LOGG_VERSION_LABEL_PREFIX = [];
foreach ($LOGG_VERSIONS as $testType => $def) {
    if (($def['status'] ?? 'retired') === 'retired') {
        continue;
    }
    $parts = logg_branch_vm_parts($testType);
    if ($parts['version'] === '' || isset($LOGG_VERSION_LABEL_PREFIX[$parts['version']])) {
        continue;
    }
    $LOGG_VERSION_LABEL_PREFIX[$parts['version']] = (string) (intval($parts['num']) + 10) . '.';
}
