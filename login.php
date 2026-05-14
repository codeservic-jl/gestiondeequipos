<?php
session_start();
require_once 'config/database.php';
// Obtener tipos de usuario
$sucursales = $conn->query("SELECT * FROM sucursales WHERE estado = 1")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);

    try {
        // Verificar si el usuario existe
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = ? AND estado = 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // En la parte del procesamiento del formulario
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['nombre_completo'];
            $_SESSION['user_type'] = $user['id_tipo'];
            $_SESSION['sucursal'] = $_POST['id_sucursal'];
            $_SESSION['nombre_completo'] = $user['nombre_completo'];

            header("Location: index.php");
            exit();
        } else {
            $error = "Usuario o contraseña incorrectos";
        }
    } catch (PDOException $e) {
        $error = "Error en la base de datos";
        error_log("Error DB: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .bg-navy-blue {
            background-color: #5AC456;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-lg w-96">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-navy-blue">RGE</h1>
                <p class="text-gray-600">Sistema de Gestión de Equipos</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700">Usuario</label>
                    <input type="text" name="usuario" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>

                <div>
                    <label class="block text-gray-700">Contraseña</label>
                    <input type="password" name="password" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                </div>

                <select name="id_sucursal" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-green-500">
                    <?php if (count($sucursales) !== 1): ?>
                    <option value="">Seleccione una sucursal</option>
                    <?php endif; ?>
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?php echo $sucursal['id_sucursal']; ?>" <?php echo count($sucursales) === 1 ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sucursal['nombre'] . ': ' . $sucursal['direccion']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"
                    class="w-full bg-navy-blue text-white py-2 rounded-lg hover:bg-green-700 transition duration-200">
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
</body>

</html>