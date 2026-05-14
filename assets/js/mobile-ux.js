// Mejoras de UX para dispositivos móviles
class MobileUX {
    constructor() {
        this.startX = 0;
        this.startY = 0;
        this.endX = 0;
        this.endY = 0;
        this.init();
    }

    init() {
        this.setupTouchGestures();
        this.setupResponsiveTables();
        this.setupScrollEffects();
        this.setupFormImprovements();
    }

    // Configurar gestos táctiles
    setupTouchGestures() {
        document.addEventListener('touchstart', (e) => {
            this.startX = e.touches[0].clientX;
            this.startY = e.touches[0].clientY;
        });

        document.addEventListener('touchend', (e) => {
            this.endX = e.changedTouches[0].clientX;
            this.endY = e.changedTouches[0].clientY;
            this.handleSwipe();
        });

        // Doble tap para zoom
        let lastTap = 0;
        document.addEventListener('touchend', (e) => {
            const currentTime = new Date().getTime();
            const tapLength = currentTime - lastTap;
            if (tapLength < 500 && tapLength > 0) {
                // Doble tap detectado
                this.handleDoubleTap(e);
            }
            lastTap = currentTime;
        });
    }

    handleSwipe() {
        const diffX = this.startX - this.endX;
        const diffY = this.startY - this.endY;
        const minSwipeDistance = 50;

        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > minSwipeDistance) {
            if (diffX > 0) {
                // Swipe izquierda
                this.handleSwipeLeft();
            } else {
                // Swipe derecha
                this.handleSwipeRight();
            }
        }
    }

    handleSwipeLeft() {
        // Navegar hacia adelante si es posible
        const nextButton = document.querySelector('[data-next]');
        if (nextButton) {
            nextButton.click();
        }
    }

    handleSwipeRight() {
        // Navegar hacia atrás
        const backButton = document.querySelector('[data-back]');
        if (backButton) {
            backButton.click();
        } else {
            // Volver atrás en el historial
            window.history.back();
        }
    }

    handleDoubleTap(e) {
        // Prevenir zoom en doble tap
        e.preventDefault();
    }

    // Configurar tablas responsivas
    setupResponsiveTables() {
        const tables = document.querySelectorAll('.table-modern');
        
        tables.forEach(table => {
            if (window.innerWidth < 768) {
                this.makeTableResponsive(table);
            }
        });

        // Escuchar cambios de tamaño de ventana
        window.addEventListener('resize', () => {
            tables.forEach(table => {
                if (window.innerWidth < 768) {
                    this.makeTableResponsive(table);
                } else {
                    this.makeTableNormal(table);
                }
            });
        });
    }

    makeTableResponsive(table) {
        if (table.classList.contains('mobile-responsive')) return;

        table.classList.add('mobile-responsive');
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const headerCells = table.querySelectorAll('thead th');
            
            cells.forEach((cell, index) => {
                if (headerCells[index]) {
                    const label = headerCells[index].textContent;
                    cell.setAttribute('data-label', label);
                }
            });
        });
    }

    makeTableNormal(table) {
        table.classList.remove('mobile-responsive');
        const cells = table.querySelectorAll('td[data-label]');
        cells.forEach(cell => {
            cell.removeAttribute('data-label');
        });
    }

    // Configurar efectos de scroll
    setupScrollEffects() {
        // Lazy loading para imágenes
        const images = document.querySelectorAll('img[data-src]');
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));

        // Scroll reveal para elementos
        const scrollElements = document.querySelectorAll('.scroll-reveal');
        const scrollObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                }
            });
        });

        scrollElements.forEach(el => scrollObserver.observe(el));
    }

    // Mejorar formularios en móvil
    setupFormImprovements() {
        // Auto-focus en el primer campo
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            const firstInput = form.querySelector('input, textarea, select');
            if (firstInput && window.innerWidth < 768) {
                setTimeout(() => {
                    firstInput.focus();
                }, 500);
            }
        });

        // Mejorar select en móvil
        const selects = document.querySelectorAll('select');
        selects.forEach(select => {
            if (window.innerWidth < 768) {
                select.addEventListener('focus', () => {
                    select.style.fontSize = '16px'; // Prevenir zoom en iOS
                });
            }
        });

        // Validación en tiempo real
        const inputs = document.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });
        });
    }

    validateField(field) {
        const value = field.value.trim();
        const isValid = field.checkValidity();
        
        if (!isValid && value !== '') {
            this.showFieldError(field);
        } else {
            this.hideFieldError(field);
        }
    }

    showFieldError(field) {
        field.classList.add('error');
        const errorMessage = field.validationMessage;
        
        let errorElement = field.parentNode.querySelector('.field-error');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'field-error text-red-600 text-sm mt-1';
            field.parentNode.appendChild(errorElement);
        }
        
        errorElement.textContent = errorMessage;
    }

    hideFieldError(field) {
        field.classList.remove('error');
        const errorElement = field.parentNode.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
    }

    // Utilidades móviles
    static isMobile() {
        return window.innerWidth < 768;
    }

    static isTablet() {
        return window.innerWidth >= 768 && window.innerWidth < 1024;
    }

    static isDesktop() {
        return window.innerWidth >= 1024;
    }

    // Vibrar en dispositivos móviles (si está disponible)
    static vibrate(pattern = 100) {
        if ('vibrate' in navigator) {
            navigator.vibrate(pattern);
        }
    }

    // Mostrar teclado virtual en móvil
    static showVirtualKeyboard(element) {
        if (this.isMobile() && element) {
            element.focus();
            element.click();
        }
    }
}

// Inicializar mejoras móviles
document.addEventListener('DOMContentLoaded', () => {
    window.mobileUX = new MobileUX();
});

// Exportar para uso global
window.MobileUX = MobileUX; 