<?php
if (!isset($_SESSION)) {
    session_start();
}

// Definir la URL base si no está definida
if (!isset($base_url)) {
    $base_url = '/gestionequipos/';
}

// Función para detectar la página actual
function getCurrentPage() {
    $current_path = $_SERVER['REQUEST_URI'];
    $current_file = basename($_SERVER['PHP_SELF']);
    
    // Verificar por directorios primero (más específico)
    if (strpos($current_path, '/parametros/') !== false) {
        // Submenús de parámetros
        if (strpos($current_path, 'empresa.php') !== false) {
            return 'parametros-empresa';
        }
        if (strpos($current_path, 'estados_orden.php') !== false) {
            return 'parametros-estados';
        }
        if (strpos($current_path, 'tipos_equipo.php') !== false) {
            return 'parametros-equipos';
        }
        if (strpos($current_path, 'tipos_servicio.php') !== false) {
            return 'parametros-servicios';
        }
        return 'parametros';
    }
    
    if (strpos($current_path, '/ordenes/') !== false) {
        return 'ordenes';
    }
    
    if (strpos($current_path, '/clientes/') !== false) {
        return 'clientes';
    }
    
    if (strpos($current_path, '/equipos/') !== false) {
        return 'equipos';
    }
    
    if (strpos($current_path, '/usuarios/') !== false) {
        return 'usuarios';
    }
    
    if (strpos($current_path, '/sucursales/') !== false) {
        return 'sucursales';
    }
    
    // Verificar páginas específicas
    if ($current_file === 'index.php' && strpos($current_path, '/modules/') === false) {
        return 'dashboard';
    }
    
    // Páginas especiales
    if (strpos($current_path, 'test_mejoras.php') !== false) {
        return 'dashboard';
    }
    
    return 'dashboard';
}

$current_page = getCurrentPage();
?>
<style>
    .bg-primary-pink { background-color:#1a3318; }
    .hover-bg-primary-pink:hover { background-color:#2a4e27; }

    /* Estilos para elementos de menú activos */
    .nav-item {
        display: flex;
        align-items: center;
        padding: 0.875rem 1.5rem;
        color: #d1d5db;
        transition: all 0.2s ease-in-out;
        text-decoration: none;
    }

    .nav-item:hover {
        background-color: #2a4e27;
        color: white;
        padding: 0.875rem 1.5rem;
    }

    .nav-item.active {
        background: linear-gradient(135deg, #3d9939 0%, #5AC456 100%);
        color: white;
        border-right: 4px solid #fbbf24;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        position: relative;
        padding: 1rem 1.5rem;
        margin: 0.125rem 0;
    }

    .nav-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, #fbbf24 0%, #f59e0b 100%);
        box-shadow: 0 0 10px rgba(251, 191, 36, 0.5);
    }

    .nav-item.active i {
        color: #fbbf24;
        animation: pulse-glow 2s infinite;
    }

    .nav-item.active span {
        font-weight: 600;
    }

    @keyframes pulse-glow {
        0%, 100% {
            text-shadow: 0 0 5px rgba(251, 191, 36, 0.5);
        }
        50% {
            text-shadow: 0 0 15px rgba(251, 191, 36, 0.8), 0 0 20px rgba(251, 191, 36, 0.6);
        }
    }

    /* Estilos para submenús activos */
    .submenu-item {
        display: flex;
        align-items: center;
        padding: 0.75rem 1.5rem 0.75rem 3rem;
        color: #d1d5db;
        transition: all 0.2s ease-in-out;
        text-decoration: none;
    }

    .submenu-item:hover {
        background-color: #2a4e27;
        color: white;
        padding: 0.75rem 1.5rem 0.75rem 3rem;
    }

    .submenu-item.active {
        background: linear-gradient(135deg, #3d9939 0%, #5AC456 100%);
        color: white;
        border-right: 4px solid #fbbf24;
        position: relative;
        padding: 0.875rem 1.5rem 0.875rem 3rem;
        margin: 0.125rem 0;
    }

    .submenu-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(180deg, #fbbf24 0%, #f59e0b 100%);
    }

    .submenu-item.active i {
        color: #fbbf24;
    }

    .submenu-item.active span {
        font-weight: 600;
    }

    /* Responsive para menú activo */
    @media (max-width: 768px) {
        .nav-item.active,
        .submenu-item.active {
            background-color: #5AC456;
            color: white;
            border-right: 4px solid #fbbf24;
        }

        .nav-item.active i,
        .submenu-item.active i {
            color: #fbbf24;
        }

        .nav-item.active span,
        .submenu-item.active span {
            font-weight: 600;
        }
    }
    
    .sidebar {
        width: 250px;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 40;
        transition: transform 0.3s ease-in-out;
    }
    
    .main-content {
        margin-left: 250px;
        transition: margin-left 0.3s ease-in-out;
    }
    
    /* Breakpoint específico para tablets */
    @media (min-width: 769px) and (max-width: 1024px) {
        .sidebar {
            width: 200px !important;
        }
        
        .main-content {
            margin-left: 200px !important;
        }
        
        /* Ajustar el botón de menú para tablets */
        #menuButton {
            display: none !important;
        }
        
        /* Optimizaciones para formularios en tablets */
        .main-content {
            padding: 1rem !important;
        }
        
        /* Ajustar grid layouts en tablets */
        .grid {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
            gap: 1.5rem !important;
        }
        
        /* Mejorar espaciado en formularios */
        .main-content form {
            max-width: 100% !important;
        }
        
        /* Optimizar campos de entrada */
        .main-content input,
        .main-content select,
        .main-content textarea {
            font-size: 16px !important;
            padding: 0.75rem !important;
        }
        
        /* Ajustar botones */
        .main-content button {
            padding: 0.75rem 1.5rem !important;
            font-size: 1rem !important;
        }
        
        /* Optimizar cards y contenedores */
        .main-content .bg-white,
        .main-content .card {
            border-radius: 12px !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05) !important;
            padding: 1.5rem !important;
            margin-bottom: 1rem !important;
        }
        
        /* Mejorar espaciado de títulos */
        .main-content h1,
        .main-content h2 {
            font-size: 1.5rem !important;
            margin-bottom: 1rem !important;
        }
        
        /* Optimizar campos de formulario específicos */
        .main-content input[type="text"],
        .main-content input[type="email"],
        .main-content input[type="tel"],
        .main-content input[type="number"],
        .main-content input[type="password"],
        .main-content select,
        .main-content textarea {
            font-size: 16px !important;
            padding: 12px !important;
            border-radius: 8px !important;
            border: 2px solid #e5e7eb !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Focus states */
        .main-content input:focus,
        .main-content select:focus,
        .main-content textarea:focus {
            outline: none !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }
        
        /* Botones optimizados para touch */
        .main-content button,
        .main-content .btn {
            padding: 12px 20px !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            border: none !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            min-height: 44px !important;
        }
        
        /* Layout de dos columnas que se adapta */
        .main-content .grid-cols-2 {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
        }
    }
    
    @media (max-width: 768px) {
        body {
            padding-top: 4rem; /* espacio para el botón hamburguesa fijo */
        }

        .sidebar {
            transform: translateX(-100%);
            z-index: 50;
            /* NO usar display:none: mantener en DOM para que la transición CSS funcione */
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
        }

        #menuButton {
            z-index: 60;
            transition: all 0.3s ease;
        }

        #menuButton:hover {
            transform: scale(1.1);
        }
    }
</style>

<!-- Sidebar -->
<nav class="sidebar bg-[#1a3318] text-white shadow-lg">
    <div class="p-6 flex flex-col items-center border-b border-gray-700">
        <div class="w-20 h-20 rounded-full bg-white overflow-hidden mb-4">
            <!-- Si tienes una imagen de usuario, reemplaza el div por una imagen -->
            <div class="w-full h-full bg-gray-300"></div>
        </div>
        <h2 class="text-lg font-semibold">Bienvenido</h2>
        <p class="text-sm text-gray-300"><?php echo $_SESSION['nombre_completo']; ?></p>
    </div>

    <div class="py-4">
        <a href="<?php echo $base_url; ?>index.php" 
           class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt w-5"></i>
            <span class="ml-3">Dashboard</span>
        </a>

        <a href="<?php echo $base_url; ?>modules/ordenes/lista.php" 
           class="nav-item <?php echo $current_page === 'ordenes' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list w-5"></i>
            <span class="ml-3">Órdenes</span>
        </a>

        <a href="<?php echo $base_url; ?>modules/clientes/lista.php" 
           class="nav-item <?php echo $current_page === 'clientes' ? 'active' : ''; ?>">
            <i class="fas fa-users w-5"></i>
            <span class="ml-3">Clientes</span>
        </a>

        <a href="<?php echo $base_url; ?>modules/equipos/lista.php" 
           class="nav-item <?php echo $current_page === 'equipos' ? 'active' : ''; ?>">
            <i class="fas fa-laptop w-5"></i>
            <span class="ml-3">Equipos</span>
        </a>

        <?php if ($_SESSION['user_type'] == 1): ?>
        <a href="<?php echo $base_url; ?>modules/usuarios/lista.php" 
           class="nav-item <?php echo $current_page === 'usuarios' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield w-5"></i>
            <span class="ml-3">Usuarios</span>
        </a>

        <a href="<?php echo $base_url; ?>modules/sucursales/lista.php" 
           class="nav-item <?php echo $current_page === 'sucursales' ? 'active' : ''; ?>">
            <i class="fas fa-building w-5"></i>
            <span class="ml-3">Sucursales</span>
        </a>

        <!-- Menú de Parámetros -->
        <div class="border-t border-gray-700 mt-4 pt-4">
            <div class="px-6 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                Parámetros
            </div>
            <a href="<?php echo $base_url; ?>modules/parametros/empresa.php" 
               class="submenu-item <?php echo $current_page === 'parametros-empresa' ? 'active' : ''; ?>">
                <i class="fas fa-cog w-5"></i>
                <span class="ml-3">Info. Empresa</span>
            </a>
            <a href="<?php echo $base_url; ?>modules/parametros/estados_orden.php" 
               class="submenu-item <?php echo $current_page === 'parametros-estados' ? 'active' : ''; ?>">
                <i class="fas fa-list-alt w-5"></i>
                <span class="ml-3">Estados de Orden</span>
            </a>
            <a href="<?php echo $base_url; ?>modules/parametros/tipos_equipo.php" 
               class="submenu-item <?php echo $current_page === 'parametros-equipos' ? 'active' : ''; ?>">
                <i class="fas fa-laptop w-5"></i>
                <span class="ml-3">Tipos de Equipo</span>
            </a>
            <a href="<?php echo $base_url; ?>modules/parametros/tipos_servicio.php" 
               class="submenu-item <?php echo $current_page === 'parametros-servicios' ? 'active' : ''; ?>">
                <i class="fas fa-tools w-5"></i>
                <span class="ml-3">Tipos de Servicio</span>
            </a>
        </div>
        <?php endif; ?>

        <a href="<?php echo $base_url; ?>logout.php" 
           onclick="return confirm('⚠️ ¿Está seguro de que desea cerrar sesión?\n\nSe perderá el acceso al sistema.')"
           class="logout-button logout-warning flex items-center px-6 py-4 text-white mt-auto transition-all duration-300 border-l-4 border-red-400 shadow-lg hover:shadow-xl transform hover:scale-105">
            <i class="fas fa-sign-out-alt w-5 text-xl"></i>
            <span class="ml-3 font-bold text-lg">Cerrar Sesión</span>
            <div class="ml-auto warning-indicator bg-red-400 text-white text-xs px-3 py-1 rounded-full">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </a>
    </div>
</nav>

<!-- Botón de menú móvil -->
<button id="menuButton" class="fixed top-4 left-4 z-50 md:hidden bg-[#5AC456] p-2 rounded-lg text-white shadow-lg">
    <i class="fas fa-bars"></i>
</button>

<!-- Incluir el script del menú móvil -->
<script src="<?php echo $base_url; ?>assets/js/mobile-menu.js"></script>

<script>
// Mejoras para el botón de cerrar sesión
document.addEventListener('DOMContentLoaded', function() {
    const logoutButton = document.querySelector('a[href*="logout.php"]');
    if (logoutButton) {
        // Agregar efecto de vibración al hacer hover
        logoutButton.addEventListener('mouseenter', function() {
            this.style.animation = 'shake 0.5s ease-in-out';
        });
        
        logoutButton.addEventListener('mouseleave', function() {
            this.style.animation = '';
        });
        
        // Confirmación mejorada
        logoutButton.addEventListener('click', function(e) {
            const confirmed = confirm('⚠️ ADVERTENCIA ⚠️\n\n¿Está seguro de que desea cerrar sesión?\n\n• Se perderá el acceso al sistema\n• Deberá volver a iniciar sesión\n• Cualquier trabajo no guardado se perderá\n\n¿Desea continuar?');
            if (!confirmed) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>

<style>
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-2px); }
    75% { transform: translateX(2px); }
}

/* Efecto de resplandor para el botón de logout */
.logout-button {
    position: relative;
}

.logout-button::after {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(45deg, #ff0000, #ff4444, #ff0000);
    border-radius: inherit;
    z-index: -1;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.logout-button:hover::after {
    opacity: 0.3;
    animation: glow 1.5s ease-in-out infinite alternate;
}

@keyframes glow {
    from {
        box-shadow: 0 0 5px #ff0000, 0 0 10px #ff0000, 0 0 15px #ff0000;
    }
    to {
        box-shadow: 0 0 10px #ff0000, 0 0 20px #ff0000, 0 0 30px #ff0000;
    }
}
</style>


