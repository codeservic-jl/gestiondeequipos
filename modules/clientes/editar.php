<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// Obtener datos del cliente
if (!isset($_GET['id'])) {
    header("Location: lista.php");
    exit();
}

try {
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
    $stmt->execute([$_GET['id']]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        header("Location: lista.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Validar que la identificación no exista (excepto para el mismo cliente)
        $stmt = $conn->prepare("SELECT id_cliente FROM clientes WHERE identificacion = ? AND id_cliente != ?");
        $stmt->execute([$_POST['identificacion'], $_GET['id']]);
        if ($stmt->fetch()) {
            throw new Exception("La identificación ya existe en el sistema.");
        }

        // Actualizar el cliente
        $stmt = $conn->prepare("UPDATE clientes SET 
            identificacion = ?,
            nombre_apellido = ?,
            empresa = ?,
            telefono = ?,
            direccion = ?,
            email = ?,
            estado = ?
            WHERE id_cliente = ?");

        $stmt->execute([
            $_POST['identificacion'],
            $_POST['nombre_apellido'],
            $_POST['empresa'],
            $_POST['telefono'],
            $_POST['direccion'],
            $_POST['email'],
            isset($_POST['estado']) ? 1 : 0,
            $_GET['id']
        ]);

        header("Location: lista.php");
        exit();
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bg-navy-blue {
            background-color: #000080;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content">
        <div class="container mx-auto px-4 py-8">
            <div class="max-w-2xl mx-auto">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold mb-6 text-navy-blue">Editar Cliente</h2>

                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Identificación *</label>
                                <input type="text" name="identificacion" required
                                    value="<?php echo htmlspecialchars($cliente['identificacion']); ?>"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder="Ingrese la identificación">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Nombre y Apellido *</label>
                                <input type="text" name="nombre_apellido" required
                                    value="<?php echo htmlspecialchars($cliente['nombre_apellido']); ?>"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder="Ingrese el nombre completo">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Empresa</label>
                                <input type="text" name="empresa"
                                    value="<?php echo htmlspecialchars($cliente['empresa']); ?>"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder="Ingrese el nombre de la empresa">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Teléfono *</label>
                                <input type="text" name="telefono" required
                                    value="<?php echo htmlspecialchars($cliente['telefono']); ?>"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder="Ingrese el teléfono">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 mb-2">Dirección</label>
                                <input type="text" name="direccion"
                                    value="<?php echo htmlspecialchars($cliente['direccion']); ?>"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder="Ingrese la dirección">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 mb-2">Email</label>
                                <input type="email" name="email"
                                    value="<?php echo htmlspecialchars($cliente['email']); ?>"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder="Ingrese el email">
                            </div>
                            <div class="md:col-span-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="estado" class="form-checkbox h-5 w-5 text-blue-600"
                                        <?php echo $cliente['estado'] ? 'checked' : ''; ?>>
                                    <span class="ml-2">Cliente Activo</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4 mt-6">
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
    </div>
</body>

</html>