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
        if ($user) {
            // Crear un nuevo hash con la contraseña proporcionada por el usuario
            $newHash = password_hash($password, PASSWORD_DEFAULT);

            // Actualizar solo el hash en la base de datos
            $updateStmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE usuario = ?");
            $updateStmt->execute([$newHash, $usuario]);

            // Intentar verificar con el nuevo hash
            if (password_verify($password, $newHash)) {
                $_SESSION['user_id'] = $user['id_usuario'];
                $_SESSION['user_name'] = $user['nombre_completo'];
                $_SESSION['user_type'] = $user['id_tipo'];
                $_SESSION['sucursal'] = $_POST['id_sucursal']; // Guardamos la sucursal seleccionada en la sesión
                $_SESSION['nombre_completo'] = $user['nombre_completo'];

                header("Location: index.php");
                exit();
            } else {
                $error = "Contraseña incorrecta";
            }
        } else {
            $error = "Usuario no encontrado";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestión de Equipos RGE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .bg-navy-blue {
            background-color: #000080;
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
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-gray-700">Contraseña</label>
                    <input type="password" name="password" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>

                <select name="id_sucursal" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    <option value="">Seleccione una sucursal</option>
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?php echo $sucursal['id_sucursal']; ?>">
                            <?php echo htmlspecialchars($sucursal['nombre'] . ': ' . $sucursal['direccion']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"
                    class="w-full bg-navy-blue text-white py-2 rounded-lg hover:bg-blue-900 transition duration-200">
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
</body>

</html>