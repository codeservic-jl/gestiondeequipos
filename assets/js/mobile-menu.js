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
            link.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    this.closeMenu();
                }
            });
        });
    }

    setupResponsive() {
        // Verificar si estamos en móvil al cargar
        if (window.innerWidth < 768) {
            this.sidebar.classList.add('hidden');
        }
    }

    toggleMenu() {
        if (this.isOpen) {
            this.closeMenu();
        } else {
            this.openMenu();
        }
    }

    openMenu() {
        this.sidebar.classList.remove('hidden');
        this.sidebar.classList.add('active');
        this.isOpen = true;
        this.menuButton.innerHTML = '<i class="fas fa-times"></i>';
        
        // Deshabilitar scroll del body
        document.body.style.overflow = 'hidden';
        
        // Agregar overlay
        this.addOverlay();
    }

    closeMenu() {
        this.sidebar.classList.add('hidden');
        this.sidebar.classList.remove('active');
        this.isOpen = false;
        this.menuButton.innerHTML = '<i class="fas fa-bars"></i>';
        
        // Habilitar scroll del body
        document.body.style.overflow = '';
        
        // Remover overlay
        this.removeOverlay();
    }

    addOverlay() {
        if (!document.getElementById('mobile-overlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'mobile-overlay';
            overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-30 md:hidden';
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