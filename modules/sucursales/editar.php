<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

if (!isset($_GET['id'])) {
    header("Location: lista.php");
    exit();
}

try {
    // Obtener datos de la sucursal
    $stmt = $conn->prepare("SELECT * FROM sucursales WHERE id_sucursal = ?");
    $stmt->execute([$_GET['id']]);
    $sucursal = $stmt->fetch();

    if (!$sucursal) {
        header("Location: lista.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $stmt = $conn->prepare("UPDATE sucursales SET nombre = ?, direccion = ?, telefono = ?, estado = ? WHERE id_sucursal = ?");
        $stmt->execute([
            $_POST['nombre'],
            $_POST['direccion'],
            $_POST['telefono'],
            isset($_POST['estado']) ? 1 : 0,
            $_GET['id']
        ]);
        
        header("Location: lista.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Error al procesar la solicitud";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Sucursal - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .bg-navy-blue { background-color: #000080; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-navy-blue mb-6">Editar Sucursal</h2>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Nombre</label>
                        <input type="text" name="nombre" required
                               value="<?php echo htmlspecialchars($sucursal['nombre']); ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">Dirección</label>
                        <input type="text" name="direccion" required
                               value="<?php echo htmlspecialchars($sucursal['direccion']); ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">Teléfono</label>
                        <input type="text" name="telefono" required
                               value="<?php echo htmlspecialchars($sucursal['telefono']); ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="estado"
                                   <?php echo $sucursal['estado'] ? 'checked' : ''; ?>
                                   class="form-checkbox h-5 w-5 text-blue-600">
                            <span class="ml-2 text-gray-700">Activo</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <a href="lista.php" 
                           class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-100">
                            Cancelar
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 bg-navy-blue text-white rounded-lg hover:bg-blue-900">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>