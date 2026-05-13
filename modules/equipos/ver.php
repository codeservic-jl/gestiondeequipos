<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// Verificar si se proporcionó un ID
if (!isset($_GET['id'])) {
    header("Location: lista.php");
    exit();
}

// Obtener los detalles del equipo
$stmt = $conn->prepare("
    SELECT e.*, c.nombre_apellido as cliente, c.empresa, c.telefono
    FROM equipos e
    LEFT JOIN clientes c ON e.id_cliente = c.id_cliente
    WHERE e.id_equipo = ?
");
$stmt->execute([$_GET['id']]);
$equipo = $stmt->fetch();

// Si no se encuentra el equipo, redirigir
if (!$equipo) {
    header("Location: lista.php");
    exit();
}

// Obtener el historial de órdenes de este equipo
$stmt = $conn->prepare("
    SELECT ot.*, u.nombre_completo as tecnico
    FROM ordenes_trabajo ot
    LEFT JOIN usuarios u ON ot.id_usuario_registro = u.id_usuario
    WHERE ot.id_equipo = ?
    ORDER BY ot.fecha_ingreso DESC
");
$stmt->execute([$_GET['id']]);
$ordenes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Equipo - Gestión de Equipos RGE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bg-navy-blue { background-color: #000080; }
        .text-navy-blue { color: #000080; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content"> 
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-navy-blue">Detalles del Equipo</h2>
            <a href="lista.php" class="text-navy-blue hover:text-blue-900">
                <i class="fas fa-arrow-left"></i> Volver a la lista
            </a>
        </div>

        <!-- Detalles del Equipo -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold mb-4">Información del Equipo</h3>
                    <div class="space-y-2">
                        <p><strong>Marca:</strong> <?php echo htmlspecialchars($equipo['marca']); ?></p>
                        <p><strong>Modelo:</strong> <?php echo htmlspecialchars($equipo['modelo']); ?></p>
                        <p><strong>Número de Serie:</strong> <?php echo htmlspecialchars($equipo['numero_serial']); ?></p>
                        <p><strong>Estado:</strong> 
                            <span class="px-2 py-1 rounded-full text-sm <?php echo $equipo['estado'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $equipo['estado'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </p>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Información del Cliente</h3>
                    <div class="space-y-2">
                        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($equipo['cliente']); ?></p>
                        <p><strong>Empresa:</strong> <?php echo htmlspecialchars($equipo['empresa'] ?: 'N/A'); ?></p>
                        <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($equipo['telefono']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial de Órdenes -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">Historial de Órdenes</h3>
            <?php if (empty($ordenes)): ?>
                <p class="text-gray-500">No hay órdenes registradas para este equipo.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Técnico</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($ordenes as $orden): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($orden['codigo']); ?></td>
                                    <td class="px-6 py-4"><?php echo date('d/m/Y H:i', strtotime($orden['fecha_ingreso'])); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full text-sm
                                            <?php echo $orden['estado'] == 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo htmlspecialchars($orden['estado']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($orden['tecnico']); ?></td>
                                    <td class="px-6 py-4">
                                        <a href="../ordenes/ver.php?id=<?php echo $orden['id_orden']; ?>" 
                                           class="text-navy-blue hover:text-blue-900">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>
</body>
</html>