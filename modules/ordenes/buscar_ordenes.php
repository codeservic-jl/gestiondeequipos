<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado');
}

require_once '../../config/database.php';

// Configuración de paginación
$items_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$termino_busqueda = isset($_GET['buscar']) ? $_GET['buscar'] : '';

// Construir la consulta base
$query = "SELECT o.*, c.nombre_apellido as cliente, u.nombre_completo as usuario_registro 
          FROM ordenes_trabajo o 
          LEFT JOIN clientes c ON o.id_cliente = c.id_cliente 
          LEFT JOIN usuarios u ON o.id_usuario_registro = u.id_usuario
          WHERE 1=1";

$params = [];

if ($termino_busqueda) {
    $query .= " AND (c.nombre_apellido LIKE :busqueda OR o.codigo LIKE :busqueda  OR o.estado LIKE :busqueda)";
    $params['busqueda'] = "%$termino_busqueda%";
}

$query .= " ORDER BY o.fecha_ingreso DESC";

// Obtener total de registros
$stmt = $conn->prepare($query);
$stmt->execute($params);
$total_registros = $stmt->rowCount();
$total_paginas = ceil($total_registros / $items_por_pagina);

// Añadir límite para la paginación
$offset = ($pagina_actual - 1) * $items_por_pagina;
$query .= " LIMIT :offset, :limit";
$params['offset'] = $offset;
$params['limit'] = $items_por_pagina;

// Ejecutar la consulta final
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$ordenes = $stmt->fetchAll();

// Generar HTML para la tabla
$html = '';
foreach ($ordenes as $orden) {
    $html .= '<tr class="table-row-enter">';
    $html .= '<td data-label="Acciones">';
    $html .= ' <div class="table-actions">';

    $html .= '<a href="ver.php?id=' . $orden['id_orden'] . '" class="action-view" title="Ver Orden"><i class="fas fa-eye"></i></a>';
    // Agregar botón de anular solo si la orden no está anulada
    if ($orden['estado'] !== 'Anulada') {
        $html .= '<a href="editar.php?id=' . $orden['id_orden'] . '" class="action-edit" title="Editar Orden"><i class="fas fa-edit"></i></a>';
        $html .= '<a href="registrar_seguimiento.php?id=' . $orden['id_orden'] . '" class="action-success" title="Registrar Seguimiento"><i class="fas fa-clipboard-check"></i></a>';
        
        $html .= '<a href="anular.php?id=' . $orden['id_orden'] . '" class="action-danger" title="Anular Orden" onclick="return confirm(\'¿Está seguro de que desea anular esta orden?\')"><i class="fas fa-ban"></i></a>';
    }

    $html .= '</div>';
    $html .= '</td>';

    $html .= '<td data-label="Cliente">' . htmlspecialchars($orden['cliente']) . '</td>';
    $html .= '<td data-label="Código" class="font-mono">' . htmlspecialchars($orden['codigo']) . '</td>';
    $html .= '<td data-label="Estado">';
    $html .= '<span class="badge ';
    if ($orden['estado'] == 'Pendiente') {
        $html .= 'badge-warning';
    } elseif ($orden['estado'] == 'En Proceso') {
        $html .= 'badge-info';
    } elseif ($orden['estado'] == 'Anulada') {
        $html .= 'badge-danger';
    } else {
        $html .= 'badge-success';
    }
    $html .= '">' . htmlspecialchars($orden['estado']) . '</span>';
    $html .= '</td>';
    $html .= '<td data-label="Fecha">' . date('d/m/Y H:i', strtotime($orden['fecha_ingreso'])) . '</td>';
    $html .= '</tr>';
}

// Devolver respuesta JSON
header('Content-Type: application/json');
echo json_encode([
    'html' => $html,
    'totalPages' => $total_paginas
]);
