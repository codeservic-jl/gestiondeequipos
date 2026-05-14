<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";
$id_orden = $_GET['id'];

$stmt = $conn->prepare("
    SELECT o.*,
           c.nombre_apellido as cliente, c.email as cliente_email, c.telefono as cliente_telefono,
           c.identificacion, c.empresa,
           u.nombre_completo as usuario_registro,
           s.nombre as nombre_sucursal, s.direccion as direccion_sucursal, s.telefono as telefono_sucursal,
           (SELECT us.nombre_completo FROM usuarios us WHERE us.id_usuario = o.tecnico_responsable_id) AS nombre_tecnico_responsable,
           (SELECT us.nombre_completo FROM usuarios us WHERE us.id_usuario = o.usuario_abono) AS nombre_usuario_abono
    FROM ordenes_trabajo o
    JOIN clientes c ON o.id_cliente = c.id_cliente
    JOIN usuarios u ON o.id_usuario_registro = u.id_usuario
    JOIN sucursales s ON o.id_sucursal = s.id_sucursal
    WHERE o.id_orden = ?
");
$stmt->execute([$id_orden]);
$orden = $stmt->fetch();

if (!$orden) { header("Location: lista.php"); exit(); }

// Equipos
$stmt = $conn->prepare("
    SELECT e.marca, e.modelo, e.numero_serial, oe.observaciones_falla_equipo
    FROM orden_equipos oe JOIN equipos e ON oe.id_equipo = e.id_equipo
    WHERE oe.id_orden = ? ORDER BY e.id_equipo
");
$stmt->execute([$id_orden]);
$equipos = $stmt->fetchAll();
if (empty($equipos)) {
    $stmt = $conn->prepare("
        SELECT e.marca, e.modelo, e.numero_serial, '' as observaciones_falla_equipo
        FROM ordenes_trabajo o JOIN equipos e ON o.id_equipo = e.id_equipo WHERE o.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $eq = $stmt->fetch();
    if ($eq) $equipos = [$eq];
}

// Seguimientos con ventas y colega
$stmt = $conn->prepare("
    SELECT s.*, u.nombre_completo as tecnico,
           v.producto as venta_producto, v.valor_compra as venta_compra,
           v.valor_venta as venta_precio, v.ganancia_neta as venta_ganancia
    FROM seguimientos_orden s
    JOIN usuarios u ON s.id_tecnico = u.id_usuario
    LEFT JOIN ventas_orden v ON v.id_seguimiento = s.id_seguimiento
    WHERE s.id_orden = ?
    ORDER BY s.fecha_registro DESC
");
$stmt->execute([$id_orden]);
$seguimientos = $stmt->fetchAll();

// Totales financieros
$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(so.valor_cobrar), 0)    as total_cobrado,
        COALESCE(SUM(so.costo_externo), 0)   as total_colegas,
        COALESCE(SUM(v.valor_venta), 0)      as total_ventas,
        COALESCE(SUM(v.ganancia_neta), 0)    as total_ganancia_ventas
    FROM seguimientos_orden so
    LEFT JOIN ventas_orden v ON v.id_seguimiento = so.id_seguimiento
    WHERE so.id_orden = ?
");
$stmt->execute([$id_orden]);
$totales = $stmt->fetch();
$ganancia_neta = ($totales['total_cobrado'] - $totales['total_colegas']) + $totales['total_ganancia_ventas'];

// Sucursales y empresa (para ticket impresión)
$sucursales = $conn->query("SELECT * FROM sucursales WHERE estado = 1")->fetchAll();
$stmtEmp = $conn->prepare("SELECT iva, nombre_empresa, slogan, leyenda1, leyenda2 FROM empresa ORDER BY nombre_empresa ASC LIMIT 1");
$stmtEmp->execute();
$datosEmpresa = $stmtEmp->fetch();
$ivaEmpresa   = $datosEmpresa['iva'] ?? 0;
$nombre_empresa = $datosEmpresa['nombre_empresa'] ?? '';
$slogan       = $datosEmpresa['slogan'] ?? '';
$leyenda1     = $datosEmpresa['leyenda1'] ?? '';
$leyenda2     = $datosEmpresa['leyenda2'] ?? '';

// Abono
$stmtAbono = $conn->prepare("SELECT metodo_pago, observaciones FROM abonos_orden WHERE id_orden = ? AND tipo_abono = 'Inicial' ORDER BY fecha_registro DESC LIMIT 1");
$stmtAbono->execute([$id_orden]);
$abono_info = $stmtAbono->fetch();

// Imágenes
$stmtImg = $conn->prepare("SELECT * FROM orden_imagenes WHERE id_orden = ? ORDER BY fecha_registro DESC");
$stmtImg->execute([$id_orden]);
$imagenes = $stmtImg->fetchAll();

// Estado → color
$estadoConfig = [
    'Pendiente'  => ['bg' => '#fef3c7', 'text' => '#92400e', 'dot' => '#f59e0b', 'icon' => 'fa-clock'],
    'En Proceso' => ['bg' => '#dbeafe', 'text' => '#1e40af', 'dot' => '#3b82f6', 'icon' => 'fa-spinner'],
    'Completado' => ['bg' => '#d1fae5', 'text' => '#065f46', 'dot' => '#10b981', 'icon' => 'fa-check-circle'],
    'Entregado'  => ['bg' => '#ede9fe', 'text' => '#4c1d95', 'dot' => '#8b5cf6', 'icon' => 'fa-box'],
    'Cancelado'  => ['bg' => '#fee2e2', 'text' => '#991b1b', 'dot' => '#ef4444', 'icon' => 'fa-ban'],
    'Anulada'    => ['bg' => '#fee2e2', 'text' => '#991b1b', 'dot' => '#ef4444', 'icon' => 'fa-ban'],
];
$sc = $estadoConfig[$orden['estado']] ?? ['bg' => '#f3f4f6', 'text' => '#374151', 'dot' => '#6b7280', 'icon' => 'fa-circle'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Orden <?php echo htmlspecialchars($orden['codigo']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <style>
        body { background: #f1f5f9; font-family: 'Segoe UI', system-ui, sans-serif; }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 8px rgba(0,0,0,.07);
            overflow: hidden;
        }
        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 700;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .card-header i { color: #16a34a; font-size: 15px; }
        .card-body { padding: 20px 24px; }

        .info-row { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 10px; font-size: 14px; }
        .info-row .lbl { color: #64748b; min-width: 100px; flex-shrink: 0; }
        .info-row .val { font-weight: 600; color: #1e293b; }

        /* Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600;
        }

        /* Timeline seguimientos */
        .seg-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            transition: box-shadow .2s;
        }
        .seg-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        .seg-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            gap: 12px;
            flex-wrap: wrap;
        }
        .seg-body { padding: 16px 20px; }

        /* Summary cards */
        .sum-card {
            border-radius: 14px;
            padding: 20px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .sum-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }

        /* Print ticket */
        @media print {
            body * { visibility: hidden; }
            #imprimir_ticket, #imprimir_ticket * { visibility: visible; }
            #imprimir_ticket { position: absolute; left:0; top:0; width:210mm; margin:0; padding:0; }
            @page { size: A4; margin: 0; }
            .main-content { margin: 0 !important; padding: 0 !important; }
        }
        #imprimir_ticket { display: none; }
    </style>
</head>
<body>
<?php include '../../includes/navbar.php'; ?>

<div class="main-content">
<div class="container mx-auto px-4 py-8 max-w-6xl">

    <!-- ── Cabecera ── -->
    <div class="card mb-6">
        <div class="p-6 flex flex-col md:flex-row justify-between items-start gap-5">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h1 class="text-2xl font-bold text-gray-900">
                        Orden <span class="text-green-700"><?php echo htmlspecialchars($orden['codigo']); ?></span>
                    </h1>
                    <span class="badge" style="background:<?php echo $sc['bg'];?>;color:<?php echo $sc['text'];?>">
                        <span style="width:7px;height:7px;border-radius:50%;background:<?php echo $sc['dot'];?>;display:inline-block;"></span>
                        <i class="fas <?php echo $sc['icon']; ?>"></i>
                        <?php echo htmlspecialchars($orden['estado']); ?>
                    </span>
                </div>
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-500">
                    <span><i class="fas fa-calendar-alt mr-1 text-green-600"></i>
                        Ingresado el <?php echo date('d/m/Y H:i', strtotime($orden['fecha_ingreso'])); ?>
                    </span>
                    <span><i class="fas fa-user-cog mr-1 text-green-600"></i>
                        <?php echo htmlspecialchars($orden['nombre_tecnico_responsable'] ?? 'Sin técnico'); ?>
                    </span>
                    <span><i class="fas fa-building mr-1 text-green-600"></i>
                        <?php echo htmlspecialchars($orden['nombre_sucursal']); ?>
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <button onclick="window.print()"
                    class="flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-semibold text-sm transition-colors">
                    <i class="fas fa-print"></i> Imprimir Orden
                </button>
                <?php if ($orden['estado'] !== 'Anulada'): ?>
                <a href="editar.php?id=<?php echo $id_orden; ?>"
                    class="flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-semibold text-sm transition-colors">
                    <i class="fas fa-edit"></i> Editar
                </a>
                <a href="registrar_seguimiento.php?id=<?php echo $id_orden; ?>"
                    class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold text-sm transition-colors">
                    <i class="fas fa-clipboard-check"></i> Registrar Seguimiento
                </a>
                <a href="anular.php?id=<?php echo $id_orden; ?>"
                    onclick="return confirm('¿Está seguro de anular esta orden?')"
                    class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold text-sm transition-colors">
                    <i class="fas fa-ban"></i> Anular
                </a>
                <?php else: ?>
                <div class="flex items-center gap-2 px-4 py-2 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm font-semibold">
                    <i class="fas fa-ban"></i> Orden anulada — solo lectura
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Grid: Cliente + Equipo ── -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-user"></i> Cliente</div>
            <div class="card-body">
                <div class="info-row"><span class="lbl">Nombre</span><span class="val"><?php echo htmlspecialchars($orden['cliente']); ?></span></div>
                <div class="info-row"><span class="lbl">Identificación</span><span class="val"><?php echo htmlspecialchars($orden['identificacion']); ?></span></div>
                <div class="info-row"><span class="lbl">Teléfono</span><span class="val"><?php echo htmlspecialchars($orden['cliente_telefono']); ?></span></div>
                <div class="info-row"><span class="lbl">Email</span><span class="val"><?php echo htmlspecialchars($orden['cliente_email']); ?></span></div>
                <?php if (!empty($orden['empresa'])): ?>
                <div class="info-row"><span class="lbl">Empresa</span><span class="val"><?php echo htmlspecialchars($orden['empresa']); ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="fas fa-laptop"></i> Equipo(s)</div>
            <div class="card-body space-y-4">
                <?php foreach ($equipos as $i => $eq): ?>
                <div class="<?php echo count($equipos) > 1 ? 'pb-4 border-b border-gray-100 last:border-0 last:pb-0' : ''; ?>">
                    <?php if (count($equipos) > 1): ?>
                    <div class="text-xs font-bold text-green-700 uppercase mb-2">Equipo #<?php echo $i+1; ?></div>
                    <?php endif; ?>
                    <div class="info-row"><span class="lbl">Marca</span><span class="val"><?php echo htmlspecialchars($eq['marca']); ?></span></div>
                    <div class="info-row"><span class="lbl">Modelo</span><span class="val"><?php echo htmlspecialchars($eq['modelo']); ?></span></div>
                    <div class="info-row"><span class="lbl">Serial</span><span class="val font-mono text-sm"><?php echo htmlspecialchars($eq['numero_serial']); ?></span></div>
                    <?php if (!empty($eq['observaciones_falla_equipo'])): ?>
                    <div class="mt-2 bg-amber-50 border-l-3 border-amber-400 rounded p-3 text-sm text-amber-800">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <?php echo nl2br(htmlspecialchars($eq['observaciones_falla_equipo'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── Problema + Estado orden ── -->
    <div class="card mb-6">
        <div class="card-header"><i class="fas fa-clipboard-list"></i> Detalles de la Orden</div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Problema reportado</div>
                <div class="bg-gray-50 rounded-xl p-4 text-sm text-gray-700 leading-relaxed border-l-4 border-gray-300">
                    <?php echo nl2br(htmlspecialchars($orden['descripcion_problema'])); ?>
                </div>
            </div>
            <div class="space-y-3">
                <div class="info-row"><span class="lbl">Registrado por</span><span class="val"><?php echo htmlspecialchars($orden['usuario_registro']); ?></span></div>
                <div class="info-row"><span class="lbl">Técnico</span><span class="val"><?php echo htmlspecialchars($orden['nombre_tecnico_responsable'] ?? '—'); ?></span></div>
                <div class="info-row"><span class="lbl">Sucursal</span><span class="val"><?php echo htmlspecialchars($orden['nombre_sucursal']); ?></span></div>
                <?php if (!empty($orden['valor_estimado']) && $orden['valor_estimado'] > 0): ?>
                <div class="info-row"><span class="lbl">Valor estimado</span><span class="val text-green-700">$<?php echo number_format($orden['valor_estimado'],2); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($orden['abono_inicial']) && $orden['abono_inicial'] > 0): ?>
                <div class="info-row"><span class="lbl">Abono inicial</span><span class="val text-green-700">$<?php echo number_format($orden['abono_inicial'],2); ?></span></div>
                <div class="info-row">
                    <span class="lbl">Estado pago</span>
                    <span class="val">
                        <span class="badge <?php
                            if ($orden['estado_pago'] == 'Pagado') echo 'bg-green-100 text-green-800';
                            elseif ($orden['estado_pago'] == 'Abonado') echo 'bg-blue-100 text-blue-800';
                            else echo 'bg-yellow-100 text-yellow-800';
                        ?>">
                            <?php echo htmlspecialchars($orden['estado_pago'] ?? 'Pendiente'); ?>
                        </span>
                    </span>
                </div>
                <?php if ($abono_info && !empty($abono_info['metodo_pago'])): ?>
                <div class="info-row"><span class="lbl">Método pago</span><span class="val"><?php echo htmlspecialchars($abono_info['metodo_pago']); ?></span></div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Resumen financiero (solo si hay seguimientos) ── -->
    <?php if (!empty($seguimientos)): ?>
    <div class="card mb-6">
        <div class="card-header"><i class="fas fa-chart-line"></i> Resumen Financiero</div>
        <div class="card-body">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="sum-card" style="background:#f0fdf4;border:1px solid #bbf7d0">
                    <div class="sum-icon" style="background:#dcfce7;color:#16a34a"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div>
                        <div style="font-size:22px;font-weight:800;color:#166534">$<?php echo number_format($totales['total_cobrado'],2); ?></div>
                        <div style="font-size:12px;color:#4b5563">Total servicios</div>
                    </div>
                </div>
                <div class="sum-card" style="background:#fffbeb;border:1px solid #fde68a">
                    <div class="sum-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-shopping-bag"></i></div>
                    <div>
                        <div style="font-size:22px;font-weight:800;color:#92400e">$<?php echo number_format($totales['total_ventas'],2); ?></div>
                        <div style="font-size:12px;color:#4b5563">Ventas productos</div>
                    </div>
                </div>
                <div class="sum-card" style="background:#fff1f2;border:1px solid #fecdd3">
                    <div class="sum-icon" style="background:#ffe4e6;color:#dc2626"><i class="fas fa-people-carry"></i></div>
                    <div>
                        <div style="font-size:22px;font-weight:800;color:#991b1b">−$<?php echo number_format($totales['total_colegas'],2); ?></div>
                        <div style="font-size:12px;color:#4b5563">Costos externos</div>
                    </div>
                </div>
                <div class="sum-card" style="background:linear-gradient(135deg,#166534,#15803d);border:0">
                    <div class="sum-icon" style="background:rgba(255,255,255,.15);color:white"><i class="fas fa-star"></i></div>
                    <div>
                        <div style="font-size:22px;font-weight:800;color:white">$<?php echo number_format($ganancia_neta,2); ?></div>
                        <div style="font-size:12px;color:#bbf7d0">Ganancia neta</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Historial de Seguimientos ── -->
    <div class="card mb-6">
        <div class="card-header">
            <i class="fas fa-history"></i> Historial de Seguimientos
            <span class="ml-auto text-xs font-normal text-gray-400"><?php echo count($seguimientos); ?> entrada(s)</span>
        </div>
        <div class="card-body">
            <?php if (empty($seguimientos)): ?>
                <div class="text-center py-12 text-gray-400">
                    <i class="fas fa-clipboard-list text-5xl mb-4 block"></i>
                    <p>No hay seguimientos registrados aún.</p>
                </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($seguimientos as $idx => $seg): ?>
                <?php
                    $hay_venta  = !empty($seg['venta_producto']);
                    $hay_colega = !empty($seg['costo_externo']);
                    $total_seg  = floatval($seg['valor_cobrar']) + ($hay_venta ? floatval($seg['venta_precio']) : 0);
                ?>
                <div class="seg-card">
                    <div class="seg-header">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="badge" style="background:#ede9fe;color:#4c1d95">
                                <i class="fas fa-tools"></i>
                                <?php echo htmlspecialchars($seg['tipo_servicio']); ?>
                            </span>
                            <span class="text-xs text-gray-400">#<?php echo count($seguimientos)-$idx; ?></span>
                            <span class="text-xs text-gray-500">
                                <i class="fas fa-user-cog mr-1"></i><?php echo htmlspecialchars($seg['tecnico']); ?>
                            </span>
                            <span class="text-xs text-gray-500">
                                <i class="fas fa-calendar mr-1"></i><?php echo date('d/m/Y H:i', strtotime($seg['fecha_registro'])); ?>
                            </span>
                            <?php if ($hay_venta): ?>
                            <span class="badge" style="background:#fef3c7;color:#92400e;font-size:11px">
                                <i class="fas fa-shopping-bag"></i> <?php echo htmlspecialchars($seg['venta_producto']); ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($hay_colega): ?>
                            <span class="badge" style="background:#dbeafe;color:#1e40af;font-size:11px">
                                <i class="fas fa-people-carry"></i> <?php echo htmlspecialchars($seg['descripcion_externo']); ?>: −$<?php echo number_format($seg['costo_externo'],2); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <div class="text-right">
                                <div class="text-xl font-bold text-green-700">$<?php echo number_format($seg['valor_cobrar'],2); ?></div>
                                <?php if ($hay_venta): ?>
                                <div class="text-xs text-amber-600 font-semibold">+ $<?php echo number_format($seg['venta_precio'],2); ?> venta</div>
                                <div class="text-xs text-indigo-700 font-bold border-t border-gray-200 pt-1">
                                    Total: $<?php echo number_format($total_seg,2); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <a href="imprimir_seguimiento.php?id=<?php echo $seg['id_seguimiento']; ?>"
                                class="flex items-center gap-2 px-3 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-lg text-xs font-semibold transition-colors">
                                <i class="fas fa-print"></i> Imprimir
                            </a>
                        </div>
                    </div>
                    <div class="seg-body">
                        <div class="bg-gray-50 rounded-xl p-4 border-l-4 border-indigo-300 text-sm text-gray-700 leading-relaxed">
                            <div class="text-xs font-bold text-indigo-600 uppercase tracking-wider mb-2">
                                <i class="fas fa-clipboard-check mr-1"></i>Procedimiento realizado
                            </div>
                            <?php echo nl2br(htmlspecialchars($seg['procedimiento'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Galería de Imágenes ── -->
    <?php if (!empty($imagenes)): ?>
    <div class="card mb-6">
        <div class="card-header"><i class="fas fa-images"></i> Evidencias Fotográficas</div>
        <div class="card-body">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($imagenes as $img): ?>
                <div class="group relative rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-all cursor-pointer"
                     onclick="window.open('../../<?php echo htmlspecialchars($img['ruta_archivo']); ?>','_blank')">
                    <img src="../../<?php echo htmlspecialchars($img['ruta_archivo']); ?>"
                         alt="Evidencia"
                         class="w-full h-44 object-cover group-hover:scale-105 transition-transform duration-300">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                        <div class="absolute bottom-2 left-3 text-white text-xs">
                            <i class="fas fa-expand mr-1"></i><?php echo date('d/m/Y', strtotime($img['fecha_registro'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /container -->
</div><!-- /main-content -->

<!-- ── Ticket de impresión (oculto en pantalla) ── -->
<div id="imprimir_ticket">
    <div style="width:210mm;padding:15mm 20mm;background:#fff;">
        <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #5AC456;padding-bottom:12px;margin-bottom:12px;">
            <div>
                <img src="../../assets/img/logo.png" alt="Logo" style="max-height:48px;">
                <div style="font-size:10px;color:#166534;margin-top:4px;"><?php echo htmlspecialchars($slogan); ?></div>
            </div>
            <div style="text-align:center;">
                <div id="qrcode"></div>
                <div style="font-size:8px;color:#166534;">Escanee para seguimiento</div>
                <script>
                    var qr = qrcode(0,'M');
                    qr.addData('<?php echo "http://{$_SERVER['HTTP_HOST']}/gestion/modules/ordenes/seguimiento_publico.php?orden=".urlencode($orden['codigo']); ?>');
                    qr.make();
                    document.getElementById('qrcode').innerHTML = qr.createImgTag(3);
                </script>
            </div>
            <div style="text-align:right;font-size:10px;">
                <?php foreach ($sucursales as $suc): ?>
                <strong><?php echo htmlspecialchars($suc['nombre']); ?></strong><br>
                <?php echo htmlspecialchars($suc['direccion']); ?><br>
                Tel: <?php echo htmlspecialchars($suc['telefono']); ?><br>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="background:#5AC456;color:white;padding:8px 12px;border-radius:5px;display:flex;justify-content:space-between;margin-bottom:12px;">
            <strong>ORDEN DE SERVICIO #<?php echo htmlspecialchars($orden['codigo']); ?></strong>
            <span>Fecha: <?php echo date('d/m/Y', strtotime($orden['fecha_ingreso'])); ?></span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:11px;margin-bottom:12px;background:#f8f9fa;padding:12px;border-radius:5px;border:1px solid #dee2e6;">
            <div>
                <strong style="color:#166534;">CLIENTE</strong><br>
                <?php echo htmlspecialchars($orden['cliente']); ?><br>
                RUC/CI: <?php echo htmlspecialchars($orden['identificacion']); ?><br>
                Tel: <?php echo htmlspecialchars($orden['cliente_telefono']); ?>
            </div>
            <div>
                <strong style="color:#166534;">EQUIPO</strong><br>
                <?php foreach ($equipos as $eq): ?>
                <?php echo htmlspecialchars($eq['marca'].' '.$eq['modelo']); ?><br>
                S/N: <?php echo htmlspecialchars($eq['numero_serial']); ?><br>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="font-size:9px;margin-bottom:12px;">
            <strong>Observación:</strong> <?php echo nl2br(htmlspecialchars($orden['descripcion_problema'])); ?>
        </div>

        <?php
        $subtotal = 0; $iva = 0;
        $abono_inicial = $orden['abono_inicial'] ?? 0;
        $valor_estimado = $orden['valor_estimado'] ?? 0;
        if ($orden['estado'] == 'Entregado') {
            $s2 = $conn->prepare("SELECT SUM(valor_cobrar) as total FROM seguimientos_orden WHERE id_orden = ?");
            $s2->execute([$id_orden]);
            $r2 = $s2->fetch();
            $subtotal = $r2['total'] ?? 0;
            $iva = $subtotal * $ivaEmpresa;
        }
        $total_svc = $subtotal + $iva;
        $total_est = ($valor_estimado > 0 && $orden['estado'] != 'Entregado') ? $valor_estimado : $total_svc;
        $saldo = $total_est - $abono_inicial;
        ?>
        <div style="display:flex;justify-content:space-between;align-items:flex-end;">
            <div style="font-size:9px;width:55%;">
                <?php echo htmlspecialchars($leyenda1); ?><br><?php echo htmlspecialchars($leyenda2); ?>
            </div>
            <div style="width:40%;font-size:11px;background:#f8f9fa;padding:10px;border-radius:5px;border:1px solid #dee2e6;">
                <?php if ($valor_estimado > 0 && $orden['estado'] != 'Entregado'): ?>
                <div style="display:flex;justify-content:space-between;color:#1d4ed8;font-weight:bold;">
                    <span>ESTIMADO:</span><span>$<?php echo number_format($valor_estimado,2); ?></span>
                </div>
                <?php else: ?>
                <div style="display:flex;justify-content:space-between;"><span>SUB-TOTAL:</span><span>$<?php echo number_format($subtotal,2); ?></span></div>
                <div style="display:flex;justify-content:space-between;font-weight:bold;border-top:1px solid #ccc;padding-top:4px;margin-top:4px;">
                    <span>TOTAL:</span><span>$<?php echo number_format($total_svc,2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($abono_inicial > 0): ?>
                <div style="display:flex;justify-content:space-between;color:#166534;">
                    <span>ABONO:</span><span>-$<?php echo number_format($abono_inicial,2); ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-weight:bold;color:#dc2626;border-top:1px solid #ccc;padding-top:4px;margin-top:4px;">
                    <span>SALDO:</span><span>$<?php echo number_format($saldo,2); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:24px;">
            <div style="text-align:center;border-top:1px solid #999;padding-top:6px;font-size:11px;">
                <?php echo htmlspecialchars($nombre_empresa); ?><br>
                <span style="font-size:9px;color:#666;">Firma Autorizada</span>
            </div>
            <div style="text-align:center;border-top:1px solid #999;padding-top:6px;font-size:11px;">
                <?php echo htmlspecialchars($orden['cliente']); ?><br>
                <span style="font-size:9px;color:#666;">Firma en total conformidad</span>
            </div>
        </div>
    </div>
</div>

</body>
</html>
