(function () {
    'use strict';

    var btn, sidebar, isOpen = false, overlay = null, scrollY = 0;

    function openMenu() {
        if (isOpen) return;
        isOpen = true;
        scrollY = window.pageYOffset || document.documentElement.scrollTop;

        // Inline styles override ALL page CSS (no specificity battles)
        sidebar.style.transform = 'translateX(0)';
        sidebar.style.zIndex = '9999';
        sidebar.style.webkitOverflowScrolling = 'touch';
        btn.innerHTML = '<i class="fas fa-times"></i>';

        // Prevent body scroll on iOS (must save + restore position)
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.top = '-' + scrollY + 'px';
        document.body.style.width = '100%';

        if (!document.getElementById('mobile-overlay')) {
            overlay = document.createElement('div');
            overlay.id = 'mobile-overlay';
            overlay.style.cssText =
                'position:fixed;top:0;left:0;width:100%;height:100%;' +
                'background:rgba(0,0,0,0.6);z-index:9998;' +
                '-webkit-tap-highlight-color:transparent;cursor:pointer;';
            overlay.addEventListener('click', closeMenu);
            overlay.addEventListener('touchstart', function (e) {
                e.preventDefault();
                closeMenu();
            }, { passive: false });
            document.body.appendChild(overlay);
        }

        if (window.mobileMenu) window.mobileMenu.isOpen = true;
    }

    function closeMenu() {
        if (!isOpen) return;
        isOpen = false;

        sidebar.style.transform = '';
        sidebar.style.zIndex = '';
        btn.innerHTML = '<i class="fas fa-bars"></i>';

        // Restore body scroll + scroll position
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        window.scrollTo(0, scrollY);

        var ov = document.getElementById('mobile-overlay');
        if (ov) ov.remove();
        overlay = null;

        if (window.mobileMenu) window.mobileMenu.isOpen = false;
    }

    function toggleMenu() {
        if (isOpen) closeMenu();
        else openMenu();
    }

    function init() {
        btn = document.getElementById('menuButton');
        sidebar = document.querySelector('.sidebar');
        if (!btn || !sidebar) return;

        // --- Tap handling: touchstart+touchend for iOS reliability ---
        var tapX = 0, tapY = 0, tapTime = 0, touchHandled = false;

        btn.addEventListener('touchstart', function (e) {
            tapX = e.touches[0].clientX;
            tapY = e.touches[0].clientY;
            tapTime = Date.now();
            touchHandled = false;
        }, { passive: true });

        btn.addEventListener('touchend', function (e) {
            var dx = Math.abs(e.changedTouches[0].clientX - tapX);
            var dy = Math.abs(e.changedTouches[0].clientY - tapY);
            if (Date.now() - tapTime < 500 && dx < 12 && dy < 12) {
                touchHandled = true;
                e.preventDefault(); // suppress ghost click on iOS
                toggleMenu();
            }
        }, { passive: false });

        // click fires on desktop (mouse) only — suppressed on touch via touchHandled
        btn.addEventListener('click', function () {
            if (!touchHandled) toggleMenu();
            touchHandled = false;
        });

        // Close menu when a nav link is clicked on mobile
        var links = sidebar.querySelectorAll('a');
        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (window.innerWidth < 768 && !e.defaultPrevented) {
                    setTimeout(closeMenu, 60);
                }
            });
        });

        // Auto-close on resize to desktop
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 768 && isOpen) closeMenu();
        });

        window.mobileMenu = {
            isOpen: isOpen,
            openMenu: openMenu,
            closeMenu: closeMenu,
            toggleMenu: toggleMenu
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Legacy compat
    window.toggleMobileMenu = function () { toggleMenu(); };
})();
