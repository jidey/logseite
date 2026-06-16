<?php
/**
 * LAZYLOAD.PHP
 * Gestionnaire de chargement lazy des images
 * Utilise l'Intersection Observer API (JavaScript côté client)
 */

class LazyLoad {
    
    /**
     * Générer une image avec lazy loading
     * @param string $src - URL source
     * @param string $alt - Texte alternatif
     * @param string $title - Titre (optionnel)
     * @param string $class - Classe CSS (optionnel)
     * @return string - HTML de l'image
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
     * Générer une image responsive avec lazy loading
     * @param string $src - URL source (mobile)
     * @param string $srcDesktop - URL source desktop (optionnel)
     * @param string $alt - Texte alternatif
     * @return string - HTML de l'image responsive
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
     * Générer une image background avec lazy loading
     * @param string $src - URL source
     * @param string $selector - Sélecteur CSS
     * @return string - Style inline
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
