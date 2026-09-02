<?php
/**
 * LAZYLOAD.PHP
 * Lazy loading manager for images
 * Uses the Intersection Observer API (client-side JavaScript)
 */

class LazyLoad {

    /**
     * Generate an image with lazy loading
     * @param string $src - Source URL
     * @param string $alt - Alt text
     * @param string $title - Title (optional)
     * @param string $class - CSS class (optional)
     * @return string - Image HTML
     */
    public static function image($src, $alt = '', $title = '', $class = '') {
        $classStr = $class ? " class=\"$class\"" : '';
        $titleStr = $title ? " title=\"$title\"" : '';
        
        // Placeholder blurred (base64 encoded 1x1 transparent PNG)
        $placeholder = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f0f0f0" width="400" height="300"/%3E%3C/svg%3E';
        
        return sprintf(
            '<img src="%s" data-src="%s" alt="%s"%s%s loading="lazy">',
            htmlspecialchars($placeholder),
            htmlspecialchars($src),
            htmlspecialchars($alt),
            $titleStr,
            $classStr
        );
    }
    
    /**
     * Generate a responsive image with lazy loading
     * @param string $src - Source URL (mobile)
     * @param string $srcDesktop - Desktop source URL (optional)
     * @param string $alt - Alt text
     * @return string - Responsive image HTML
     */
    public static function imageResponsive($src, $srcDesktop = '', $alt = '') {
        $placeholder = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f0f0f0" width="400" height="300"/%3E%3C/svg%3E';
        $desktopAttr = $srcDesktop ? " data-src-desktop=\"" . htmlspecialchars($srcDesktop) . "\"" : '';
        
        return sprintf(
            '<img src="%s" data-src="%s" alt="%s"%s loading="lazy">',
            htmlspecialchars($placeholder),
            htmlspecialchars($src),
            htmlspecialchars($alt),
            $desktopAttr
        );
    }
    
    /**
     * Generate a background image with lazy loading
     * @param string $src - Source URL
     * @param string $selector - CSS selector
     * @return string - Inline style
     */
    public static function backgroundImage($src, $selector = 'div') {
        return sprintf(
            '<%s class="lazy-bg" data-src="%s" style="background-color: #f0f0f0;"></%s>',
            $selector,
            htmlspecialchars($src),
            $selector
        );
    }
}

?>
