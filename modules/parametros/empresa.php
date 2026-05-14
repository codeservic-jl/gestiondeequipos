<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// Obtener información actual de la empresa
$stmt = $conn->prepare("SELECT * FROM empresa LIMIT 1");
$stmt->execute();
$empresa = $stmt->fetch();

// Si no existe, crear un registro por defecto
if (!$empresa) {
    $stmt = $conn->prepare("INSERT INTO empresa (nombre_empresa, slogan, leyenda1, leyenda2, iva) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['REBALLING GUAYAQUIL ECUADOR', 'EXPERTOS EN REBALLING Y ELECTRÓNICA', 'Leyenda 1', 'Leyenda 2', 0.15]);
    $empresa = [
        'id_empresa' => $conn->lastInsertId(),
        'nombre_empresa' => 'REBALLING GUAYAQUIL ECUADOR',
        'slogan' => 'EXPERTOS EN REBALLING Y ELECTRÓNICA',
        'leyenda1' => 'Leyenda 1',
        'leyenda2' => 'Leyenda 2',
        'iva' => 0.15
    ];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $conn->prepare("UPDATE empresa SET nombre_empresa = ?, slogan = ?, leyenda1 = ?, leyenda2 = ?, iva = ? WHERE id_empresa = ?");
        $stmt->execute([
            $_POST['nombre_empresa'],
            $_POST['slogan'],
            $_POST['leyenda1'],
            $_POST['leyenda2'],
            $_POST['iva'],
            $empresa['id_empresa']
        ]);
        
        $_SESSION['success'] = "Información de la empresa actualizada correctamente";
        header("Location: empresa.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al actualizar la información: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información de la Empresa - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

    <div class="main-content">
        <div class="container mx-auto px-4 py-8">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">Información de la Empresa</h1>
                    <a href="../../index.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Volver
                    </a>
                </div>

                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Nombre de la Empresa -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre de la Empresa *
                            </label>
                            <input type="text" name="nombre_empresa" 
                                   value="<?php echo htmlspecialchars($empresa['nombre_empresa']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Slogan -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Slogan *
                            </label>
                            <input type="text" name="slogan" 
                                   value="<?php echo htmlspecialchars($empresa['slogan']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Leyenda 1 -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Leyenda 1 *
                            </label>
                            <textarea name="leyenda1" rows="3" required
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($empresa['leyenda1']); ?></textarea>
                        </div>

                        <!-- Leyenda 2 -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Leyenda 2 *
                            </label>
                            <textarea name="leyenda2" rows="3" required
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($empresa['leyenda2']); ?></textarea>
                        </div>

                        <!-- IVA -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                IVA (%) *
                            </label>
                            <input type="number" name="iva" step="0.01" min="0" max="1"
                                   value="<?php echo htmlspecialchars($empresa['iva']); ?>" 
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="window.location.href='../../index.php'" 
                                class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors duration-200">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-save mr-2"></i>Guardar Cambios
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