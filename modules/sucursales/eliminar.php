<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

if (isset($_GET['id'])) {
    try {
        // Verificar si la sucursal tiene registros relacionados
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM usuarios WHERE id_sucursal = ?");
        $stmt->execute([$_GET['id']]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            $_SESSION['error'] = "No se puede eliminar la sucursal porque tiene usuarios asociados";
        } else {
            // Eliminar la sucursal
            $stmt = $conn->prepare("UPDATE sucursales SET estado = 0 WHERE id_sucursal = ?");
            $stmt->execute([$_GET['id']]);
            $_SESSION['success'] = "Sucursal eliminada correctamente";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al eliminar la sucursal";
    }
}

header("Location: lista.php");
exit();