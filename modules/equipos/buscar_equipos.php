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
$query = "SELECT e.*, c.nombre_apellido as cliente 
          FROM equipos e 
          LEFT JOIN clientes c ON e.id_cliente = c.id_cliente
          WHERE 1=1";
$params = [];

if ($termino_busqueda) {
    $query .= " AND (e.marca LIKE :busqueda OR e.modelo LIKE :busqueda OR e.numero_serial LIKE :busqueda OR c.nombre_apellido LIKE :busqueda)";
    $params['busqueda'] = "%$termino_busqueda%";
}

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
$equipos = $stmt->fetchAll();

// Generar HTML para la tabla
$html = '';
foreach ($equipos as $equipo) {
    $html .= '<tr class="border-b hover:bg-gray-50">';
    $html .= '<td class="px-4 py-2  text-gray-500">';
    $html .= '<a href="ver.php?id=' . $equipo['id_equipo'] . '" class="text-green-700 hover:text-blue-800 mr-2"> <i class="fas fa-eye"></i></a>';
    $html .= '<a href="editar.php?id=' . $equipo['id_equipo'] . '" class="text-yellow-600 hover:text-yellow-800 mr-2"><i class="fas fa-edit"></i> </a>';
    $html .= '<a href="#" class="text-red-600 hover:text-red-800 mr-2" onclick="confirmarEliminacion(' . $equipo['id_equipo'] . ')" ><i class="fas fa-trash"></i></a>';
    $html .= '</td>';
    $html .= '<td class="px-4 py-2 text-gray-500">' . htmlspecialchars($equipo['cliente']) . '</td>';
    $html .= '<td class="px-4 py-2 text-gray-500">' . htmlspecialchars($equipo['marca']) . '</td>';
    $html .= '<td class="px-4 py-2 text-gray-500">' . htmlspecialchars($equipo['modelo']) . '</td>';
    $html .= '<td class="px-4 py-2 text-gray-500">' . htmlspecialchars($equipo['numero_serial']) . '</td>';
    $html .= '</tr>';
}

// Devolver respuesta JSON
header('Content-Type: application/json');
echo json_encode([
    'html' => $html,
    'totalPages' => $total_paginas
]);
