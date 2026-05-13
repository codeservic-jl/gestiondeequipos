<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

if (isset($_GET['id'])) {
    try {
        // Verificar si el usuario tiene registros relacionados
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_trabajo WHERE id_usuario_registro = ?");
        $stmt->execute([$_GET['id']]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            $_SESSION['error'] = "No se puede eliminar el usuario porque tiene órdenes de trabajo asociadas";
        } else {
            // Desactivar el usuario en lugar de eliminarlo
            $stmt = $conn->prepare("UPDATE usuarios SET estado = 0 WHERE id_usuario = ?");
            $stmt->execute([$_GET['id']]);
            $_SESSION['success'] = "Usuario eliminado correctamente";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al eliminar el usuario";
    }
}

header("Location: lista.php");
exit();