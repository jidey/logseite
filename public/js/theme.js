/**
 * THEME.JS - Gestion du thème Dark/Light partagé
 * À inclure dans toutes les pages LOGG
 * Synchronisé avec index.php via localStorage 'logg-theme'
 */
(function() {
    // Appliquer le thème immédiatement (avant le rendu complet)
    function applyInitialTheme() {
        const savedTheme = localStorage.getItem('logg-theme');
        let isDark;
        if (savedTheme === 'dark') {
            isDark = true;
        } else if (savedTheme === 'light') {
            isDark = false;
        } else {
            isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        if (isDark) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        window.__loggIsDark = isDark;
    }
    applyInitialTheme();

    // Appliquer la classe sur body une fois disponible
    function applyBodyClass() {
        if (window.__loggIsDark) {
            document.body.classList.add('dark-mode');
            document.body.classList.remove('light-mode');
        } else {
            document.body.classList.add('light-mode');
            document.body.classList.remove('dark-mode');
        }
        updateThemeButton();
    }

    // Mettre à jour le libellé du bouton
    window.updateThemeButton = function() {
        const btn = document.getElementById('themeToggle');
        if (!btn) return;
        const isDark = document.body.classList.contains('dark-mode');
        btn.textContent = isDark ? '🌙 Dark' : '☀️ Light';
        btn.title = isDark ? 'Currently in Dark Mode' : 'Currently in Light Mode';
    };

    // Basculer le thème
    window.toggleTheme = function() {
        const isDark = document.body.classList.contains('dark-mode');
        if (isDark) {
            document.body.classList.remove('dark-mode');
            document.body.classList.add('light-mode');
            localStorage.setItem('logg-theme', 'light');
        } else {
            document.body.classList.add('dark-mode');
            document.body.classList.remove('light-mode');
            localStorage.setItem('logg-theme', 'dark');
        }
        updateThemeButton();
    };

    // Au chargement du DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyBodyClass);
    } else {
        applyBodyClass();
    }
})();
