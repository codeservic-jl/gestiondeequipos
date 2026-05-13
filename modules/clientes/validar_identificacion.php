<?php
require_once '../../config/database.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificacion = $_POST['identificacion'] ?? '';
    
    // Validar que la identificación no esté vacía
    if (empty($identificacion)) {
        echo json_encode(['exists' => false]);
        exit;
    }

    // Consultar en la base de datos
    $stmt = $conn->prepare("SELECT id_cliente FROM clientes WHERE identificacion = ?");
    $stmt->execute([$identificacion]);
    
    // Devolver true si existe, false si no
    echo json_encode(['exists' => (bool)$stmt->fetch()]);
} else {
    http_response_code(405); // Método no permitido
    echo json_encode(['error' => 'Método no permitido']);
}