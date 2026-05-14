<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// Obtener datos de la orden
$stmt = $conn->prepare("
    SELECT o.*, 
           c.nombre_apellido as cliente, c.email as cliente_email, c.telefono as cliente_telefono,
           GROUP_CONCAT(
               CONCAT('Marca: ',e.marca, 
               ' Modelo: ', e.modelo,
              '<br>'  '<b> Falla:</b> ', oe.observaciones_falla_equipo  )
               SEPARATOR '<br>'
           ) as equipos,
           u.nombre_completo as usuario_registro,
           c.identificacion as identificacion,
           c.empresa as empresa,
           s.nombre as nombre_sucursal,
           s.direccion as direccion_sucursal,
           s.telefono as telefono_sucursal,
           (SELECT us.nombre_completo FROM usuarios us WHERE us.id_usuario = o.tecnico_responsable_id ) AS nombre_tecnico_responsable
    FROM ordenes_trabajo o
    JOIN clientes c ON o.id_cliente = c.id_cliente
    JOIN orden_equipos oe ON o.id_orden = oe.id_orden
    JOIN equipos e ON oe.id_equipo = e.id_equipo
    JOIN usuarios u ON o.id_usuario_registro = u.id_usuario
    JOIN sucursales s ON o.id_sucursal = s.id_sucursal
    WHERE o.id_orden = ?
    GROUP BY o.id_orden
");
$stmt->execute([$_GET['id']]);
$orden = $stmt->fetch();

if (!$orden) {
    header("Location: lista.php");
    exit();
}

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
                                leyenda1 FROM empresa ORDER BY nombre_empresa ASC");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
                width: 100%;
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
                border-color: #2d6b2b;
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
            --primary-color: #2d6b2b;
            --secondary-color: #1a237e;
            --accent-color: #304ffe;
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
                                <span class="status-badge <?php echo $orden['estado'] == 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                    <i class="fas <?php echo $orden['estado'] == 'Pendiente' ? 'fa-clock' : 'fa-check-circle'; ?>"></i>
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
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-id-card text-gray-400"></i>
                            <span class="font-medium">Nombre:</span>
                            <span><?php echo htmlspecialchars($orden['cliente']); ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-envelope text-gray-400"></i>
                            <span class="font-medium">Email:</span>
                            <span><?php echo htmlspecialchars($orden['cliente_email']); ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-phone text-gray-400"></i>
                            <span class="font-medium">Teléfono:</span>
                            <span><?php echo htmlspecialchars($orden['cliente_telefono']); ?></span>
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
                    <div class="space-y-3">
                        <?php if (!empty($orden['equipos'])): ?>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <?php echo nl2br($orden['equipos']); ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 italic">No hay equipos registrados para esta orden.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Detalles de la Orden -->
            <div class="modern-card mb-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-clipboard-list text-purple-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-primary">Detalles de la Orden</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="font-medium">Estado:</span>
                            <span class="status-badge <?php echo $orden['estado'] == 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                <i class="fas <?php echo $orden['estado'] == 'Pendiente' ? 'fa-clock' : 'fa-check-circle'; ?>"></i>
                                <?php echo htmlspecialchars($orden['estado']); ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-building text-gray-400"></i>
                            <span class="font-medium">Sucursal:</span>
                            <span><?php echo htmlspecialchars($orden['nombre_sucursal']); ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-user-edit text-gray-400"></i>
                            <span class="font-medium">Registrado por:</span>
                            <span><?php echo htmlspecialchars($orden['usuario_registro']); ?></span>
                        </div>


                        <div class="flex items-center gap-2">
                            <i class="fas fa-user-edit text-gray-400"></i>
                            <span class="font-medium">Tecnico responsable:</span>
                            <span><?php echo htmlspecialchars($orden['nombre_tecnico_responsable']); ?></span>
                        </div>

                    </div>
                    <div>
                        <h3 class="font-medium mb-2">Descripción del Problema:</h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <?php echo nl2br(htmlspecialchars($orden['descripcion_problema'])); ?>
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
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-3">
                            <i class="fas fa-clipboard-list text-5xl"></i>
                        </div>
                        <p class="text-gray-500">No hay seguimientos registrados para esta orden.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($seguimientos as $seguimiento): ?>
                            <div class="timeline-item border-primary">
                                <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                                    <div>
                                        <h3 class="font-semibold text-lg">
                                            <?php echo htmlspecialchars($seguimiento['tipo_servicio']); ?>
                                        </h3>
                                        <p class="text-sm text-gray-500 flex items-center gap-2">
                                            <i class="fas fa-user-cog"></i>
                                            <?php echo htmlspecialchars($seguimiento['tecnico']); ?> -
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($seguimiento['fecha_registro'])); ?>
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end">
                                        <p class="font-semibold text-green-600">
                                            $<?php echo number_format($seguimiento['valor_cobrar'], 2); ?>
                                        </p>
                                        <a href="imprimir_seguimiento.php?id=<?php echo $seguimiento['id_seguimiento']; ?>"
                                            class="btn bg-gray-600 hover:bg-gray-700 text-white w-full md:w-auto">

                                            <i class="fas fa-print"></i> Imprimir seguimiento
                                        </a>
                                    </div>
                                </div>
                                <div class="mt-3 bg-gray-50 rounded-lg p-4">
                                    <?php
                                    if (!empty($seguimientos)) {
                                        echo nl2br(htmlspecialchars($seguimientos[0]['procedimiento']));
                                    }
                                    ?>
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
                <div class="border-t pt-4">
                    <h3 class="font-semibold text-lg mb-4">Evidencias Fotográficas de la orden</h3>
                    <div class="grid grid-cols-4 md:grid-cols-3 gap-4">
                        <?php foreach ($imagenes as $imagen): ?>
                            <div class="relative group">
                                <img
                                    src="../../<?php echo htmlspecialchars($imagen['ruta_archivo']); ?>"
                                    alt="Evidencia de trabajo"
                                    class="w-full h-48 object-cover rounded-lg shadow-md transition-transform duration-300 group-hover:scale-105"
                                    onclick="window.open(this.src, '_blank')">
                                <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white p-2 text-sm rounded-b-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <?php echo date('d/m/Y H:i', strtotime($imagen['fecha_registro'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Contenido del ticket para impresión -->
    <div id="imprimir_ticket" class="hidden">
        <!-- Vista de impresión (solo se muestra al imprimi r) -->
        <div class="print-only">
            <div class="ticket border p-4 mb-6">
                <div class="text-center">
                    <div class="header-info">
                        <?php foreach ($sucursales as $sucursal): ?>
                            - <?php echo htmlspecialchars($sucursal['nombre']); ?>: <?php echo htmlspecialchars($sucursal['direccion']); ?>
                            Teléfono: <?php echo htmlspecialchars($sucursal['telefono']); ?> <br>
                        <?php endforeach; ?>
                    </div>

                    <div class="company-logo" style="margin: 10px 0;  margin-left: 25%;">
                        <img src="../../assets/img/logo.png" alt="CS" style="max-width: 150px;"><br>
                        <?php echo htmlspecialchars($slogan) ?>
                    </div>
                </div>

                <div style="margin-top: 20px;font-size: 10px;">
                    <div style="display: flex; justify-content: space-between;">
                        <div>CIUDAD: GUAYAQUIL</div>
                        <div>
                            <?php
                            $fecha = new DateTime($orden['fecha_ingreso']);
                            echo "" . $fecha->format('d') . " / " . $fecha->format('m') . " / " . $fecha->format('Y');
                            ?>
                        </div>
                    </div>
                </div>
                <!-- información del cliente y orden -->
                <div style="margin-top: 12px;">
                    <table class="equipment-table" style="margin-top: 10px;font-size: 9px;">
                        <tr>
                            <th class="border p-1"> </th>
                            <th class="border p-1"></th>
                        </tr>
                        <tr style="margin-top: 10px;">
                            <td class="border p-1">
                                <div style="margin-top: 10px;">
                                    <div>CLIENTE: <?php echo htmlspecialchars($orden['cliente']); ?></div>
                                    <div>RUC/CI: <?php echo htmlspecialchars($orden['identificacion'] ?? ''); ?></div>
                                    <div>EMPRESA: <?php echo htmlspecialchars($orden['empresa'] ?? ''); ?></div>
                                    <div>TELF: <?php echo htmlspecialchars($orden['cliente_telefono'] ?? ''); ?></div>
                                    <div>CODIGO: <?php echo htmlspecialchars($orden['codigo'] ?? ''); ?></div>
                                    <div>Registrado por: <?php echo htmlspecialchars($orden['usuario_registro'] ?? ''); ?></div>
                                </div>
                            </td>
                            <td class="border p-1">
                                <div style="margin-top: 10px;">

                                    <!-- Dentro del div del ticket, antes del div de totales -->
                                    <div style="text-align: center; margin: 9px 0;">
                                        <?php
                                        $url_seguimiento = "http://{$_SERVER['HTTP_HOST']}/gestionequipos-rgb/modules/ordenes/seguimiento_publico.php?orden=" . urlencode($orden['codigo']);
                                        ?>
                                        <div id="qrcode" style="display: inline-block;"></div>
                                        <script>
                                            var qr = qrcode(0, 'M');
                                            qr.addData('<?php echo $url_seguimiento; ?>');
                                            qr.make();
                                            document.getElementById('qrcode').innerHTML = qr.createImgTag(4);
                                        </script>
                                        <div style="font-size: 8px; margin-top: 5px;">Escanee para seguimiento</div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="margin-top: 12px;">
                    <table class="equipment-table" style="margin-top: 10px;font-size: 9px;">
                        <tr>
                            <th class="border p-1">SERIE N.</th>
                            <th class="border p-1">DESCRIPCIÓN</th>
                        </tr>
                        <?php
                        $stmt = $conn->prepare("
                            SELECT 
                                e.marca,
                                e.modelo,
                                e.numero_serial,
                                oe.observaciones_falla_equipo
                            FROM orden_equipos oe
                            JOIN equipos e ON e.id_equipo = oe.id_equipo
                            WHERE oe.id_orden = ?
                        ");
                        $stmt->execute([$_GET['id']]);
                        $equipos_ticket = $stmt->fetchAll();

                        foreach ($equipos_ticket as $equipo): ?>
                            <tr style="margin-top: 10px;font-size: 10px;">
                                <td class="border p-1"><?php echo htmlspecialchars($equipo['numero_serial']); ?></td>
                                <td class="border p-1">
                                    <?php
                                    echo htmlspecialchars($equipo['marca'] . ' ' . $equipo['modelo']) . ', ';
                                    echo nl2br(htmlspecialchars('Falla: ' . $equipo['observaciones_falla_equipo']));
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div style="margin-top: 20px; font-size: 7px;">
                    <p>NOTA: <?php echo htmlspecialchars($leyenda1) ?></p>
                </div>

                <div style="margin-top: 20px; font-size: 7px;">
                    <p>NOTA: <?php echo htmlspecialchars($leyenda2) ?></p>
                </div>
                <div style="margin-top: 30px; display: flex; justify-content: space-between;font-size: 10px;">
                    <div style="text-align: center; width: 45%;">
                        ______________________<br><?php echo htmlspecialchars($nombre_empresa) ?>
                    </div>
                    <div style="text-align: center; width: 45%;font-size: 10px;">
                        ______________________<br> <?php echo htmlspecialchars($orden['cliente']); ?>
                    </div>
                </div>

            </div>
            <div style="margin-top: 20px; text-align: right;font-size: 10px;">
                <?php
                // Calcular el total basado en seguimientos si la orden está entregada
                $subtotal = 0;
                $iva = 0;
                if ($orden['estado'] == 'Entregado') {
                    $stmt = $conn->prepare("SELECT SUM(valor_cobrar) as total FROM seguimientos_orden WHERE id_orden = ?");
                    $stmt->execute([$_GET['id']]);
                    $resultado = $stmt->fetch();
                    $subtotal = $resultado['total'] ?? 0;
                    $iva = $subtotal *  $ivaEmpresa; // 12% IVA
                }
                ?>
                <div style="margin-top: 20px; text-align: right;">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-medium">SUB-TOTAL:</span>
                        <span>$ <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-medium">I.V.A. 0%:</span>
                        <span>$ 0.00</span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-medium">I.V.A. <?php echo $ivaEmpresa ?>%:</span>
                        <span>$ <?php echo number_format($iva, 2); ?></span>
                    </div>
                    <div class="flex justify-between items-center font-bold">
                        <span>TOTAL US $:</span>
                        <span>$ <?php echo number_format($subtotal + $iva, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Fin del Contenido del Ticket -->
    </div>
    </div>
</body>

</html>