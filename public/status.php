<?php
/**
 * STATUS.PHP
 * Shows the morning check report of a day (latest by default), uploaded by status_upload.php,
 * which keeps the reports of the last 30 days.
 *
 *   status.php                  latest report
 *   status.php?date=2026-11-05  report of that day
 *   status.php?raw=1&date=...   the report itself (loaded in the sandboxed iframe)
 */

require_once '../../_config/config.php';

$dir     = SHARED_DATA_DIR . '_status' . DIRECTORY_SEPARATOR;
$reports = array_map(fn($f) => basename($f, '.html'), glob($dir . '????-??-??.html') ?: []);
rsort($reports);

$date = $_GET['date'] ?? ($reports[0] ?? '');
if (!in_array($date, $reports, true)) {
    $date = '';
}

if (isset($_GET['raw'])) {
    if ($date === '') {
        http_response_code(404);
        exit;
    }
    // The report runs in an opaque origin, also when opened directly: no access to LOGG cookies
    header("Content-Security-Policy: sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox");
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    $html = file_get_contents($dir . $date . '.html');
    // Links leave the iframe: Jenkins and Allure refuse to be framed
    if (stripos($html, '<base') === false) {
        $html = preg_replace('/<head(\s[^>]*)?>/i', '$0<base target="_blank">', $html, 1);
    }
    echo $html;
    exit;
}

$generated = $date !== '' ? filemtime($dir . $date . '.html') : null;
$isToday   = $date === date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQS Status</title>
    <script src="js/theme.js"></script>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/theme.css" rel="stylesheet">
    <style type="text/css">
        body { margin: 0; }
        .status-bar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; padding: 8px 16px; }
        .status-bar h1 { font-size: 1.3rem; margin: 0; }
        .status-frame { display: block; width: 100%; height: calc(100vh - 56px); border: 0; }
    </style>
</head>
<body>
    <div class="status-bar">
        <h1>🩺 Morning Status</h1>
        <?php if ($date !== ''): ?>
            <form method="get" class="form-inline">
                <select name="date" class="form-control form-control-sm" onchange="this.form.submit()">
                    <?php foreach ($reports as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>" <?= $r === $date ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <span class="text-muted small">Generated <?= date('Y-m-d H:i', $generated) ?></span>
            <?php if (!$isToday): ?>
                <span class="badge badge-warning">No report for today</span>
            <?php endif; ?>
            <a href="status.php?raw=1&amp;date=<?= urlencode($date) ?>" target="_blank" class="btn btn-sm btn-outline-secondary ml-auto">Open alone</a>
        <?php endif; ?>
    </div>
    <?php if ($date !== ''): ?>
        <iframe class="status-frame" src="status.php?raw=1&amp;date=<?= urlencode($date) ?>"
                sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox" title="Morning check report"></iframe>
    <?php else: ?>
        <div class="alert alert-info m-3">No morning check report uploaded yet.</div>
    <?php endif; ?>
</body>
</html>
