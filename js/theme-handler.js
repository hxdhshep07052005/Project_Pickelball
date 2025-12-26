/**
 * Theme and Language Handler
 * Manages theme switching and language preferences
 */

(function() {
    'use strict';

    // Get user preferences from session/cookie or use defaults
    const getTheme = () => {
        // Try to get from data attribute first (set by PHP)
        const htmlTheme = document.documentElement.getAttribute('data-theme');
        if (htmlTheme) return htmlTheme;
        
        const bodyTheme = document.body.getAttribute('data-theme');
        if (bodyTheme) return bodyTheme;
        
        // Try cookie
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'user_theme' && value) return value;
        }
        
        // Try localStorage
        const stored = localStorage.getItem('user_theme');
        if (stored) return stored;
        
        // Default
        return 'light';
    };

    const getLanguage = () => {
        // Try to get from data attribute first (set by PHP)
        const htmlLang = document.documentElement.getAttribute('lang');
        if (htmlLang) return htmlLang;
        
        // Try localStorage
        const stored = localStorage.getItem('user_language');
        if (stored) return stored;
        
        // Default
        return 'en';
    };

    // Apply theme
    const applyTheme = (theme) => {
        document.body.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme', theme);
        document.body.classList.remove('theme-light', 'theme-dark');
        document.body.classList.add('theme-' + theme);
        document.documentElement.classList.remove('theme-light', 'theme-dark');
        document.documentElement.classList.add('theme-' + theme);
        localStorage.setItem('user_theme', theme);
    };

    // Apply language
    const applyLanguage = (lang) => {
        document.documentElement.setAttribute('lang', lang);
        localStorage.setItem('user_language', lang);
    };

    // Initialize on page load
    const init = () => {
        const theme = getTheme();
        const language = getLanguage();
        
        applyTheme(theme);
        applyLanguage(language);
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Export functions for global use
    window.themeHandler = {
        applyTheme: applyTheme,
        applyLanguage: applyLanguage,
        getTheme: getTheme,
        getLanguage: getLanguage
    };
})();

