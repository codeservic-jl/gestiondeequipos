<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// Procesar eliminación
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    try {
        $stmt = $conn->prepare("UPDATE orden_estados SET estado = 0 WHERE id_orden_estado = ?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['success'] = "Estado eliminado correctamente";
        header("Location: estados_orden.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al eliminar el estado";
    }
}

// Procesar formulario de nuevo/editar
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $nombre_estado = trim($_POST['nombre_estado']);
        
        // Validar que el nombre no esté vacío
        if (empty($nombre_estado)) {
            $_SESSION['error'] = "El nombre del estado no puede estar vacío";
        } else {
            if (isset($_POST['id_orden_estado']) && !empty($_POST['id_orden_estado'])) {
                // Actualizar - verificar que no exista otro con el mismo nombre
                $stmt = $conn->prepare("SELECT COUNT(*) FROM orden_estados WHERE nombre_estado = ? AND id_orden_estado != ? AND estado = 1");
                $stmt->execute([$nombre_estado, $_POST['id_orden_estado']]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "Ya existe un estado con el nombre '" . htmlspecialchars($nombre_estado) . "'";
                } else {
                    $stmt = $conn->prepare("UPDATE orden_estados SET nombre_estado = ? WHERE id_orden_estado = ?");
                    $stmt->execute([$nombre_estado, $_POST['id_orden_estado']]);
                    $_SESSION['success'] = "Estado actualizado correctamente";
                    header("Location: estados_orden.php");
                    exit();
                }
            } else {
                // Crear nuevo - verificar que no exista
                $stmt = $conn->prepare("SELECT COUNT(*) FROM orden_estados WHERE nombre_estado = ? AND estado = 1");
                $stmt->execute([$nombre_estado]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "Ya existe un estado con el nombre '" . htmlspecialchars($nombre_estado) . "'";
                } else {
                    $stmt = $conn->prepare("INSERT INTO orden_estados (nombre_estado, estado) VALUES (?, 1)");
                    $stmt->execute([$nombre_estado]);
                    $_SESSION['success'] = "Estado creado correctamente";
                    header("Location: estados_orden.php");
                    exit();
                }
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al guardar el estado: " . $e->getMessage();
    }
}

// Obtener estado para editar
$estado_editar = null;
$mostrar_formulario = false;

if (isset($_GET['editar'])) {
    if ($_GET['editar'] === 'nuevo') {
        $estado_editar = 'nuevo';
        $mostrar_formulario = true;
    } elseif (is_numeric($_GET['editar'])) {
        $stmt = $conn->prepare("SELECT * FROM orden_estados WHERE id_orden_estado = ?");
        $stmt->execute([$_GET['editar']]);
        $estado_editar = $stmt->fetch();
        $mostrar_formulario = true;
    }
}

// Obtener todos los estados
try {
    $stmt = $conn->prepare("SELECT * FROM orden_estados WHERE estado = 1 ORDER BY id_orden_estado");
    $stmt->execute();
    $estados = $stmt->fetchAll();
} catch (PDOException $e) {
    $estados = [];
    error_log("Error al obtener estados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Estados de Orden - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/modern-ux.css" rel="stylesheet">
    <link href="../../assets/css/tables.css" rel="stylesheet">
    <script src="../../assets/js/notifications.js"></script>
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
                    <h1 class="text-2xl font-bold text-gray-800">
                        <?php 
                        if ($estado_editar === 'nuevo') {
                            echo 'Nuevo Estado';
                        } elseif ($estado_editar) {
                            echo 'Editar Estado';
                        } else {
                            echo 'Estados de Orden';
                        }
                        ?>
                    </h1>
                    <div class="flex space-x-2">
                        <?php if ($mostrar_formulario): ?>
                            <a href="estados_orden.php" class="btn-secondary btn-modern">
                                <i class="fas fa-arrow-left mr-2"></i>Volver
                            </a>
                        <?php else: ?>
                            <a href="../../index.php" class="btn-secondary btn-modern">
                                <i class="fas fa-arrow-left mr-2"></i>Volver
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$mostrar_formulario): ?>
                    <!-- Lista de Estados -->
                    <div class="table-container">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th data-label="Nombre">Nombre</th>
                                    <th data-label="Acciones">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($estados)): ?>
                                    <tr>
                                        <td colspan="2" class="table-empty">
                                            <div class="table-empty-icon">
                                                <i class="fas fa-list-alt"></i>
                                            </div>
                                            <div class="table-empty-text">No hay estados registrados</div>
                                            <div class="table-empty-subtext">Crea un nuevo estado para comenzar</div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($estados as $estado): ?>
                                        <tr class="table-row-enter">
                                            <td data-label="Nombre"><?php echo htmlspecialchars($estado['nombre_estado']); ?></td>
                                            <td data-label="Acciones">
                                                <div class="table-actions">
                                                    <a href="?editar=<?php echo $estado['id_orden_estado']; ?>" 
                                                       class="action-edit" title="Editar estado">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?eliminar=<?php echo $estado['id_orden_estado']; ?>" 
                                                       onclick="return confirm('¿Está seguro de que desea eliminar este estado?')"
                                                       class="action-delete" title="Eliminar estado">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Botón para agregar nuevo -->
                    <div class="mt-6">
                        <a href="?editar=nuevo" class="btn-primary btn-modern">
                            <i class="fas fa-plus mr-2"></i>Nuevo Estado
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Formulario de Edición/Creación -->
                    <form method="POST" class="space-y-6 slide-in-right">
                        <input type="hidden" name="id_orden_estado" value="<?php echo $estado_editar && is_array($estado_editar) ? $estado_editar['id_orden_estado'] : ''; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nombre del Estado -->
                            <div class="form-group">
                                <label class="form-label">
                                    Nombre del Estado *
                                </label>
                                <input type="text" name="nombre_estado" 
                                       value="<?php echo $estado_editar && is_array($estado_editar) ? htmlspecialchars($estado_editar['nombre_estado']) : ''; ?>" 
                                       required
                                       class="form-input w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 bg-white"
                                       placeholder="Ej: En proceso, Completado, Cancelado">
                            </div>

                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="estados_orden.php" 
                               class="btn-secondary btn-modern">
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="btn-primary btn-modern">
                                <i class="fas fa-save mr-2"></i>
                                <?php 
                                if ($estado_editar === 'nuevo') {
                                    echo 'Crear';
                                } elseif ($estado_editar) {
                                    echo 'Actualizar';
                                } else {
                                    echo 'Guardar';
                                }
                                ?>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
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