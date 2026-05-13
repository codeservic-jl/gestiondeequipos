<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit('No autorizado');
}
require_once '../../config/database.php';

// Configuración de paginación
$items_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$termino_busqueda = isset($_GET['buscar']) ? $_GET['buscar'] : '';

// Construir la consulta base
$query = "SELECT u.*, t.nombre as tipo_usuario, s.nombre as nombre_sucursal 
          FROM usuarios u 
          LEFT JOIN tipos_usuario t ON u.id_tipo = t.id_tipo
          LEFT JOIN sucursales s ON u.id_sucursal = s.id_sucursal
          WHERE 1=1";
$params = [];

// Añadir condiciones de búsqueda si existe un término
if ($termino_busqueda) {
    $query .= " AND (u.nombre_completo LIKE :busqueda OR u.usuario LIKE :busqueda OR t.nombre LIKE :busqueda OR s.nombre LIKE :busqueda)";
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
$usuarios = $stmt->fetchAll();

// Generar HTML para la tabla
$html = '';
foreach ($usuarios as $usuario) {
    $html .= '<tr class="hover:bg-gray-50">';
    $html .= '<td class="px-6 py-4 text-sm">';
    if ($_SESSION['user_type'] == 1) {
        $html .= '<a href="editar.php?id=' . $usuario['id_usuario'] . '" class="text-navy-blue hover:text-blue-900 mr-3">';
        $html .= '<i class="fas fa-edit"></i>';
        $html .= '</a>';
        $html .= '<a href="#" onclick="confirmarEliminacion(' . $usuario['id_usuario'] . ')" class="text-red-600 hover:text-red-900">';
        $html .= '<i class="fas fa-trash"></i>';
        $html .= '</a>';
    }
    $html .= '</td>';
    $html .= '<td class="px-6 py-4">';
    $html .= '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . 
             ($usuario['estado'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . '">';
    $html .= $usuario['estado'] ? 'Activo' : 'Inactivo';
    $html .= '</span>';
    $html .= '</td>';
    $html .= '<td class="px-6 py-4">' . htmlspecialchars($usuario['nombre_completo']) . '</td>';
    $html .= '<td class="px-6 py-4">' . htmlspecialchars($usuario['usuario']) . '</td>';
    $html .= '<td class="px-6 py-4">' . htmlspecialchars($usuario['tipo_usuario']) . '</td>';
    $html .= '<td class="px-6 py-4">' . htmlspecialchars($usuario['nombre_sucursal']) . '</td>';
    $html .= '</tr>';
}

// Devolver respuesta JSON
header('Content-Type: application/json');
echo json_encode([
    'html' => $html,
    'totalPages' => $total_paginas
]);
?>