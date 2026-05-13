<?php
// Configuración de la base de datos
define('DB_HOST', 'localhosr');
define('DB_USER', 'localhost');
define('DB_PASS', '');
define('DB_NAME', 'ingresoequiposcd');

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8");
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
