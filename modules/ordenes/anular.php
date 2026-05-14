<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// Verificar que se proporcione un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de orden no válido";
    header("Location: lista.php");
    exit();
}

$id_orden = $_GET['id'];

// Obtener la orden existente
$stmt = $conn->prepare("
    SELECT o.*, c.nombre_apellido, c.empresa, c.telefono, c.direccion, c.email, c.identificacion,
           e.marca, e.modelo, e.numero_serial, u.nombre_completo as tecnico_nombre
    FROM ordenes_trabajo o
    LEFT JOIN clientes c ON o.id_cliente = c.id_cliente
    LEFT JOIN equipos e ON o.id_equipo = e.id_equipo
    LEFT JOIN usuarios u ON o.tecnico_responsable_id = u.id_usuario
    WHERE o.id_orden = ?
");
$stmt->execute([$id_orden]);
$orden = $stmt->fetch();

if (!$orden) {
    $_SESSION['error'] = "Orden no encontrada";
    header("Location: lista.php");
    exit();
}

// Verificar que la orden no esté ya anulada
if ($orden['estado'] === 'Anulada') {
    $_SESSION['error'] = "La orden ya está anulada";
    header("Location: ver.php?id=" . $id_orden);
    exit();
}

// Procesar la anulación
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->beginTransaction();

        // Validar motivo de anulación
        if (empty($_POST['motivo_anulacion'])) {
            throw new Exception("Debe especificar el motivo de la anulación");
        }

        // Actualizar el estado de la orden a "Anulada"
        $stmt = $conn->prepare("
            UPDATE ordenes_trabajo SET 
                estado = 'Anulada',
                fecha_actualizacion = NOW()
            WHERE id_orden = ?
        ");

        $stmt->execute([$id_orden]);

        // Registrar el seguimiento de anulación
        $stmt_historial = $conn->prepare("
            INSERT INTO seguimientos_orden (
                id_orden, id_tecnico, tipo_servicio, procedimiento, valor_cobrar, fecha_registro
            ) VALUES (?, ?, 'Anulación de Orden', ?, '0', NOW())
        ");

        $descripcion_anulacion = "Orden anulada por " . $_SESSION['nombre_completo'] . 
                                ".\nMotivo: " . trim($_POST['motivo_anulacion']) . 
                                "\nFecha de anulación: " . date('d/m/Y H:i:s');
        
        $stmt_historial->execute([
            $id_orden,
            $_SESSION['user_id'],
            $descripcion_anulacion
        ]);

        $conn->commit();
        $_SESSION['success'] = "Orden anulada correctamente";
        header("Location: ver.php?id=" . $id_orden);
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error al anular la orden: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Anular Orden de Trabajo - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/modern-ux.css" rel="stylesheet">
    <script src="../../assets/js/notifications.js"></script>
    <script src="../../assets/js/mobile-ux.js"></script>
</head>

<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>

    <!-- Sistema de Notificaciones -->
    <div id="alertContainer" class="fixed top-4 right-4 z-50 w-full max-w-sm">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="transform transition-all duration-300 ease-in-out mb-4 bg-red-100 border-l-4 border-red-500 rounded-lg shadow-lg">
                <div class="flex items-center p-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="transform transition-all duration-300 ease-in-out mb-4 bg-green-100 border-l-4 border-green-500 rounded-lg shadow-lg">
                <div class="flex items-center p-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="main-content gradient-bg min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <div class="card-modern p-6 fade-in">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-navy-blue">Anular Orden de Trabajo</h1>
                    <div class="flex space-x-2">
                        <a href="ver.php?id=<?php echo $id_orden; ?>" 
                           class="btn-secondary btn-modern">
                            <i class="fas fa-arrow-left mr-2"></i>Volver
                        </a>
                    </div>
                </div>

                <!-- Advertencia de Anulación -->
                <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-600 mt-1 mr-3 text-xl"></i>
                        <div>
                            <h3 class="text-lg font-semibold text-red-800 mb-2">Advertencia Importante</h3>
                            <p class="text-red-700 mb-3">
                                Está a punto de anular la orden de trabajo <strong>#<?php echo htmlspecialchars($orden['codigo']); ?></strong>. 
                                Esta acción cambiará el estado de la orden a "Anulada" y deshabilitará las opciones de seguimiento y modificación.
                            </p>
                            <ul class="text-red-700 text-sm space-y-1">
                                <li>• La orden no podrá ser modificada después de la anulación</li>
                                <li>• No se podrán registrar nuevos seguimientos</li>
                                <li>• La anulación quedará registrada en el historial</li>
                                <li>• Esta acción es reversible solo por un administrador</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Información de la Orden -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h2 class="text-lg font-semibold text-blue-800 mb-2">
                        <i class="fas fa-info-circle mr-2"></i>Información de la Orden
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-hashtag text-blue-600"></i>
                            <div>
                                <span class="font-medium text-gray-700">Código:</span>
                                <span class="text-gray-900 font-mono"><?php echo htmlspecialchars($orden['codigo']); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-calendar-alt text-blue-600"></i>
                            <div>
                                <span class="font-medium text-gray-700">Fecha de Ingreso:</span>
                                <span class="text-gray-900"><?php echo date('d/m/Y H:i', strtotime($orden['fecha_ingreso'])); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-info-circle text-blue-600"></i>
                            <div>
                                <span class="font-medium text-gray-700">Estado Actual:</span>
                                <span class="badge <?php echo $orden['estado'] == 'Pendiente' ? 'badge-warning' : 'badge-success'; ?>">
                                    <?php echo htmlspecialchars($orden['estado']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información del Cliente -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <h2 class="text-lg font-semibold text-green-800 mb-2">
                        <i class="fas fa-user mr-2"></i>Información del Cliente
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-user-circle text-green-600"></i>
                            <div>
                                <span class="font-medium text-gray-700">Cliente:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($orden['nombre_apellido']); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-phone text-green-600"></i>
                            <div>
                                <span class="font-medium text-gray-700">Teléfono:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($orden['telefono']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario de Anulación -->
                <form method="POST" class="space-y-6">
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-edit mr-2"></i>Motivo de Anulación
                        </h3>
                        
                        <div class="form-group">
                            <label class="block text-gray-700 font-medium mb-2">
                                Motivo de la Anulación *
                            </label>
                            <textarea name="motivo_anulacion" rows="4" required
                                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-red-500 transition-colors"
                                      placeholder="Describa el motivo por el cual se anula esta orden de trabajo..."></textarea>
                            <p class="text-sm text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Este motivo quedará registrado en el historial de la orden
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <a href="ver.php?id=<?php echo $id_orden; ?>" 
                           class="btn-secondary btn-modern">
                            Cancelar
                        </a>
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors duration-200"
                                onclick="return confirm('¿Está seguro de que desea anular esta orden? Esta acción no se puede deshacer fácilmente.')">
                            <i class="fas fa-ban mr-2"></i>Anular Orden
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide notifications after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('#alertContainer > div');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>

</html> 