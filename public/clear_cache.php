<?php
/**
 * CLEAR_CACHE.PHP
 * Script pour vider le cache LOGG
 * 
 * Utilisation :
 * - Placer ce fichier dans public/
 * - Accéder à http://localhost/logg/public/clear_cache.php
 * - Ou exécuter en CLI : php public/clear_cache.php
 */

// Déterminer le dossier cache
$cacheDir = sys_get_temp_dir() . '/logg_cache';

echo "🧹 Nettoyage du cache LOGG...\n\n";

if (!is_dir($cacheDir)) {
    echo "ℹ️ Cache non trouvé (normal si jamais utilisé).\n";
    exit(0);
}

$files = glob($cacheDir . '/*');
$count = 0;

if (empty($files)) {
    echo "ℹ️ Dossier cache vide.\n";
} else {
    foreach ($files as $file) {
        if (is_file($file)) {
            if (unlink($file)) {
                echo "✅ Supprimé: " . basename($file) . "\n";
                $count++;
            }
        }
    }
    echo "\n✅ Cache vidé! ($count fichiers supprimés)\n";
}

echo "\nℹ️ Le cache sera régénéré au prochain accès.\n";
?>
