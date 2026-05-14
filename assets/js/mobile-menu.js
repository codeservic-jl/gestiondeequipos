(function () {
    'use strict';

    var btn, sidebar, isOpen = false, overlay = null;

    function openMenu() {
        if (isOpen) return;
        isOpen = true;

        // Controlar sidebar por inline style (evita conflictos CSS de cualquier página)
        sidebar.style.transform = 'translateX(0)';
        sidebar.style.zIndex = '9999';
        btn.innerHTML = '<i class="fas fa-times"></i>';

        // Prevenir scroll del body (sin position:fixed que rompe fixed overlay en iOS)
        document.body.style.overflow = 'hidden';

        // Crear overlay — se agrega al <html> (no al body) para evitar el bug
        // de iOS donde fixed children de position:fixed body se posicionan mal
        if (!document.getElementById('mobile-overlay')) {
            overlay = document.createElement('div');
            overlay.id = 'mobile-overlay';
            overlay.style.position   = 'fixed';
            overlay.style.top        = '0';
            overlay.style.left       = '0';
            overlay.style.width      = '100vw';
            overlay.style.height     = '100vh';
            overlay.style.background = 'rgba(0,0,0,0.6)';
            overlay.style.zIndex     = '9998';
            overlay.style.cursor     = 'pointer';
            overlay.style.webkitTapHighlightColor = 'transparent';

            // Bloquear scroll a través del overlay en iOS
            overlay.addEventListener('touchmove', function (e) {
                e.preventDefault();
            }, { passive: false });

            overlay.addEventListener('click', closeMenu);
            overlay.addEventListener('touchend', function (e) {
                e.preventDefault();
                closeMenu();
            }, { passive: false });

            // Adjuntar al <html> en lugar de body para evitar el bug de iOS
            document.documentElement.appendChild(overlay);
        }

        if (window.mobileMenu) window.mobileMenu.isOpen = true;
    }

    function closeMenu() {
        if (!isOpen) return;
        isOpen = false;

        sidebar.style.transform = '';
        sidebar.style.zIndex = '';
        btn.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.style.overflow = '';

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
        btn     = document.getElementById('menuButton');
        sidebar = document.querySelector('.sidebar');
        if (!btn || !sidebar) return;

        // Manejo de tap confiable en iOS: touchstart + touchend
        var tapX = 0, tapY = 0, tapTime = 0, touchHandled = false;

        btn.addEventListener('touchstart', function (e) {
            tapX  = e.touches[0].clientX;
            tapY  = e.touches[0].clientY;
            tapTime = Date.now();
            touchHandled = false;
        }, { passive: true });

        btn.addEventListener('touchend', function (e) {
            var dx = Math.abs(e.changedTouches[0].clientX - tapX);
            var dy = Math.abs(e.changedTouches[0].clientY - tapY);
            if (Date.now() - tapTime < 500 && dx < 12 && dy < 12) {
                touchHandled = true;
                e.preventDefault(); // evitar ghost click en iOS
                toggleMenu();
            }
        }, { passive: false });

        // click solo dispara en desktop (mouse); en touch ya fue manejado arriba
        btn.addEventListener('click', function () {
            if (!touchHandled) toggleMenu();
            touchHandled = false;
        });

        // Cerrar al tocar un enlace del menú en móvil
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (window.innerWidth < 768 && !e.defaultPrevented) {
                    setTimeout(closeMenu, 60);
                }
            });
        });

        // Cerrar al rotar a desktop
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

    window.toggleMobileMenu = function () { toggleMenu(); };
})();
