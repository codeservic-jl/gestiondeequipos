<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['numero_serial'])) {
    echo json_encode(['error' => 'Número de serie no proporcionado']);
    exit;
}

$numero_serial = trim($_GET['numero_serial']);

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM equipos WHERE numero_serial = ?");
    $stmt->execute([$numero_serial]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'existe' => $result['total'] > 0
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error al verificar el número de serie']);
}
?>