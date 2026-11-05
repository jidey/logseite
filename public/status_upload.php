<?php
/**
 * STATUS_UPLOAD.PHP
 * Receives the HTML report of the morning check and stores it as the report of the day,
 * shown by status.php.
 *
 *   curl -X POST --data-binary @status.html -H "Content-Type: text/html"
 *        -H "X-Status-Token: <token>" https://sqs-sel-cent1.cas-software.dev/logg/public/status_upload.php
 *
 * The token is the content of _config/status_token.txt (shared by logg and logdev). Without that
 * file the upload is disabled.
 *
 * JSON response: { "success": true, "date": "2026-11-05", "bytes": 12345 }
 */

header('Content-Type: application/json');

require_once '../../_config/config.php';

const STATUS_MAX_BYTES = 5 * 1024 * 1024;
const STATUS_KEEP_DAYS = 30;

function fail(int $code, string $error): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    fail(405, 'POST only');
}

$tokenFile = SHARED_DATA_DIR . '_config' . DIRECTORY_SEPARATOR . 'status_token.txt';
$expected  = is_file($tokenFile) ? trim((string)file_get_contents($tokenFile)) : '';
if ($expected === '') {
    fail(503, 'Upload disabled: _config/status_token.txt is missing');
}
$token = $_SERVER['HTTP_X_STATUS_TOKEN'] ?? '';
if (!hash_equals($expected, $token)) {
    fail(403, 'Invalid token');
}

$html = file_get_contents('php://input', false, null, 0, STATUS_MAX_BYTES + 1);
if ($html === false || $html === '') {
    fail(400, 'Empty body');
}
if (strlen($html) > STATUS_MAX_BYTES) {
    fail(413, 'Report larger than ' . STATUS_MAX_BYTES . ' bytes');
}
if (!mb_check_encoding($html, 'UTF-8') || !preg_match('/<html[\s>]/i', $html)) {
    fail(400, 'Body is not a UTF-8 HTML document');
}

$dir = SHARED_DATA_DIR . '_status';
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fail(500, 'Cannot create the _status folder');
}

$date   = date('Y-m-d');
$target = $dir . DIRECTORY_SEPARATOR . $date . '.html';
$tmp    = $target . '.tmp';
if (file_put_contents($tmp, $html, LOCK_EX) === false || !rename($tmp, $target)) {
    @unlink($tmp);
    fail(500, 'Cannot write the report');
}

$limit = date('Y-m-d', strtotime('-' . STATUS_KEEP_DAYS . ' days'));
foreach (glob($dir . DIRECTORY_SEPARATOR . '????-??-??.html') as $old) {
    if (basename($old, '.html') < $limit) {
        @unlink($old);
    }
}

echo json_encode(['success' => true, 'date' => $date, 'bytes' => strlen($html)]);
