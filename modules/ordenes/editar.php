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

// Obtener datos necesarios para el formulario
$tipos = $conn->query("SELECT * FROM tipos_equipo WHERE estado = 1")->fetchAll();
$clientes = $conn->query("SELECT * FROM clientes WHERE estado = 1")->fetchAll();

// Obtener equipos del cliente
$stmt = $conn->prepare("SELECT * FROM equipos WHERE id_cliente = ? AND estado = 1");
$stmt->execute([$orden['id_cliente']]);
$equipos_cliente = $stmt->fetchAll();

// Obtener técnicos disponibles
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE estado = 1 AND id_tipo IN (2, 3)");
$stmt->execute();
$tecnicos = $stmt->fetchAll();

// Obtener estados de orden
$estados = $conn->query("SELECT * FROM orden_estados WHERE estado = 1")->fetchAll();

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->beginTransaction();

        // Validar datos básicos
        if (empty($_POST['descripcion_problema'])) {
            throw new Exception("La descripción del problema no puede estar vacía");
        }

        // Preparar arrays para detectar cambios
        $cambios = [];
        $valores_anteriores = [
            'descripcion_problema' => $orden['descripcion_problema'],
            'tecnico_responsable_id' => $orden['tecnico_responsable_id'],
            'estado' => $orden['estado']
        ];
        
        $valores_nuevos = [
            'descripcion_problema' => trim($_POST['descripcion_problema']),
            'tecnico_responsable_id' => $_POST['tecnico_responsable_id'],
            'estado' => $_POST['estado']
        ];

        // Detectar cambios en descripción del problema
        if ($valores_anteriores['descripcion_problema'] !== $valores_nuevos['descripcion_problema']) {
            $cambios[] = "Descripción del problema";
        }

        // Detectar cambios en técnico responsable
        if ($valores_anteriores['tecnico_responsable_id'] != $valores_nuevos['tecnico_responsable_id']) {
            $tecnico_anterior = "Sin asignar";
            $tecnico_nuevo = "Sin asignar";
            
            if ($valores_anteriores['tecnico_responsable_id']) {
                $stmt = $conn->prepare("SELECT nombre_completo FROM usuarios WHERE id_usuario = ?");
                $stmt->execute([$valores_anteriores['tecnico_responsable_id']]);
                $tecnico_anterior = $stmt->fetchColumn() ?: "Sin asignar";
            }
            
            if ($valores_nuevos['tecnico_responsable_id']) {
                $stmt = $conn->prepare("SELECT nombre_completo FROM usuarios WHERE id_usuario = ?");
                $stmt->execute([$valores_nuevos['tecnico_responsable_id']]);
                $tecnico_nuevo = $stmt->fetchColumn() ?: "Sin asignar";
            }
            
            $cambios[] = "Técnico responsable: $tecnico_anterior → $tecnico_nuevo";
        }

        // Detectar cambios en estado
        if ($valores_anteriores['estado'] !== $valores_nuevos['estado']) {
            $cambios[] = "Estado: " . $valores_anteriores['estado'] . " → " . $valores_nuevos['estado'];
        }

        // Actualizar la orden
        $stmt = $conn->prepare("
            UPDATE ordenes_trabajo SET 
                descripcion_problema = ?,
                tecnico_responsable_id = ?,
                estado = ?,
                fecha_actualizacion = NOW()
            WHERE id_orden = ?
        ");

        $stmt->execute([
            $valores_nuevos['descripcion_problema'],
            $valores_nuevos['tecnico_responsable_id'],
            $valores_nuevos['estado'],
            $id_orden
        ]);

        // Registrar el cambio en el historial solo si hay cambios
        if (!empty($cambios)) {
            $stmt_historial = $conn->prepare("
                INSERT INTO seguimientos_orden (
                    id_orden, id_tecnico, tipo_servicio, procedimiento, valor_cobrar, fecha_registro
                ) VALUES (?, ?, 'Modificación de Orden', ?, '0', NOW())
            ");

            $descripcion_cambio = "Orden modificada por " . $_SESSION['nombre_completo'] . 
                                 ". Cambios realizados:\n• " . implode("\n• ", $cambios);
            
            $stmt_historial->execute([
                $id_orden,
                $_SESSION['user_id'],
                $descripcion_cambio
            ]);
        }

        $conn->commit();
        $_SESSION['success'] = "Orden actualizada correctamente";
        header("Location: ver.php?id=" . $id_orden);
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error al actualizar la orden: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Editar Orden de Trabajo - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/modern-ux.css" rel="stylesheet">
    <script src="../../assets/js/notifications.js"></script>
    <script src="../../assets/js/mobile-ux.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
                    <h1 class="text-2xl font-bold text-navy-blue">Editar Orden de Trabajo</h1>
                    <div class="flex space-x-2">
                        <a href="ver.php?id=<?php echo $id_orden; ?>" 
                           class="btn-secondary btn-modern">
                            <i class="fas fa-arrow-left mr-2"></i>Volver
                        </a>
                    </div>
                </div>

                <!-- Información de la Orden -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 slide-in-left">
                    <h2 class="text-lg font-semibold text-blue-800 mb-2">
                        <i class="fas fa-clipboard-list mr-2"></i>Información de la Orden
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-hashtag text-green-700"></i>
                            <div>
                                <span class="font-medium text-gray-700">Código:</span>
                                <span class="text-gray-900 font-mono"><?php echo htmlspecialchars($orden['codigo']); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-calendar-alt text-green-700"></i>
                            <div>
                                <span class="font-medium text-gray-700">Fecha de Ingreso:</span>
                                <span class="text-gray-900"><?php echo date('d/m/Y H:i', strtotime($orden['fecha_ingreso'])); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-info-circle text-green-700"></i>
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
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 slide-in-left">
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
                            <i class="fas fa-building text-green-600"></i>
                            <div>
                                <span class="font-medium text-gray-700">Empresa:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($orden['empresa']); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-phone text-green-600"></i>
                            <div>
                                <span class="font-medium text-gray-700">Teléfono:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($orden['telefono']); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-envelope text-green-600"></i>
                            <div>
                                <span class="font-medium text-gray-700">Email:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($orden['email']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información del Equipo -->
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6 slide-in-left">
                    <h2 class="text-lg font-semibold text-purple-800 mb-2">
                        <i class="fas fa-desktop mr-2"></i>Información del Equipo
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-industry text-purple-600"></i>
                            <div>
                                <span class="font-medium text-gray-700">Marca:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($orden['marca']); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-cube text-purple-600"></i>
                            <div>
                                <span class="font-medium text-gray-700">Modelo:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($orden['modelo']); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-barcode text-purple-600"></i>
                            <div>
                                <span class="font-medium text-gray-700">Número de Serie:</span>
                                <span class="text-gray-900 font-mono"><?php echo htmlspecialchars($orden['numero_serial']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario de Edición -->
                <form method="POST" class="space-y-6 slide-in-right">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Descripción del Problema -->
                        <div class="md:col-span-2 form-group">
                            <label class="form-label">
                                Descripción del Problema *
                            </label>
                            <textarea name="descripcion_problema" rows="4" required
                                      class="form-textarea w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                      placeholder="Describa el problema del equipo..."><?php echo htmlspecialchars($orden['descripcion_problema']); ?></textarea>
                        </div>

                        <!-- Técnico Responsable -->
                        <div class="form-group">
                            <label class="form-label">
                                Técnico Responsable  (por asignar)*
                            </label>
                            <select name="tecnico_responsable_id" required 
                            class="form-select w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 bg-white">
                                <option value="">Seleccione un técnico</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?php echo $tecnico['id_usuario']; ?>" 
                                            <?php echo ($orden['tecnico_responsable_id'] == $tecnico['id_usuario']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tecnico['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Estado de la Orden -->
                        <div class="form-group">
                            <label class="form-label">
                                Estado de la Orden *
                            </label>
                            <select name="estado" required
                            class="form-select w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 bg-white">
                                <?php foreach ($estados as $estado): ?>
                                    <option value="<?php echo $estado['nombre_estado']; ?>" 
                                            <?php echo ($orden['estado'] == $estado['nombre_estado']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($estado['nombre_estado']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <a href="ver.php?id=<?php echo $id_orden; ?>" 
                           class="btn-secondary btn-modern">
                            Cancelar
                        </a>
                        <button type="submit" 
                                class="btn-primary btn-modern">
                            <i class="fas fa-save mr-2"></i>Actualizar Orden
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