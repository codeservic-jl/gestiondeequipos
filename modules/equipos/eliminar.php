<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

if (isset($_GET['id'])) {
    try {
        // Verificar si el equipo existe
        $stmt = $conn->prepare("SELECT id_equipo FROM equipos WHERE id_equipo = ?");
        $stmt->execute([$_GET['id']]);
        if (!$stmt->fetch()) {
            $_SESSION['error'] = "El equipo no existe.";
            header("Location: lista.php");
            exit();
        }

        // Verificar si el equipo tiene órdenes asociadas
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ordenes_trabajo WHERE id_equipo = ?");
        $stmt->execute([$_GET['id']]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = "No se puede eliminar el equipo porque tiene órdenes de trabajo asociadas.";
            header("Location: lista.php");
            exit();
        }

        // Eliminar el equipo
        $stmt = $conn->prepare("DELETE FROM equipos WHERE id_equipo = ?");
        $stmt->execute([$_GET['id']]);

        $_SESSION['success'] = "Equipo eliminado correctamente de la base de datos.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al eliminar el equipo.";
    }
} else {
    $_SESSION['error'] = "ID de equipo no proporcionado.";
}

header("Location: lista.php");
exit();