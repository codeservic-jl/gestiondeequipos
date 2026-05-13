<?php
require_once 'config/database.php';

echo "<h2>Configuración de Tablas de Parámetros</h2>";

try {
    // Crear tabla orden_estados si no existe
    $sql = "CREATE TABLE IF NOT EXISTS `orden_estados` (
        `id_orden_estado` int(11) NOT NULL AUTO_INCREMENT,
        `nombre_estado` varchar(100) NOT NULL,
        `descripcion` varchar(200) DEFAULT NULL,
        `color` varchar(7) DEFAULT '#6B7280',
        `estado` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id_orden_estado`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
    
    $conn->exec($sql);
    echo "✓ Tabla orden_estados creada/verificada<br>";
    
    // Verificar si hay datos
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM orden_estados");
    $stmt->execute();
    $count = $stmt->fetch()['total'];
    
    if ($count == 0) {
        echo "Insertando datos por defecto...<br>";
        
        $inserts = [
            ['Pendiente'],
            ['En Proceso'],
            ['Completado'],
            ['Entregado'],
            ['Cancelado']
        ];
        
        $stmt = $conn->prepare("INSERT INTO orden_estados (nombre_estado, estado) VALUES (?, 1)");
        foreach ($inserts as $insert) {
            $stmt->execute($insert);
        }
        echo "✓ Datos por defecto insertados<br>";
    } else {
        echo "✓ La tabla ya tiene {$count} registros<br>";
    }
    
    echo "<br><strong>Configuración completada. Ahora puedes usar la sección de parámetros.</strong><br>";
    echo "<a href='modules/parametros/estados_orden.php'>Ir a Estados de Orden</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 