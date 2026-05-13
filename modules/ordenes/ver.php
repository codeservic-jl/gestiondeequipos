<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

$id_orden = $_GET['id'];

// Obtener datos de la orden
$stmt = $conn->prepare("
    SELECT o.*, 
           c.nombre_apellido as cliente, c.email as cliente_email, c.telefono as cliente_telefono,
           u.nombre_completo as usuario_registro,
           c.identificacion as identificacion,
           c.empresa as empresa,
           s.nombre as nombre_sucursal,
           s.direccion as direccion_sucursal,
           s.telefono as telefono_sucursal,
           (SELECT us.nombre_completo FROM usuarios us WHERE us.id_usuario = o.tecnico_responsable_id ) AS nombre_tecnico_responsable,
           (SELECT us.nombre_completo FROM usuarios us WHERE us.id_usuario = o.usuario_abono ) AS nombre_usuario_abono
    FROM ordenes_trabajo o
    JOIN clientes c ON o.id_cliente = c.id_cliente
    JOIN usuarios u ON o.id_usuario_registro = u.id_usuario
    JOIN sucursales s ON o.id_sucursal = s.id_sucursal
    WHERE o.id_orden = ?
");
$stmt->execute([$_GET['id']]);
$orden = $stmt->fetch();

if (!$orden) {
    header("Location: lista.php");
    exit();
}

// Obtener equipos de la orden
$stmt = $conn->prepare("
    SELECT e.marca, e.modelo, e.numero_serial, oe.observaciones_falla_equipo
    FROM orden_equipos oe
    JOIN equipos e ON oe.id_equipo = e.id_equipo
    WHERE oe.id_orden = ?
    ORDER BY e.id_equipo
");
$stmt->execute([$_GET['id']]);
$equipos = $stmt->fetchAll();

// Si no hay equipos en orden_equipos, obtener el equipo directo de la orden
if (empty($equipos)) {
    $stmt = $conn->prepare("
        SELECT e.marca, e.modelo, e.numero_serial, '' as observaciones_falla_equipo
        FROM ordenes_trabajo o
        JOIN equipos e ON o.id_equipo = e.id_equipo
        WHERE o.id_orden = ?
    ");
    $stmt->execute([$_GET['id']]);
    $equipo_directo = $stmt->fetch();
    
    if ($equipo_directo) {
        $equipos = [$equipo_directo];
    }
}

// Debug temporal - comentar después de verificar
// error_log("Equipos encontrados para orden " . $_GET['id'] . ": " . count($equipos));
// foreach ($equipos as $equipo) {
//     error_log("Equipo: " . $equipo['marca'] . " " . $equipo['modelo'] . " " . $equipo['numero_serial']);
// }

// Obtener seguimientos de la orden
$stmt = $conn->prepare("
    SELECT s.*, u.nombre_completo as tecnico
    FROM seguimientos_orden s
    JOIN usuarios u ON s.id_tecnico = u.id_usuario
    WHERE s.id_orden = ?
    ORDER BY s.fecha_registro DESC
");
$stmt->execute([$_GET['id']]);
$seguimientos = $stmt->fetchAll();

// Obtener las sucursales
$sucursales = $conn->query("SELECT * FROM sucursales WHERE estado = 1")->fetchAll();


// Consulta los deatos de la empresa para el ticket
//$ivaEmpresa = 0;
$stmtEmpresa = $conn->prepare("SELECT iva, nombre_empresa, slogan,
                                leyenda1, leyenda2 FROM empresa ORDER BY nombre_empresa ASC");
$stmtEmpresa->execute();
$datosEmpresa        = $stmtEmpresa->fetch();
$ivaEmpresa     = $datosEmpresa['iva'] ?? 0;
$nombre_empresa = $datosEmpresa['nombre_empresa'] ?? '';
$slogan         = $datosEmpresa['slogan'] ?? '';
$leyenda1       = $datosEmpresa['leyenda1'] ?? '';
$leyenda2       = $datosEmpresa['leyenda2'] ?? '';

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Orden - <?php echo $orden['codigo']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #4f46e5;
            --accent-color: #818cf8;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --background-color: #f8fafc;
            --card-gradient: linear-gradient(145deg, #fff, #f8fafc);
        }

        .company-logo {
            font-size: 9px;
            font-weight: bold;
            margin-bottom: 5px;
            align-items: center;
            margin-left: 50%;
        }

        .contact-info {
            font-size: 9px;
            margin-bottom: 10px;
        }

        .order-number {
            color: #666;
            font-size: 9px;
        }

        .bg-primary {
            background-color: var(--primary-color);
        }

        .text-primary {
            color: var(--primary-color);
        }

        body {
            background: linear-gradient(135deg, var(--background-color), #fff);
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .modern-card {
            @apply bg-white rounded-3xl shadow-lg p-8 transition-all duration-300;
            background: var(--card-gradient);
            border: 1px solid rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .modern-card:hover {
            @apply shadow-xl;
            transform: translateY(-3px);
            border-color: var(--accent-color);
        }

        .btn {
            @apply px-6 py-3 rounded-2xl font-semibold transition-all duration-300 flex items-center gap-3;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: 2px solid transparent;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .status-badge {
            @apply px-4 py-2 rounded-2xl text-sm font-semibold inline-flex items-center gap-2;
            background: linear-gradient(135deg, var(--success-color), #34d399);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .status-badge.pending {
            background: linear-gradient(135deg, var(--warning-color), #fbbf24);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
        }

        .timeline-item {
            @apply border-l-4 pl-8 py-6 relative transition-all duration-300;
            border-image: linear-gradient(to bottom, var(--primary-color), var(--accent-color)) 1;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -12px;
            top: 28px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: 4px solid white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .timeline-item:hover {
            background: linear-gradient(to right, rgba(99, 102, 241, 0.05), transparent);
        }

        .section-header {
            @apply flex items-center gap-6 mb-8;
        }

        .section-icon {
            @apply p-4 rounded-2xl text-2xl;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(129, 140, 248, 0.1));
        }

        .info-group {
            @apply p-4 rounded-xl transition-all duration-300;
            background: rgba(99, 102, 241, 0.03);
        }

        .info-group:hover {
            background: rgba(99, 102, 241, 0.08);
        }

        .info-label {
            @apply text-gray-500 text-sm font-medium mb-1;
            letter-spacing: 0.5px;
        }

        .info-value {
            @apply text-gray-900 font-semibold;
            font-size: 1.1rem;
        }

        /* Animaciones para elementos al cargar la página */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modern-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .modern-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .modern-card:nth-child(3) {
            animation-delay: 0.4s;
        }

        /* Estilos para el ticket de impresión */
        @media print {

            /* Ocultar todo excepto el ticket */
            body * {
                visibility: hidden;
            }

            /* Mostrar solo el ticket */
            #imprimir_ticket,
            #imprimir_ticket * {
                visibility: visible;
            }

            /* Posicionar el ticket correctamente */
            #imprimir_ticket {
                position: absolute;
                left: 0;
                top: 0;
                width: 210mm;
                height: 148.5mm;
                margin: 0;
                padding: 0;
            }

            @page {
                size: A4;
                margin: 0;
            }

            /* Ajustes adicionales para impresión */
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Asegurar que el ticket se vea bien en papel */
            .ticket {
                width: 80mm;
                /* Ancho estándar para tickets */
                margin: 0 auto;
                padding: 10mm;
                border: none !important;
            }

            /* Ajustes para la tabla de equipos */
            .equipment-table {
                width: 100%;
                border-collapse: collapse;
                border-color: #000080;
            }

            /* Ocultar elementos no necesarios para impresión */
            .no-print {
                display: none !important;
            }

            /* Mostrar el ticket en impresión */
            #imprimir_ticket {
                display: block !important;
            }
        }

        /* Ocultar el ticket en vista normal */
        #imprimir_ticket {
            display: none;
        }

        :root {
            --primary-color: #000080;
            --secondary-color: #1a237e;
            --accent-color: #304ffe;
        }

        /* Estilos adicionales para mejorar la UI */
        .prose {
            line-height: 1.6;
        }

        .prose p {
            margin-bottom: 1rem;
        }

        .prose p:last-child {
            margin-bottom: 0;
        }

        /* Animaciones suaves para hover */
        .modern-card {
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            transform: translateY(-2px);
        }

        /* Mejoras para los badges de estado */
        .status-badge {
            transition: all 0.2s ease;
        }

        .status-badge:hover {
            transform: scale(1.05);
        }

        /* Efectos para las imágenes */
        .group:hover img {
            filter: brightness(1.1);
        }

        /* Mejoras para los botones */
        .btn {
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content min-h-screen py-12">
        <div class="container mx-auto px-4">
            <!-- Encabezado con información principal -->
            <div class="modern-card mb-8">
                <div class="flex flex-col md:flex-row justify-between items-start gap-8 p-6">

                    <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                        <button onclick="window.print()" class="btn text-white w-full md:w-auto">
                            <i class="fas fa-print text-xl"></i>
                            <span>Imprimir Orden</span>
                        </button>
                        <a href="lista.php" class="btn bg-gray-600 hover:bg-gray-700 text-white w-full md:w-auto text-center">
                            <i class="fas fa-arrow-left text-xl"></i>
                            <span>Lista de ordenes</span>
                        </a>
                    </div>
                    <div class="flex items-start gap-6 w-full md:w-auto">
                        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 p-5 rounded-2xl text-white shadow-lg">
                            <i class="fas fa-file-alt text-3xl"></i>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center gap-3">
                                <h1 class="text-3xl font-bold text-gray-900">
                                    Orden #<?php echo htmlspecialchars($orden['codigo']); ?>
                                </h1>
                                <span class="status-badge <?php 
                                    if ($orden['estado'] == 'Pendiente') {
                                        echo 'bg-yellow-100 text-yellow-800';
                                    } elseif ($orden['estado'] == 'Anulada') {
                                        echo 'bg-red-100 text-red-800';
                                    } else {
                                        echo 'bg-green-100 text-green-800';
                                    }
                                ?>">
                                    <i class="fas <?php 
                                        if ($orden['estado'] == 'Pendiente') {
                                            echo 'fa-clock';
                                        } elseif ($orden['estado'] == 'Anulada') {
                                            echo 'fa-ban';
                                        } else {
                                            echo 'fa-check-circle';
                                        }
                                    ?>"></i>
                                    <?php echo htmlspecialchars($orden['estado']); ?>
                                </span>
                            </div>
                            <p class="text-gray-500 flex items-center gap-2">
                                <i class="fas fa-calendar-alt"></i>
                                Ingresado el <?php echo date('d/m/Y H:i', strtotime($orden['fecha_ingreso'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de Acción -->
            <?php if ($orden['estado'] !== 'Anulada'): ?>
            <div class="modern-card mb-6">
                <div class="flex flex-col md:flex-row gap-4 justify-center">
                    <a href="editar.php?id=<?php echo $id_orden; ?>" 
                       class="btn bg-yellow-600 hover:bg-yellow-700 text-white text-center">
                        <i class="fas fa-edit text-xl"></i>
                        <span>Editar Orden</span>
                    </a>
                    <a href="registrar_seguimiento.php?id=<?php echo $id_orden; ?>" 
                       class="btn bg-green-600 hover:bg-green-700 text-white text-center">
                        <i class="fas fa-clipboard-check text-xl"></i>
                        <span>Registrar Seguimiento</span>
                    </a>
                    <a href="anular.php?id=<?php echo $id_orden; ?>" 
                       class="btn bg-red-600 hover:bg-red-700 text-white text-center"
                       onclick="return confirm('¿Está seguro de que desea anular esta orden? Esta acción cambiará el estado a Anulada y deshabilitará futuras modificaciones.')">
                        <i class="fas fa-ban text-xl"></i>
                        <span>Anular Orden</span>
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="modern-card mb-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        <div>
                            <h3 class="text-lg font-semibold text-red-800">Orden Anulada</h3>
                            <p class="text-red-700">Esta orden ha sido anulada y no puede ser modificada ni recibir nuevos seguimientos.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Grid principal de información -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Información del Cliente -->
                <div class="modern-card">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-user text-primary text-xl"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-primary">Información del Cliente</h2>
                    </div>
                    <div class="space-y-4">
                        <div class="bg-blue-50 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-id-card text-blue-600 w-5"></i>
                                        <div>
                                            <span class="text-sm text-gray-500">Nombre</span>
                                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($orden['cliente']); ?></div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-envelope text-blue-600 w-5"></i>
                                        <div>
                                            <span class="text-sm text-gray-500">Email</span>
                                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($orden['cliente_email']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-phone text-blue-600 w-5"></i>
                                        <div>
                                            <span class="text-sm text-gray-500">Teléfono</span>
                                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($orden['cliente_telefono']); ?></div>
                                        </div>
                                    </div>
                                    <?php if (!empty($orden['identificacion'])): ?>
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-id-badge text-blue-600 w-5"></i>
                                        <div>
                                            <span class="text-sm text-gray-500">Identificación</span>
                                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($orden['identificacion']); ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($orden['empresa'])): ?>
                            <div class="mt-4 pt-4 border-t border-blue-200">
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-building text-blue-600 w-5"></i>
                                    <div>
                                        <span class="text-sm text-gray-500">Empresa</span>
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($orden['empresa']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Información de Equipos -->
                <div class="modern-card">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-laptop text-green-600 text-xl"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-primary">Información de Equipos</h2>
                    </div>
                    <div class="space-y-4">
                        <?php if (!empty($equipos)): ?>
                            <div class="space-y-4">
                                <?php foreach ($equipos as $index => $equipo): ?>
                                    <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                                        <div class="flex items-center gap-2 mb-3">
                                            <i class="fas fa-desktop text-green-600"></i>
                                            <span class="font-semibold text-green-800">Equipo #<?php echo $index + 1; ?></span>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                                            <div class="flex items-center gap-3">
                                                <i class="fas fa-industry text-green-600 w-5"></i>
                                                <div>
                                                    <span class="text-sm text-gray-500">Marca</span>
                                                    <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($equipo['marca']); ?></div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <i class="fas fa-cube text-green-600 w-5"></i>
                                                <div>
                                                    <span class="text-sm text-gray-500">Modelo</span>
                                                    <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($equipo['modelo']); ?></div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <i class="fas fa-barcode text-green-600 w-5"></i>
                                                <div>
                                                    <span class="text-sm text-gray-500">Número de Serie</span>
                                                    <div class="font-semibold text-gray-900 font-mono"><?php echo htmlspecialchars($equipo['numero_serial']); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (!empty($equipo['observaciones_falla_equipo'])): ?>
                                            <div class="mt-4 p-4 bg-red-50 rounded-lg border-l-4 border-red-400">
                                                <div class="flex items-start gap-3">
                                                    <i class="fas fa-exclamation-triangle text-red-500 mt-1"></i>
                                                    <div class="flex-1"> 
                                                        <p class="text-gray-700 mt-2 leading-relaxed"><?php echo nl2br(htmlspecialchars($equipo['observaciones_falla_equipo'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="text-gray-400 mb-3">
                                    <i class="fas fa-laptop text-5xl"></i>
                                </div>
                                <p class="text-gray-500">No hay equipos registrados para esta orden.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Información de Pago -->
            <?php if ($orden['abono_inicial'] > 0): ?>
            <div class="modern-card mb-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-primary">Información de Pago</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-green-600 mb-2">
                                $<?php echo number_format($orden['abono_inicial'], 2); ?>
                            </div>
                            <div class="text-sm text-gray-600">Abono Inicial</div>
                        </div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <div class="text-center">
                            <div class="text-lg font-semibold text-blue-600 mb-2">
                                <?php echo htmlspecialchars($orden['estado_pago'] ?? 'Pendiente'); ?>
                            </div>
                            <div class="text-sm text-gray-600">Estado de Pago</div>
                        </div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                        <div class="text-center">
                            <div class="text-sm text-purple-600 mb-2">
                                <?php echo date('d/m/Y H:i', strtotime($orden['fecha_abono'])); ?>
                            </div>
                            <div class="text-sm text-gray-600">Fecha de Abono</div>
                            <?php if (!empty($orden['nombre_usuario_abono'])): ?>
                            <div class="text-xs text-gray-500 mt-1">
                                Por: <?php echo htmlspecialchars($orden['nombre_usuario_abono']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Información adicional del abono -->
                <?php
                // Obtener información del abono desde la tabla abonos_orden
                $stmt = $conn->prepare("
                    SELECT metodo_pago, observaciones 
                    FROM abonos_orden 
                    WHERE id_orden = ? AND tipo_abono = 'Inicial' 
                    ORDER BY fecha_registro DESC 
                    LIMIT 1
                ");
                $stmt->execute([$id_orden]);
                $abono_info = $stmt->fetch();
                ?>
                
                <?php if ($abono_info): ?>
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center gap-3 mb-2">
                            <i class="fas fa-credit-card text-gray-600"></i>
                            <span class="font-medium text-gray-800">Método de Pago</span>
                        </div>
                        <div class="text-gray-900"><?php echo htmlspecialchars($abono_info['metodo_pago']); ?></div>
                    </div>
                    <?php if (!empty($abono_info['observaciones'])): ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center gap-3 mb-2">
                            <i class="fas fa-comment text-gray-600"></i>
                            <span class="font-medium text-gray-800">Observaciones</span>
                        </div>
                        <div class="text-gray-900"><?php echo nl2br(htmlspecialchars($abono_info['observaciones'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Detalles de la Orden -->
            <div class="modern-card mb-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-clipboard-list text-purple-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-primary">Detalles de la Orden</h2>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="space-y-6">
                        <div class="bg-purple-50 rounded-lg p-4">
                            <h3 class="font-semibold text-purple-800 mb-4 flex items-center gap-2">
                                <i class="fas fa-info-circle"></i>
                                Información General
                            </h3>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-tag text-purple-600 w-5"></i>
                                        <span class="text-sm text-gray-500">Estado</span>
                                    </div>
                                    <span class="status-badge <?php echo $orden['estado'] == 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                        <i class="fas <?php echo $orden['estado'] == 'Pendiente' ? 'fa-clock' : 'fa-check-circle'; ?>"></i>
                                        <?php echo htmlspecialchars($orden['estado']); ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-building text-purple-600 w-5"></i>
                                    <div>
                                        <span class="text-sm text-gray-500">Sucursal</span>
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($orden['nombre_sucursal']); ?></div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-user-edit text-purple-600 w-5"></i>
                                    <div>
                                        <span class="text-sm text-gray-500">Registrado por</span>
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($orden['usuario_registro']); ?></div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <i class="fas fa-user-cog text-purple-600 w-5"></i>
                                    <div>
                                        <span class="text-sm text-gray-500">Técnico responsable</span>
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($orden['nombre_tecnico_responsable'] ?? 'Por asignar'); ?></div>
                                    </div>
                                </div>
                                <?php if (isset($orden['estado_pago'])): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-dollar-sign text-purple-600 w-5"></i>
                                        <span class="text-sm text-gray-500">Estado de Pago</span>
                                    </div>
                                    <span class="status-badge <?php 
                                        if ($orden['estado_pago'] == 'Pendiente') {
                                            echo 'bg-yellow-100 text-yellow-800';
                                        } elseif ($orden['estado_pago'] == 'Abonado') {
                                            echo 'bg-blue-100 text-blue-800';
                                        } elseif ($orden['estado_pago'] == 'Pagado') {
                                            echo 'bg-green-100 text-green-800';
                                        } else {
                                            echo 'bg-red-100 text-red-800';
                                        }
                                    ?>">
                                        <i class="fas <?php 
                                            if ($orden['estado_pago'] == 'Pendiente') {
                                                echo 'fa-clock';
                                            } elseif ($orden['estado_pago'] == 'Abonado') {
                                                echo 'fa-dollar-sign';
                                            } elseif ($orden['estado_pago'] == 'Pagado') {
                                                echo 'fa-check-circle';
                                            } else {
                                                echo 'fa-times-circle';
                                            }
                                        ?>"></i>
                                        <?php echo htmlspecialchars($orden['estado_pago'] ?? 'Pendiente'); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <h3 class="font-semibold text-purple-800 flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            Descripción del Problema
                        </h3>
                        <div class="bg-gray-50 rounded-lg p-6 border-l-4 border-purple-400">
                            <div class="prose max-w-none">
                                <?php echo nl2br(htmlspecialchars($orden['descripcion_problema'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Historial de Seguimientos -->
            <div class="modern-card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-indigo-100 p-3 rounded-full">
                        <i class="fas fa-history text-indigo-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-primary">Historial de Seguimientos</h2>
                </div>

                <?php if (empty($seguimientos)): ?>
                    <div class="text-center py-12">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-clipboard-list text-6xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-600 mb-2">No hay seguimientos registrados</h3>
                        <p class="text-gray-500">Esta orden aún no tiene seguimientos registrados.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($seguimientos as $index => $seguimiento): ?>
                            <div class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-300">
                                <div class="p-6">
                                    <div class="flex flex-col lg:flex-row justify-between items-start gap-4 mb-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <span class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-medium">
                                                    <?php echo htmlspecialchars($seguimiento['tipo_servicio']); ?>
                                                </span>
                                                <span class="text-sm text-gray-500">
                                                    #<?php echo count($seguimientos) - $index; ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-4 text-sm text-gray-600">
                                                <div class="flex items-center gap-2">
                                                    <i class="fas fa-user-cog text-indigo-500"></i>
                                                    <span><?php echo htmlspecialchars($seguimiento['tecnico']); ?></span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <i class="fas fa-calendar text-indigo-500"></i>
                                                    <span><?php echo date('d/m/Y H:i', strtotime($seguimiento['fecha_registro'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-end gap-3">
                                            <div class="text-right">
                                                <div class="text-2xl font-bold text-green-600">
                                                    $<?php echo number_format($seguimiento['valor_cobrar'], 2); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">Valor del servicio</div>
                                            </div>
                                            <a href="imprimir_seguimiento.php?id=<?php echo $seguimiento['id_seguimiento']; ?>"
                                                class="btn bg-gray-600 hover:bg-gray-700 text-white text-sm px-4 py-2 rounded-lg transition-colors duration-200">
                                                <i class="fas fa-print mr-2"></i> Imprimir
                                            </a>
                                        </div>
                                    </div>
                                    <?php if (!empty($seguimiento['procedimiento'])): ?>
                                        <div class="bg-gray-50 rounded-lg p-4 border-l-4 border-indigo-400">
                                            <div class="flex items-start gap-3">
                                                <i class="fas fa-clipboard-check text-indigo-500 mt-1"></i>
                                                <div class="flex-1">
                                                    <h4 class="font-medium text-indigo-800 mb-2">Procedimiento realizado:</h4>
                                                    <div class="text-gray-700 leading-relaxed">
                                                        <?php echo nl2br(htmlspecialchars($seguimiento['procedimiento'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Galería de Imágenes -->
            <?php
            $stmt = $conn->prepare("SELECT * FROM orden_imagenes WHERE id_orden = ? ORDER BY fecha_registro DESC");
            $stmt->execute([$_GET['id']]);
            $imagenes = $stmt->fetchAll();

            if ($imagenes): ?>
                <div class="modern-card">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="bg-orange-100 p-3 rounded-full">
                            <i class="fas fa-images text-orange-600 text-xl"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-primary">Evidencias Fotográficas</h2>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <?php foreach ($imagenes as $imagen): ?>
                            <div class="group relative overflow-hidden rounded-lg shadow-md hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                                <img
                                    src="../../<?php echo htmlspecialchars($imagen['ruta_archivo']); ?>"
                                    alt="Evidencia de trabajo"
                                    class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-110 cursor-pointer"
                                    onclick="window.open(this.src, '_blank')">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <div class="absolute bottom-0 left-0 right-0 p-4 text-white">
                                        <div class="flex items-center justify-between">
                                            <div class="text-sm">
                                                <i class="fas fa-calendar mr-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($imagen['fecha_registro'])); ?>
                                            </div>
                                            <div class="text-sm">
                                                <i class="fas fa-expand mr-1"></i>
                                                Ampliar
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Haz clic en cualquier imagen para ampliarla
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Contenido del ticket para impresión -->
    <div id="imprimir_ticket" class="hidden">
        <div class="print-only" style="width: 210mm; height: 148.5mm; padding: 20mm; margin: 0 auto; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
            <!-- Encabezado con logo, QR y datos de empresa -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #000080; padding-bottom: 15px;">
                <div style="width: 30%;">
                    <img src="../../assets/img/logo.png" alt="RGE" style="max-width: 120px; height: auto;">
                    <div style="font-size: 12px; color: #000080; margin-top: 5px;"><?php echo htmlspecialchars($slogan) ?></div>
                </div>
                <div style="width: 40%; text-align: center;">
                    <?php
                    $url_seguimiento = "http://{$_SERVER['HTTP_HOST']}/gestion/modules/ordenes/seguimiento_publico.php?orden=" . urlencode($orden['codigo']);
                    ?>
                    <div id="qrcode" style="display: flex; justify-content: center; margin-bottom: 5px;"></div>
                    <div style="font-size: 9px; color: #000080;">Escanee para seguimiento</div>
                    <script>
                        var qr = qrcode(0, 'M');
                        qr.addData('<?php echo $url_seguimiento; ?>');
                        qr.make();
                        document.getElementById('qrcode').innerHTML = qr.createImgTag(3);
                    </script>
                </div>
                <div style="width: 30%; text-align: right; font-size: 11px;">
                    <?php foreach ($sucursales as $sucursal): ?>
                        <div style="margin-bottom: 3px;">
                            <strong><?php echo htmlspecialchars($sucursal['nombre']); ?></strong><br>
                            <?php echo htmlspecialchars($sucursal['direccion']); ?><br>
                            Tel: <?php echo htmlspecialchars($sucursal['telefono']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Información de la orden y fecha -->
            <div style="background: #000080; color: white; padding: 10px; margin-bottom: 15px; border-radius: 5px; display: flex; justify-content: space-between;">
                <div style="font-size: 16px; font-weight: bold;">ORDEN DE SERVICIO #<?php echo htmlspecialchars($orden['codigo']); ?></div>
                <div>
                    <?php
                    $fecha = new DateTime($orden['fecha_ingreso']);
                    echo "Fecha: " . $fecha->format('d/m/Y');
                    ?>
                </div>
            </div>

            <!-- Información del cliente -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #dee2e6;">
                <div style="font-size: 14px; font-weight: bold; color: #000080; margin-bottom: 10px;">INFORMACIÓN DEL CLIENTE</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px;">
                    <div>
                        <strong>Cliente:</strong> <?php echo htmlspecialchars($orden['cliente']); ?><br>
                        <strong>RUC/CI:</strong> <?php echo htmlspecialchars($orden['identificacion'] ?? ''); ?><br>
                        <strong>Empresa:</strong> <?php echo htmlspecialchars($orden['empresa'] ?? ''); ?>
                    </div>
                    <div>
                        <strong>Teléfono:</strong> <?php echo htmlspecialchars($orden['cliente_telefono'] ?? ''); ?><br>
                        <strong>Registrado por:</strong> <?php echo htmlspecialchars($orden['usuario_registro'] ?? ''); ?><br>
                        <strong>Ciudad:</strong> GUAYAQUIL
                    </div>
                </div>
            </div>

            <!-- Información de Valor Estimado (si está disponible) -->
            <?php //if ($orden['valor_estimado'] > 0): ?>
            <!-- <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #2196f3;">
                <div style="font-size: 14px; font-weight: bold; color: #2196f3; margin-bottom: 10px;">INFORMACIÓN DE VALOR ESTIMADO</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px;">
                    <div>
                        <strong>Valor Estimado:</strong> $<?php //echo number_format($orden['valor_estimado'], 2); ?><br>
                        <strong>Estado de la Orden:</strong> <?php //echo htmlspecialchars($orden['estado']); ?><br>
                        <strong>Fecha de Registro:</strong> <?php //echo date('d/m/Y H:i', strtotime($orden['fecha_ingreso'])); ?>
                    </div>
                    <div>
                        <strong>Tipo de Valor:</strong> 
                        <?php //if ($orden['estado'] == 'Entregado'): ?>
                            <span style="color: #4caf50;">Valor Final</span>
                        <?php //else: ?>
                            <span style="color: #ff9800;">Valor Estimado</span>
                        <?php //endif; ?>
                        <br>
                        <strong>Registrado por:</strong> <?php //echo htmlspecialchars($orden['usuario_registro'] ?? 'N/A'); ?><br>
                        <strong>Nota:</strong> 
                        <?php //if ($orden['estado'] == 'Entregado'): ?>
                            El valor final se calcula según los servicios realizados
                        <?php //else: ?>
                            Este es un valor estimado, puede variar según los servicios necesarios
                        <?php //endif; ?>
                    </div>
                </div>
            </div> -->
            <?php //endif; ?>

            <!-- Tabla de equipos -->
            <div style="margin-bottom: 15px;">
            <div class="prose max-w-none" style="margin-bottom: 15px; font-size: 9px;">
                <b> Observacion general de la orden: </b> <?php echo nl2br(htmlspecialchars($orden['descripcion_problema'])); ?>
            </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <tr style="background: #000080; color: white;">
                        <th style="padding: 8px; text-align: left;">SERIE N.</th>
                        <th style="padding: 8px; text-align: left;">DESCRIPCIÓN</th>
                    </tr>
                    <?php
                    $stmt = $conn->prepare("SELECT e.marca, e.modelo, e.numero_serial, oe.observaciones_falla_equipo 
                                      FROM orden_equipos oe 
                                      JOIN equipos e ON e.id_equipo = oe.id_equipo 
                                      WHERE oe.id_orden = ?");
                    $stmt->execute([$_GET['id']]);
                    $equipos_ticket = $stmt->fetchAll();

                    foreach ($equipos_ticket as $equipo): ?>
                        <tr style="border-bottom: 1px solid #dee2e6;">
                            <td style="padding: 8px;"><?php echo htmlspecialchars($equipo['numero_serial']); ?></td>
                            <td style="padding: 8px;">
                                <strong><?php echo htmlspecialchars($equipo['marca'] . ' ' . $equipo['modelo']); ?></strong><br>
                                <?php echo nl2br(htmlspecialchars($equipo['observaciones_falla_equipo'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- Notas -->
            <div style="margin-bottom: 15px; font-size: 9px;">
                <div style="margin-bottom: 10px;">
                    <strong>NOTA:</strong><br>
                    <?php echo htmlspecialchars($leyenda1) ?>
                </div>
                <div  style="margin-bottom: 10px;">
                    <?php echo htmlspecialchars($leyenda2) ?>
                </div>
            </div>

            <!-- Firmas y Totales -->
            <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                <div style="width: 60%; display: flex; justify-content: space-between;">
                    <div style="text-align: center; width: 45%;">
                        <div style="border-top: 1px solid #000; padding-top: 5px; font-size: 11px;">
                            <?php echo htmlspecialchars($nombre_empresa) ?><br>
                            <span style="font-size: 9px;">Firma Autorizada</span>
                        </div>
                    </div>
                    <div style="text-align: center; width: 45%;">
                        <div style="border-top: 1px solid #000; padding-top: 5px; font-size: 11px;">
                            <?php echo htmlspecialchars($orden['cliente']); ?><br>
                            <span style="font-size: 9px;">Firma en fotal conformidad</span>
                        </div>
                    </div>
                </div>
                <div style="width: 35%; text-align: right; font-size: 11px;">
                    <?php
                    $subtotal = 0;
                    $iva = 0;
                    $abono_inicial = $orden['abono_inicial'] ?? 0;
                    $valor_estimado = $orden['valor_estimado'] ?? 0;
                    
                    // Calcular total de servicios (solo si la orden está entregada)
                    if ($orden['estado'] == 'Entregado') {
                        $stmt = $conn->prepare("SELECT SUM(valor_cobrar) as total FROM seguimientos_orden WHERE id_orden = ? AND tipo_servicio != 'Abono Inicial'");
                        $stmt->execute([$_GET['id']]);
                        $resultado = $stmt->fetch();
                        $subtotal = $resultado['total'] ?? 0;
                        $iva = $subtotal * $ivaEmpresa;
                    }
                    
                    $total_servicios = $subtotal + $iva;
                    
                    // Usar valor estimado si está disponible y la orden no está entregada
                    if ($valor_estimado > 0 && $orden['estado'] != 'Entregado') {
                        $total_estimado = $valor_estimado;
                        $saldo_pendiente = $total_estimado - $abono_inicial;
                    } else {
                        $total_estimado = $total_servicios;
                        $saldo_pendiente = $total_servicios - $abono_inicial;
                    }
                    ?>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; border: 1px solid #dee2e6;">
                        <?php if ($valor_estimado > 0 && $orden['estado'] != 'Entregado'): ?>
                        <!-- Mostrar valor estimado cuando la orden no está entregada -->
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px; color: #007bff; font-weight: bold;">
                            <span>VALOR ESTIMADO:</span>
                            <span>$ <?php echo number_format($valor_estimado, 2); ?></span>
                        </div>
                        <?php else: ?>
                        <!-- Mostrar desglose de servicios cuando la orden está entregada -->
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>SUB-TOTAL:</span>
                            <span>$ <?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>I.V.A. 0%:</span>
                            <span>$ 0.00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>I.V.A. <?php echo $ivaEmpresa ?>%:</span>
                            <span>$ <?php echo number_format($iva, 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-weight: bold; border-top: 1px solid #dee2e6; padding-top: 5px; margin-bottom: 5px;">
                            <span>TOTAL SERVICIOS:</span>
                            <span>$ <?php echo number_format($total_servicios, 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($abono_inicial > 0): ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px; color: #28a745; font-weight: bold;">
                            <span>ABONO INICIAL:</span>
                            <span>-$ <?php echo number_format($abono_inicial, 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-weight: bold; border-top: 1px solid #dee2e6; padding-top: 5px; color: #dc3545;">
                            <span>SALDO PENDIENTE:</span>
                            <span>$ <?php echo number_format($saldo_pendiente, 2); ?></span>
                        </div>
                        <?php else: ?>
                        <div style="display: flex; justify-content: space-between; font-weight: bold; border-top: 1px solid #dee2e6; padding-top: 5px;">
                            <span>TOTAL A PAGAR:</span>
                            <span>$ <?php echo number_format($total_estimado, 2); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>