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
$query = "SELECT * FROM sucursales WHERE 1=1";
$params = [];

// Añadir condiciones de búsqueda si existe un término
if ($termino_busqueda) {
    $query .= " AND (nombre LIKE :busqueda OR direccion LIKE :busqueda OR telefono LIKE :busqueda)";
    $params['busqueda'] = "%$termino_busqueda%";
}

$query .= " ORDER BY nombre";

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
$sucursales = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sucursales - Ingreso de equipos</title>
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
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="text-2xl font-bold text-navy-blue">Lista de Sucursales</h2>

                <!-- Barra de búsqueda en tiempo real -->
                <div class="flex-1 max-w-md">
                    <div class="relative">
                        <input type="text"
                            id="searchInput"
                            class="w-full px-4 py-2 pr-10 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-navy-blue"
                            placeholder="Buscar sucursal...">
                        <span class="absolute right-3 top-2.5 text-gray-400">
                            <i class="fas fa-search"></i>
                        </span>
                    </div>
                </div>

                <a href="nueva.php" class="bg-navy-blue text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-plus"></i> Nueva Sucursal
                </a>
            </div>

            <!-- Tabla responsive -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto" id="dataTable">
                        <thead class="bg-navy-blue text-white">
                            <tr>
                                <th class="px-4 py-2">Acciones</th>
                                <th class="px-4 py-2">Estado</th>
                                <th class="px-4 py-2">Nombre</th>
                                <th class="px-4 py-2">Dirección</th>
                                <th class="px-4 py-2">Teléfono</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sucursales as $sucursal): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm">
                                        <a href="editar.php?id=<?php echo $sucursal['id_sucursal']; ?>"
                                            class="text-navy-blue hover:text-green-700 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($sucursal['estado'] ): ?>
                                        <a href="#" onclick="confirmarEliminacion(<?php echo $sucursal['id_sucursal']; ?>)"
                                            class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $sucursal['estado'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $sucursal['estado'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($sucursal['nombre']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($sucursal['direccion']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($sucursal['telefono']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginación -->
            <div class="mt-6 flex justify-center">
                <div class="flex space-x-2">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <a href="?pagina=<?php echo $i; ?><?php echo $termino_busqueda ? '&buscar=' . urlencode($termino_busqueda) : ''; ?>"
                            class="px-3 py-1 rounded <?php echo $pagina_actual == $i ? 'bg-navy-blue text-white' : 'bg-gray-200'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const table = document.getElementById('dataTable');
            const rows = table.getElementsByTagName('tr');

            searchInput.addEventListener('keyup', function(e) {
                const searchText = e.target.value.toLowerCase();

                // Comenzar desde 1 para saltar el encabezado
                for (let i = 1; i < rows.length; i++) {
                    const row = rows[i];
                    const cells = row.getElementsByTagName('td');
                    let found = false;

                    for (let j = 0; j < cells.length; j++) {
                        const cellText = cells[j].textContent.toLowerCase();
                        if (cellText.indexOf(searchText) > -1) {
                            found = true;
                            break;
                        }
                    }

                    row.style.display = found ? '' : 'none';
                }
            });
        });
    </script>

    <script>
        function confirmarEliminacion(id) {
            if (confirm('¿Está seguro de que desea eliminar esta sucursal?')) {
                window.location.href = 'eliminar.php?id=' + id;
            }
        }
    </script>
</body>

</html>