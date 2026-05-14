<?php
// ARCHIVO TEMPORAL DE DIAGNÓSTICO - ELIMINAR DESPUÉS
echo "<pre>";
echo "=== VARIABLES DE ENTORNO ===\n";
echo "MYSQLHOST: " . (getenv('MYSQLHOST') ?: 'NO DEFINIDA') . "\n";
echo "MYSQLPORT: " . (getenv('MYSQLPORT') ?: 'NO DEFINIDA') . "\n";
echo "MYSQLUSER: " . (getenv('MYSQLUSER') ?: 'NO DEFINIDA') . "\n";
echo "MYSQLDATABASE: " . (getenv('MYSQLDATABASE') ?: 'NO DEFINIDA') . "\n";
echo "MYSQLPASSWORD: " . (getenv('MYSQLPASSWORD') ? '***DEFINIDA***' : 'NO DEFINIDA') . "\n\n";

echo "=== INTENTANDO CONEXIÓN ===\n";
$host = getenv('MYSQLHOST') ?: 'localhost';
$port = getenv('MYSQLPORT') ?: '3306';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db   = getenv('MYSQLDATABASE') ?: 'railway';

echo "Host: $host\nPort: $port\nUser: $user\nDB: $db\n\n";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "CONEXIÓN: OK\n\n";

    echo "=== SUCURSALES ===\n";
    $rows = $conn->query("SELECT * FROM sucursales")->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);

    echo "\n=== USUARIOS ===\n";
    $rows = $conn->query("SELECT id_usuario, usuario, LENGTH(password) as hash_len, estado FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);

    echo "\n=== TEST PASSWORD VERIFY ===\n";
    $u = $conn->query("SELECT password FROM usuarios WHERE usuario='admin'")->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        echo "Hash en DB: " . $u['password'] . "\n";
        echo "Verifica 'password': " . (password_verify('password', $u['password']) ? 'TRUE ✓' : 'FALSE ✗') . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "</pre>";
