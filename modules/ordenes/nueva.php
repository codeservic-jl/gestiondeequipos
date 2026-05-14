<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// Obtener tipos de equipo
$tipos = $conn->query("SELECT * FROM tipos_equipo WHERE estado = 1")->fetchAll();
$sucursalEnSesion = $_SESSION['sucursal'];
// Obtener clientes activos
$query = "SELECT * FROM clientes WHERE estado = 1";
/* if ($_SESSION['user_type'] != 1) {
    $query .= " AND id_sucursal = :sucursal";
    $stmt = $conn->prepare($query);
    $stmt->execute(['sucursal' => $_SESSION['sucursal']]);
    $clientes = $stmt->fetchAll();
} else { */
$clientes = $conn->query($query)->fetchAll();
/* } */

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->beginTransaction();

        /* si el cliente es nuevo */
        if ($_POST['tipo_cliente'] === 'nuevo') {
            // Validar que se haya seleccionado el tipo de cliente
            if (empty($_POST['id_tipo_cliente'])) {
                throw new Exception("Debe seleccionar el tipo de cliente (Personal o Empresa).");
            }
            
            // Validaciones mejoradas para nuevo cliente
            $tipo_cliente = $_POST['id_tipo_cliente'];
            
            // Validación de identificación según tipo de cliente
            if ($tipo_cliente == '2') { // Empresa
                if (!preg_match('/^\d{13}$/', $_POST['identificacion'])) {
                    throw new Exception("El RUC debe contener exactamente 13 dígitos.");
                }
            } else { // Personal
                if (!preg_match('/^\d{10}$/', $_POST['identificacion'])) {
                    throw new Exception("La cédula debe contener exactamente 10 dígitos.");
                }
            }

            // Validación de teléfono más flexible
            if (!preg_match('/^\d{7,12}$/', $_POST['telefono'])) {
                throw new Exception("El teléfono debe contener entre 7 y 12 dígitos.");
            }

            // Validación de nombre más flexible
            $palabras_nombre = str_word_count(trim($_POST['nombre_apellido']), 0);
            if ($palabras_nombre > 6) {
                throw new Exception("El nombre completo no debe exceder las 6 palabras.");
            }

            if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("El formato del email no es válido.");
            }

            // Validar que la identificación no exista
            $stmt = $conn->prepare("SELECT id_cliente FROM clientes WHERE identificacion = ?");
            $stmt->execute([$_POST['identificacion']]);
            if ($stmt->fetch()) {
                throw new Exception("La identificación ya existe en el sistema.");
            }

            // Validar que los números de serie no estén vacíos y sean únicos
            $numeros_serie = [];
            $i = 1;
            while (isset($_POST['numero_serial_' . $i])) {
                $serial = trim($_POST['numero_serial_' . $i]);
                if (empty($serial)) {
                    // Generar número de serie automático si está vacío
                    $serial = 'S/N-' . uniqid();
                }
                
                // Validar duplicados en el formulario
                if (in_array($serial, $numeros_serie)) {
                    throw new Exception("El número de serie " . $serial . " está duplicado en el formulario.");
                }
                
                // Validar duplicados en la base de datos
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM equipos WHERE numero_serial = ?");
                $stmt->execute([$serial]);
                $result = $stmt->fetch();
                if ($result['total'] > 0) {
                    throw new Exception("El número de serie " . $serial . " ya existe en el sistema.");
                }
                
                $numeros_serie[] = $serial;
                $i++;
            }

            // Registrar nuevo cliente
            $stmt = $conn->prepare("INSERT INTO clientes (
                identificacion, nombre_apellido, empresa, id_tipo_cliente, telefono, direccion, email, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");

            $stmt->execute([
                $_POST['identificacion'],
                $_POST['nombre_apellido'],
                $_POST['empresa'],
                $_POST['id_tipo_cliente'],
                $_POST['telefono'],
                $_POST['direccion'],
                !empty($_POST['email']) ? $_POST['email'] : 'N/A'
            ]);

            $id_cliente = $conn->lastInsertId();

            // Registrar los equipos
            $i = 1;
            $id_equipo = null; // Inicializar variable
            $numero_serial = null; // Inicializar variable
            $equipos_registrados = []; // Array para guardar los IDs de equipos registrados
            
            while (isset($_POST['marca_' . $i])) {
                // Usar valores por defecto si están vacíos
                $marca = !empty(trim($_POST['marca_' . $i])) ? trim($_POST['marca_' . $i]) : 'S/N';
                $modelo = !empty(trim($_POST['modelo_' . $i])) ? trim($_POST['modelo_' . $i]) : 'S/N';
                $numero_serial = !empty(trim($_POST['numero_serial_' . $i])) ? trim($_POST['numero_serial_' . $i]) : 'S/N-' . uniqid();

                // Registrar el equipo
                $stmt = $conn->prepare("INSERT INTO equipos ( id_cliente, marca, modelo, numero_serial, estado) VALUES (?, ?, ?, ?, 1)");

                $stmt->execute([
                    $id_cliente,
                    $marca,
                    $modelo,
                    $numero_serial
                ]);

                $id_equipo_actual = $conn->lastInsertId();
                $equipos_registrados[] = $id_equipo_actual;

                if ($i === 1) {
                    // Guardamos el ID del primer equipo para la orden
                    $id_equipo = $id_equipo_actual;
                }

                $i++;
            }
            
            // Verificar que se registró al menos un equipo
            if (!$id_equipo) {
                throw new Exception("Debe registrar al menos un equipo para el cliente nuevo.");
            }
        } else {
            // Cliente existente
            $id_cliente = $_POST['id_cliente'];

            // Verificar si es un EQUIPO nuevo o existente
            if ($_POST['tipo_equipo'] === 'nuevo') {
                // Validar que los números de serie no estén vacíos y sean únicos
                $numeros_serie = [];
                $i = 1;
                while (isset($_POST['numero_serial_nuevo_' . $i])) {
                    $serial_equipo = trim($_POST['numero_serial_nuevo_' . $i]);

                    if (empty($serial_equipo)) {
                        // Generar número de serie automático si está vacío
                        $serial_equipo = 'S/N-' . uniqid();
                    }
                    
                    // Validar duplicados en el formulario
                    if (in_array($serial_equipo, $numeros_serie)) {
                        throw new Exception("El número de serie " . $serial_equipo . " está duplicado en el formulario.");
                    }
                    
                    // Validar duplicados en la base de datos
                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM equipos WHERE numero_serial = ?");
                    $stmt->execute([$serial_equipo]);
                    $result = $stmt->fetch();
                    if ($result['total'] > 0) {
                        throw new Exception("El número de serie " . $serial_equipo . " ya existe en el sistema.");
                    }
                    
                    $numeros_serie[] = $serial_equipo;
                    $i++;
                }
                
                // Registrar los equipos
                $i = 1;
                $id_equipo = null; // Inicializar variable
                $numero_serial = null; // Inicializar variable
                $equipos_registrados = []; // Inicializar array para equipos registrados
                
                while (isset($_POST['marca_nuevo_' . $i])) {
                    // Usar valores por defecto si están vacíos
                    $marca = !empty(trim($_POST['marca_nuevo_' . $i])) ? trim($_POST['marca_nuevo_' . $i]) : 'S/N';
                    $modelo = !empty(trim($_POST['modelo_nuevo_' . $i])) ? trim($_POST['modelo_nuevo_' . $i]) : 'S/N';
                    $numero_serial = !empty(trim($_POST['numero_serial_nuevo_' . $i])) ? trim($_POST['numero_serial_nuevo_' . $i]) : 'S/N-' . uniqid();

                    // Registrar el equipo
                    $stmt = $conn->prepare("INSERT INTO equipos ( id_cliente, marca, modelo, numero_serial, estado) VALUES (?, ?, ?, ?, 1)");

                    $stmt->execute([
                        $id_cliente,
                        $marca,
                        $modelo,
                        $numero_serial
                    ]);

                    $id_equipo_actual = $conn->lastInsertId();
                    $equipos_registrados[] = $id_equipo_actual;

                    if ($i === 1) {
                        // Guardamos el ID del primer equipo para la orden
                        $id_equipo = $id_equipo_actual;
                    }

                    $i++;
                }
                
                // Verificar que se registró al menos un equipo
                if (!$id_equipo) {
                    throw new Exception("Debe registrar al menos un equipo nuevo.");
                }
            } else {
                // Equipo existente
                $id_equipo = $_POST['id_equipo'];

                // Obtener número serial del equipo existente
                $stmt = $conn->prepare("SELECT numero_serial FROM equipos WHERE id_equipo = ?");
                $stmt->execute([$id_equipo]);
                $equipo = $stmt->fetch();
                $numero_serial = $equipo['numero_serial'];
            }
        }

        // Validar que tenemos los datos necesarios para la orden
        if (!$id_equipo) {
            throw new Exception("Error: No se pudo obtener el ID del equipo para la orden.");
        }
        
        if (!$numero_serial) {
            throw new Exception("Error: No se pudo obtener el número de serie para la orden.");
        }

        // Generar código único
        $stmt = $conn->prepare("SELECT MAX(id_orden) as ultimo FROM ordenes_trabajo");
        $stmt->execute();
        $ultimo = $stmt->fetch();
        $siguiente_id = ($ultimo['ultimo'] ?? 0) + 1;

        $codigo = 'RGE' . str_pad($siguiente_id, 5, '0', STR_PAD_LEFT) . $numero_serial;

        // Verificar que el código no exista
        $stmt = $conn->prepare("SELECT id_orden FROM ordenes_trabajo WHERE codigo = ?");
        $stmt->execute([$codigo]);
        if ($stmt->fetch()) {
            throw new Exception("Error al generar el código único. Por favor, intente nuevamente.");
        }

        // Registrar orden de trabajo
        $stmt = $conn->prepare("INSERT INTO ordenes_trabajo (
            codigo, id_cliente, id_equipo, descripcion_problema, valor_estimado,
            estado, id_usuario_registro, tecnico_responsable_id, id_sucursal, fecha_ingreso
        ) VALUES (?, ?, ?, ?, ?, 'Pendiente', ?, ?, ?, NOW())");

        $stmt->execute([
            $codigo,
            $id_cliente,
            $id_equipo,
            $_POST['descripcion_problema'],
            !empty($_POST['valor_estimado']) ? $_POST['valor_estimado'] : null,
            $_SESSION['user_id'],
            $_POST['tecnico_responsable_id'],
            $sucursalEnSesion
        ]);

        $id_orden = $conn->lastInsertId();

        // Registrar relaciones en orden_equipos con observaciones
        if ($_POST['tipo_cliente'] === 'nuevo') {
            // Cliente nuevo - registrar todos los equipos registrados
            $i = 1;
            foreach ($equipos_registrados as $id_equipo_actual) {
                $observaciones = isset($_POST['observaciones_equipo_existente_' . $i]) ? trim($_POST['observaciones_equipo_existente_' . $i]) : '';

                $stmt = $conn->prepare("INSERT INTO orden_equipos (id_orden, id_equipo, observaciones_falla_equipo) VALUES (?, ?, ?)");
                $stmt->execute([
                    $id_orden,
                    $id_equipo_actual,
                    $observaciones
                ]);
                $i++;
            }
        } else {
            if ($_POST['tipo_equipo'] === 'nuevo') {
                // Cliente existente con equipos nuevos - registrar todos los equipos registrados
                $i = 1;
                foreach ($equipos_registrados as $id_equipo_actual) {
                    $observaciones = isset($_POST['observaciones_nuevo_' . $i]) ? trim($_POST['observaciones_nuevo_' . $i]) : '';
                    
                    $stmt = $conn->prepare("INSERT INTO orden_equipos (id_orden, id_equipo, observaciones_falla_equipo) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $id_orden,
                        $id_equipo_actual,
                        $observaciones
                    ]);
                    $i++;
                }
            } else {
                // Cliente existente con equipo existente
                $observaciones = isset($_POST['observaciones_equipo_existente']) ? trim($_POST['observaciones_equipo_existente']) : '';

                $stmt = $conn->prepare("INSERT INTO orden_equipos (id_orden, id_equipo, observaciones_falla_equipo) VALUES (?, ?, ?)");
                $stmt->execute([
                    $id_orden,
                    $_POST['id_equipo'],
                    $observaciones
                ]);
            }
        }

        // Procesar y guardar las imágenes
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
                        $stmt = $conn->prepare("INSERT INTO orden_imagenes (id_orden, nombre_archivo, ruta_archivo, tamano_archivo) VALUES (?, ?, ?, ?)");
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

        // Procesar abono inicial si se especificó
        if (isset($_POST['realizar_abono']) && $_POST['realizar_abono'] === 'si') {
            $abono_inicial = floatval($_POST['monto_abono']);
            $metodo_pago = $_POST['metodo_pago'];
            $observaciones_pago = trim($_POST['observaciones_pago'] ?? '');
            
            // Validar monto
            if ($abono_inicial <= 0) {
                throw new Exception("El monto del abono debe ser mayor a 0");
            }
            
            // Validar método de pago
            if (empty($metodo_pago)) {
                throw new Exception("Debe seleccionar un método de pago");
            }
            
            // Actualizar orden con abono
            $stmt = $conn->prepare("
                UPDATE ordenes_trabajo SET 
                    abono_inicial = ?,
                    estado_pago = 'Abonado',
                    fecha_abono = NOW(),
                    usuario_abono = ?
                WHERE id_orden = ?
            ");
            $stmt->execute([$abono_inicial, $_SESSION['user_id'], $id_orden]);
            
            // Registrar abono en historial
            $stmt = $conn->prepare("
                INSERT INTO abonos_orden (
                    id_orden, monto, tipo_abono, metodo_pago, observaciones, id_usuario_registro
                ) VALUES (?, ?, 'Inicial', ?, ?, ?)
            ");
            $stmt->execute([
                $id_orden, 
                $abono_inicial, 
                $metodo_pago, 
                $observaciones_pago, 
                $_SESSION['user_id']
            ]);
            
            // Registrar en seguimientos
            $stmt = $conn->prepare("
                INSERT INTO seguimientos_orden (
                    id_orden, id_tecnico, tipo_servicio, procedimiento, valor_cobrar, fecha_registro
                ) VALUES (?, ?, 'Abono Inicial', ?, ?, NOW())
            ");
            
            $descripcion_abono = "Abono inicial recibido: $" . number_format($abono_inicial, 2) . 
                                "\nMétodo de pago: " . $metodo_pago;
            if (!empty($observaciones_pago)) {
                $descripcion_abono .= "\nObservaciones: " . $observaciones_pago;
            }
            
            $stmt->execute([
                $id_orden,
                $_SESSION['user_id'],
                $descripcion_abono,
                $abono_inicial
            ]);
        }

        $conn->commit();

        // Si la petición es AJAX, devolver JSON
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'id_orden' => $id_orden]);
            exit();
        }

        // Si no es AJAX, redirigir normalmente
        /* header("Location: lista.php"); */
        /* header("Location: index.php");  */
        header("Location: ver.php?id=" . $id_orden);
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(400);
            ob_clean(); // Limpiar cualquier salida anterior
            echo json_encode([
                'success' => false,
                'error' => $error
            ]);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Orden de Trabajo - Ingreso de equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/tablet-optimization.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .bg-navy-blue {
            background-color: #5AC456;
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
            
            /* Optimizaciones específicas para el formulario de nueva orden */
            .form-section {
                margin-bottom: 2rem !important;
            }
            
            .radio-group {
                display: flex !important;
                gap: 1rem !important;
                flex-wrap: wrap !important;
            }
            
            .radio-group label {
                display: flex !important;
                align-items: center !important;
                gap: 0.5rem !important;
                padding: 0.5rem 1rem !important;
                border: 2px solid #e5e7eb !important;
                border-radius: 8px !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
            }
            
            .radio-group label:hover {
                border-color: #3b82f6 !important;
                background-color: #eff6ff !important;
            }
            
            .radio-group input[type="radio"]:checked + label {
                border-color: #3b82f6 !important;
                background-color: #dbeafe !important;
            }
        }

        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
            animation: fadeIn 0.3s ease-in;
        }

        .input-error {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
        }

        .input-success {
            border-color: #10b981 !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
        }

        .form-group {
            position: relative;
        }

        .form-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            transition: color 0.3s ease;
        }

        .form-input {
            padding-left: 40px;
            transition: all 0.3s ease;
        }

        .form-input:focus + .form-icon {
            color: #3b82f6;
        }

        .form-input.input-error + .form-icon {
            color: #dc2626;
        }

        .form-input.input-success + .form-icon {
            color: #10b981;
        }

        .floating-label {
            position: absolute;
            left: 40px;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            padding: 0 4px;
            color: #6b7280;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .form-input:focus ~ .floating-label,
        .form-input:not(:placeholder-shown) ~ .floating-label {
            top: 0;
            font-size: 0.75rem;
            color: #3b82f6;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
            border-color: #9ca3af;
        }

        .section-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 0.5rem;
            color: #3b82f6;
        }

        .radio-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .radio-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .radio-option:hover {
            border-color: #3b82f6;
            background-color: #f0f9ff;
        }

        .radio-option input[type="radio"] {
            margin-right: 0.75rem;
        }

        .radio-option.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        /* Estilos para campos de búsqueda */
        .search-container {
            position: relative;
        }

        .search-container input[type="text"] {
            padding-right: 60px;
        }

        .search-container .search-icon {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }

        .search-container .clear-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .search-container .clear-btn:hover {
            color: #6b7280;
            background-color: #f3f4f6;
        }

        .search-container .clear-btn:active {
            transform: translateY(-50%) scale(0.95);
        }

        /* Estilos para selects con búsqueda */
        .searchable-select {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
        }

        .searchable-select option {
            padding: 8px 12px;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.2s ease;
        }

        .searchable-select option:hover {
            background-color: #f0f9ff;
        }

        .searchable-select option:checked {
            background-color: #dbeafe;
            color: #1e40af;
        }

        /* Estilos para select personalizado con búsqueda integrada */
        .custom-select-container {
            position: relative;
        }

        .custom-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        .custom-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #3b82f6;
            border-top: none;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            z-index: 50;
            max-height: 300px;
            overflow: hidden;
        }

        .search-box {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .search-box input {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .options-container {
            max-height: 250px;
            overflow-y: auto;
        }

        .custom-option {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.2s ease;
            font-size: 14px;
        }

        .custom-option:hover {
            background-color: #f0f9ff;
        }

        .custom-option.selected {
            background-color: #dbeafe;
            color: #1e40af;
            font-weight: 500;
        }

        .custom-option.hidden {
            display: none;
        }

        /* Scrollbar personalizado para las opciones */
        .options-container::-webkit-scrollbar {
            width: 6px;
        }

        .options-container::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .options-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .options-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content">
        <div class="container mx-auto px-4 py-8">
            <div class="max-w-4xl mx-auto">
                <!-- Header con título y botones de acción -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">
                                <i class="fas fa-plus-circle text-blue-600 mr-3"></i>
                                Nueva Orden de Trabajo
                            </h1>
                            <p class="text-gray-600 mt-2">Complete la información para crear una nueva orden de trabajo</p>
                        </div>
                        <div class="flex space-x-4">
                            <a href="lista.php" 
                               class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
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

                    <form method="POST" class="space-y-6" x-data="{ 
                        tipoCliente: 'existente',
                        mostrarEquipoExistente: true,
                        mostrarClienteExistente: true
                    }" id="ordenForm" enctype="multipart/form-data">
                        <!-- Div para mensajes de error -->
                        <div id="errorMessage" class="hidden bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg">
                        </div>

                        <!-- Sección 1: Descripción General -->
                        <div class="card p-6">
                            <div class="section-title">
                                <i class="fas fa-clipboard-list"></i>
                                Descripción General del Problema
                            </div>
                            <div class="form-group">
                                <label class="block text-gray-700 font-medium mb-3">Descripción General de la orden de trabajo *</label>
                                <textarea name="descripcion_problema"
                                    id="descripcion_problema"
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                    rows="4"
                                    required
                                    placeholder="Describa el problema general de la orden, síntomas, comportamiento anormal, etc."></textarea>
                            </div>
                            
                        </div>

                        <!-- Sección 2: Selección de Cliente -->
                        <div class="card p-6">
                            <div class="section-title">
                                <i class="fas fa-user"></i>
                                Información del Cliente
                            </div>
                            
                            <!-- Selector de tipo de cliente -->
                            <div class="radio-group">
                                <label class="radio-option" :class="{ 'selected': tipoCliente === 'existente' }">
                                    <input type="radio" name="tipo_cliente" value="existente"
                                        x-model="tipoCliente"
                                        class="mr-3">
                                    <div>
                                        <div class="font-medium text-gray-800">Cliente Existente</div>
                                        <div class="text-sm text-gray-600">Seleccionar de la base de datos</div>
                                    </div>
                                </label>
                                <label class="radio-option" :class="{ 'selected': tipoCliente === 'nuevo' }">
                                    <input type="radio" name="tipo_cliente" value="nuevo"
                                        x-model="tipoCliente"
                                        class="mr-3">
                                    <div>
                                        <div class="font-medium text-gray-800">Nuevo Cliente</div>
                                        <div class="text-sm text-gray-600">Registrar información nueva</div>
                                    </div>
                                </label>
                            </div>

                            <!-- Cliente Existente -->
                            <div x-show="tipoCliente === 'existente'" class="space-y-4">
                                <div class="form-group">
                                    <label class="block text-gray-700 font-medium mb-2">Seleccionar Cliente *</label>
                                    <div class="custom-select-container">
                                        <select name="id_cliente" id="id_cliente"
                                            x-bind:required="tipoCliente === 'existente'"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors custom-select">
                                            <option value="">Seleccione un cliente</option>
                                            <?php foreach ($clientes as $cliente): ?>
                                                <option value="<?php echo $cliente['id_cliente']; ?>" 
                                                        data-nombre="<?php echo htmlspecialchars($cliente['nombre_apellido']); ?>"
                                                        data-identificacion="<?php echo htmlspecialchars($cliente['identificacion']); ?>"
                                                        data-empresa="<?php echo htmlspecialchars($cliente['empresa'] ?? ''); ?>">
                                                    <?php echo htmlspecialchars($cliente['nombre_apellido']); ?> 
                                                    (<?php echo htmlspecialchars($cliente['identificacion']); ?>)
                                                    <?php if (!empty($cliente['empresa'])): ?>
                                                        - <?php echo htmlspecialchars($cliente['empresa']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="custom-select-dropdown" id="dropdown_cliente" style="display: none;">
                                            <div class="search-box">
                                                <input type="text" 
                                                       id="buscar_cliente" 
                                                       placeholder="Buscar cliente..."
                                                       class="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                                                       oninput="filtrarClientes(this.value)">
                                            </div>
                                            <div class="options-container" id="opciones_cliente">
                                                <!-- Las opciones se cargarán dinámicamente -->
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Selección de Equipo -->
                                <div x-data="{ tipoEquipo: 'existente' }">
                                    <div class="radio-group">
                                        <label class="radio-option" :class="{ 'selected': tipoEquipo === 'existente' }">
                                            <input type="radio" name="tipo_equipo" value="existente" checked
                                                x-model="tipoEquipo"
                                                class="mr-3">
                                            <div>
                                                <div class="font-medium text-gray-800">Equipo Existente</div>
                                                <div class="text-sm text-gray-600">Seleccionar de la base de datos</div>
                                            </div>
                                        </label>
                                        <label class="radio-option" :class="{ 'selected': tipoEquipo === 'nuevo' }">
                                            <input type="radio" name="tipo_equipo" value="nuevo"
                                                x-model="tipoEquipo"
                                                class="mr-3">
                                            <div>
                                                <div class="font-medium text-gray-800">Nuevo Equipo</div>
                                                <div class="text-sm text-gray-600">Registrar equipo nuevo</div>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Equipo Existente -->
                                    <div x-show="tipoEquipo === 'existente'" class="space-y-4">
                                        <div class="form-group">
                                            <label class="block text-gray-700 font-medium mb-2">Equipo del Cliente seleccionado *</label>
                                            <div class="custom-select-container">
                                                <select name="id_equipo" id="id_equipo"
                                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors custom-select">
                                                    <option value="">Primero seleccione un cliente</option>
                                                </select>
                                                <div class="custom-select-dropdown" id="dropdown_equipo" style="display: none;">
                                                    <div class="search-box">
                                                        <input type="text" 
                                                               id="buscar_equipo" 
                                                               placeholder="Buscar equipo..."
                                                               class="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                                                               oninput="filtrarEquipos(this.value)">
                                                    </div>
                                                    <div class="options-container" id="opciones_equipo">
                                                        <!-- Las opciones se cargarán dinámicamente -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="block text-gray-700 font-medium mb-2">
                                                Observaciones específicas del equipo existente *
                                            </label>
                                            <textarea name="observaciones_equipo_existente"
                                                id="observaciones_equipo_existente"
                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                                rows="3"
                                                placeholder="Describa el equipo, características, estado actual, etc."></textarea>
                                        </div>
                                    </div>

                                    <!-- Nuevo Equipo para Cliente Existente -->
                                    <div x-show="tipoEquipo === 'nuevo'" class="space-y-4">
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <div class="flex items-start">
                                                <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                                                <div class="text-sm text-blue-800">
                                                    <strong>Información importante:</strong> Solo la marca es obligatoria. Si no conoce el modelo o número de serie, se usarán valores por defecto: "S/N" para modelo y se generará un número de serie automático.
                                                </div>
                                            </div>
                                        </div>
                                        <div x-data="{ equiposCount: 1 }">
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-lg font-semibold text-gray-800">Equipos a registrar</h3>
                                                <div class="flex space-x-2">
                                                    <button type="button" @click="if(equiposCount > 1) equiposCount--"
                                                        class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 transition-colors text-sm">
                                                        <i class="fas fa-minus mr-1"></i> Quitar
                                                    </button>
                                                    <button type="button" @click="if(equiposCount < 10) equiposCount++"
                                                        class="bg-green-500 text-white px-3 py-1 rounded-lg hover:bg-green-600 transition-colors text-sm">
                                                        <i class="fas fa-plus mr-1"></i> Agregar
                                                    </button>
                                                </div>
                                            </div>
                                            <template x-for="i in equiposCount" :key="i">
                                                <div class="card p-4 mb-4">
                                                    <h4 class="font-medium text-gray-800 mb-4">Equipo <span x-text="i"></span></h4>
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div class="form-group">
                                                            <label class="block text-gray-700 font-medium mb-2">Marca *</label>
                                                            <input type="text" :name="'marca_nuevo_' + i" :id="'marca_nuevo_' + i" x-bind:required="tipoEquipo === 'nuevo'"
                                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                                                placeholder="Ej: Apple, Samsung, HP" maxlength="50">
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="block text-gray-700 font-medium mb-2">Modelo</label>
                                                            <input type="text" :name="'modelo_nuevo_' + i" :id="'modelo_nuevo_' + i"
                                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                                                placeholder="Ej: iPhone 13, Galaxy S21 (S/N si no se conoce)" maxlength="50">
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="block text-gray-700 font-medium mb-2">Número de Serie</label>
                                                            <input type="text" :name="'numero_serial_nuevo_' + i" :id="'numero_serial_nuevo_' + i"
                                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                                                placeholder="Se generará automáticamente si está vacío" maxlength="50">
                                                        </div>
                                                        <div class="form-group md:col-span-2">
                                                            <label class="block text-gray-700 font-medium mb-2">Observaciones</label>
                                                            <textarea :name="'observaciones_nuevo_' + i" :id="'observaciones_nuevo_' + i"
                                                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                                                rows="3" placeholder="Observaciones adicionales del equipo" maxlength="500"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cliente Nuevo -->
                        <div x-show="tipoCliente === 'nuevo'" class="space-y-6">
                            <!-- Sección 3: Tipo de Cliente -->
                            <div class="card p-6">
                                <div class="section-title">
                                    <i class="fas fa-users"></i>
                                    Tipo de Cliente
                                </div>
                                <div class="radio-group">
                                    <label class="radio-option" onclick="seleccionarTipoCliente(this, '1')">
                                        <input type="radio" name="id_tipo_cliente" value="1" class="mr-3" onchange="actualizarValidacionesNueva()" x-bind:required="tipoCliente === 'nuevo'">
                                        <div>
                                            <div class="font-medium text-gray-800">Personal</div>
                                            <div class="text-sm text-gray-600">Cédula de 10 dígitos</div>
                                        </div>
                                    </label>
                                    <label class="radio-option" onclick="seleccionarTipoCliente(this, '2')">
                                        <input type="radio" name="id_tipo_cliente" value="2" class="mr-3" onchange="actualizarValidacionesNueva()" x-bind:required="tipoCliente === 'nuevo'">
                                        <div>
                                            <div class="font-medium text-gray-800">Empresa</div>
                                            <div class="text-sm text-gray-600">RUC de 13 dígitos</div>
                                        </div>
                                    </label>
                                </div>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                                    <div class="flex items-start">
                                        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                                        <div class="text-sm text-blue-800">
                                            <strong>Información importante:</strong> Seleccione el tipo de cliente para activar las validaciones correspondientes.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sección 4: Información del Cliente -->
                            <div class="card p-6">
                                <div class="section-title">
                                    <i class="fas fa-user-circle"></i>
                                    Información del Cliente
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="form-group">
                                        <label class="block text-gray-700 font-medium mb-2">Identificación *</label>
                                        <input type="text" name="identificacion" id="identificacion_nueva" x-bind:required="tipoCliente === 'nuevo'"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                            placeholder="Ingrese la identificación" maxlength="13" oninput="validarIdentificacionNueva(this)"
                                            onkeypress="return soloNumeros(event)" onpaste="manejarPegado(event)">
                                        <span class="error-message" id="error-identificacion-nueva"></span>
                                    </div>

                                    <div class="form-group">
                                        <label class="block text-gray-700 font-medium mb-2">Nombre y Apellido *</label>
                                        <input type="text" name="nombre_apellido" id="nombre_apellido_nueva" x-bind:required="tipoCliente === 'nuevo'"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                            placeholder="Ingrese nombre y apellido" maxlength="100" oninput="validarNombreNueva(this)">
                                        <span class="error-message" id="error-nombre-nueva"></span>
                                    </div>

                                    <div class="form-group">
                                        <label class="block text-gray-700 font-medium mb-2">Teléfono *</label>
                                        <input type="text" name="telefono" id="telefono_nueva" x-bind:required="tipoCliente === 'nuevo'"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                            placeholder="Ingrese el teléfono" maxlength="12" oninput="validarTelefonoNueva(this)"
                                            onkeypress="return soloNumeros(event)" onpaste="manejarPegado(event)">
                                        <span class="error-message" id="error-telefono-nueva"></span>
                                    </div>

                                    <div class="form-group">
                                        <label class="block text-gray-700 font-medium mb-2">Dirección *</label>
                                        <input type="text" name="direccion" id="direccion_nueva" x-bind:required="tipoCliente === 'nuevo'"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                            placeholder="Ingrese la dirección" maxlength="200" oninput="validarDireccionNueva(this)">
                                        <span class="error-message" id="error-direccion-nueva"></span>
                                    </div>

                                    <div class="form-group md:col-span-2">
                                        <label class="block text-gray-700 font-medium mb-2">Empresa (Opcional)</label>
                                        <input type="text" name="empresa" id="empresa_nueva"
                                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                            placeholder="Ingrese el nombre de la empresa (opcional)" maxlength="100">
                                    </div>
                                </div>
                            </div>

                            <!-- Sección 5: Datos del Equipo -->
                            <div class="card p-6">
                                <div class="section-title">
                                    <i class="fas fa-laptop"></i>
                                    Datos de los Equipos
                                </div>
                                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r-lg mb-6">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-info-circle text-blue-400"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-blue-700 font-medium">Información importante:</p>
                                            <p class="text-blue-600 text-sm">Si no conoce algunos datos del equipo, puede dejarlos vacíos. Se usarán valores por defecto: "S/N" para marca/modelo y se generará un número de serie automático.</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div x-data="{ equiposCount: 1 }">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="text-lg font-semibold text-gray-800">Equipos a registrar</h4>
                                        <button type="button"
                                            @click="if(equiposCount < 10) equiposCount++"
                                            class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors flex items-center">
                                            <i class="fas fa-plus mr-2"></i> Agregar Equipo
                                        </button>
                                    </div>
                                    
                                    <template x-for="i in equiposCount" :key="i">
                                        <div class="card p-6 mb-4">
                                            <div class="flex items-center justify-between mb-4">
                                                <h5 class="font-semibold text-gray-800">Equipo #<span x-text="i"></span></h5>
                                                <button type="button" 
                                                    x-show="equiposCount > 1"
                                                    @click="equiposCount--"
                                                    class="text-red-500 hover:text-red-700">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div class="form-group">
                                                    <label class="block text-gray-700 font-medium mb-2">Marca</label>
                                                    <input type="text" :name="'marca_' + i"
                                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                                        placeholder="Ej: Lenovo (S/N si no se conoce)">
                                                </div>
                                                <div class="form-group">
                                                    <label class="block text-gray-700 font-medium mb-2">Modelo</label>
                                                    <input type="text" :name="'modelo_' + i"
                                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                                        placeholder="Ej: ThinkPad T14 (S/N si no se conoce)">
                                                </div>
                                                <div class="form-group md:col-span-2">
                                                    <label class="block text-gray-700 font-medium mb-2">Número de Serie</label>
                                                    <input type="text" :name="'numero_serial_' + i"
                                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                                        placeholder="Se generará automáticamente si está vacío">
                                                </div>
                                                <div class="form-group md:col-span-2">
                                                    <label class="block text-gray-700 font-medium mb-2">
                                                        Observaciones específicas del equipo
                                                    </label>
                                                    <textarea :name="'observaciones_equipo_existente_' + i"
                                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                                        rows="3"
                                                        placeholder="Describa el problema o características del equipo"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="mb-6">
                        <label class="block text-gray-700 mb-2">Técnico Responsable (a asignar)*</label>
                        <select name="tecnico_responsable_id" required
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="">Seleccione *</option>
                            <?php
                            $stmt = $conn->query("SELECT u.id_usuario, u.nombre_completo , tu.nombre
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

                    <!-- Sección de Información de Pago -->
                    <div class="card p-6 mb-6">
                        <div class="section-title">
                            <i class="fas fa-dollar-sign"></i>
                            Información de Pago
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                                <div class="text-sm text-blue-800">
                                    <strong>Información importante:</strong> 
                                    El cliente puede realizar un abono inicial al dejar su equipo. 
                                    Este abono se descontará del costo total del servicio.
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                                <label class="block text-gray-700 font-medium mb-3">
                                    Valor Estimado de la Orden 
                                    <span class="text-sm text-gray-500 font-normal">(Opcional)</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                    <input type="text" 
                                           name="valor_estimado" 
                                           id="valor_estimado"
                                           class="w-full pl-8 pr-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                           placeholder="0.00"
                                           oninput="formatearMoneda(this)"
                                           onblur="validarMoneda(this)">
                                </div>
                                <p class="text-sm text-gray-600 mt-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Ingrese un valor estimado o total de la orden para calcular el saldo pendiente del cliente
                                </p>
                            </div>
                            
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group">
                                <label class="block text-gray-700 font-medium mb-2">
                                    ¿Realizará abono inicial?
                                </label>
                                <div class="radio-group">
                                    <label class="radio-option" onclick="seleccionarAbono(this, 'si')">
                                        <input type="radio" name="realizar_abono" value="si" class="mr-3" onchange="toggleSeccionAbono()">
                                        <div>
                                            <div class="font-medium text-gray-800">Sí</div>
                                            <div class="text-sm text-gray-600">Cliente pagará un abono</div>
                                        </div>
                                    </label>
                                    <label class="radio-option" onclick="seleccionarAbono(this, 'no')">
                                        <input type="radio" name="realizar_abono" value="no" checked class="mr-3" onchange="toggleSeccionAbono()">
                                        <div>
                                            <div class="font-medium text-gray-800">No</div>
                                            <div class="text-sm text-gray-600">Sin abono inicial</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group" id="seccion_abono" style="display: none;">
                                <label class="block text-gray-700 font-medium mb-2">Monto del Abono *</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                    <input type="text" name="monto_abono"
                                           class="w-full pl-8 pr-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                           placeholder="0.00" 
                                           oninput="formatearMoneda(this)"
                                           onblur="validarMoneda(this)">
                                </div>
                            </div>

                            <div class="form-group" id="seccion_metodo_pago" style="display: none;">
                                <label class="block text-gray-700 font-medium mb-2">Método de Pago *</label>
                                <select name="metodo_pago" 
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors">
                                    <option value="">Seleccione método de pago</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Tarjeta">Tarjeta de Crédito/Débito</option>
                                    <option value="Transferencia">Transferencia Bancaria</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>

                            <div class="form-group md:col-span-2" id="seccion_observaciones_pago" style="display: none;">
                                <label class="block text-gray-700 font-medium mb-2">Observaciones del Pago</label>
                                <textarea name="observaciones_pago" rows="3"
                                          class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 transition-colors"
                                          placeholder="Observaciones adicionales sobre el pago..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-700 mb-2">Imágenes (Opcional)</label>
                        <input type="file" name="imagenes[]" accept="image/*" multiple
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                            onchange="validarImagenes(this)">
                        <p class="text-xs text-gray-500 mt-1">
                            Puede seleccionar múltiples imágenes. Tamaño máximo por archivo: 10MB. 
                            Las imágenes se redimensionarán automáticamente a máximo 1200x1200 píxeles y se comprimirán.
                            Formatos permitidos: JPG, PNG, GIF, WEBP.
                        </p>
                        <div id="preview-container" class="grid grid-cols-5 gap-4 mt-4"></div>
                        <div id="imagenes-guardadas" class="hidden"></div>
                    </div>

                    <script>
                        let imagenesActuales = [];

                        function validarImagenes(input) {
                            const previewContainer = document.getElementById('preview-container');
                            const imagenesGuardadas = document.getElementById('imagenes-guardadas');
                            const nuevasImagenes = Array.from(input.files);

                            // Combinar imágenes existentes con nuevas
                            imagenesActuales = [...imagenesActuales, ...nuevasImagenes];

                            // Limpiar y mostrar previsualizaciones
                            previewContainer.innerHTML = '';
                            imagenesGuardadas.innerHTML = '';

                            imagenesActuales.forEach((file, index) => {
                                if (!file.type.startsWith('image/')) {
                                    imagenesActuales = imagenesActuales.filter((_, i) => i !== index);
                                    return;
                                }

                                // Validar tamaño máximo (100MB)
                                const maxSize = 100 * 1024 * 1024; // 100MB
                                if (file.size > maxSize) {
                                    alert(`La imagen "${file.name}" es demasiado grande. Máximo 100MB.`);
                                    imagenesActuales = imagenesActuales.filter((_, i) => i !== index);
                                    return;
                                }

                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    const imgContainer = document.createElement('div');
                                    imgContainer.className = 'relative';

                                    const img = document.createElement('img');
                                    img.src = e.target.result;
                                    img.className = 'w-full h-32 object-cover rounded';

                                    const deleteBtn = document.createElement('button');
                                    deleteBtn.type = 'button';
                                    deleteBtn.className = 'absolute top-0 right-0 bg-red-500 text-white rounded-full p-1 m-1';
                                    deleteBtn.innerHTML = '×';
                                    deleteBtn.onclick = () => eliminarImagen(index);

                                    imgContainer.appendChild(img);
                                    imgContainer.appendChild(deleteBtn);
                                    previewContainer.appendChild(imgContainer);
                                }
                                reader.readAsDataURL(file);

                                // Comprimir y redimensionar imagen si es necesario
                                comprimirImagen(file, index);
                            });
                        }

                        function eliminarImagen(index) {
                            imagenesActuales = imagenesActuales.filter((_, i) => i !== index);
                            validarImagenes({
                                files: imagenesActuales
                            });
                        }

                        function comprimirImagen(file, index) {
                            const maxSize = 3 * 1024 * 1024; // 3MB
                            if (file.size <= maxSize) return;

                            const img = new Image();
                            img.src = URL.createObjectURL(file);
                            img.onload = function() {
                                const canvas = document.createElement('canvas');
                                let width = img.width;
                                let height = img.height;

                                // Redimensionar a máximo 1200x1200 píxeles
                                if (width > height) {
                                    if (width > 1200) {
                                        height *= 1200 / width;
                                        width = 1200;
                                    }
                                } else {
                                    if (height > 1200) {
                                        width *= 1200 / height;
                                        height = 1200;
                                    }
                                }

                                canvas.width = width;
                                canvas.height = height;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(img, 0, 0, width, height);

                                canvas.toBlob(function(blob) {
                                    const compressedFile = new File([blob], file.name, {
                                        type: 'image/jpeg',
                                        lastModified: Date.now()
                                    });
                                    imagenesActuales[index] = compressedFile;
                                }, 'image/jpeg', 0.7);
                            };
                        }
                    </script>
                    <!-- Botones de acción -->
                    <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                        <a href="lista.php" 
                           class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Cancelar
                        </a>
                        <button type="submit" 
                                class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition-colors font-semibold">
                            <i class="fas fa-save mr-2"></i>Crear Orden de Trabajo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        console.log('Script de validaciones cargado');
        
        document.querySelector('form').addEventListener('submit', function(e) {
            const tipoCliente = document.querySelector('input[name="tipo_cliente"]:checked').value;
            const tipoClienteSelect = document.querySelector('select[name="id_tipo_cliente"]');

            if (tipoCliente === 'nuevo' && !tipoClienteSelect.value) {
                e.preventDefault();
                alert('Por favor, seleccione el tipo de cliente');
                tipoClienteSelect.focus();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const tipoCliente = document.querySelector('input[name="tipo_cliente"]:checked').value;

                if (tipoCliente === 'existente') {
                    const idCliente = document.getElementById('id_cliente').value;
                    if (!idCliente) {
                        e.preventDefault();
                        alert('Por favor, seleccione un cliente');
                        return;
                    }
                }
            });
        });

        /* validaciones para el numero de serial */
        document.addEventListener('DOMContentLoaded', function() {
            // Función para validar número de serie
            function validarNumeroSerie(input) {
                const numeroSerie = input.value.trim();
                if (numeroSerie === '') return;

                fetch('validar_serie.php?numero_serial=' + encodeURIComponent(numeroSerie))
                    .then(response => response.json())
                    .then(data => {
                        const errorDiv = input.parentElement.querySelector('.error-serial');
                        if (data.existe) {
                            input.classList.add('border-red-500');
                            if (!errorDiv) {
                                const mensaje = document.createElement('div');
                                mensaje.className = 'error-serial text-red-500 text-sm mt-1';
                                mensaje.textContent = 'El número de serie ya existe en el sistema.';
                                input.parentElement.appendChild(mensaje);
                            }
                        } else {
                            input.classList.remove('border-red-500');
                            if (errorDiv) {
                                errorDiv.remove();
                            }
                        }
                    });
            }

            // Función para agregar validación a los campos de número de serie
            function agregarValidacionSerie(contenedor) {
                const inputsSerial = contenedor.querySelectorAll('input[name^="numero_serial"]');
                inputsSerial.forEach(input => {
                    input.addEventListener('blur', function() {
                        validarNumeroSerie(this);
                    });
                });
            }

            // Agregar validación a los campos iniciales
            agregarValidacionSerie(document);

            // Modificar el evento de agregar equipo
            const contenedorEquipos = document.getElementById('contenedor-equipos');
            if (contenedorEquipos) {
                const observador = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1) { // Es un elemento
                                    agregarValidacionSerie(node);
                                }
                            });
                        }
                    });
                });

                observador.observe(contenedorEquipos, {
                    childList: true,
                    subtree: true
                });
            }

            // Modificar el evento de agregar equipos nuevos de clientes nuevos
            const contenedorEquiposNuevos = document.getElementById('contenedor-equipos-nuevos');
            if (contenedorEquiposNuevos) {
                const observador = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.nodeType === 1) { // Es un elemento
                                    agregarValidacionSerie(node);
                                }
                            });
                        }
                    });
                });

                observador.observe(contenedorEquiposNuevos, {
                    childList: true,
                    subtree: true
                });
            }
            /* ---------------------------------- */
        });

        document.addEventListener('DOMContentLoaded', function() {
            const clienteSelect = document.getElementById('id_cliente');
            if (clienteSelect) {
                // Remover el evento onfocus del HTML y agregarlo aquí
                clienteSelect.removeAttribute('onfocus');
                clienteSelect.addEventListener('focus', function(e) {
                    console.log('Focus en select cliente');
                    e.preventDefault();
                    e.stopPropagation();
                    mostrarBusquedaCliente();
                });
                
                clienteSelect.addEventListener('change', function() {
                    const clienteId = this.value;
                    
                    if (clienteId) {
                        fetch(`get_equipos.php?cliente_id=${clienteId}`)
                            .then(response => response.text())
                            .then(html => {
                                const equipoSelect = document.getElementById('id_equipo');
                                if (equipoSelect) {
                                    equipoSelect.innerHTML = html;
                                }
                            })
                            .catch(error => console.error('Error:', error));
                    } else {
                        const equipoSelect = document.getElementById('id_equipo');
                        if (equipoSelect) {
                            equipoSelect.innerHTML = '<option value="">Primero seleccione un cliente</option>';
                        }
                    }
                });
            }

            const equipoSelect = document.getElementById('id_equipo');
            if (equipoSelect) {
                // Remover el evento onfocus del HTML y agregarlo aquí
                equipoSelect.removeAttribute('onfocus');
                equipoSelect.addEventListener('focus', function(e) {
                    console.log('Focus en select equipo');
                    e.preventDefault();
                    e.stopPropagation();
                    mostrarBusquedaEquipo();
                });
            }
        });

        // Función para filtrar clientes en tiempo real
        function filtrarClientes(busqueda) {
            const opcionesContainer = document.getElementById('opciones_cliente');
            const opciones = opcionesContainer.querySelectorAll('.custom-option');
            const busquedaLower = busqueda.toLowerCase();
            
            opciones.forEach(opcion => {
                const nombre = opcion.getAttribute('data-nombre') || '';
                const identificacion = opcion.getAttribute('data-identificacion') || '';
                const empresa = opcion.getAttribute('data-empresa') || '';
                
                const textoCompleto = `${nombre} ${identificacion} ${empresa}`.toLowerCase();
                
                if (textoCompleto.includes(busquedaLower)) {
                    opcion.classList.remove('hidden');
                } else {
                    opcion.classList.add('hidden');
                }
            });
        }

        // Función para filtrar equipos en tiempo real
        function filtrarEquipos(busqueda) {
            const opcionesContainer = document.getElementById('opciones_equipo');
            const opciones = opcionesContainer.querySelectorAll('.custom-option');
            const busquedaLower = busqueda.toLowerCase();
            
            opciones.forEach(opcion => {
                const marca = opcion.getAttribute('data-marca') || '';
                const modelo = opcion.getAttribute('data-modelo') || '';
                const serial = opcion.getAttribute('data-serial') || '';
                
                const textoCompleto = `${marca} ${modelo} ${serial}`.toLowerCase();
                
                if (textoCompleto.includes(busquedaLower)) {
                    opcion.classList.remove('hidden');
                } else {
                    opcion.classList.add('hidden');
                }
            });
        }

        // Variables globales para nueva orden
        let equiposCount = 1;
        let dropdownClienteAbierto = false;
        let dropdownEquipoAbierto = false;

        // Función para mostrar búsqueda de cliente
        function mostrarBusquedaCliente() {
            console.log('Intentando abrir dropdown cliente');
            
            const dropdown = document.getElementById('dropdown_cliente');
            const select = document.getElementById('id_cliente');
            const opcionesContainer = document.getElementById('opciones_cliente');
            
            if (!dropdown || !select || !opcionesContainer) {
                console.error('Elementos no encontrados');
                return;
            }
            
            // Cargar opciones dinámicamente
            opcionesContainer.innerHTML = '';
            Array.from(select.options).forEach(option => {
                if (option.value !== '') {
                    const div = document.createElement('div');
                    div.className = 'custom-option';
                    div.setAttribute('data-value', option.value);
                    div.setAttribute('data-nombre', option.getAttribute('data-nombre') || '');
                    div.setAttribute('data-identificacion', option.getAttribute('data-identificacion') || '');
                    div.setAttribute('data-empresa', option.getAttribute('data-empresa') || '');
                    div.textContent = option.textContent;
                    
                    div.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Opción seleccionada:', this.getAttribute('data-value'));
                        select.value = this.getAttribute('data-value');
                        select.dispatchEvent(new Event('change'));
                        ocultarBusquedaCliente();
                    });
                    
                    opcionesContainer.appendChild(div);
                }
            });
            
            dropdown.style.display = 'block';
            dropdownClienteAbierto = true;
            console.log('Dropdown cliente abierto');
            
            // Enfocar el campo de búsqueda
            setTimeout(() => {
                const searchInput = document.getElementById('buscar_cliente');
                if (searchInput) {
                    searchInput.focus();
                }
            }, 50);
        }

        // Función para ocultar búsqueda de cliente
        function ocultarBusquedaCliente() {
            console.log('Cerrando dropdown cliente');
            const dropdown = document.getElementById('dropdown_cliente');
            if (dropdown) {
                dropdown.style.display = 'none';
                dropdownClienteAbierto = false;
            }
        }

        // Función para mostrar búsqueda de equipo
        function mostrarBusquedaEquipo() {
            console.log('Intentando abrir dropdown equipo');
            
            const dropdown = document.getElementById('dropdown_equipo');
            const select = document.getElementById('id_equipo');
            const opcionesContainer = document.getElementById('opciones_equipo');
            
            if (!dropdown || !select || !opcionesContainer) {
                console.error('Elementos no encontrados');
                return;
            }
            
            // Verificar si hay opciones
            if (select.options.length <= 1) {
                console.log('No hay equipos disponibles');
                return;
            }
            
            // Cargar opciones dinámicamente
            opcionesContainer.innerHTML = '';
            Array.from(select.options).forEach(option => {
                if (option.value !== '') {
                    const div = document.createElement('div');
                    div.className = 'custom-option';
                    div.setAttribute('data-value', option.value);
                    div.setAttribute('data-marca', option.getAttribute('data-marca') || '');
                    div.setAttribute('data-modelo', option.getAttribute('data-modelo') || '');
                    div.setAttribute('data-serial', option.getAttribute('data-serial') || '');
                    div.textContent = option.textContent;
                    
                    div.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Opción seleccionada:', this.getAttribute('data-value'));
                        select.value = this.getAttribute('data-value');
                        select.dispatchEvent(new Event('change'));
                        ocultarBusquedaEquipo();
                    });
                    
                    opcionesContainer.appendChild(div);
                }
            });
            
            dropdown.style.display = 'block';
            dropdownEquipoAbierto = true;
            console.log('Dropdown equipo abierto');
            
            // Enfocar el campo de búsqueda
            setTimeout(() => {
                const searchInput = document.getElementById('buscar_equipo');
                if (searchInput) {
                    searchInput.focus();
                }
            }, 50);
        }

        // Función para ocultar búsqueda de equipo
        function ocultarBusquedaEquipo() {
            console.log('Cerrando dropdown equipo');
            const dropdown = document.getElementById('dropdown_equipo');
            if (dropdown) {
                dropdown.style.display = 'none';
                dropdownEquipoAbierto = false;
            }
        }

        // Función para limpiar búsquedas
        function limpiarBusqueda(tipo) {
            if (tipo === 'cliente') {
                document.getElementById('buscar_cliente').value = '';
                filtrarClientes('');
            } else if (tipo === 'equipo') {
                document.getElementById('buscar_equipo').value = '';
                filtrarEquipos('');
            }
        }

        // Manejar clics fuera de los dropdowns para cerrarlos
        document.addEventListener('click', function(event) {
            // Verificar si el clic fue en el select
            if (event.target.id === 'id_cliente' || event.target.id === 'id_equipo') {
                console.log('Clic en select, no hacer nada');
                return;
            }
            
            // Verificar si el clic fue dentro de algún dropdown
            if (event.target.closest('.custom-select-dropdown')) {
                console.log('Clic dentro del dropdown, no cerrar');
                return;
            }
            
            // Verificar si el clic fue dentro del contenedor del select
            if (event.target.closest('.custom-select-container')) {
                console.log('Clic dentro del contenedor, no cerrar');
                return;
            }
            
            // Si llegamos aquí, el clic fue fuera, cerrar dropdowns
            console.log('Clic fuera, cerrando dropdowns');
            if (dropdownClienteAbierto) {
                ocultarBusquedaCliente();
            }
            if (dropdownEquipoAbierto) {
                ocultarBusquedaEquipo();
            }
        });

        // Prevenir que el dropdown se cierre cuando se hace clic dentro
        document.addEventListener('click', function(event) {
            if (event.target.closest('.custom-select-dropdown')) {
                event.stopPropagation();
            }
        });

        // Manejar eventos de teclado en los campos de búsqueda
        document.addEventListener('keydown', function(event) {
            const searchCliente = document.getElementById('buscar_cliente');
            const searchEquipo = document.getElementById('buscar_equipo');
            
            // Si se presiona Escape, cerrar dropdowns
            if (event.key === 'Escape') {
                if (searchCliente && document.activeElement === searchCliente) {
                    ocultarBusquedaCliente();
                }
                if (searchEquipo && document.activeElement === searchEquipo) {
                    ocultarBusquedaEquipo();
                }
            }
            
            // Si se presiona Enter en una opción, seleccionarla
            if (event.key === 'Enter') {
                const activeOption = document.querySelector('.custom-option:hover');
                if (activeOption) {
                    activeOption.click();
                }
            }
        });

        // Variables globales para nueva orden
        let tipoClienteActualNueva = '1'; // Por defecto Personal

        // Función para permitir solo números
        function soloNumeros(event) {
            const charCode = (event.which) ? event.which : event.keyCode;
            if (charCode > 31 && (charCode < 48 || charCode > 57)) {
                return false;
            }
            return true;
        }

        // Función para limpiar caracteres no numéricos
        function limpiarCaracteresNoNumericos(input) {
            const valor = input.value;
            const valorLimpio = valor.replace(/[^0-9]/g, '');
            if (valor !== valorLimpio) {
                input.value = valorLimpio;
                // Disparar el evento input para activar las validaciones
                input.dispatchEvent(new Event('input'));
            }
        }

        // Función para manejar el pegado de texto
        function manejarPegado(event) {
            event.preventDefault();
            const textoPegado = (event.clipboardData || window.clipboardData).getData('text');
            const textoLimpio = textoPegado.replace(/[^0-9]/g, '');
            event.target.value = textoLimpio;
            // Disparar el evento input para activar las validaciones
            event.target.dispatchEvent(new Event('input'));
        }

        // Funciones para el formulario de nueva orden
        function seleccionarTipoCliente(elemento, tipo) {
            // Remover selección anterior
            document.querySelectorAll('.radio-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Seleccionar el nuevo
            elemento.classList.add('selected');
            tipoClienteActualNueva = tipo;
        }

        function actualizarValidacionesNueva() {
            console.log('actualizarValidacionesNueva llamado');
            const tipoCliente = document.querySelector('input[name="id_tipo_cliente"]:checked');
            if (tipoCliente) {
                tipoClienteActualNueva = tipoCliente.value;
                const identificacion = document.getElementById('identificacion_nueva');
                
                // Actualizar maxlength y placeholder según tipo
                if (tipoClienteActualNueva === '2') { // Empresa
                    identificacion.maxLength = 13;
                    identificacion.placeholder = 'Ingrese RUC (13 dígitos)';
                } else { // Personal
                    identificacion.maxLength = 10;
                    identificacion.placeholder = 'Ingrese cédula (10 dígitos)';
                }
                
                // Limpiar validación anterior
                limpiarValidacionNueva(identificacion, 'error-identificacion-nueva');
                
                // Revalidar si hay contenido
                if (identificacion.value) {
                    validarIdentificacionNueva(identificacion);
                }
            } else {
                // Si no hay tipo seleccionado, limpiar validación y mostrar mensaje
                const identificacion = document.getElementById('identificacion_nueva');
                if (identificacion && identificacion.value) {
                    mostrarErrorNueva(identificacion, 'error-identificacion-nueva', true, 'Primero seleccione el tipo de cliente');
                }
            }
        }

        function mostrarErrorNueva(input, errorId, mostrar, mensaje = '') {
            console.log('mostrarErrorNueva llamado:', {errorId, mostrar, mensaje});
            const errorSpan = document.getElementById(errorId);
            if (mostrar) {
                input.classList.remove('input-success');
                input.classList.add('input-error');
                errorSpan.style.display = 'block';
                if (mensaje) {
                    errorSpan.textContent = mensaje;
                }
            } else {
                input.classList.remove('input-error');
                input.classList.add('input-success');
                errorSpan.style.display = 'none';
            }
        }

        function limpiarValidacionNueva(input, errorId) {
            input.classList.remove('input-error', 'input-success');
            const errorSpan = document.getElementById(errorId);
            if (errorSpan) {
                errorSpan.style.display = 'none';
            }
        }

        function validarIdentificacionNueva(input) {
            console.log('validarIdentificacionNueva llamado con valor:', input.value);
            // Limpiar caracteres no numéricos en tiempo real
            limpiarCaracteresNoNumericos(input);
            
            const valor = input.value.trim();
            if (valor === '') {
                limpiarValidacionNueva(input, 'error-identificacion-nueva');
                return false;
            }

            // Verificar que se haya seleccionado el tipo de cliente
            const tipoClienteSeleccionado = document.querySelector('input[name="id_tipo_cliente"]:checked');
            if (!tipoClienteSeleccionado) {
                mostrarErrorNueva(input, 'error-identificacion-nueva', true, 'Primero seleccione el tipo de cliente');
                return false;
            }

            let valido = false;
            let mensaje = '';

            if (tipoClienteActualNueva === '2') { // Empresa
                valido = /^\d{13}$/.test(valor);
                mensaje = 'El RUC debe contener exactamente 13 dígitos';
            } else { // Personal
                valido = /^\d{10}$/.test(valor);
                mensaje = 'La cédula debe contener exactamente 10 dígitos';
            }

            console.log('Validación identificación:', {valido, mensaje, tipoCliente: tipoClienteActualNueva});
            mostrarErrorNueva(input, 'error-identificacion-nueva', !valido, mensaje);
            return valido;
        }

        function validarNombreNueva(input) {
            const valor = input.value.trim();
            if (valor === '') {
                limpiarValidacionNueva(input, 'error-nombre-nueva');
                return false;
            }

            const palabras = valor.split(/\s+/).filter(palabra => palabra.length > 0);
            const valido = palabras.length <= 6 && palabras.length >= 2;
            const mensaje = palabras.length < 2 ? 'Ingrese nombre y apellido' : 'Máximo 6 palabras permitidas';
            
            mostrarErrorNueva(input, 'error-nombre-nueva', !valido, mensaje);
            return valido;
        }

        function validarTelefonoNueva(input) {
            // Limpiar caracteres no numéricos en tiempo real
            limpiarCaracteresNoNumericos(input);
            
            const valor = input.value.trim();
            if (valor === '') {
                limpiarValidacionNueva(input, 'error-telefono-nueva');
                return false;
            }

            const valido = /^\d{7,12}$/.test(valor);
            const mensaje = 'El teléfono debe contener entre 7 y 12 dígitos';
            
            mostrarErrorNueva(input, 'error-telefono-nueva', !valido, mensaje);
            return valido;
        }

        function validarEmailNueva(input) {
            const valor = input.value.trim();
            if (valor === '') {
                limpiarValidacionNueva(input, 'error-email-nueva');
                return true; // Email es opcional
            }

            const valido = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor);
            const mensaje = 'El formato del email no es válido';
            
            mostrarErrorNueva(input, 'error-email-nueva', !valido, mensaje);
            return valido;
        }

        // Validación AJAX para identificación en nueva orden
        let timeoutIdNueva;
        document.addEventListener('DOMContentLoaded', function() {
            const identificacionNueva = document.getElementById('identificacion_nueva');
            if (identificacionNueva) {
                identificacionNueva.addEventListener('input', function(e) {
                    clearTimeout(timeoutIdNueva);
                    const identificacion = e.target.value.trim();
                    
                    // Solo validar si la identificación es válida según el tipo
                    let esValida = false;
                    if (tipoClienteActualNueva === '2') {
                        esValida = /^\d{13}$/.test(identificacion);
                    } else {
                        esValida = /^\d{10}$/.test(identificacion);
                    }
                    
                    if (!esValida || identificacion === '') {
                        return;
                    }

                    timeoutIdNueva = setTimeout(() => {
                        const formData = new FormData();
                        formData.append('identificacion', identificacion);

                        fetch('validar_identificacion.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.exists) {
                                mostrarErrorNueva(e.target, 'error-identificacion-nueva', true, 'Esta identificación ya existe en el sistema');
                            }
                        })
                        .catch(error => console.error('Error:', error));
                    }, 500);
                });
            }

            // Validación del formulario antes de enviar
            const form = document.getElementById('ordenForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const tipoCliente = document.querySelector('input[name="tipo_cliente"]:checked');
                    
                    if (tipoCliente && tipoCliente.value === 'nuevo') {
                        const tipoClienteSeleccionado = document.querySelector('input[name="id_tipo_cliente"]:checked');
                        if (!tipoClienteSeleccionado) {
                            e.preventDefault();
                            alert('Por favor, seleccione el tipo de cliente (Personal o Empresa) antes de continuar.');
                            return false;
                        }
                        
                        // Validar todos los campos requeridos
                        const identificacion = document.getElementById('identificacion_nueva');
                        const nombre = document.getElementById('nombre_apellido_nueva');
                        const telefono = document.getElementById('telefono_nueva');
                        const direccion = document.getElementById('direccion_nueva');
                        
                        if (!identificacion.value.trim()) {
                            e.preventDefault();
                            alert('Por favor, complete la identificación.');
                            identificacion.focus();
                            return false;
                        }
                        
                        if (!nombre.value.trim()) {
                            e.preventDefault();
                            alert('Por favor, complete el nombre y apellido.');
                            nombre.focus();
                            return false;
                        }
                        
                        if (!telefono.value.trim()) {
                            e.preventDefault();
                            alert('Por favor, complete el teléfono.');
                            telefono.focus();
                            return false;
                        }
                        
                        if (!direccion.value.trim()) {
                            e.preventDefault();
                            alert('Por favor, complete la dirección.');
                            direccion.focus();
                            return false;
                        }
                    }
                });
            }

            // Inicializar validaciones cuando se carga la página
            actualizarValidacionesNueva();
        });

        // Función para manejar dinámicamente los campos required
        function actualizarCamposRequired() {
            const tipoCliente = document.querySelector('input[name="tipo_cliente"]:checked')?.value;
            const tipoEquipo = document.querySelector('input[name="tipo_equipo"]:checked')?.value;
            
            // Campos de cliente nuevo
            const camposCliente = [
                'id_tipo_cliente',
                'identificacion',
                'nombre_apellido',
                'telefono',
                'direccion'
            ];
            
            // Campos de equipo nuevo
            const camposEquipo = [
                'marca_nuevo_1'
            ];
            
            // Actualizar campos de cliente
            camposCliente.forEach(campo => {
                const elemento = document.querySelector(`[name="${campo}"]`);
                if (elemento) {
                    if (tipoCliente === 'nuevo') {
                        elemento.setAttribute('required', 'required');
                    } else {
                        elemento.removeAttribute('required');
                    }
                }
            });
            
            // Actualizar campos de equipo
            camposEquipo.forEach(campo => {
                const elemento = document.querySelector(`[name="${campo}"]`);
                if (elemento) {
                    if (tipoEquipo === 'nuevo') {
                        elemento.setAttribute('required', 'required');
                    } else {
                        elemento.removeAttribute('required');
                    }
                }
            });
        }

        // Función para validar el formulario antes de enviar
        function validarFormularioAntesDeEnviar(event) {
            const tipoCliente = document.querySelector('input[name="tipo_cliente"]:checked')?.value;
            const tipoEquipo = document.querySelector('input[name="tipo_equipo"]:checked')?.value;
            
            // Si es cliente existente, verificar que se seleccionó un cliente
            if (tipoCliente === 'existente') {
                const clienteSelect = document.getElementById('id_cliente');
                if (!clienteSelect || !clienteSelect.value) {
                    alert('Debe seleccionar un cliente existente');
                    event.preventDefault();
                    return false;
                }
                
                // Si es cliente existente, verificar el tipo de equipo
                if (tipoEquipo === 'existente') {
                    const equipoSelect = document.getElementById('id_equipo');
                    if (!equipoSelect || !equipoSelect.value) {
                        alert('Debe seleccionar un equipo existente');
                        event.preventDefault();
                        return false;
                    }
                } else if (tipoEquipo === 'nuevo') {
                    const marca = document.querySelector('[name="marca_nuevo_1"]');
                    if (!marca || !marca.value.trim()) {
                        alert('Debe completar al menos la marca del equipo nuevo');
                        event.preventDefault();
                        return false;
                    }
                }
            } else if (tipoCliente === 'nuevo') {
                // Para cliente nuevo, verificar que se completó al menos la marca del primer equipo
                const marca = document.querySelector('[name="marca_1"]');
                if (!marca || !marca.value.trim()) {
                    alert('Debe completar al menos la marca del equipo');
                    event.preventDefault();
                    return false;
                }
            }
            
            // Actualizar campos required antes de enviar
            actualizarCamposRequired();
            
            return true;
        }

        // Agregar event listeners cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Event listeners para radio buttons
            document.querySelectorAll('input[name="tipo_cliente"]').forEach(radio => {
                radio.addEventListener('change', actualizarCamposRequired);
            });
            
            document.querySelectorAll('input[name="tipo_equipo"]').forEach(radio => {
                radio.addEventListener('change', actualizarCamposRequired);
            });
            
            // Event listener para el formulario
            const formulario = document.querySelector('form');
            if (formulario) {
                formulario.addEventListener('submit', validarFormularioAntesDeEnviar);
            }
            
            // Inicializar campos required
            actualizarCamposRequired();
        });

        // ===== FUNCIONES PARA MANEJO DE ABONOS =====
        
        // Función para seleccionar opción de abono
        function seleccionarAbono(elemento, valor) {
            // Remover selección anterior
            document.querySelectorAll('.radio-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Seleccionar el nuevo
            elemento.classList.add('selected');
        }

        // Función para mostrar/ocultar secciones de abono
        function toggleSeccionAbono() {
            const realizarAbono = document.querySelector('input[name="realizar_abono"]:checked').value;
            const seccionAbono = document.getElementById('seccion_abono');
            const seccionMetodoPago = document.getElementById('seccion_metodo_pago');
            const seccionObservacionesPago = document.getElementById('seccion_observaciones_pago');
            
            if (realizarAbono === 'si') {
                seccionAbono.style.display = 'block';
                seccionMetodoPago.style.display = 'block';
                seccionObservacionesPago.style.display = 'block';
                
                // Hacer campos requeridos
                document.querySelector('input[name="monto_abono"]').required = true;
                document.querySelector('select[name="metodo_pago"]').required = true;
            } else {
                seccionAbono.style.display = 'none';
                seccionMetodoPago.style.display = 'none';
                seccionObservacionesPago.style.display = 'none';
                
                // Quitar requerimiento y limpiar campos
                document.querySelector('input[name="monto_abono"]').required = false;
                document.querySelector('select[name="metodo_pago"]').required = false;
                document.querySelector('input[name="monto_abono"]').value = '';
                document.querySelector('select[name="metodo_pago"]').value = '';
                document.querySelector('textarea[name="observaciones_pago"]').value = '';
            }
        }

        // Función para formatear moneda mientras se escribe
        function formatearMoneda(input) {
            // Remover todo excepto números y punto decimal
            let valor = input.value.replace(/[^\d.]/g, '');
            
            // Asegurar que solo haya un punto decimal
            const partes = valor.split('.');
            if (partes.length > 2) {
                valor = partes[0] + '.' + partes.slice(1).join('');
            }
            
            // Limitar a máximo 2 decimales
            if (partes.length === 2 && partes[1].length > 2) {
                valor = partes[0] + '.' + partes[1].substring(0, 2);
            }
            
            // No permitir más de 10 dígitos antes del punto decimal
            if (partes[0].length > 10) {
                valor = partes[0].substring(0, 10) + (partes[1] ? '.' + partes[1] : '');
            }
            
            input.value = valor;
        }

        // Función para validar y formatear moneda al perder el foco
        function validarMoneda(input) {
            let valor = parseFloat(input.value) || 0;
            
            // Validar que no sea negativo
            if (valor < 0) {
                valor = 0;
            }
            
            // Validar que no exceda un límite razonable (ej: $1,000,000)
            if (valor > 1000000) {
                alert('El valor no puede exceder $1,000,000');
                valor = 1000000;
            }
            
            // Formatear a 2 decimales
            input.value = valor.toFixed(2);
        }

        // Función para validar monto de abono (mantener compatibilidad)
        function validarMontoAbono(input) {
            validarMoneda(input);
        }

        // Función para validar abono en el formulario
        function validarAbonoEnFormulario() {
            const realizarAbono = document.querySelector('input[name="realizar_abono"]:checked')?.value;
            
            if (realizarAbono === 'si') {
                const montoAbono = document.querySelector('input[name="monto_abono"]').value;
                const metodoPago = document.querySelector('select[name="metodo_pago"]').value;
                
                if (!montoAbono || parseFloat(montoAbono) <= 0) {
                    alert('Debe ingresar un monto válido para el abono');
                    document.querySelector('input[name="monto_abono"]').focus();
                    return false;
                }
                
                if (!metodoPago) {
                    alert('Debe seleccionar un método de pago');
                    document.querySelector('select[name="metodo_pago"]').focus();
                    return false;
                }
            }
            
            return true;
        }

        // Modificar la función de validación del formulario para incluir abono
        const validacionOriginal = validarFormularioAntesDeEnviar;
        function validarFormularioAntesDeEnviar(event) {
            // Ejecutar validación original
            const resultadoOriginal = validacionOriginal(event);
            if (!resultadoOriginal) return false;
            
            // Agregar validación de abono
            return validarAbonoEnFormulario();
        }
    </script>
</body>

</html>