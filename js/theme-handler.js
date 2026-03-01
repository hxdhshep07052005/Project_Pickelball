

(function() {
    'use strict';

    const getTheme = () => {
        const htmlTheme = document.documentElement.getAttribute('data-theme');
        if (htmlTheme) return htmlTheme;

        const bodyTheme = document.body.getAttribute('data-theme');
        if (bodyTheme) return bodyTheme;

        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'user_theme' && value) return value;
        }

        const stored = localStorage.getItem('user_theme');
        if (stored) return stored;

        return 'light';
    };

    const getLanguage = () => {
        const htmlLang = document.documentElement.getAttribute('lang');
        if (htmlLang) return htmlLang;

        const stored = localStorage.getItem('user_language');
        if (stored) return stored;

        return 'en';
    };

    const applyTheme = (theme) => {
        document.body.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme', theme);
        document.body.classList.remove('theme-light', 'theme-dark');
        document.body.classList.add('theme-' + theme);
        document.documentElement.classList.remove('theme-light', 'theme-dark');
        document.documentElement.classList.add('theme-' + theme);
        localStorage.setItem('user_theme', theme);
    };

    const applyLanguage = (lang) => {
        document.documentElement.setAttribute('lang', lang);
        localStorage.setItem('user_language', lang);
    };

    const init = () => {
        const theme = getTheme();
        const language = getLanguage();

        applyTheme(theme);
        applyLanguage(language);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.themeHandler = {
        applyTheme: applyTheme,
        applyLanguage: applyLanguage,
        getTheme: getTheme,
        getLanguage: getLanguage
    };
})();
