<?php
session_start();
if (!isset($_SESSION['user_id'])) {
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
$query = "SELECT * FROM clientes WHERE 1=1";
$params = [];

// Añadir condiciones de búsqueda si existe un término
if ($termino_busqueda) {
    $query .= " AND (nombre_apellido LIKE :busqueda OR email LIKE :busqueda OR telefono LIKE :busqueda)";
    $params['busqueda'] = "%$termino_busqueda%";
}

// Remover esta validación si no es necesaria
/* if ($_SESSION['user_type'] != 1) {
    $query .= " AND id_sucursal = :sucursal";
    $params['sucursal'] = $_SESSION['sucursal'];
} */

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
    if ($key == 'offset' || $key == 'limit') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
}
$stmt->execute();
$clientes = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Clientes - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-navy-blue">Lista de Clientes</h1>
                    <div class="flex flex-col md:flex-row items-center gap-4">
                        <div class="relative w-full md:w-64">
                            <input type="text" id="searchInput"
                                placeholder="Buscar cliente..."
                                class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button class="absolute right-3 top-2.5 text-gray-500">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <a href="nuevo.php" class="w-full md:w-auto bg-blue-900 text-white px-4 py-2 rounded-lg hover:bg-blue-800 flex items-center justify-center transition-colors duration-200 shadow-md">
                            <i class="fas fa-plus mr-2"></i> Nuevo Cliente
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-navy-blue">
                            <tr>
                                <th class="px-4 py-2">Acciones</th>
                                <th class="px-4 py-2">Estado</th>
                                <th class="px-4 py-2">Cliente</th>
                                <th class="px-4 py-2">Teléfono</th>
                                <th class="px-4 py-2">Email</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($clientes as $cliente): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex items-center space-x-2">
                                            <a href="ver_cliente.php?id=<?php echo $cliente['id_cliente']; ?>"
                                                class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="editar.php?id=<?php echo $cliente['id_cliente']; ?>"
                                                class="text-yellow-600 hover:text-yellow-800">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="eliminar.php?id=<?php echo $cliente['id_cliente']; ?>"
                                                class="text-red-600 hover:text-red-800"
                                                onclick="return confirm('¿Está seguro de que desea eliminar este cliente?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $cliente['estado'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $cliente['estado'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($cliente['nombre_apellido']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <a href="https://wa.me/+593<?php echo htmlspecialchars($cliente['telefono']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($cliente['telefono']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($cliente['email']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Contenedor para la paginación -->
                <div id="paginationContainer" class="mt-4 flex justify-center">
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <a href="?pagina=<?php echo $i; ?>"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium 
                                  <?php echo $i === $pagina_actual ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const tableBody = document.getElementById('tableBody');
            const paginationContainer = document.getElementById('paginationContainer');
            let currentPage = <?php echo $pagina_actual; ?>;
            let searchTimeout;

            // Función para cargar datos
            async function loadData(page, search = '') {
                try {
                    const response = await fetch(`buscar_clientes.php?pagina=${page}&buscar=${encodeURIComponent(search)}`, {
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
                let html = '<nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">';

                // Botón anterior
                if (currentPage > 1) {
                    html += `<button class="pagination-btn relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" data-page="${currentPage - 1}">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>`;
                }

                // Números de página
                for (let i = 1; i <= totalPages; i++) {
                    html += `<button class="pagination-btn relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium ${i === currentPage ? 'text-navy-blue bg-blue-50 border-navy-blue z-10' : 'text-gray-500 hover:bg-gray-50'}" data-page="${i}">${i}</button>`;
                }

                // Botón siguiente
                if (currentPage < totalPages) {
                    html += `<button class="pagination-btn relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" data-page="${currentPage + 1}">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>`;
                }

                html += '</nav>';
                paginationContainer.innerHTML = html;

                // Agregar eventos a los botones de paginación
                document.querySelectorAll('.pagination-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const page = parseInt(this.dataset.page);
                        currentPage = page;
                        loadData(page, searchInput.value);
                    });
                });
            }

            // Evento de búsqueda
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    loadData(1, this.value);
                }, 300);
            });

            // Cargar datos iniciales
            loadData(currentPage, searchInput.value);
        });
    </script>
</body>

</html>