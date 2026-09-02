<?php
/**
 * CLEAR_CACHE.PHP
 * Script to clear the LOGG cache
 *
 * Usage:
 * - Place this file in public/
 * - Access http://localhost/logg/public/clear_cache.php
 * - Or run via CLI: php public/clear_cache.php
 */

// Determine the cache folder
$cacheDir = sys_get_temp_dir() . '/logg_cache';

echo "🧹 Clearing the LOGG cache...\n\n";

if (!is_dir($cacheDir)) {
    echo "ℹ️ Cache not found (normal if it was never used).\n";
    exit(0);
}

$files = glob($cacheDir . '/*');
$count = 0;

if (empty($files)) {
    echo "ℹ️ Cache folder is empty.\n";
} else {
    foreach ($files as $file) {
        if (is_file($file)) {
            if (unlink($file)) {
                echo "✅ Deleted: " . basename($file) . "\n";
                $count++;
            }
        }
    }
    echo "\n✅ Cache cleared! ($count files deleted)\n";
}

echo "\nℹ️ The cache will be regenerated on the next access.\n";
?>

