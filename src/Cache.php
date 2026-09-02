<?php
/**
 * CACHE.PHP
 * Simple file-based cache manager
 * Used to cache frequent DB queries
 */

class Cache {
    private $cacheDir;
    private $defaultTTL = 300; // 5 minutes by default

    /**
     * Constructor
     * @param string $cacheDir Cache directory (default /tmp)
     */
    public function __construct($cacheDir = null) {
        if ($cacheDir === null) {
            $cacheDir = sys_get_temp_dir() . '/logg_cache';
        }

        $this->cacheDir = $cacheDir;

        // Create the directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get a value from the cache
     * @param string $key Cache key
     * @return mixed|null Cached value or null if expired/nonexistent
     */
    public function get($key) {
        $file = $this->getCacheFile($key);

        if (!file_exists($file)) {
            return null;
        }

        // Check whether the cache is expired
        $cacheData = json_decode(file_get_contents($file), true);

        if (!$cacheData || !isset($cacheData['expire'])) {
            return null;
        }

        // If expired, return null and delete the file
        if (time() > $cacheData['expire']) {
            @unlink($file);
            return null;
        }
        
        return $cacheData['value'];
    }
    
    /**
     * Save a value into the cache
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (default 300 = 5min)
     * @return bool Whether the save succeeded
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
     * Check whether a key exists and is not expired
     * @param string $key Cache key
     * @return bool
     */
    public function has($key) {
        return $this->get($key) !== null;
    }

    /**
     * Delete a key from the cache
     * @param string $key Cache key
     * @return bool
     */
    public function delete($key) {
        $file = $this->getCacheFile($key);
        return @unlink($file);
    }

    /**
     * Clear the entire cache
     * @return int Number of files deleted
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
     * Get the cache status (debug info)
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
     * Clean up expired cache files
     * @return int Number of files deleted
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
     * Get the cache file path
     * @param string $key
     * @return string
     */
    private function getCacheFile($key) {
        // Sanitize the file name
        $filename = md5($key) . '.cache';
        return $this->cacheDir . '/' . $filename;
    }
}

?>
