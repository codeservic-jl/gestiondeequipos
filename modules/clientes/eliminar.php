<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

if (isset($_GET['id'])) {
    try {
        $conn->beginTransaction();

        // Verificar si el cliente tiene órdenes pendientes o no entregadas
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM ordenes_trabajo ot 
            JOIN equipos e ON ot.id_equipo = e.id_equipo 
            WHERE e.id_cliente = ? 
            AND e.estado = 1 
            AND ot.estado != 'Entregado'
        ");
        $stmt->execute([$_GET['id']]);
        $ordenesPendientes = $stmt->fetchColumn();

        if ($ordenesPendientes > 0) {
            $_SESSION['error'] = "No se puede inactivar el cliente porque tiene órdenes de trabajo pendientes o no entregadas";
            $conn->rollBack();
        } else {
            // Verificar equipos asociados
            $stmt = $conn->prepare("SELECT COUNT(*) FROM equipos WHERE id_cliente = ? AND estado = 1");
            $stmt->execute([$_GET['id']]);
            $equiposAsociados = $stmt->fetchColumn();

            if ($equiposAsociados > 0) {
                // Inactivar equipos asociados
                $stmt = $conn->prepare("UPDATE equipos SET estado = 0 WHERE id_cliente = ?");
                $stmt->execute([$_GET['id']]);
                
                // Inactivar solo las órdenes completadas/entregadas
                $stmt = $conn->prepare("
                    UPDATE ordenes_trabajo ot 
                    JOIN equipos e ON ot.id_equipo = e.id_equipo 
                    SET ot.estado = 0 
                    WHERE e.id_cliente = ? 
                    AND ot.estado = 'Entregado'
                ");
                $stmt->execute([$_GET['id']]);
            }

            // Inactivar el cliente
            $stmt = $conn->prepare("UPDATE clientes SET estado = 0 WHERE id_cliente = ?");
            $stmt->execute([$_GET['id']]);

            $conn->commit();
            $_SESSION['success'] = "Cliente inactivado correctamente junto con sus equipos y órdenes completadas en el caso hayan existido";
        }

    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error al inactivar el cliente: " . $e->getMessage();
    }
}

header("Location: lista.php");
exit();