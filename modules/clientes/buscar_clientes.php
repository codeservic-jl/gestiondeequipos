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
$query = "SELECT * FROM clientes WHERE 1=1";
$params = [];

if ($termino_busqueda) {
    $query .= " AND (nombre_apellido LIKE :busqueda OR email LIKE :busqueda OR telefono LIKE :busqueda OR estado LIKE :busqueda)";
    $params['busqueda'] = "%$termino_busqueda%";
}
/* $query .= " ORDER BY fecha_ingreso ASC"; */
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
$clientes = $stmt->fetchAll();

// Generar HTML para la tabla
$html = '';
foreach ($clientes as $cliente) {
    $html .= '<tr class="border-b hover:bg-gray-50">';
    $html .= '<td class="px-4 py-2  text-gray-500">';
    $html .= '<a href="ver_cliente.php?id=' . $cliente['id_cliente'] . '" class="text-blue-600 hover:text-blue-800 mr-2" title="Ver detalles"><i class="fas fa-eye"></i></a>';
    $html .= '<a href="editar.php?id=' . $cliente['id_cliente'] . '" class="text-yellow-600 hover:text-yellow-800 mr-2" title="Editar cliente"><i class="fas fa-edit"></i></a>';
    $html .= '<a href="eliminar.php?id=' . $cliente['id_cliente'] . '" class="text-red-600 hover:text-red-800" onclick="return confirm(\'¿Está seguro de que desea eliminar este cliente?\')" title="Eliminar cliente"><i class="fas fa-trash"></i></a>';
    $html .= '</td>';
    $html .= '<td class="px-6 py-2  text-gray-500"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . ($cliente['estado'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . '">' . ($cliente['estado'] ? 'Activo' : 'Inactivo') . '</span></td>';
    $html .= '<td class="px-4 py-2  text-gray-500">' . htmlspecialchars($cliente['nombre_apellido']) . '</td>';
    $html .= '<td class="px-4 py-2  text-gray-500">';
    $html .= '<a href="https://wa.me/+593' . htmlspecialchars($cliente['telefono']) . '" target="_blank">';
    $html .= htmlspecialchars($cliente['telefono']);
    $html .= '</a></td>';
    $html .= '<td class="px-4 py-2  text-gray-500">' . htmlspecialchars($cliente['email']) . '</td>';
    $html .= '</tr>';
}

// Devolver respuesta JSON
header('Content-Type: application/json');
echo json_encode([
    'html' => $html,
    'totalPages' => $total_paginas,
    'currentPage' => $pagina_actual
]);