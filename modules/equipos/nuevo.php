<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// Obtener clientes activos
$clientes = $conn->query("SELECT * FROM clientes WHERE estado = 1 ORDER BY nombre_apellido")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Verificar límite de equipos por cliente
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM equipos WHERE id_cliente = ? AND estado = 1");
        $stmt->execute([$_POST['id_cliente']]);
        $total_equipos = $stmt->fetch()['total'];

        if ($total_equipos >= 10) {
            throw new Exception("El cliente ya tiene el máximo de 10 equipos registrados");
        }

        $stmt = $conn->prepare("INSERT INTO equipos (id_cliente, marca, modelo, numero_serial, estado) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['id_cliente'],
            $_POST['marca'],
            $_POST['modelo'],
            $_POST['numero_serial'],
            isset($_POST['estado']) ? 1 : 0
        ]);
        
        header("Location: lista.php");
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Nuevo Equipo - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .bg-navy-blue { background-color: #5AC456; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="main-content">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-6 text-navy-blue">Nuevo Equipo</h2>
                
                <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Cliente</label>
                        <select name="id_cliente" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="">Seleccione un cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id_cliente']; ?>">
                                <?php echo htmlspecialchars($cliente['nombre_apellido']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Marca</label>
                        <input type="text" name="marca" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Modelo</label>
                        <input type="text" name="modelo" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Número de Serie</label>
                        <input type="text" name="numero_serial" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="estado" id="estado" checked
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <label for="estado" class="ml-2 block text-gray-700">Equipo Activo</label>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <a href="lista.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                            Cancelar
                        </a>
                        <button type="submit" class="px-4 py-2 bg-navy-blue text-white rounded-lg hover:bg-green-700">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>
</body>
</html>