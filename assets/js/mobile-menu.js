// Sistema de menú móvil para todas las páginas
class MobileMenu {
    constructor() {
        this.menuButton = null;
        this.sidebar = null;
        this.isOpen = false;
        this.init();
    }

    init() {
        // Buscar elementos del menú
        this.menuButton = document.getElementById('menuButton');
        this.sidebar = document.querySelector('.sidebar');
        
        if (this.menuButton && this.sidebar) {
            this.setupEventListeners();
            this.setupResponsive();
        }
    }

    setupEventListeners() {
        // Botón de menú
        this.menuButton.addEventListener('click', () => {
            this.toggleMenu();
        });

        // Cerrar menú al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.sidebar.contains(e.target) && !this.menuButton.contains(e.target)) {
                this.closeMenu();
            }
        });

        // Cerrar menú al redimensionar la ventana
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768 && this.isOpen) {
                this.closeMenu();
            }
        });

        // Cerrar menú al hacer clic en un enlace (en móvil)
        const menuLinks = this.sidebar.querySelectorAll('a');
        menuLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                if (window.innerWidth < 768 && !e.defaultPrevented) {
                    this.closeMenu();
                }
            });
        });
    }

    setupResponsive() {
        // El CSS ya maneja el estado inicial via transform, no se necesita display:none
    }

    toggleMenu() {
        if (this.isOpen) {
            this.closeMenu();
        } else {
            this.openMenu();
        }
    }

    openMenu() {
        this.sidebar.classList.add('active');
        this.isOpen = true;
        this.menuButton.innerHTML = '<i class="fas fa-times"></i>';
        document.body.style.overflow = 'hidden';
        this.addOverlay();
    }

    closeMenu() {
        this.sidebar.classList.remove('active');
        this.isOpen = false;
        this.menuButton.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.style.overflow = '';
        this.removeOverlay();
    }

    addOverlay() {
        if (!document.getElementById('mobile-overlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'mobile-overlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:45;';
            overlay.addEventListener('click', () => this.closeMenu());
            document.body.appendChild(overlay);
        }
    }

    removeOverlay() {
        const overlay = document.getElementById('mobile-overlay');
        if (overlay) {
            overlay.remove();
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.mobileMenu = new MobileMenu();
});

// Función global para compatibilidad
function toggleMobileMenu() {
    if (window.mobileMenu) {
        window.mobileMenu.toggleMenu();
    }
} 