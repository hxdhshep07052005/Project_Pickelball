(function() {
    'use strict';

    function removeLoadingClass() {
        document.body.classList.remove('page-loading');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removeLoadingClass);
    } else {
        removeLoadingClass();
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#' || href === '') return;

                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const links = document.querySelectorAll('a[href]');

        links.forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                const url = new URL(href, window.location.origin);

                if (href.startsWith('#') ||
                    href.startsWith('javascript:') ||
                    href.startsWith('mailto:') ||
                    href.startsWith('tel:') ||
                    (url.origin !== window.location.origin && !href.startsWith('/'))) {
                    return;
                }

                if (url.pathname === window.location.pathname && url.search === window.location.search) {
                    return;
                }

                if (url.pathname !== window.location.pathname) {
                    document.body.style.opacity = '0.7';
                    document.body.style.transition = 'opacity 0.2s ease-out';
                }
            });
        });
    });
})();
