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
        $stmt = $conn->prepare("UPDATE tipos_equipo SET estado = 0 WHERE id_tipo = ?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['success'] = "Tipo de equipo eliminado correctamente";
        header("Location: tipos_equipo.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al eliminar el tipo de equipo";
    }
}

// Procesar formulario de nuevo/editar
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $nombre = trim($_POST['nombre']);
        
        // Validar que el nombre no esté vacío
        if (empty($nombre)) {
            $_SESSION['error'] = "El nombre del tipo de equipo no puede estar vacío";
        } else {
            if (isset($_POST['id_tipo']) && !empty($_POST['id_tipo'])) {
                // Actualizar - verificar que no exista otro con el mismo nombre
                $stmt = $conn->prepare("SELECT COUNT(*) FROM tipos_equipo WHERE nombre = ? AND id_tipo != ? AND estado = 1");
                $stmt->execute([$nombre, $_POST['id_tipo']]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "Ya existe un tipo de equipo con el nombre '" . htmlspecialchars($nombre) . "'";
                } else {
                    $stmt = $conn->prepare("UPDATE tipos_equipo SET nombre = ? WHERE id_tipo = ?");
                    $stmt->execute([$nombre, $_POST['id_tipo']]);
                    $_SESSION['success'] = "Tipo de equipo actualizado correctamente";
                    header("Location: tipos_equipo.php");
                    exit();
                }
            } else {
                // Crear nuevo - verificar que no exista
                $stmt = $conn->prepare("SELECT COUNT(*) FROM tipos_equipo WHERE nombre = ? AND estado = 1");
                $stmt->execute([$nombre]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "Ya existe un tipo de equipo con el nombre '" . htmlspecialchars($nombre) . "'";
                } else {
                    $stmt = $conn->prepare("INSERT INTO tipos_equipo (nombre, estado) VALUES (?, 1)");
                    $stmt->execute([$nombre]);
                    $_SESSION['success'] = "Tipo de equipo creado correctamente";
                    header("Location: tipos_equipo.php");
                    exit();
                }
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al guardar el tipo de equipo: " . $e->getMessage();
    }
}

// Obtener tipo para editar
$tipo_editar = null;
$mostrar_formulario = false;

if (isset($_GET['editar'])) {
    if ($_GET['editar'] === 'nuevo') {
        $tipo_editar = 'nuevo';
        $mostrar_formulario = true;
    } elseif (is_numeric($_GET['editar'])) {
        $stmt = $conn->prepare("SELECT * FROM tipos_equipo WHERE id_tipo = ?");
        $stmt->execute([$_GET['editar']]);
        $tipo_editar = $stmt->fetch();
        $mostrar_formulario = true;
    }
}

// Obtener todos los tipos
$stmt = $conn->prepare("SELECT * FROM tipos_equipo WHERE estado = 1 ORDER BY nombre");
$stmt->execute();
$tipos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Equipo - Gestión de Equipos RGE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/modern-ux.css" rel="stylesheet">
    <link href="../../assets/css/tables.css" rel="stylesheet">
    <script src="../../assets/js/notifications.js"></script>
    <script src="../../assets/js/mobile-menu.js"></script>
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
                        if ($tipo_editar === 'nuevo') {
                            echo 'Nuevo Tipo de Equipo';
                        } elseif ($tipo_editar) {
                            echo 'Editar Tipo de Equipo';
                        } else {
                            echo 'Tipos de Equipo';
                        }
                        ?>
                    </h1>
                    <div class="flex space-x-2">
                        <?php if ($mostrar_formulario): ?>
                            <a href="tipos_equipo.php" class="btn-secondary btn-modern">
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
                    <!-- Lista de Tipos -->
                    <div class="table-container">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th data-label="ID">ID</th>
                                    <th data-label="Nombre">Nombre</th>
                                    <th data-label="Acciones">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tipos)): ?>
                                    <tr>
                                        <td colspan="3" class="table-empty">
                                            <div class="table-empty-icon">
                                                <i class="fas fa-laptop"></i>
                                            </div>
                                            <div class="table-empty-text">No hay tipos de equipo registrados</div>
                                            <div class="table-empty-subtext">Crea un nuevo tipo de equipo para comenzar</div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tipos as $tipo): ?>
                                        <tr class="table-row-enter">
                                            <td data-label="ID"><?php echo $tipo['id_tipo']; ?></td>
                                            <td data-label="Nombre"><?php echo htmlspecialchars($tipo['nombre']); ?></td>
                                            <td data-label="Acciones">
                                                <div class="table-actions">
                                                    <a href="?editar=<?php echo $tipo['id_tipo']; ?>" 
                                                       class="action-edit" title="Editar tipo de equipo">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?eliminar=<?php echo $tipo['id_tipo']; ?>" 
                                                       onclick="return confirm('¿Está seguro de que desea eliminar este tipo de equipo?')"
                                                       class="action-delete" title="Eliminar tipo de equipo">
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
                            <i class="fas fa-plus mr-2"></i>Nuevo Tipo de Equipo
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Formulario de Edición/Creación -->
                    <form method="POST" class="space-y-6 slide-in-right">
                        <input type="hidden" name="id_tipo" value="<?php echo $tipo_editar && is_array($tipo_editar) ? $tipo_editar['id_tipo'] : ''; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nombre del Tipo -->
                            <div class="md:col-span-2 form-group">
                                <label class="form-label">
                                    Nombre del Tipo de Equipo *
                                </label>
                                <input type="text" name="nombre" 
                                       value="<?php echo $tipo_editar && is_array($tipo_editar) ? htmlspecialchars($tipo_editar['nombre']) : ''; ?>" 
                                       required
                                       class="form-input w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 bg-white"
                                       placeholder="Ej: Laptop, Desktop, Impresora, etc.">
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="tipos_equipo.php" 
                               class="btn-secondary btn-modern">
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="btn-primary btn-modern">
                                <i class="fas fa-save mr-2"></i>
                                <?php 
                                if ($tipo_editar === 'nuevo') {
                                    echo 'Crear';
                                } elseif ($tipo_editar) {
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