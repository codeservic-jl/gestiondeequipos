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
$query = "SELECT o.*, c.nombre_apellido as cliente, u.nombre_completo as usuario_registro 
          FROM ordenes_trabajo o 
          LEFT JOIN clientes c ON o.id_cliente = c.id_cliente 
          LEFT JOIN usuarios u ON o.id_usuario_registro = u.id_usuario
          WHERE 1=1";

$params = [];

// Añadir condiciones de búsqueda si existe un término
if ($termino_busqueda) {
    $query .= " AND (c.nombre_apellido LIKE :busqueda OR o.codigo LIKE :busqueda)";
    $params['busqueda'] = "%$termino_busqueda%";
}

$query .= " ORDER BY o.fecha_ingreso DESC";

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
$ordenes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Ordenes de trabajo - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/modern-ux.css" rel="stylesheet">
    <link href="../../assets/css/tables.css" rel="stylesheet">
    <script src="../../assets/js/notifications.js"></script>
    <script src="../../assets/js/mobile-ux.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content gradient-bg min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <div class="card-modern p-6 fade-in">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-navy-blue">Órdenes de Trabajo</h1>
                    <div class="flex flex-col md:flex-row items-center gap-4">
                        <div class="relative w-full md:w-64">
                            <input type="text" id="searchInput"
                                placeholder="Buscar orden..."
                                class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button class="absolute right-3 top-2.5 text-gray-500">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <a href="nueva.php" class="btn-primary btn-modern">
                            <i class="fas fa-plus mr-2"></i> Nueva Orden
                        </a>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th data-label="Acciones">Acciones</th>
                                <th data-label="Cliente">Cliente</th>
                                <th data-label="Código">Código</th>
                                <th data-label="Estado">Estado</th>
                                <th data-label="Fecha">Fecha</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (empty($ordenes)): ?>
                                <tr>
                                    <td colspan="5" class="table-empty">
                                        <div class="table-empty-icon">
                                            <i class="fas fa-clipboard-list"></i>
                                        </div>
                                        <div class="table-empty-text">No hay órdenes registradas</div>
                                        <div class="table-empty-subtext">Crea una nueva orden para comenzar</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ordenes as $orden): ?>
                                    <tr class="table-row-enter">
                                        <td data-label="Acciones">
                                            <div class="table-actions">
                                                <a href="ver.php?id=<?php echo $orden['id_orden']; ?>"
                                                   class="action-view" title="Ver Orden">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="editar.php?id=<?php echo $orden['id_orden']; ?>"
                                                   class="action-edit" title="Editar Orden">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="registrar_seguimiento.php?id=<?php echo $orden['id_orden']; ?>"
                                                   class="action-success" title="Registrar Seguimiento">
                                                    <i class="fas fa-clipboard-check"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <td data-label="Cliente"><?php echo htmlspecialchars($orden['cliente']); ?></td>
                                        <td data-label="Código" class="font-mono"><?php echo htmlspecialchars($orden['codigo']); ?></td>
                                        <td data-label="Estado">
                                            <span class="badge <?php echo $orden['estado'] == 'Pendiente' ? 'badge-warning' : ($orden['estado'] == 'En Proceso' ? 'badge-info' : 'badge-success'); ?>">
                                                <?php echo htmlspecialchars($orden['estado']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Fecha"><?php echo date('d/m/Y H:i', strtotime($orden['fecha_ingreso'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                    const response = await fetch(`buscar_ordenes.php?pagina=${page}&buscar=${encodeURIComponent(search)}`, {
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

                for (let i = 1; i <= totalPages; i++) {
                    html += `<a href="?pagina=${i}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium ${i === currentPage ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50'}">${i}</a>`;
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