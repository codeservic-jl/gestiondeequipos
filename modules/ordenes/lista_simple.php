<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

echo "<h1>Lista Simple</h1>";
echo "<p>Sesión iniciada correctamente</p>";
echo "<p>Usuario ID: " . $_SESSION['user_id'] . "</p>";

// Probar conexión a base de datos
try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM ordenes_trabajo");
    $result = $stmt->fetch();
    echo "<p>Total de órdenes: " . $result['total'] . "</p>";
} catch (Exception $e) {
    echo "<p>Error en BD: " . $e->getMessage() . "</p>";
}
?> 