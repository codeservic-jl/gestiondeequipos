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

    // Obtener seguimientos existentes
    $stmt = $conn->prepare("
        SELECT so.*, u.nombre_completo as usuario_nombre
        FROM seguimientos_orden so
        LEFT JOIN usuarios u ON so.id_tecnico = u.id_usuario
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
        $valor_cobrar = isset($_POST['valor_cobrar']) && !empty($_POST['valor_cobrar']) ? $_POST['valor_cobrar'] : 0.00;
        $id_tecnico = $_POST['id_tecnico'];
        $nuevo_estado = $_POST['nuevo_estado'];
        $fecha_actualizacion = date('Y-m-d H:i:s');

        // Registrar el seguimiento
        $stmt = $conn->prepare("
            INSERT INTO seguimientos_orden (id_orden, id_tecnico, tipo_servicio, procedimiento, valor_cobrar, fecha_registro)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $id_orden, 
            $id_tecnico, 
            $tipo_servicio, 
            $procedimiento, 
            $valor_cobrar
        ]);

        // Actualizar el estado de la orden
        $stmt = $conn->prepare("
            UPDATE ordenes_trabajo 
            SET estado = ?, fecha_actualizacion = ?
            WHERE id_orden = ?
        ");
        $stmt->execute([$nuevo_estado, $fecha_actualizacion, $id_orden]);

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
            SELECT so.*, u.nombre_completo as usuario_nombre
            FROM seguimientos_orden so
            LEFT JOIN usuarios u ON so.id_tecnico = u.id_usuario
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
    <link href="../../assets/css/tablet-optimization.css" rel="stylesheet">
    <style>
        .bg-navy-blue {
            background-color: #5AC456;
        }

        .parentesis {
            font-size: 11px;
            color: #666;
        }

        .success-message {
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .timeline-item {
            position: relative;
            padding-left: 2rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.5rem;
            width: 0.75rem;
            height: 0.75rem;
            background: #3b82f6;
            border-radius: 50%;
        }
        .timeline-item::after {
            content: '';
            position: absolute;
            left: 0.375rem;
            top: 1.25rem;
            width: 2px;
            height: calc(100% - 0.75rem);
            background: #e5e7eb;
        }
        .timeline-item:last-child::after {
            display: none;
        }
        
        /* Estilos de sidebar y responsive - EXACTAMENTE como en test_php_tablet.html */
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 40;
            transition: transform 0.3s ease-in-out;
        }
        .main-content {
            margin-left: 250px;
            transition: margin-left 0.3s ease-in-out;
        }
        @media (min-width: 769px) and (max-width: 1024px) {
            .sidebar { width: 200px !important; }
            .main-content { margin-left: 200px !important; }
            #menuButton { display: none !important; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); z-index: 50; }
            .sidebar.active { transform: translateX(0); }
            .sidebar.hidden { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            #menuButton { z-index: 60; transition: all 0.3s ease; }
            #menuButton:hover { transform: scale(1.1); }
        }
        
        /* Estilos específicos para tablet en este formulario - EXACTAMENTE como en test_php_tablet.html */
        @media (min-width: 769px) and (max-width: 1024px) {
            .container {
                padding: 1rem !important;
            }
            
            .max-w-4xl {
                max-width: 100% !important;
            }
            
            .bg-white {
                padding: 1.5rem !important;
                margin: 0.5rem !important;
            }
            
            .grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
                gap: 1.5rem !important;
            }
            
            input, select, textarea {
                font-size: 16px !important;
                padding: 12px !important;
                border-radius: 8px !important;
                border: 2px solid #e5e7eb !important;
            }
            
            input:focus, select:focus, textarea:focus {
                outline: none !important;
                border-color: #3b82f6 !important;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
            }
            
            button {
                padding: 12px 20px !important;
                font-size: 16px !important;
                min-height: 44px !important;
            }
            
            .flex.space-x-4 {
                flex-direction: column !important;
                gap: 1rem !important;
            }
            
            .flex.space-x-4 > * {
                width: 100% !important;
                text-align: center !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-6">
                                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">
                            <i class="fas fa-clipboard-check text-blue-600 mr-3"></i>
                            Registrar Seguimiento
                        </h1>
                        <p class="text-gray-600 mt-2">Orden: <?php echo htmlspecialchars($orden['codigo']); ?></p>
                    </div>
                    <div class="flex space-x-4">
                        <a href="ver.php?id=<?php echo $id_orden; ?>" 
                           class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                            <i class="fas fa-eye mr-2"></i>Ver Orden
                        </a>
                        <a href="lista.php" 
                           class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-list mr-2"></i>Lista de Órdenes
                        </a>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-r-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-red-700 font-medium">Error:</p>
                                <p class="text-red-600"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6 rounded-r-lg success-message">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-green-700 font-medium">Éxito:</p>
                                <p class="text-green-600"><?php echo htmlspecialchars($success); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Información de la Orden -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                Información de la Orden
                            </h2>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Código:</label>
                                    <p class="text-lg font-semibold text-blue-600"><?php echo htmlspecialchars($orden['codigo']); ?></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Cliente:</label>
                                    <p class="text-gray-800"><?php echo htmlspecialchars($orden['cliente_nombre']); ?></p>
                                    <p class="text-sm text-gray-600">ID: <?php echo htmlspecialchars($orden['identificacion']); ?></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Equipo:</label>
                                    <p class="text-gray-800"><?php echo htmlspecialchars($orden['marca'] . ' ' . $orden['modelo']); ?></p>
                                    <p class="text-sm text-gray-600">S/N: <?php echo htmlspecialchars($orden['numero_serial']); ?></p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Problema:</label>
                                    <p class="text-gray-800"><?php echo nl2br(htmlspecialchars($orden['descripcion_problema'])); ?></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Técnico:</label>
                                    <p class="text-gray-800"><?php echo htmlspecialchars($orden['tecnico_nombre'] ?? 'Por asignar'); ?></p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Sucursal:</label>
                                    <p class="text-gray-800"><?php echo htmlspecialchars($orden['sucursal_nombre']); ?></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Fecha de Ingreso:</label>
                                    <p class="text-gray-800"><?php echo date('d/m/Y H:i', strtotime($orden['fecha_ingreso'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de Seguimiento -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                                <i class="fas fa-plus-circle text-green-600 mr-2"></i>
                                Nuevo Seguimiento
                            </h2>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Tipo de Servicio *
                                        </label>
                                        <select name="tipo_servicio" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                            <option value="">Seleccione el tipo de servicio</option>
                                            <?php foreach ($tipos_servicio as $tipo): ?>
                                                <option value="<?php echo htmlspecialchars($tipo['nombre']); ?>">
                                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                                    <?php if (!empty($tipo['descripcion'])): ?>
                                                        - <?php echo htmlspecialchars($tipo['descripcion']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Técnico Responsable *
                                        </label>
                                        <select name="id_tecnico" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
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

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Valor a Cobrar
                                        </label>
                                        <input type="number" name="valor_cobrar" step="0.01" min="0"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                            placeholder="0.00">
                                        <p class="text-xs text-gray-500 mt-1">Dejar en 0 si no hay cobro</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Nuevo Estado de la Orden *
                                        </label>
                                        <select name="nuevo_estado" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                            <option value="">Seleccione un estado</option>
                                            <?php foreach ($estados as $estado): ?>
                                                <option value="<?php echo $estado['nombre_estado']; ?>"
                                                    <?php echo ($orden['estado'] == $estado['nombre_estado']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($estado['nombre_estado']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Imágenes (Opcional)
                                        </label>
                                        <input type="file" name="imagenes[]" accept="image/*" multiple
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                        <p class="text-xs text-gray-500 mt-1">
                                            Puede seleccionar múltiples imágenes. Tamaño máximo por archivo: 10MB. 
                                            Las imágenes se redimensionarán automáticamente a máximo 1200x1200 píxeles y se comprimirán.
                                            Formatos permitidos: JPG, PNG, GIF, WEBP.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Procedimiento Realizado *
                                    </label>
                                    <textarea name="procedimiento" required rows="4"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                        placeholder="Describa el trabajo realizado, diagnóstico, reparación, o cualquier procedimiento importante..."></textarea>
                                </div>
                                
                                <div class="mt-6 flex justify-end space-x-4">
                                    <a href="lista.php" 
                                       class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                                        Cancelar
                                    </a>
                                    <button type="submit" 
                                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                        <i class="fas fa-save mr-2"></i>Registrar Seguimiento
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Historial de Seguimientos -->
                        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">
                                <i class="fas fa-history text-purple-600 mr-2"></i>
                                Historial de Seguimientos
                            </h2>
                            
                            <?php if (empty($seguimientos)): ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-clipboard-list text-gray-400 text-4xl mb-4"></i>
                                    <p class="text-gray-500">No hay seguimientos registrados aún</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($seguimientos as $seguimiento): ?>
                                        <div class="timeline-item">
                                            <div class="bg-gray-50 rounded-lg p-4">
                                                <div class="flex items-center justify-between mb-2">
                                                    <div class="flex items-center space-x-3">
                                                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
                                                            <?php echo htmlspecialchars($seguimiento['tipo_servicio']); ?>
                                                        </span>
                                                        <span class="text-sm text-gray-500">
                                                            <?php echo date('d/m/Y H:i', strtotime($seguimiento['fecha_registro'])); ?>
                                                        </span>
                                                        <?php if ($seguimiento['valor_cobrar'] > 0): ?>
                                                            <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                                                                $<?php echo number_format($seguimiento['valor_cobrar'], 2); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="text-sm text-gray-500">
                                                        Por: <?php echo htmlspecialchars($seguimiento['usuario_nombre']); ?>
                                                    </span>
                                                </div>
                                                <p class="text-gray-800"><?php echo nl2br(htmlspecialchars($seguimiento['procedimiento'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
        // Auto-hide success message after 5 seconds
        setTimeout(function() {
            const successMessage = document.querySelector('.success-message');
            if (successMessage) {
                successMessage.style.opacity = '0';
                successMessage.style.transform = 'translateY(-20px)';
                setTimeout(() => successMessage.remove(), 500);
            }
        }, 5000);

        // Validación de archivos antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            const fileInput = document.querySelector('input[type="file"]');
            const maxSize = 100 * 1024 * 1024; // 100MB en bytes (se comprimirán automáticamente)
            
            if (fileInput.files.length > 0) {
                for (let i = 0; i < fileInput.files.length; i++) {
                    const file = fileInput.files[i];
                    
                    // Verificar tamaño (más permisivo ya que se comprimirán)
                    if (file.size > maxSize) {
                        e.preventDefault();
                        alert('El archivo "' + file.name + '" es demasiado grande. Máximo 100MB por archivo.');
                        return false;
                    }
                    
                    // Verificar tipo de archivo
                    if (!file.type.startsWith('image/')) {
                        e.preventDefault();
                        alert('El archivo "' + file.name + '" no es una imagen válida');
                        return false;
                    }
                }
            }

            // Mostrar indicador de carga
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Procesando...';
            submitBtn.disabled = true;

            // Restaurar botón después de 15 segundos por si hay error
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 15000);
        });

        // Validación inmediata al seleccionar archivos
        document.querySelector('input[type="file"]').addEventListener('change', function(e) {
            const files = e.target.files;
            const maxSize = 100 * 1024 * 1024; // 100MB
            const maxSizeMB = 100;
            const infoDiv = document.createElement('div');
            infoDiv.className = 'mt-2 text-sm';
            
            // Limpiar información anterior
            const oldInfo = this.parentNode.querySelector('.file-validation-info');
            if (oldInfo) {
                oldInfo.remove();
            }
            
            if (files.length > 0) {
                let hasError = false;
                let errorMessage = '';
                let info = '<strong>Archivos seleccionados:</strong><br>';
                let totalSize = 0;
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                    totalSize += file.size;
                    
                    // Verificar tamaño inmediatamente
                    if (file.size > maxSize) {
                        hasError = true;
                        errorMessage += `• ${file.name} (${sizeMB} MB) - <span style="color: red;">❌ Demasiado grande (máximo ${maxSizeMB}MB)</span><br>`;
                    } else {
                        info += `• ${file.name} (${sizeMB} MB) - <span style="color: green;">✅ OK</span><br>`;
                    }
                    
                    // Verificar tipo de archivo
                    if (!file.type.startsWith('image/')) {
                        hasError = true;
                        errorMessage += `• ${file.name} - <span style="color: red;">❌ No es una imagen válida</span><br>`;
                    }
                }
                
                // Verificar tamaño total (aproximadamente 8MB límite del servidor)
                const totalSizeMB = (totalSize / (1024 * 1024)).toFixed(2);
                if (totalSize > 8 * 1024 * 1024) { // 8MB
                    hasError = true;
                    errorMessage += `<br><strong>⚠️ Advertencia:</strong> El tamaño total (${totalSizeMB} MB) puede exceder el límite del servidor (8MB).<br>`;
                    errorMessage += '<em>Considere subir menos archivos o archivos más pequeños.</em><br>';
                }
                
                if (hasError) {
                    infoDiv.className = 'mt-2 text-sm text-red-600 bg-red-50 p-3 rounded border border-red-200 file-validation-info';
                    infoDiv.innerHTML = '<strong>❌ Errores detectados:</strong><br>' + errorMessage + 
                        '<br><em>Por favor, seleccione archivos válidos antes de continuar.</em>' +
                        '<br><button type="button" onclick="clearFileSelection()" class="mt-2 px-3 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700">Limpiar selección</button>';
                } else {
                    infoDiv.className = 'mt-2 text-sm text-gray-600 bg-green-50 p-3 rounded border border-green-200 file-validation-info';
                    infoDiv.innerHTML = info + '<em class="text-blue-600">Las imágenes se redimensionarán automáticamente a máximo 1200x1200 píxeles y se comprimirán.</em>';
                }
            }
            
            // Agregar nueva información
            this.parentNode.appendChild(infoDiv);
            
            // Deshabilitar botón de envío si hay errores
            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = hasError;
                if (hasError) {
                    submitBtn.title = 'Corrija los errores en los archivos antes de continuar';
                    submitBtn.style.opacity = '0.5';
                } else {
                    submitBtn.title = '';
                    submitBtn.style.opacity = '1';
                }
            }
        });

        // Función para limpiar la selección de archivos
        function clearFileSelection() {
            const fileInput = document.querySelector('input[type="file"]');
            if (!fileInput) return;
            
            fileInput.value = ''; // Limpiar el input de archivo
            
            // Buscar específicamente la información de validación de archivos
            // Buscar en el contenedor padre del input de archivo
            const fileContainer = fileInput.parentElement;
            if (fileContainer) {
                const infoDiv = fileContainer.querySelector('.file-validation-info');
                if (infoDiv) {
                    infoDiv.remove(); // Eliminar solo la información de validación
                }
            }
            
            // Habilitar el botón de envío
            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.title = '';
                submitBtn.style.opacity = '1';
            }
        }

        // Verificar límites del servidor al cargar la página
        window.addEventListener('load', function() {
            // Crear un archivo de prueba para verificar límites
            const testFile = new File([''], 'test.txt', { type: 'text/plain' });
            const formData = new FormData();
            formData.append('test', testFile);
            
            // Enviar una petición de prueba para verificar límites
            fetch('test_simple.php', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    console.log('Servidor configurado correctamente para archivos grandes');
                } else {
                    console.warn('El servidor puede tener límites restrictivos');
                }
            }).catch(error => {
                console.warn('No se pudo verificar la configuración del servidor:', error);
            });
        });
    </script>
</body>

</html>