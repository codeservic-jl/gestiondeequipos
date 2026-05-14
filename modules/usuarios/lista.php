<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// Configuración de paginación
$items_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$termino_busqueda = isset($_GET['buscar']) ? $_GET['buscar'] : '';

// Construir la consulta base
$query = "SELECT u.*, t.nombre as tipo_usuario, s.nombre as nombre_sucursal 
          FROM usuarios u 
          LEFT JOIN tipos_usuario t ON u.id_tipo = t.id_tipo 
          LEFT JOIN sucursales s ON u.id_sucursal = s.id_sucursal 
          WHERE 1=1";

$params = [];

// Añadir condiciones de búsqueda si existe un término
if ($termino_busqueda) {
    $query .= " AND (u.nombre_completo LIKE :busqueda OR u.usuario LIKE :busqueda OR t.nombre LIKE :busqueda OR s.nombre LIKE :busqueda)";
    $params['busqueda'] = "%$termino_busqueda%";
}

$query .= " ORDER BY u.nombre_completo";

// Obtener total de registros para la paginación
$stmt = $conn->prepare($query);
$stmt->execute($params);
$total_registros = $stmt->rowCount();
$total_paginas = ceil($total_registros / $items_por_pagina);

// Añadir límite para la paginación
$offset = ($pagina_actual - 1) * $items_por_pagina;
$query .= " LIMIT :offset, :limit";
$params['offset'] = $offset;
$params['limit'] = $items_por_pagina;

// Ejecutar la consulta final
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$usuarios = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .bg-navy-blue {
            background-color: #5AC456;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>

    <!-- Se muestran los mensajesluego de eliminar un registro -->
    <div id="alertContainer" class="fixed top-4 right-4 z-50 w-full max-w-sm">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="transform transition-all duration-300 ease-in-out mb-4 bg-red-100 border-l-4 border-red-500 rounded-lg shadow-lg"
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 5000)"
                @click.away="show = false">
                <div class="flex items-center p-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">
                            <?php echo $_SESSION['error'];
                            unset($_SESSION['error']); ?>
                        </p>
                    </div>
                    <div class="ml-auto pl-3">
                        <div class="-mx-1.5 -my-1.5">
                            <button @click="show = false" class="text-red-500 hover:text-red-600 rounded-md p-1.5 focus:outline-none">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="transform transition-all duration-300 ease-in-out mb-4 bg-green-100 border-l-4 border-green-500 rounded-lg shadow-lg"
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 5000)"
                @click.away="show = false">
                <div class="flex items-center p-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">
                            <?php echo $_SESSION['success'];
                            unset($_SESSION['success']); ?>
                        </p>
                    </div>
                    <div class="ml-auto pl-3">
                        <div class="-mx-1.5 -my-1.5">
                            <button @click="show = false" class="text-green-500 hover:text-green-600 rounded-md p-1.5 focus:outline-none">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>


    <div class="main-content">
        <div class="container mx-auto px-4 py-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-navy-blue">Gestión de Usuarios</h1>
                <div class="flex flex-col md:flex-row items-center gap-4">
                    <div class="relative w-full md:w-64">
                        <input type="text" id="searchInput"
                            placeholder="Buscar usuario..."
                            class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button class="absolute right-3 top-2.5 text-gray-500">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <a href="nuevo.php" class="bg-navy-blue text-white px-4 py-2 rounded-lg hover:bg-green-700">
                        <i class="fas fa-plus"></i> Nuevo Usuario
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-x-auto">
                <table class="min-w-full table-auto" id="dataTable">
                    <thead class="bg-navy-blue text-white">
                        <tr>
                            <th class="px-4 py-2">Acciones</th>
                            <th class="px-4 py-2">Estado</th>
                            <th class="px-4 py-2">Nombre</th>
                            <th class="px-4 py-2">Usuario</th>
                            <th class="px-4 py-2">Tipo</th>
                            <th class="px-4 py-2">Sucursal</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="bg-white divide-y divide-gray-200">
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm">
                                    <a href="editar.php?id=<?php echo $usuario['id_usuario']; ?>"
                                        class="text-navy-blue hover:text-green-700 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                <?php if( $usuario['estado'] !== 0): ?>
                                    <a href="#" onclick="confirmarEliminacion(<?php echo $usuario['id_usuario']; ?>)"
                                        class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">

                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $usuario['estado'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $usuario['estado'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($usuario['tipo_usuario']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($usuario['nombre_sucursal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
                <div id="paginationContainer" class="mt-6 flex justify-center">
                    <nav class="flex space-x-2">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <a href="?pagina=<?php echo $i; ?><?php echo $termino_busqueda ? '&buscar=' . urlencode($termino_busqueda) : ''; ?>"
                                class="px-3 py-1 rounded <?php echo $i === $pagina_actual ? 'bg-blue-900 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmarEliminacion(id) {
            if (confirm('¿Está seguro de que desea eliminar este usuario?')) {
                window.location.href = 'eliminar.php?id=' + id;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const tableBody = document.getElementById('tableBody');
            const paginationContainer = document.getElementById('paginationContainer');
            let currentPage = <?php echo $pagina_actual; ?>;
            let searchTimeout;

            // Función para cargar datos
            async function loadData(page, search = '') {
                try {
                    const response = await fetch(`buscar_usuarios.php?pagina=${page}&buscar=${encodeURIComponent(search)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json();

                    // Actualizar tabla
                    tableBody.innerHTML = data.html;

                    // Actualizar paginación
                    if (paginationContainer) {
                        updatePagination(data.totalPages, page);
                    }

                    // Actualizar URL sin recargar
                    const url = new URL(window.location);
                    url.searchParams.set('pagina', page);
                    if (search) url.searchParams.set('buscar', search);
                    else url.searchParams.delete('buscar');
                    window.history.pushState({}, '', url);
                } catch (error) {
                    console.error('Error al cargar datos:', error);
                }
            }

            // Función para actualizar paginación
            function updatePagination(totalPages, currentPage) {
                let html = '<nav class="flex space-x-2">';
                for (let i = 1; i <= totalPages; i++) {
                    html += `<a href="?pagina=${i}" class="px-3 py-1 rounded ${i === currentPage ? 'bg-blue-900 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}">${i}</a>`;
                }
                html += '</nav>';
                paginationContainer.innerHTML = html;

                // Agregar eventos a los enlaces de paginación
                document.querySelectorAll('#paginationContainer a').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const page = parseInt(this.href.split('pagina=')[1]);
                        loadData(page, searchInput.value);
                    });
                });
            }

            // Agregar el manejador de eventos para la búsqueda
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    loadData(1, this.value);
                }, 300);
            });
        });
    </script>
</body>

</html>