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
$query = "SELECT e.*, c.nombre_apellido as cliente 
          FROM equipos e 
          LEFT JOIN clientes c ON e.id_cliente = c.id_cliente
          WHERE 1=1";
$params = [];

// Añadir condiciones de búsqueda si existe un término
if ($termino_busqueda) {
    $query .= " AND (e.marca LIKE :busqueda OR e.modelo LIKE :busqueda OR e.numero_serial LIKE :busqueda OR c.nombre_apellido LIKE :busqueda)";
    $params['busqueda'] = "%$termino_busqueda%";
}

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
$equipos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Lista de Equipos - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>

    <!-- Sistema de Notificaciones -->
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
                    <h1 class="text-2xl font-bold text-navy-blue">Lista de Equipos</h1>
                    <div class="flex flex-col md:flex-row items-center gap-4">
                        <div class="relative w-full md:w-64">
                            <input type="text" id="searchInput"
                                value="<?php echo htmlspecialchars($termino_busqueda); ?>"
                                placeholder="Buscar equipo..."
                                class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button class="absolute right-3 top-2.5 text-gray-500">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <a href="nuevo.php" class="w-full md:w-auto bg-blue-900 text-white px-4 py-2 rounded-lg hover:bg-blue-800 flex items-center justify-center transition-colors duration-200 shadow-md">
                            <i class="fas fa-plus mr-2"></i> Nuevo Equipo
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-navy-blue">
                            <tr>
                                <th class="px-4 py-2">Acciones</th>
                                <th class="px-4 py-2">Cliente</th>
                                <th class="px-4 py-2">Marca</th>
                                <th class="px-4 py-2">Modelo</th>
                                <th class="px-4 py-2">Número de Serie</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"> <!-- Falta este ID en el tbody -->
                            <?php foreach ($equipos as $equipo): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex items-center space-x-2">
                                            <a href="ver.php?id=<?php echo $equipo['id_equipo']; ?>"
                                                class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="editar.php?id=<?php echo $equipo['id_equipo']; ?>"
                                                class="text-yellow-600 hover:text-yellow-800">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" onclick="confirmarEliminacion(<?php echo $equipo['id_equipo']; ?>)"
                                                class="text-red-600 hover:text-red-800" title="Eliminar equipo">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($equipo['cliente']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($equipo['marca']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($equipo['modelo']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($equipo['numero_serial']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <!-- Después de la tabla -->
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
    </div>

    <script>
        function confirmarEliminacion(id) {
            if (confirm('¿Está seguro de que desea eliminar este equipo?')) {
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
                    const response = await fetch(`buscar_equipos.php?pagina=${page}&buscar=${encodeURIComponent(search)}`, {
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

            // Falta el cierre de la función principal y el manejo del evento de búsqueda

            // Agregar el manejador de eventos para la búsqueda
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    loadData(1, this.value);
                }, 300);
            });
        }); // Esta es la llave que falta
    </script>


</body>

</html>