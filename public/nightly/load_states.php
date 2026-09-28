<?php
require_once __DIR__ . '/../../../_config/config.php';

// Returns every saved nightly checkbox state found in the shared
// _nightly-data folder (one <key>.txt file per checkbox, written by
// save_column.php). No hardcoded key list: any new column (PGL, x19, ...)
// is picked up automatically as soon as its checkbox has been saved once.
// Keys without a file are simply absent; vm_config.php leaves those
// checkboxes unchecked and Jenkins defaults them to "unchecked".

$dataDir = SHARED_DATA_DIR . '_nightly-data';
$results = [];

if (is_dir($dataDir)) {
    foreach (glob($dataDir . DIRECTORY_SEPARATOR . '*.txt') ?: [] as $filename) {
        $key = basename($filename, '.txt');

        // Only expose well-formed keys (e.g. x18rc_pgl, x17rc_2_testcomplete)
        if (!preg_match('/^[a-z0-9_]+$/i', $key)) {
            continue;
        }

        $state = trim((string) @file_get_contents($filename));
        $results[$key] = ($state === 'checked') ? 'checked' : 'unchecked';
    }
}

// Never let IIS or the browser cache this list
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo json_encode((object) $results);