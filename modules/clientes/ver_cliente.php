<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

try {
    // Obtener información del cliente
    $stmt = $conn->prepare("
        SELECT c.*, COUNT(e.id_equipo) as total_equipos
        FROM clientes c
        LEFT JOIN equipos e ON c.id_cliente = e.id_cliente AND e.estado = 1
        WHERE c.id_cliente = ?
        GROUP BY c.id_cliente
    ");
    $stmt->execute([$_GET['id']]);
    $cliente = $stmt->fetch();

    // Obtener equipos del cliente
    $stmt = $conn->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM ordenes_trabajo WHERE id_equipo = e.id_equipo) as total_ordenes
        FROM equipos e
        WHERE e.id_cliente = ? AND e.estado = 1
    ");
    $stmt->execute([$_GET['id']]);
    $equipos = $stmt->fetchAll();

    // Obtener últimas órdenes de trabajo
    $stmt = $conn->prepare("
        SELECT ot.*, e.marca, e.modelo, ot.id_orden
        FROM ordenes_trabajo ot
        JOIN equipos e ON ot.id_equipo = e.id_equipo
        WHERE ot.id_cliente = ?
        ORDER BY ot.fecha_ingreso DESC
        LIMIT 5
    ");
    $stmt->execute([$_GET['id']]);
    $ordenes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error al obtener los datos del cliente";
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Cliente - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2d6b2b;
            --secondary-color: #1a237e;
            --accent-color: #304ffe;
        }

        .bg-primary {
            background-color: var(--primary-color);
        }

        .text-primary {
            color: var(--primary-color);
        }

        /* Animaciones y transiciones */
        .hover-scale {
            transition: transform 0.2s ease;
        }

        .hover-scale:hover {
            transform: scale(1.02);
        }

        /* Estilos modernos para cards */
        .modern-card {
            @apply bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow duration-300;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Estilos para tablas responsivas */
        .responsive-table {
            @apply w-full overflow-x-auto rounded-lg;
            scrollbar-width: thin;
        }

        .responsive-table::-webkit-scrollbar {
            height: 6px;
        }

        .responsive-table::-webkit-scrollbar-thumb {
            background-color: var(--primary-color);
            border-radius: 3px;
        }

        /* Mejoras responsive */
        @media (max-width: 768px) {
            .info-grid {
                @apply grid-cols-1 gap-4;
            }

            .modern-card {
                @apply p-4;
            }

            .table-container {
                @apply -mx-4;
            }
        }

        /* Estilos para badges */
        .status-badge {
            @apply px-3 py-1 rounded-full text-sm font-medium;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($cliente)): ?>
                <!-- Información Principal del Cliente -->
                <div class="modern-card mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-4">
                            <div class="bg-primary rounded-full p-3 text-white">
                                <i class="fas fa-user-circle text-2xl"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-primary">
                                    <?php echo htmlspecialchars($cliente['nombre_apellido']); ?>
                                </h1>
                                <p class="text-gray-600">
                                    <i class="fas fa-id-card mr-2"></i>
                                    <?php echo htmlspecialchars($cliente['identificacion']); ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right hidden md:block">
                                <p class="text-sm text-gray-600">Total Equipos</p>
                                <p class="text-3xl font-bold text-primary"><?php echo $cliente['total_equipos']; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="flex items-center gap-3">
                            <div class="bg-blue-100 rounded-full p-2 text-blue-600">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Teléfono</p>
                                <p class="font-medium"><?php echo htmlspecialchars($cliente['telefono']); ?></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="bg-green-100 rounded-full p-2 text-green-600">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Email</p>
                                <p class="font-medium"><?php echo htmlspecialchars($cliente['email']); ?></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="bg-purple-100 rounded-full p-2 text-purple-600">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Dirección</p>
                                <p class="font-medium"><?php echo htmlspecialchars($cliente['direccion']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Equipos del Cliente -->
                <div class="modern-card mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="bg-indigo-100 p-3 rounded-full">
                                <i class="fas fa-laptop text-indigo-600 text-xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-primary">Equipos Registrados</h2>
                        </div>
                    </div>

                    <div class="responsive-table">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marca</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modelo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número Serial</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Órdenes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($equipos as $equipo): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <i class="fas fa-desktop text-gray-400 mr-2"></i>
                                                <?php echo htmlspecialchars($equipo['marca']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($equipo['modelo']); ?></td>
                                        <td class="px-6 py-4 font-mono text-sm"><?php echo htmlspecialchars($equipo['numero_serial']); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                                <?php echo $equipo['total_ordenes']; ?> órdenes
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Últimas Órdenes -->
                <div class="modern-card">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="bg-yellow-100 p-3 rounded-full">
                                <i class="fas fa-history text-yellow-600 text-xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-primary">Últimas Órdenes de Trabajo</h2>
                        </div>
                    </div>

                    <div class="responsive-table">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Equipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($ordenes as $orden): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap font-medium text-primary">
                                            <?php if (($orden['estado'] == 'Pendiente') || ($orden['estado'] == 'En Proceso')) { ?>
                                                <a href="../ordenes/registrar_seguimiento.php?id=<?php echo $orden['id_orden']; ?>">
                                                    <?php echo htmlspecialchars($orden['codigo']); ?>
                                                </a>
                                            <?php } else {
                                                echo htmlspecialchars($orden['codigo']);
                                            }   ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <i class="fas fa-laptop text-gray-400 mr-2"></i>
                                                <?php echo htmlspecialchars($orden['marca'] . ' ' . $orden['modelo']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <i class="far fa-calendar-alt text-gray-400 mr-2"></i>
                                                <?php echo date('d/m/Y', strtotime($orden['fecha_ingreso'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="status-badge <?php echo $orden['estado'] == 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                                <?php echo htmlspecialchars($orden['estado']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>