<?php
/**
 * CACHE.PHP
 * Gestionnaire de cache simple basé sur les fichiers
 * Utilisé pour mettre en cache les requêtes DB fréquentes
 */

class Cache {
    private $cacheDir;
    private $defaultTTL = 300; // 5 minutes par défaut
    
    /**
     * Constructeur
     * @param string $cacheDir Répertoire du cache (par défaut /tmp)
     */
    public function __construct($cacheDir = null) {
        if ($cacheDir === null) {
            $cacheDir = sys_get_temp_dir() . '/logg_cache';
        }
        
        $this->cacheDir = $cacheDir;
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Récupérer une valeur du cache
     * @param string $key Clé du cache
     * @return mixed|null Valeur en cache ou null si expiré/inexistant
     */
    public function get($key) {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        // Vérifier si le cache est expiré
        $cacheData = json_decode(file_get_contents($file), true);
        
        if (!$cacheData || !isset($cacheData['expire'])) {
            return null;
        }
        
        // Si expiré, retourner null et supprimer le fichier
        if (time() > $cacheData['expire']) {
            @unlink($file);
            return null;
        }
        
        return $cacheData['value'];
    }
    
    /**
     * Sauvegarder une valeur en cache
     * @param string $key Clé du cache
     * @param mixed $value Valeur à cacher
     * @param int $ttl Durée de vie en secondes (par défaut 300 = 5min)
     * @return bool Succès de la sauvegarde
     */
    public function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->defaultTTL;
        }
        
        $file = $this->getCacheFile($key);
        
        $cacheData = [
            'key' => $key,
            'value' => $value,
            'created' => time(),
            'expire' => time() + $ttl,
            'ttl' => $ttl
        ];
        
        $result = @file_put_contents($file, json_encode($cacheData));
        return $result !== false;
    }
    
    /**
     * Vérifier si une clé existe et n'est pas expiré
     * @param string $key Clé du cache
     * @return bool
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Supprimer une clé du cache
     * @param string $key Clé du cache
     * @return bool
     */
    public function delete($key) {
        $file = $this->getCacheFile($key);
        return @unlink($file);
    }
    
    /**
     * Vider tout le cache
     * @return int Nombre de fichiers supprimés
     */
    public function flush() {
        $count = 0;
        $files = glob($this->cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Obtenir le statut du cache (info de debug)
     * @return array
     */
    public function getStats() {
        $files = glob($this->cacheDir . '/*.cache');
        $totalSize = 0;
        $validItems = 0;
        $expiredItems = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            
            $cacheData = json_decode(file_get_contents($file), true);
            if ($cacheData && isset($cacheData['expire'])) {
                if (time() > $cacheData['expire']) {
                    $expiredItems++;
                } else {
                    $validItems++;
                }
            }
        }
        
        return [
            'total_items' => count($files),
            'valid_items' => $validItems,
            'expired_items' => $expiredItems,
            'total_size_kb' => round($totalSize / 1024, 2),
            'cache_dir' => $this->cacheDir
        ];
    }
    
    /**
     * Nettoyer les fichiers de cache expiré
     * @return int Nombre de fichiers supprimés
     */
    public function cleanup() {
        $count = 0;
        $files = glob($this->cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            $cacheData = json_decode(file_get_contents($file), true);
            
            if (!$cacheData || !isset($cacheData['expire']) || time() > $cacheData['expire']) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Obtenir le chemin du fichier cache
     * @param string $key
     * @return string
     */
    private function getCacheFile($key) {
        // Sanitize le nom de fichier
        $filename = md5($key) . '.cache';
        return $this->cacheDir . '/' . $filename;
    }
}

?>
