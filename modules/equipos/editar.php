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
    // Obtener datos del equipo
    $stmt = $conn->prepare("
        SELECT e.*, c.nombre_apellido as cliente 
        FROM equipos e
        LEFT JOIN clientes c ON e.id_cliente = c.id_cliente
        WHERE e.id_equipo = ?
    ");
    $stmt->execute([$_GET['id']]);
    $equipo = $stmt->fetch();

    if (!$equipo) {
        header("Location: lista.php");
        exit();
    }

    // Obtener lista de clientes para el select
    $query = "SELECT * FROM clientes WHERE estado = 1";
    if ($_SESSION['user_type'] != 1) {
        $query .= " AND id_sucursal = :sucursal";
        $stmt = $conn->prepare($query);
        $stmt->execute(['sucursal' => $_SESSION['sucursal']]);
        $clientes = $stmt->fetchAll();
    } else {
        $clientes = $conn->query($query)->fetchAll();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $stmt = $conn->prepare("
            UPDATE equipos 
            SET marca = ?, 
                modelo = ?, 
                numero_serial = ?, 
                id_cliente = ?,
                estado = ?
            WHERE id_equipo = ?
        ");
        
        $stmt->execute([
            $_POST['marca'],
            $_POST['modelo'],
            $_POST['numero_serial'],
            $_POST['id_cliente'],
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Editar Equipo - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .bg-navy-blue {
            background-color: #5AC456;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-navy-blue mb-6">Editar Equipo</h2>

                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="cliente">
                            Cliente
                        </label>
                        <select name="id_cliente" id="cliente" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-blue" required>
                            <option value="">Seleccione un cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id_cliente']; ?>" <?php echo $cliente['id_cliente'] == $equipo['id_cliente'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nombre_apellido']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="marca">
                            Marca
                        </label>
                        <input type="text" name="marca" id="marca" 
                               value="<?php echo htmlspecialchars($equipo['marca']); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-blue" 
                               required>
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="modelo">
                            Modelo
                        </label>
                        <input type="text" name="modelo" id="modelo" 
                               value="<?php echo htmlspecialchars($equipo['modelo']); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-blue" 
                               required>
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="numero_serial">
                            Número de Serie
                        </label>
                        <input type="text" name="numero_serial" id="numero_serial" 
                               value="<?php echo htmlspecialchars($equipo['numero_serial']); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-navy-blue" 
                               required>
                    </div>

                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="estado" class="form-checkbox h-5 w-5 text-navy-blue" 
                                   <?php echo $equipo['estado'] ? 'checked' : ''; ?>>
                            <span class="ml-2 text-gray-700">Activo</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <a href="lista.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                            Cancelar
                        </a>
                        <button type="submit" class="bg-navy-blue text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>
</body>
</html>