<?php
// Configuración para subida de archivos - Límites más altos
@ini_set('upload_max_filesize', '100M');
@ini_set('post_max_size', '120M');
@ini_set('max_execution_time', 900);
@ini_set('memory_limit', '1G');
@ini_set('max_input_vars', 20000);
@ini_set('max_file_uploads', 50);
@ini_set('max_input_time', 900);

// Configuración adicional para manejo de archivos grandes
if (function_exists('set_time_limit')) {
    @set_time_limit(900);
}

// Verificar configuración aplicada
error_log("Upload max filesize: " . ini_get('upload_max_filesize'));
error_log("Post max size: " . ini_get('post_max_size'));
error_log("Memory limit: " . ini_get('memory_limit'));

// Verificar si hay error de límite POST
if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $postMaxSize = ini_get('post_max_size');
    $postMaxSizeBytes = return_bytes($postMaxSize);
    
    if ($_SERVER['CONTENT_LENGTH'] > $postMaxSizeBytes) {
        error_log("POST Content-Length (" . $_SERVER['CONTENT_LENGTH'] . ") exceeds limit (" . $postMaxSizeBytes . ")");
    }
}

// Función para convertir tamaños de archivo
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

session_start();
require_once '../../config/database.php';

// Función para redimensionar y comprimir imágenes
function resizeAndCompressImage($sourcePath, $destinationPath, $maxWidth = 1200, $maxHeight = 1200, $quality = 80) {
    // Obtener información de la imagen
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }

    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];

    // Determinar el tipo de imagen
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }

    if (!$sourceImage) {
        return false;
    }

    // Calcular nuevas dimensiones manteniendo la proporción
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    
    // Si la imagen es más pequeña que el máximo, no redimensionar
    if ($ratio >= 1) {
        $newWidth = $originalWidth;
        $newHeight = $originalHeight;
    } else {
        $newWidth = round($originalWidth * $ratio);
        $newHeight = round($originalHeight * $ratio);
    }

    // Crear nueva imagen
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Preservar transparencia para PNG y GIF
    if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    // Redimensionar
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

    // Guardar imagen comprimida
    $success = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $success = imagejpeg($newImage, $destinationPath, $quality);
            break;
        case 'image/png':
            // Para PNG, la calidad es de 0-9, donde 9 es la máxima compresión
            $pngQuality = round((100 - $quality) / 11.111111);
            $success = imagepng($newImage, $destinationPath, $pngQuality);
            break;
        case 'image/gif':
            $success = imagegif($newImage, $destinationPath);
            break;
        case 'image/webp':
            $success = imagewebp($newImage, $destinationPath, $quality);
            break;
    }

    // Liberar memoria
    imagedestroy($sourceImage);
    imagedestroy($newImage);

    return $success;
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Verificar si se proporcionó un ID de orden
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: lista.php");
    exit();
}

$id_orden = $_GET['id'];
$base_url = "../../";

// Obtener información de la orden
try {
    $stmt = $conn->prepare("
        SELECT ot.*, c.nombre_apellido as cliente_nombre, c.identificacion, c.telefono,
               e.marca, e.modelo, e.numero_serial, s.nombre as sucursal_nombre,
               u.nombre_completo as tecnico_nombre
        FROM ordenes_trabajo ot
        INNER JOIN clientes c ON ot.id_cliente = c.id_cliente
        INNER JOIN equipos e ON ot.id_equipo = e.id_equipo
        INNER JOIN sucursales s ON ot.id_sucursal = s.id_sucursal
        LEFT JOIN usuarios u ON ot.tecnico_responsable_id = u.id_usuario
        WHERE ot.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $orden = $stmt->fetch();

    if (!$orden) {
        throw new Exception("Orden no encontrada");
    }

    // Obtener estados disponibles
    $stmt = $conn->prepare("SELECT * FROM orden_estados ORDER BY nombre_estado");
    $stmt->execute();
    $estados = $stmt->fetchAll();

    // Obtener tipos de servicio disponibles
    $stmt = $conn->prepare("SELECT * FROM tipos_servicio WHERE estado = 1 ORDER BY nombre");
    $stmt->execute();
    $tipos_servicio = $stmt->fetchAll();

    // Obtener seguimientos existentes con info de ventas y colega
    $stmt = $conn->prepare("
        SELECT so.*, u.nombre_completo as usuario_nombre,
               v.producto as venta_producto, v.valor_compra as venta_compra,
               v.valor_venta as venta_precio, v.ganancia_neta as venta_ganancia
        FROM seguimientos_orden so
        LEFT JOIN usuarios u ON so.id_tecnico = u.id_usuario
        LEFT JOIN ventas_orden v ON v.id_seguimiento = so.id_seguimiento
        WHERE so.id_orden = ?
        ORDER BY so.fecha_registro DESC
    ");
    $stmt->execute([$id_orden]);
    $seguimientos = $stmt->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Procesar el formulario de seguimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Debug temporal - verificar datos recibidos
        error_log("POST data received: " . print_r($_POST, true));
        error_log("FILES data received: " . print_r($_FILES, true));
        error_log("Content-Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'not set'));
        error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);

        // Verificar si hay datos POST
        if (empty($_POST)) {
            // Verificar si es un error de límite POST
            if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
                $postMaxSize = ini_get('post_max_size');
                $postMaxSizeBytes = return_bytes($postMaxSize);
                
                if ($_SERVER['CONTENT_LENGTH'] > $postMaxSizeBytes) {
                    throw new Exception("El archivo es demasiado grande. Límite actual: $postMaxSize. Tamaño del archivo: " . 
                        round($_SERVER['CONTENT_LENGTH'] / (1024 * 1024), 2) . "MB");
                }
            }
            
            throw new Exception("No se recibieron datos del formulario. Verifique que el formulario se envió correctamente. " .
                "Tamaño del archivo: " . (isset($_SERVER['CONTENT_LENGTH']) ? round($_SERVER['CONTENT_LENGTH'] / (1024 * 1024), 2) . "MB" : "desconocido"));
        }

        // Validar que todos los campos requeridos estén presentes
        if (!isset($_POST['tipo_servicio']) || empty($_POST['tipo_servicio'])) {
            throw new Exception("Debe seleccionar un tipo de servicio");
        }

        if (!isset($_POST['procedimiento']) || empty(trim($_POST['procedimiento']))) {
            throw new Exception("Debe ingresar el procedimiento realizado");
        }

        if (!isset($_POST['id_tecnico']) || empty($_POST['id_tecnico'])) {
            throw new Exception("Debe seleccionar un técnico responsable");
        }

        if (!isset($_POST['nuevo_estado']) || empty($_POST['nuevo_estado'])) {
            throw new Exception("Debe seleccionar un nuevo estado");
        }

        $tipo_servicio = $_POST['tipo_servicio'];
        $procedimiento = trim($_POST['procedimiento']);
        $valor_cobrar  = floatval($_POST['valor_cobrar'] ?? 0);
        $id_tecnico    = $_POST['id_tecnico'];
        $nuevo_estado  = $_POST['nuevo_estado'];

        // Colega / tercero
        $costo_externo       = null;
        $descripcion_externo = null;
        if (!empty($_POST['envio_colega']) && $_POST['envio_colega'] === 'si') {
            $descripcion_externo = trim($_POST['descripcion_colega'] ?? '');
            $costo_externo       = floatval($_POST['costo_colega'] ?? 0);
        }

        // Registrar el seguimiento
        $stmt = $conn->prepare("
            INSERT INTO seguimientos_orden
                (id_orden, id_tecnico, tipo_servicio, procedimiento, valor_cobrar,
                 costo_externo, descripcion_externo, fecha_registro)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $id_orden, $id_tecnico, $tipo_servicio, $procedimiento,
            $valor_cobrar, $costo_externo, $descripcion_externo
        ]);

        $id_seguimiento = $conn->lastInsertId();

        // Registrar venta de producto si se indicó
        if (!empty($_POST['vendio_algo']) && $_POST['vendio_algo'] === 'si') {
            $producto      = trim($_POST['producto_vendido'] ?? '');
            $valor_compra  = floatval($_POST['valor_compra'] ?? 0);
            $valor_venta   = floatval($_POST['valor_venta'] ?? 0);
            $ganancia_neta = $valor_venta - $valor_compra;
            if (!empty($producto)) {
                $stmt = $conn->prepare("
                    INSERT INTO ventas_orden
                        (id_orden, id_seguimiento, producto, valor_compra, valor_venta, ganancia_neta, id_usuario_registro)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$id_orden, $id_seguimiento, $producto, $valor_compra, $valor_venta, $ganancia_neta, $_SESSION['user_id']]);
            }
        }

        // Actualizar el estado de la orden
        $stmt = $conn->prepare("
            UPDATE ordenes_trabajo
            SET estado = ?
            WHERE id_orden = ?
        ");
        $stmt->execute([$nuevo_estado, $id_orden]);

        // Procesar imágenes si se subieron
        if (isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['name'][0])) {
            require_once __DIR__ . '/utils_image_upload.php';
            $uploadDir = __DIR__ . '/../../uploads/ordenes/' . $id_orden . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name)) {
                    $fileName = $_FILES['imagenes']['name'][$key];
                    $baseName = uniqid() . '_' . pathinfo($fileName, PATHINFO_FILENAME);
                    $webpName = procesarImagenWebP($tmp_name, $uploadDir, $baseName);
                    if ($webpName) {
                        $webpPath = 'uploads/ordenes/' . $id_orden . '/' . $webpName;
                        $fileSize = file_exists($uploadDir . $webpName) ? filesize($uploadDir . $webpName) : 0;
                        $stmt = $conn->prepare("INSERT INTO orden_imagenes (id_orden, nombre_archivo, ruta_archivo, tamano_archivo, fecha_registro) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([
                            $id_orden,
                            $webpName,
                            $webpPath,
                            $fileSize
                        ]);
                    } else {
                        throw new Exception("Error al procesar la imagen: $fileName");
                    }
                }
            }
        }

        $conn->commit();
        
        // Contar imágenes procesadas
        $imagenesProcesadas = 0;
        if (isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['name'][0])) {
            $imagenesProcesadas = count(array_filter($_FILES['imagenes']['tmp_name']));
        }
        
        if ($imagenesProcesadas > 0) {
            $success = "Seguimiento registrado exitosamente. Se procesaron $imagenesProcesadas imagen(es).";
        } else {
            $success = "Seguimiento registrado exitosamente.";
        }

        // Recargar los seguimientos
        $stmt = $conn->prepare("
            SELECT so.*, u.nombre_completo as usuario_nombre,
                   v.producto as venta_producto, v.valor_compra as venta_compra,
                   v.valor_venta as venta_precio, v.ganancia_neta as venta_ganancia
            FROM seguimientos_orden so
            LEFT JOIN usuarios u ON so.id_tecnico = u.id_usuario
            LEFT JOIN ventas_orden v ON v.id_seguimiento = so.id_seguimiento
            WHERE so.id_orden = ?
            ORDER BY so.fecha_registro DESC
        ");
        $stmt->execute([$id_orden]);
        $seguimientos = $stmt->fetchAll();

    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Seguimiento - Orden <?php echo $orden['codigo']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .success-toast { animation: slideDown .4s ease; }
        @keyframes slideDown {
            from { transform: translateY(-16px); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }
        .tl-item { position: relative; padding-left: 1.75rem; }
        .tl-item::before {
            content: '';
            position: absolute;
            left: .2rem; top: .65rem;
            width: .65rem; height: .65rem;
            background: #22c55e;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #22c55e;
        }
        .tl-item::after {
            content: '';
            position: absolute;
            left: .48rem; top: 1.35rem;
            width: 1px;
            height: calc(100% - .6rem);
            background: #d1fae5;
        }
        .tl-item:last-child::after { display: none; }
        .badge {
            display: inline-flex; align-items: center; gap: .25rem;
            font-size: .72rem; font-weight: 600;
            padding: .2rem .55rem; border-radius: 9999px;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content">
    <div class="max-w-5xl mx-auto px-4 py-6">

        <!-- Page header -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <i class="fas fa-clipboard-check text-green-600"></i>
                    Registrar Seguimiento
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">Orden <span class="font-semibold text-green-700"><?php echo htmlspecialchars($orden['codigo']); ?></span></p>
            </div>
            <div class="flex gap-2">
                <a href="ver.php?id=<?php echo $id_orden; ?>"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 text-sm font-medium hover:bg-gray-50 transition">
                    <i class="fas fa-eye"></i> Ver Orden
                </a>
                <a href="lista.php"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition">
                    <i class="fas fa-list"></i> Lista de Órdenes
                </a>
            </div>
        </div>

        <!-- Order info banner -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-5">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Cliente</p>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($orden['cliente_nombre']); ?></p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($orden['identificacion']); ?></p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Equipo</p>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($orden['marca'] . ' ' . $orden['modelo']); ?></p>
                    <p class="text-xs text-gray-500">S/N: <?php echo htmlspecialchars($orden['numero_serial']); ?></p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Técnico</p>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($orden['tecnico_nombre'] ?? 'Por asignar'); ?></p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($orden['sucursal_nombre']); ?></p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Ingreso</p>
                    <p class="font-semibold text-gray-800"><?php echo date('d/m/Y', strtotime($orden['fecha_ingreso'])); ?></p>
                    <p class="text-xs text-gray-500"><?php echo date('H:i', strtotime($orden['fecha_ingreso'])); ?></p>
                </div>
            </div>
            <?php if (!empty($orden['descripcion_problema'])): ?>
            <div class="mt-3 pt-3 border-t border-gray-100">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Problema reportado</p>
                <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($orden['descripcion_problema'])); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Alerts -->
        <?php if (isset($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-4 flex items-start gap-2">
            <i class="fas fa-exclamation-triangle mt-0.5 flex-shrink-0"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 mb-4 flex items-start gap-2 success-toast" id="successMsg">
            <i class="fas fa-check-circle mt-0.5 flex-shrink-0 text-green-500"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>

        <!-- Seguimiento form -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-bold text-gray-800 mb-5 flex items-center gap-2">
                <i class="fas fa-plus-circle text-green-600"></i>
                Nuevo Seguimiento
            </h2>

            <form method="POST" enctype="multipart/form-data">
                <!-- Tipo + Técnico -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tipo de Servicio <span class="text-red-500">*</span></label>
                        <select name="tipo_servicio" required
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white">
                            <option value="">Seleccione el tipo de servicio</option>
                            <?php foreach ($tipos_servicio as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo['nombre']); ?>">
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                    <?php if (!empty($tipo['descripcion'])): ?> &ndash; <?php echo htmlspecialchars($tipo['descripcion']); ?><?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Técnico Responsable <span class="text-red-500">*</span></label>
                        <select name="id_tecnico" required
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white">
                            <option value="">Seleccione el técnico</option>
                            <?php
                            $stmt = $conn->query("SELECT u.id_usuario, u.nombre_completo, tu.nombre
                                        FROM usuarios u
                                        INNER JOIN tipos_usuario tu ON u.id_tipo = tu.id_tipo
                                        WHERE u.estado = 1 AND u.id_tipo = 2");
                            $tecnicos = $stmt->fetchAll();
                            foreach ($tecnicos as $tecnico): ?>
                                <option value="<?php echo $tecnico['id_usuario']; ?>">
                                    <?php echo htmlspecialchars($tecnico['nombre_completo'] . ' (' . $tecnico['nombre'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Valor cobrar + Estado -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Valor a Cobrar</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-gray-500 text-sm font-medium">$</span>
                            <input type="number" name="valor_cobrar" step="0.01" min="0"
                                oninput="calcularGananciaOrden()"
                                class="w-full pl-7 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                placeholder="0.00">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Dejar en 0 si no hay cobro</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nuevo Estado de la Orden <span class="text-red-500">*</span></label>
                        <select name="nuevo_estado" required
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent bg-white">
                            <option value="">Seleccione un estado</option>
                            <?php foreach ($estados as $estado): ?>
                                <option value="<?php echo $estado['nombre_estado']; ?>"
                                    <?php echo ($orden['estado'] == $estado['nombre_estado']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($estado['nombre_estado']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Procedimiento -->
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Procedimiento Realizado <span class="text-red-500">*</span></label>
                    <textarea name="procedimiento" required rows="4"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none"
                        placeholder="Describa el trabajo realizado, diagnóstico, reparación o cualquier procedimiento importante…"></textarea>
                </div>

                <!-- Imágenes -->
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Imágenes <span class="text-xs font-normal text-gray-400">(Opcional)</span>
                    </label>
                    <input type="file" name="imagenes[]" accept="image/*" multiple
                        class="w-full text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF, WEBP &middot; máx. 10 MB por archivo &middot; se redimensionan a 1200 &times; 1200 px</p>
                </div>

                <hr class="border-gray-100 mb-5">

                <!-- Venta de producto -->
                <div class="border border-amber-200 bg-amber-50 rounded-xl p-4 mb-4">
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" name="vendio_algo" id="vendio_algo" value="si"
                               onchange="toggleSeccionVenta(this)"
                               class="w-4 h-4 rounded accent-amber-500 cursor-pointer">
                        <span class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                            <i class="fas fa-shopping-bag text-amber-500"></i>
                            ¿Se vendió algún producto en esta visita?
                        </span>
                    </label>

                    <div id="seccion_venta" class="mt-4 space-y-3" style="display:none">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Producto vendido <span class="text-red-500">*</span></label>
                            <input type="text" name="producto_vendido" id="producto_vendido"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
                                   placeholder="Ej: Memoria RAM 8GB, Cable HDMI…">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    Valor de compra <span class="text-red-500">*</span>
                                    <span class="text-xs font-normal text-gray-400 ml-1">lo que costó</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500 text-sm">$</span>
                                    <input type="number" name="valor_compra" id="valor_compra" min="0" step="0.01"
                                           oninput="calcularGananciaVenta()"
                                           class="w-full pl-7 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
                                           placeholder="0.00">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    Valor de venta <span class="text-red-500">*</span>
                                    <span class="text-xs font-normal text-gray-400 ml-1">lo que cobró</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500 text-sm">$</span>
                                    <input type="number" name="valor_venta" id="valor_venta" min="0" step="0.01"
                                           oninput="calcularGananciaVenta()"
                                           class="w-full pl-7 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
                                           placeholder="0.00">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                    Ganancia producto <span class="text-xs font-normal text-gray-400">auto</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-gray-500 text-sm">$</span>
                                    <input type="number" id="ganancia_producto_display"
                                           class="w-full pl-7 pr-3 py-2.5 border border-gray-200 bg-gray-100 rounded-lg text-sm font-bold text-green-700"
                                           placeholder="0.00" readonly tabindex="-1">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Envío a colega -->
                <div class="border border-blue-200 bg-blue-50 rounded-xl p-4 mb-5">
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" name="envio_colega" id="envio_colega" value="si"
                               onchange="toggleSeccionColega(this)"
                               class="w-4 h-4 rounded accent-blue-600 cursor-pointer">
                        <span class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                            <i class="fas fa-people-carry text-blue-500"></i>
                            ¿Se envió el equipo a un colega o taller externo?
                        </span>
                    </label>

                    <div id="seccion_colega" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3" style="display:none">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                Descripción <span class="text-red-500">*</span>
                                <span class="text-xs font-normal text-gray-400 ml-1">quién o qué servicio</span>
                            </label>
                            <input type="text" name="descripcion_colega" id="descripcion_colega"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                                   placeholder="Ej: Taller de pantallas Juan…">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Lo que nos cobran <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-gray-500 text-sm">$</span>
                                <input type="number" name="costo_colega" id="costo_colega" min="0" step="0.01"
                                       oninput="calcularGananciaOrden()"
                                       class="w-full pl-7 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                                       placeholder="0.00">
                            </div>
                            <p class="text-xs text-blue-600 mt-1"><i class="fas fa-info-circle mr-1"></i>Se resta del valor cobrado al cliente</p>
                        </div>
                    </div>
                </div>

                <!-- Resumen ganancia -->
                <div id="resumen_ganancia" class="hidden mb-5">
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                        <p class="text-xs font-semibold text-green-800 uppercase tracking-wide mb-3">Resumen financiero de este seguimiento</p>
                        <div class="flex flex-wrap gap-4 items-center">
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Cobrado al cliente</p>
                                <p class="text-lg font-bold text-gray-800" id="resumen_cobrar">$0.00</p>
                            </div>
                            <span class="text-gray-400 text-xl font-light">&minus;</span>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Costo colega</p>
                                <p class="text-lg font-bold text-red-600" id="resumen_colega">$0.00</p>
                            </div>
                            <span class="text-gray-400 text-xl font-light">=</span>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Ganancia neta</p>
                                <p class="text-lg font-bold text-green-700" id="resumen_ganancia_val">$0.00</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <a href="lista.php"
                       class="px-5 py-2.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition">
                        Cancelar
                    </a>
                    <button type="submit"
                        class="px-6 py-2.5 rounded-lg text-sm font-semibold bg-green-600 text-white hover:bg-green-700 transition flex items-center gap-2">
                        <i class="fas fa-save"></i> Registrar Seguimiento
                    </button>
                </div>
            </form>
        </div>

        <!-- Historial de Seguimientos -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-800 mb-5 flex items-center gap-2">
                <i class="fas fa-history text-purple-600"></i>
                Historial de Seguimientos
                <span class="ml-1 text-xs font-normal bg-gray-100 text-gray-600 rounded-full px-2 py-0.5"><?php echo count($seguimientos); ?></span>
            </h2>

            <?php if (empty($seguimientos)): ?>
                <div class="text-center py-10">
                    <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-400 text-sm">No hay seguimientos registrados aún</p>
                </div>
            <?php else: ?>
                <div class="space-y-5">
                    <?php foreach ($seguimientos as $seg): ?>
                    <div class="tl-item pb-5">
                        <div class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                                <div class="flex flex-wrap gap-2 items-center">
                                    <span class="badge bg-green-100 text-green-800">
                                        <i class="fas fa-tools text-xs"></i>
                                        <?php echo htmlspecialchars($seg['tipo_servicio']); ?>
                                    </span>
                                    <?php if ($seg['valor_cobrar'] > 0): ?>
                                    <span class="badge bg-emerald-100 text-emerald-800">
                                        <i class="fas fa-dollar-sign text-xs"></i>
                                        Cobrado: $<?php echo number_format($seg['valor_cobrar'], 2); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($seg['costo_externo']) && $seg['costo_externo'] > 0): ?>
                                    <span class="badge bg-blue-100 text-blue-800">
                                        <i class="fas fa-people-carry text-xs"></i>
                                        <?php echo htmlspecialchars($seg['descripcion_externo'] ?? 'Colega'); ?>: &minus;$<?php echo number_format($seg['costo_externo'], 2); ?>
                                    </span>
                                    <?php
                                    $gan_ord = floatval($seg['valor_cobrar']) - floatval($seg['costo_externo']);
                                    ?>
                                    <span class="badge <?php echo $gan_ord >= 0 ? 'bg-green-200 text-green-900' : 'bg-red-100 text-red-800'; ?>">
                                        Gan. orden: $<?php echo number_format($gan_ord, 2); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($seg['venta_producto'])): ?>
                                    <span class="badge bg-amber-100 text-amber-800">
                                        <i class="fas fa-shopping-bag text-xs"></i>
                                        <?php echo htmlspecialchars($seg['venta_producto']); ?>
                                        &middot; venta $<?php echo number_format($seg['venta_precio'], 2); ?>
                                        &middot; gan. $<?php echo number_format($seg['venta_ganancia'], 2); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xs text-gray-500"><?php echo date('d/m/Y H:i', strtotime($seg['fecha_registro'])); ?></p>
                                    <p class="text-xs text-gray-400">Por: <?php echo htmlspecialchars($seg['usuario_nombre']); ?></p>
                                    <a href="editar_seguimiento.php?id=<?php echo $seg['id_seguimiento']; ?>"
                                       class="inline-block mt-1 text-xs text-amber-600 hover:text-amber-800 font-medium">
                                        <i class="fas fa-pencil-alt mr-0.5"></i>Editar
                                    </a>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($seg['procedimiento'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
    </div>

    <script>
        function toggleSeccionVenta(cb) {
            document.getElementById('seccion_venta').style.display = cb.checked ? 'block' : 'none';
            const p = document.getElementById('producto_vendido');
            const c = document.getElementById('valor_compra');
            const v = document.getElementById('valor_venta');
            if (cb.checked) {
                p.required = c.required = v.required = true;
                p.focus();
            } else {
                p.required = c.required = v.required = false;
                p.value = c.value = v.value = '';
                document.getElementById('ganancia_producto_display').value = '';
            }
        }

        function calcularGananciaVenta() {
            const compra = parseFloat(document.getElementById('valor_compra').value) || 0;
            const venta  = parseFloat(document.getElementById('valor_venta').value)  || 0;
            const gan    = venta - compra;
            const el     = document.getElementById('ganancia_producto_display');
            el.value      = gan.toFixed(2);
            el.style.color = gan >= 0 ? '#16a34a' : '#dc2626';
        }

        function toggleSeccionColega(cb) {
            document.getElementById('seccion_colega').style.display = cb.checked ? 'grid' : 'none';
            const d = document.getElementById('descripcion_colega');
            const c = document.getElementById('costo_colega');
            if (cb.checked) {
                d.required = c.required = true;
                d.focus();
            } else {
                d.required = c.required = false;
                d.value = c.value = '';
                calcularGananciaOrden();
            }
        }

        function calcularGananciaOrden() {
            const cobrar = parseFloat(document.querySelector('input[name="valor_cobrar"]').value) || 0;
            const colega = parseFloat(document.getElementById('costo_colega').value) || 0;
            const resumen = document.getElementById('resumen_ganancia');
            if (cobrar > 0 || document.getElementById('envio_colega').checked) {
                resumen.classList.remove('hidden');
                document.getElementById('resumen_cobrar').textContent = '$' + cobrar.toFixed(2);
                document.getElementById('resumen_colega').textContent  = '$' + colega.toFixed(2);
                const gan = cobrar - colega;
                const el  = document.getElementById('resumen_ganancia_val');
                el.textContent = '$' + gan.toFixed(2);
                el.style.color = gan >= 0 ? '#15803d' : '#dc2626';
            } else {
                resumen.classList.add('hidden');
            }
        }

        const toast = document.getElementById('successMsg');
        if (toast) {
            setTimeout(() => {
                toast.style.transition = 'opacity .4s';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 400);
            }, 4500);
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            const fileInput = document.querySelector('input[type="file"]');
            if (fileInput && fileInput.files.length > 0) {
                for (const file of fileInput.files) {
                    if (file.size > 100 * 1024 * 1024) {
                        e.preventDefault();
                        alert('El archivo "' + file.name + '" supera los 100 MB.');
                        return;
                    }
                    if (!file.type.startsWith('image/')) {
                        e.preventDefault();
                        alert('El archivo "' + file.name + '" no es una imagen válida.');
                        return;
                    }
                }
            }
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Procesando…';
            btn.disabled  = true;
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-save mr-2"></i>Registrar Seguimiento';
                btn.disabled = false;
            }, 15000);
        });
    </script>
</body>

</html>
