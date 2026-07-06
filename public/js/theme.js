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
        applyTheme(window.__loggIsDark);
        updateThemeButton();
        // Activer les transitions APRÈS le premier rendu (évite le fondu blanc→noir au load)
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                document.body.classList.add('theme-ready');
            });
        });
    }

    // Mettre à jour le libellé du bouton
    window.updateThemeButton = function() {
        const btn = document.getElementById('themeToggle');
        if (!btn) return;
        const isDark = document.body.classList.contains('dark-mode');
        btn.textContent = isDark ? '🌙 Dark' : '☀️ Light';
        btn.title = isDark ? 'Currently in Dark Mode' : 'Currently in Light Mode';
    };

    // Appliquer un thème de façon cohérente (html + body)
    function applyTheme(isDark) {
        if (isDark) {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.body.classList.add('dark-mode');
            document.body.classList.remove('light-mode');
        } else {
            document.documentElement.removeAttribute('data-theme');
            document.body.classList.add('light-mode');
            document.body.classList.remove('dark-mode');
        }
        window.__loggIsDark = isDark;
    }

    // Basculer le thème (basé sur localStorage, source de vérité)
    window.toggleTheme = function() {
        const current = localStorage.getItem('logg-theme');
        const isDarkNow = (current === 'dark');
        const newTheme = isDarkNow ? 'light' : 'dark';
        localStorage.setItem('logg-theme', newTheme);
        applyTheme(newTheme === 'dark');
        updateThemeButton();
    };

    // Au chargement du DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyBodyClass);
    } else {
        applyBodyClass();
    }
})();