<?php
require_once '../../config/database.php';

$filtro_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$filtro_orden = isset($_GET['orden']) ? $_GET['orden'] : '';

$sql = "SELECT o.*, c.nombre_apellido as cliente, c.email, c.telefono,
               s.nombre as sucursal, u.nombre_completo as tecnico
        FROM ordenes_trabajo o
        JOIN clientes c ON o.id_cliente = c.id_cliente
        JOIN sucursales s ON o.id_sucursal = s.id_sucursal
        LEFT JOIN usuarios u ON o.tecnico_responsable_id = u.id_usuario
        WHERE 1=1";

if ($filtro_cliente) {
    $sql .= " AND (c.nombre_apellido LIKE ? OR c.identificacion LIKE ?)";
    $params[] = "%$filtro_cliente%";
    $params[] = "%$filtro_cliente%";
}

if ($filtro_orden) {
    $sql .= " AND o.codigo = ?";
    $params[] = $filtro_orden;
}

$sql .= " ORDER BY o.fecha_ingreso DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params ?? []);
$ordenes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Órdenes - RGE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-center mb-8">Seguimiento de su órden de trabajo</h1>

            <!-- Formulario de búsqueda -->
            <form class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cliente o Identificación</label>
                        <input type="text" name="cliente" value="<?php echo htmlspecialchars($filtro_cliente); ?>" 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div> -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Número de Orden</label>
                        <input type="text" name="orden" value="<?php echo htmlspecialchars($filtro_orden); ?>"
                            class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <button type="submit" class="mt-4 w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                    Buscar
                </button>
            </form>

            <!-- Resultados -->
            <?php foreach ($ordenes as $orden): ?>
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Orden #<?php echo htmlspecialchars($orden['codigo']); ?></h2>
                            <p class="text-gray-600"><?php echo htmlspecialchars($orden['cliente']); ?></p>
                        </div>
                        <span class="px-4 py-2 rounded-full text-sm font-semibold <?php echo $orden['estado'] == 'Entregado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                            <?php echo htmlspecialchars($orden['estado']); ?>
                        </span>
                    </div>

                    <!-- Detalles de la orden -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-sm text-gray-600">Fecha de ingreso:</p>
                            <p class="font-medium"><?php echo date('d/m/Y H:i', strtotime($orden['fecha_ingreso'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Sucursal:</p>
                            <p class="font-medium"><?php echo htmlspecialchars($orden['sucursal']); ?></p>
                        </div>
                    </div>

                    <!-- Seguimientos -->
                    <?php
                    $stmt = $conn->prepare("SELECT s.*, u.nombre_completo as tecnico 
                                          FROM seguimientos_orden s 
                                          JOIN usuarios u ON s.id_tecnico = u.id_usuario 
                                          WHERE s.id_orden = ? 
                                          ORDER BY s.fecha_registro DESC");
                    $stmt->execute([$orden['id_orden']]);
                    $seguimientos = $stmt->fetchAll();
                    ?>

                    <?php if ($seguimientos): ?>
                        <div class="border-t pt-4">
                            <h3 class="font-semibold text-lg mb-4">Historial de Seguimientos</h3>
                            <?php foreach ($seguimientos as $seguimiento): ?>
                                <div class="mb-4 pl-4 border-l-4 border-blue-500">
                                    <p class="font-medium"><?php echo htmlspecialchars($seguimiento['tipo_servicio']); ?></p>
                                    <p class="text-sm text-gray-600 mb-2">
                                        Por: <?php echo htmlspecialchars($seguimiento['tecnico']); ?> -
                                        <?php echo date('d/m/Y H:i', strtotime($seguimiento['fecha_registro'])); ?>
                                    </p>
                                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($seguimiento['procedimiento'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Galería de Imágenes -->
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM orden_imagenes WHERE id_orden = ? ORDER BY fecha_registro DESC");
                    $stmt->execute([$orden['id_orden']]);
                    $imagenes = $stmt->fetchAll();

                    if ($imagenes): ?>
                        <div class="border-t pt-4">
                            <h3 class="font-semibold text-lg mb-4">Evidencias Fotográficas de la orden</h3>
                            <div class="grid grid-cols-4 md:grid-cols-3 gap-4">
                                <?php foreach ($imagenes as $imagen): ?>
                                    <div class="relative group">
                                        <img
                                            src="../../<?php echo htmlspecialchars($imagen['ruta_archivo']); ?>"
                                            alt="Evidencia de trabajo"
                                            class="w-full h-48 object-cover rounded-lg shadow-md transition-transform duration-300 group-hover:scale-105"
                                            onclick="window.open(this.src, '_blank')">
                                        <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white p-2 text-sm rounded-b-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                            <?php echo date('d/m/Y H:i', strtotime($imagen['fecha_registro'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if (empty($ordenes)): ?>
                <div class="text-center py-8 text-gray-600">
                    No se encontraron órdenes de trabajo con los criterios especificados.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>