// Sistema de Notificaciones Moderno
class NotificationSystem {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Crear contenedor de notificaciones si no existe
        if (!document.getElementById('notificationContainer')) {
            this.container = document.createElement('div');
            this.container.id = 'notificationContainer';
            this.container.className = 'fixed top-4 right-4 z-50 w-full max-w-sm space-y-2';
            document.body.appendChild(this.container);
        } else {
            this.container = document.getElementById('notificationContainer');
        }
    }

    show(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type} slide-in-right`;
        
        const icon = this.getIcon(type);
        const bgColor = this.getBgColor(type);
        const borderColor = this.getBorderColor(type);
        const textColor = this.getTextColor(type);

        notification.innerHTML = `
            <div class="transform transition-all duration-300 ease-in-out mb-4 ${bgColor} border-l-4 ${borderColor} rounded-lg shadow-lg">
                <div class="flex items-center p-4">
                    <div class="flex-shrink-0">
                        <i class="${icon} ${textColor} text-xl"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm ${textColor}">
                            ${message}
                        </p>
                    </div>
                    <div class="ml-4 flex-shrink-0">
                        <button onclick="this.parentElement.parentElement.parentElement.remove()" 
                                class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;

        this.container.appendChild(notification);

        // Auto-hide después del tiempo especificado
        if (duration > 0) {
            setTimeout(() => {
                this.hide(notification);
            }, duration);
        }

        return notification;
    }

    hide(notification) {
        if (notification && notification.parentNode) {
            notification.classList.add('slide-out-right');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }

    success(message, duration = 5000) {
        return this.show(message, 'success', duration);
    }

    error(message, duration = 7000) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration = 6000) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration = 5000) {
        return this.show(message, 'info', duration);
    }

    getIcon(type) {
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    getBgColor(type) {
        const colors = {
            success: 'bg-green-100',
            error: 'bg-red-100',
            warning: 'bg-yellow-100',
            info: 'bg-blue-100'
        };
        return colors[type] || colors.info;
    }

    getBorderColor(type) {
        const colors = {
            success: 'border-green-500',
            error: 'border-red-500',
            warning: 'border-yellow-500',
            info: 'border-blue-500'
        };
        return colors[type] || colors.info;
    }

    getTextColor(type) {
        const colors = {
            success: 'text-green-600',
            error: 'text-red-600',
            warning: 'text-yellow-600',
            info: 'text-blue-600'
        };
        return colors[type] || colors.info;
    }
}

// Inicializar sistema de notificaciones cuando el DOM esté listo
let notifications;
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { notifications = new NotificationSystem(); window.notifications = notifications; });
} else {
    notifications = new NotificationSystem();
    window.notifications = notifications;
}

// Función global para mostrar notificaciones
function showNotification(message, type = 'info', duration = 5000) {
    return notifications.show(message, type, duration);
}

// Función para mostrar notificaciones de éxito
function showSuccess(message, duration = 5000) {
    return notifications.success(message, duration);
}

// Función para mostrar notificaciones de error
function showError(message, duration = 7000) {
    return notifications.error(message, duration);
}

// Función para mostrar notificaciones de advertencia
function showWarning(message, duration = 6000) {
    return notifications.warning(message, duration);
}

// Función para mostrar notificaciones informativas
function showInfo(message, duration = 5000) {
    return notifications.info(message, duration);
}

// Auto-hide notificaciones existentes después de 5 segundos
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('#alertContainer > div');
        alerts.forEach(alert => {
            alert.style.display = 'none';
        });
    }, 5000);
});

// Exportar para uso global
window.notifications = notifications;
window.showNotification = showNotification;
window.showSuccess = showSuccess;
window.showError = showError;
window.showWarning = showWarning;
window.showInfo = showInfo; 