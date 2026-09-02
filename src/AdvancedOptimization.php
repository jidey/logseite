<?php
/**
 * ADVANCEDOPTIMIZATION.PHP
 * Advanced optimizations: GZIP, CDN, Service Worker, etc.
 */

class AdvancedOptimization {

    /**
     * Enable GZIP compression
     * To be called at the start of the PHP script
     */
    public static function enableGzip() {
        if (!headers_sent()) {
            // Check the browser's GZIP support
            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                ob_start('ob_gzhandler');
            }
        }
    }
    
    /**
     * Add the cache headers
     * @param int $ttl - Time to live in seconds
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
     * Add the security headers
     */
    public static function setSecurityHeaders() {
        if (!headers_sent()) {
            // Prevent clickjacking
            header('X-Frame-Options: SAMEORIGIN');
            // Prevent MIME sniffing
            header('X-Content-Type-Options: nosniff');
            // Enable XSS Protection
            header('X-XSS-Protection: 1; mode=block');
            // Content Security Policy (basic)
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net");
        }
    }
    
    /**
     * Add the performance headers
     */
    public static function setPerformanceHeaders() {
        if (!headers_sent()) {
            // Preload the critical resources
            header('Link: </public/css/styles.min.css>; rel=preload; as=style', false);
            header('Link: </public/js/app.min.js>; rel=preload; as=script', false);
            // DNS Prefetch for CDN
            header('Link: <https://cdn.jsdelivr.net>; rel=dns-prefetch', false);
        }
    }
    
    /**
     * Generate a Service Worker registration script
     * @return string - HTML script
     */
    public static function registerServiceWorker() {
        return <<<'HTML'
<script>
// Register the Service Worker for offline support
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
     * Add the preload for fonts
     * @param array $fonts - Array of font URLs
     */
    public static function preloadFonts($fonts = []) {
        if (!headers_sent()) {
            foreach ($fonts as $font) {
                header('Link: <' . htmlspecialchars($font) . '>; rel=preload; as=font; crossorigin', false);
            }
        }
    }
    
    /**
     * Optimize images with srcset
     * @param string $src - URL mobile/small screen
     * @param array $sizes - Array of sizes [480=>url, 768=>url, 1200=>url]
     * @param string $alt - Alt text
     * @return string - HTML img tag with srcset
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
     * Minify HTML
     * @param string $html - HTML to minify
     * @return string - Minified HTML
     */
    public static function minifyHtml($html) {
        // Remove the comments
        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);

        // Remove unnecessary whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        
        return trim($html);
    }
    
    /**
     * Analyze performance
     * @return array - Performance metrics
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
     * Add the HTTP/2 Push headers
     */
    public static function http2Push() {
        if (!headers_sent() && isset($_SERVER['HTTP2'])) {
            header('Link: </public/css/styles.min.css>; rel=preload; as=style', false);
            header('Link: </public/js/app.min.js>; rel=preload; as=script', false);
        }
    }
}

?>
