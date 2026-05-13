<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit('No autorizado');
}
require_once '../../config/database.php';

// Modificar para aceptar tanto POST como GET
$id_cliente = isset($_POST['id_cliente']) ? $_POST['id_cliente'] : (isset($_GET['cliente_id']) ? $_GET['cliente_id'] : null);

if ($id_cliente) {
    $query = "SELECT * FROM equipos WHERE id_cliente = ? AND estado = 1 ORDER BY marca, modelo";
    $stmt = $conn->prepare($query);
    $stmt->execute([$id_cliente]);
    $equipos = $stmt->fetchAll();
    
    echo '<option value="">Seleccione un equipo</option>';
    foreach ($equipos as $equipo) {
        $marca = htmlspecialchars($equipo['marca']);
        $modelo = htmlspecialchars($equipo['modelo']);
        $numero_serial = htmlspecialchars($equipo['numero_serial']);
        
        echo '<option value="' . $equipo['id_equipo'] . '" 
                      data-marca="' . $marca . '"
                      data-modelo="' . $modelo . '"
                      data-serial="' . $numero_serial . '">' . 
             $marca . ' ' . $modelo . ' (S/N: ' . $numero_serial . ')' . 
             '</option>';
    }
} else {
    echo '<option value="">Primero seleccione un cliente</option>';
}
?>