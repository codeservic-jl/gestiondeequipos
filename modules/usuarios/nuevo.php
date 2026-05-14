<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// Obtener tipos de usuario
$tipos = $conn->query("SELECT * FROM tipos_usuario WHERE estado = 1")->fetchAll();

// Obtener sucursales activas
$sucursales = $conn->query("SELECT * FROM sucursales WHERE estado = 1")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validar que la sucursal exista y esté activa
        $stmt = $conn->prepare("SELECT id_sucursal FROM sucursales WHERE id_sucursal = ? AND estado = 1");
        $stmt->execute([$_POST['id_sucursal']]);
        if (!$stmt->fetch()) {
            throw new Exception("La sucursal seleccionada no es válida");
        }
        
        // Generar hash de la contraseña
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre_completo, usuario, password, id_tipo, id_sucursal, estado) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['nombre_completo'],
            $_POST['usuario'],
            $password_hash,
            $_POST['id_tipo'],
            $_POST['id_sucursal'],
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Usuario - Ingreso de equipos</title>
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
                <h2 class="text-2xl font-bold mb-6 text-navy-blue">Nuevo Usuario</h2>
                
                <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Nombre Completo</label>
                        <input type="text" name="nombre_completo" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Usuario</label>
                        <input type="text" name="usuario" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Contraseña</label>
                        <input type="password" name="password" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Tipo de Usuario</label>
                        <select name="id_tipo" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="">Seleccione un tipo</option>
                            <?php foreach ($tipos as $tipo): ?>
                            <option value="<?php echo $tipo['id_tipo']; ?>">
                                <?php echo htmlspecialchars($tipo['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Sucursal</label>
                        <select name="id_sucursal" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="">Seleccione una sucursal</option>
                            <?php foreach ($sucursales as $sucursal): ?>
                            <option value="<?php echo $sucursal['id_sucursal']; ?>">
                                <?php echo htmlspecialchars($sucursal['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="estado" id="estado" checked
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <label for="estado" class="ml-2 block text-gray-700">Usuario Activo</label>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <a href="lista.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                            Cancelar
                        </a>
                        <button type="submit" class="px-4 py-2 bg-navy-blue text-white rounded-lg hover:bg-blue-900">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>