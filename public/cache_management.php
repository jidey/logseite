<?php
/**
 * CACHE MANAGEMENT PAGE
 * Interface to manage and monitor the system cache
 * URL: http://localhost/log/public/cache_management.php
 */

require_once '../../_config/config.php';
require_once '../src/TestLogRepository.php';

$repo = new TestLogRepository($pdo);
$cacheStats = $repo->getCacheStats();

// Actions
$message = null;
$messageType = 'info'; // success, danger, info

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'flush') {
        $count = $repo->clearCache();
        $message = "Cache cleared! $count files deleted.";
        $messageType = 'success';
    } elseif ($_GET['action'] === 'cleanup') {
        $count = $repo->cleanupCache();
        $message = "Cache cleaned up! $count expired files deleted.";
        $messageType = 'success';
    }
}

// Get stats after the action
$cacheStats = $repo->getCacheStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cache Management</title>
    <!-- Theme init (before CSS to avoid the flash) -->
    <script src="js/theme.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/theme.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .stat-box {
            background: var(--bg-tertiary);
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
            color: var(--text-primary);
        }
        .stat-box strong { font-size: 1.3em; color: var(--link-color); }
    </style>
</head>
<body>
    <!-- Theme Toggle Button -->
    <button id="themeToggle" class="btn btn-sm btn-outline-secondary theme-toggle-btn" title="Toggle Dark/Light Mode" onclick="toggleTheme()">
        🌙 Dark
    </button>
    <div class="container">
        <h1>⚙️ Cache Management</h1>
        <p class="text-muted">Manage and monitor the system cache</p>

        <hr>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Cache Statistics -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">📊 Cache Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="stat-box">
                            <strong>Total Items:</strong><br>
                            <?php echo $cacheStats['total_items']; ?> files
                        </div>
                        <div class="stat-box">
                            <strong>Valid Items:</strong><br>
                            <span style="color: green;">✓ <?php echo $cacheStats['valid_items']; ?> files</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-box">
                            <strong>Expired Items:</strong><br>
                            <span style="color: red;">✗ <?php echo $cacheStats['expired_items']; ?> files</span>
                        </div>
                        <div class="stat-box">
                            <strong>Cache Size:</strong><br>
                            📦 <?php echo $cacheStats['total_size_kb']; ?> KB
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <strong>Cache Directory:</strong><br>
                    <code><?php echo htmlspecialchars($cacheStats['cache_dir']); ?></code>
                </div>
            </div>
        </div>

        <!-- Cache Actions -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">🔧 Cache Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Clear All Cache</h6>
                        <p class="text-muted">Remove all cached files</p>
                        <a href="?action=flush" class="btn btn-danger" onclick="return confirm('Clear all cache?')">
                            🗑️ Flush Cache
                        </a>
                    </div>
                    <div class="col-md-6">
                        <h6>Clean Up Expired</h6>
                        <p class="text-muted">Remove only expired cache files</p>
                        <a href="?action=cleanup" class="btn btn-warning">
                            🧹 Cleanup Expired
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cache Info -->
        <div class="alert alert-info mt-4">
            <h6>ℹ️ Cache Configuration</h6>
            <ul style="margin: 10px 0;">
                <li><strong>TestTypes Cache TTL:</strong> 1 hour (3600 seconds)</li>
                <li><strong>Storage:</strong> Filesystem (temporary directory)</li>
                <li><strong>Format:</strong> JSON files with metadata</li>
                <li><strong>Auto-cleanup:</strong> Expired files removed on next access</li>
            </ul>
        </div>

        <div class="mt-5">
            <a href="index.php" class="btn btn-secondary">← Back</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
