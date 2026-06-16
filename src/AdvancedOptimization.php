<?php
/**
 * ADVANCEDOPTIMIZATION.PHP
 * Optimisations avancées : GZIP, CDN, Service Worker, etc.
 */

class AdvancedOptimization {
    
    /**
     * Activer GZIP compression
     * À appeler au début du script PHP
     */
    public static function enableGzip() {
        if (!headers_sent()) {
            // Vérifier le support GZIP du navigateur
            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                ob_start('ob_gzhandler');
            }
        }
    }
    
    /**
     * Ajouter les headers de cache
     * @param int $ttl - Durée de vie en secondes
     */
    public static function setCacheHeaders($ttl = 3600) {
        if (!headers_sent()) {
            header('Cache-Control: public, max-age=' . $ttl);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $ttl) . ' GMT');
            header('Pragma: public');
            header('ETag: ' . md5($_SERVER['REQUEST_URI'] ?? ''));
        }
    }
    
    /**
     * Ajouter les headers de sécurité
     */
    public static function setSecurityHeaders() {
        if (!headers_sent()) {
            // Prévenir le clickjacking
            header('X-Frame-Options: SAMEORIGIN');
            // Prévenir le MIME sniffing
            header('X-Content-Type-Options: nosniff');
            // Activer le XSS Protection
            header('X-XSS-Protection: 1; mode=block');
            // Content Security Policy (basique)
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net");
        }
    }
    
    /**
     * Ajouter les headers pour la performance
     */
    public static function setPerformanceHeaders() {
        if (!headers_sent()) {
            // Preload les ressources critiques
            header('Link: </public/css/styles.min.css>; rel=preload; as=style', false);
            header('Link: </public/js/app.min.js>; rel=preload; as=script', false);
            // DNS Prefetch pour CDN
            header('Link: <https://cdn.jsdelivr.net>; rel=dns-prefetch', false);
        }
    }
    
    /**
     * Générer un Service Worker registration script
     * @return string - Script HTML
     */
    public static function registerServiceWorker() {
        return <<<'HTML'
<script>
// Enregistrer le Service Worker pour offline support
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(reg => console.log('Service Worker registered'))
            .catch(err => console.log('Service Worker registration failed:', err));
    });
}
</script>
HTML;
    }
    
    /**
     * Ajouter le preload pour les fonts
     * @param array $fonts - Array de URLs de fonts
     */
    public static function preloadFonts($fonts = []) {
        if (!headers_sent()) {
            foreach ($fonts as $font) {
                header('Link: <' . htmlspecialchars($font) . '>; rel=preload; as=font; crossorigin', false);
            }
        }
    }
    
    /**
     * Optimiser les images avec srcset
     * @param string $src - URL mobile/small screen
     * @param array $sizes - Array de tailles [480=>url, 768=>url, 1200=>url]
     * @param string $alt - Texte alternatif
     * @return string - HTML img tag avec srcset
     */
    public static function responsiveImage($src, $sizes = [], $alt = '') {
        $srcset = htmlspecialchars($src);
        
        foreach ($sizes as $width => $url) {
            $srcset .= ', ' . htmlspecialchars($url) . ' ' . $width . 'w';
        }
        
        return sprintf(
            '<img src="%s" srcset="%s" alt="%s" sizes="(max-width: 480px) 100vw, (max-width: 768px) 90vw, 80vw">',
            htmlspecialchars($src),
            $srcset,
            htmlspecialchars($alt)
        );
    }
    
    /**
     * Minifier HTML
     * @param string $html - HTML à minifier
     * @return string - HTML minifié
     */
    public static function minifyHtml($html) {
        // Supprimer les commentaires
        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);
        
        // Supprimer les espaces inutiles
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        
        return trim($html);
    }
    
    /**
     * Analyser la performance
     * @return array - Métriques de performance
     */
    public static function getPerformanceMetrics() {
        return [
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'memory_peak' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
            'execution_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . ' ms',
            'database_queries' => isset($GLOBALS['db_query_count']) ? $GLOBALS['db_query_count'] : 'N/A'
        ];
    }
    
    /**
     * Ajouter les headers HTTP/2 Push
     */
    public static function http2Push() {
        if (!headers_sent() && isset($_SERVER['HTTP2'])) {
            header('Link: </public/css/styles.min.css>; rel=preload; as=style', false);
            header('Link: </public/js/app.min.js>; rel=preload; as=script', false);
        }
    }
}

?>
