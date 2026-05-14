<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// ── Período ──────────────────────────────────────────────────
$periodo = $_GET['periodo'] ?? 'mes';
$fecha_desde = $_GET['desde'] ?? '';
$fecha_hasta = $_GET['hasta'] ?? '';

$hoy = date('Y-m-d');
switch ($periodo) {
    case 'hoy':
        $desde = $hoy;
        $hasta = $hoy;
        break;
    case 'semana':
        $desde = date('Y-m-d', strtotime('monday this week'));
        $hasta = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mes':
        $desde = date('Y-m-01');
        $hasta = date('Y-m-t');
        break;
    case 'personalizado':
        $desde = $fecha_desde ?: date('Y-m-01');
        $hasta = $fecha_hasta ?: $hoy;
        break;
    default:
        $desde = date('Y-m-01');
        $hasta = date('Y-m-t');
}

$desde_dt = $desde . ' 00:00:00';
$hasta_dt = $hasta . ' 23:59:59';

// ── Totales del período ───────────────────────────────────────
$stmtTot = $conn->prepare("
    SELECT
        COALESCE(SUM(so.valor_cobrar), 0)             AS total_cobrado,
        COALESCE(SUM(so.costo_externo), 0)            AS total_colegas,
        COALESCE(SUM(v.valor_venta), 0)               AS total_ventas,
        COALESCE(SUM(v.ganancia_neta), 0)             AS ganancia_ventas,
        COUNT(DISTINCT so.id_seguimiento)             AS num_servicios,
        COUNT(DISTINCT so.id_orden)                   AS num_ordenes
    FROM seguimientos_orden so
    LEFT JOIN ventas_orden v ON v.id_seguimiento = so.id_seguimiento
    WHERE so.fecha_registro BETWEEN ? AND ?
");
$stmtTot->execute([$desde_dt, $hasta_dt]);
$totales = $stmtTot->fetch();

$ganancia_servicios = $totales['total_cobrado'] - $totales['total_colegas'];
$ganancia_neta_total = $ganancia_servicios + $totales['ganancia_ventas'];

// ── Detalle por seguimiento ───────────────────────────────────
$stmtDet = $conn->prepare("
    SELECT
        so.id_seguimiento,
        so.fecha_registro,
        so.tipo_servicio,
        so.valor_cobrar,
        so.costo_externo,
        so.descripcion_externo,
        ot.codigo          AS orden_codigo,
        ot.id_orden,
        c.nombre_apellido  AS cliente,
        u.nombre_completo  AS tecnico,
        v.producto         AS venta_producto,
        v.valor_venta      AS venta_precio,
        v.ganancia_neta    AS venta_ganancia
    FROM seguimientos_orden so
    JOIN ordenes_trabajo ot ON so.id_orden = ot.id_orden
    JOIN clientes c ON ot.id_cliente = c.id_cliente
    JOIN usuarios u ON so.id_tecnico = u.id_usuario
    LEFT JOIN ventas_orden v ON v.id_seguimiento = so.id_seguimiento
    WHERE so.fecha_registro BETWEEN ? AND ?
    ORDER BY so.fecha_registro DESC
");
$stmtDet->execute([$desde_dt, $hasta_dt]);
$detalle = $stmtDet->fetchAll();

// ── Ganancia por técnico ──────────────────────────────────────
$stmtTec = $conn->prepare("
    SELECT
        u.nombre_completo AS tecnico,
        COUNT(so.id_seguimiento) AS servicios,
        COALESCE(SUM(so.valor_cobrar), 0)  AS cobrado,
        COALESCE(SUM(so.costo_externo), 0) AS colegas,
        COALESCE(SUM(v.ganancia_neta), 0)  AS venta_ganancia
    FROM seguimientos_orden so
    JOIN usuarios u ON so.id_tecnico = u.id_usuario
    LEFT JOIN ventas_orden v ON v.id_seguimiento = so.id_seguimiento
    WHERE so.fecha_registro BETWEEN ? AND ?
    GROUP BY so.id_tecnico, u.nombre_completo
    ORDER BY cobrado DESC
");
$stmtTec->execute([$desde_dt, $hasta_dt]);
$por_tecnico = $stmtTec->fetchAll();

// ── Labels de período ─────────────────────────────────────────
$labels = ['hoy'=>'Hoy','semana'=>'Esta semana','mes'=>'Este mes','personalizado'=>'Personalizado'];
$label_actual = $labels[$periodo] ?? 'Este mes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reportes de Ganancias</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: 'Segoe UI', system-ui, sans-serif; }

        .card { background: white; border-radius: 16px; box-shadow: 0 1px 8px rgba(0,0,0,.07); overflow: hidden; }
        .card-header {
            padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 700; color: #166534;
            text-transform: uppercase; letter-spacing: .5px;
        }

        .sum-card {
            border-radius: 16px; padding: 22px 24px;
            display: flex; align-items: center; gap: 16px;
        }
        .sum-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .sum-label { font-size: 12px; color: rgba(255,255,255,.75); margin-bottom: 2px; }
        .sum-value { font-size: 26px; font-weight: 800; color: white; line-height: 1; }
        .sum-sub   { font-size: 11px; color: rgba(255,255,255,.65); margin-top: 3px; }

        /* período pills */
        .pill {
            padding: 7px 18px; border-radius: 999px; font-size: 13px; font-weight: 600;
            border: 1.5px solid #e2e8f0; color: #475569; cursor: pointer;
            text-decoration: none; transition: all .15s;
            display: inline-block;
        }
        .pill.active, .pill:hover {
            background: #166534; color: white; border-color: #166534;
        }

        /* tabla */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { padding: 10px 14px; background: #f8fafc; font-size: 11px; font-weight: 700;
             text-transform: uppercase; letter-spacing: .5px; color: #64748b;
             border-bottom: 1px solid #e2e8f0; text-align: left; }
        td { padding: 11px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }

        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600;
        }
        .money { font-weight: 700; font-family: monospace; font-size: 14px; }
        .money.green { color: #166534; }
        .money.red   { color: #dc2626; }
        .money.amber { color: #92400e; }

        /* Ganancia neta highlight */
        .ganancia-cel { font-size: 15px; font-weight: 800; color: #166534; }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .card { box-shadow: none; border: 1px solid #e2e8f0; }
        }
    </style>
</head>
<body>
<?php include '../../includes/navbar.php'; ?>

<div class="main-content">
<div class="container mx-auto px-4 py-8 max-w-7xl">

    <!-- ── Encabezado ── -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                <i class="fas fa-chart-bar text-green-600 mr-2"></i>Reporte de Ganancias
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                <?php echo $label_actual; ?> &mdash;
                <?php echo date('d/m/Y', strtotime($desde)); ?> al <?php echo date('d/m/Y', strtotime($hasta)); ?>
            </p>
        </div>
        <button onclick="window.print()" class="no-print flex items-center gap-2 px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg text-sm font-semibold">
            <i class="fas fa-print"></i> Imprimir / PDF
        </button>
    </div>

    <!-- ── Filtros de período ── -->
    <div class="card mb-6 no-print">
        <div class="p-5 flex flex-wrap gap-3 items-end">
            <a href="?periodo=hoy"    class="pill <?php echo $periodo=='hoy'    ?'active':''; ?>"><i class="fas fa-sun mr-1"></i>Hoy</a>
            <a href="?periodo=semana" class="pill <?php echo $periodo=='semana' ?'active':''; ?>"><i class="fas fa-calendar-week mr-1"></i>Esta semana</a>
            <a href="?periodo=mes"    class="pill <?php echo $periodo=='mes'    ?'active':''; ?>"><i class="fas fa-calendar-alt mr-1"></i>Este mes</a>
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="periodo" value="personalizado">
                <input type="date" name="desde" value="<?php echo htmlspecialchars($periodo=='personalizado'?$desde:date('Y-m-01')); ?>"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-green-500">
                <span class="text-gray-400 text-sm">→</span>
                <input type="date" name="hasta" value="<?php echo htmlspecialchars($periodo=='personalizado'?$hasta:$hoy); ?>"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-green-500">
                <button type="submit"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-semibold">
                    Aplicar
                </button>
            </form>
        </div>
    </div>

    <!-- ── Tarjetas de resumen ── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        <div class="sum-card" style="background:linear-gradient(135deg,#1e40af,#3b82f6)">
            <div class="sum-icon" style="background:rgba(255,255,255,.15);color:white">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div>
                <div class="sum-label">Total cobrado servicios</div>
                <div class="sum-value">$<?php echo number_format($totales['total_cobrado'],2); ?></div>
                <div class="sum-sub"><?php echo $totales['num_servicios']; ?> servicio(s) · <?php echo $totales['num_ordenes']; ?> orden(es)</div>
            </div>
        </div>

        <div class="sum-card" style="background:linear-gradient(135deg,#92400e,#d97706)">
            <div class="sum-icon" style="background:rgba(255,255,255,.15);color:white">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <div>
                <div class="sum-label">Ingresos por ventas</div>
                <div class="sum-value">$<?php echo number_format($totales['total_ventas'],2); ?></div>
                <div class="sum-sub">Ganancia: $<?php echo number_format($totales['ganancia_ventas'],2); ?></div>
            </div>
        </div>

        <div class="sum-card" style="background:linear-gradient(135deg,#991b1b,#dc2626)">
            <div class="sum-icon" style="background:rgba(255,255,255,.15);color:white">
                <i class="fas fa-people-carry"></i>
            </div>
            <div>
                <div class="sum-label">Costos externos / colegas</div>
                <div class="sum-value">$<?php echo number_format($totales['total_colegas'],2); ?></div>
                <div class="sum-sub">Deducidos del total</div>
            </div>
        </div>

        <div class="sum-card" style="background:linear-gradient(135deg,#14532d,#16a34a)">
            <div class="sum-icon" style="background:rgba(255,255,255,.2);color:white;font-size:26px">
                <i class="fas fa-star"></i>
            </div>
            <div>
                <div class="sum-label">Ganancia neta total</div>
                <div class="sum-value">$<?php echo number_format($ganancia_neta_total,2); ?></div>
                <div class="sum-sub">Servicios + productos − colegas</div>
            </div>
        </div>
    </div>

    <!-- ── Por técnico ── -->
    <?php if (!empty($por_tecnico)): ?>
    <div class="card mb-6">
        <div class="card-header"><i class="fas fa-user-cog"></i> Ganancia por Técnico</div>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th>Técnico</th>
                        <th>Servicios</th>
                        <th>Total cobrado</th>
                        <th>Costo colegas</th>
                        <th>Ganancia ventas</th>
                        <th>Ganancia neta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($por_tecnico as $tec): ?>
                    <?php $gn = ($tec['cobrado'] - $tec['colegas']) + $tec['venta_ganancia']; ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#16a34a,#4ade80);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:13px;flex-shrink:0;">
                                    <?php echo strtoupper(substr($tec['tecnico'],0,1)); ?>
                                </div>
                                <span class="font-semibold"><?php echo htmlspecialchars($tec['tecnico']); ?></span>
                            </div>
                        </td>
                        <td><span class="badge" style="background:#ede9fe;color:#4c1d95"><?php echo $tec['servicios']; ?></span></td>
                        <td><span class="money green">$<?php echo number_format($tec['cobrado'],2); ?></span></td>
                        <td><span class="money red">−$<?php echo number_format($tec['colegas'],2); ?></span></td>
                        <td><span class="money amber">$<?php echo number_format($tec['venta_ganancia'],2); ?></span></td>
                        <td><span class="ganancia-cel">$<?php echo number_format($gn,2); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Detalle de seguimientos ── -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list-alt"></i> Detalle de Servicios
            <span class="ml-auto text-xs font-normal text-gray-400 normal-case"><?php echo count($detalle); ?> registro(s)</span>
        </div>

        <?php if (empty($detalle)): ?>
        <div class="p-12 text-center text-gray-400">
            <i class="fas fa-chart-bar text-5xl mb-4 block"></i>
            <p class="font-medium">Sin registros en este período</p>
            <p class="text-sm mt-1">Selecciona un rango de fechas diferente.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Orden</th>
                        <th>Cliente</th>
                        <th>Técnico</th>
                        <th>Servicio</th>
                        <th>Cobrado</th>
                        <th>Colega</th>
                        <th>Venta / Ganancia</th>
                        <th>Ganancia neta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalle as $row):
                        $gn_row = (floatval($row['valor_cobrar']) - floatval($row['costo_externo'] ?? 0))
                                + floatval($row['venta_ganancia'] ?? 0);
                    ?>
                    <tr>
                        <td class="text-xs text-gray-500 whitespace-nowrap">
                            <?php echo date('d/m/Y', strtotime($row['fecha_registro'])); ?><br>
                            <span class="text-gray-400"><?php echo date('H:i', strtotime($row['fecha_registro'])); ?></span>
                        </td>
                        <td>
                            <a href="../ordenes/ver.php?id=<?php echo $row['id_orden']; ?>"
                                class="font-mono text-xs text-green-700 hover:underline font-semibold">
                                <?php echo htmlspecialchars($row['orden_codigo']); ?>
                            </a>
                        </td>
                        <td class="font-medium"><?php echo htmlspecialchars($row['cliente']); ?></td>
                        <td class="text-gray-600"><?php echo htmlspecialchars($row['tecnico']); ?></td>
                        <td>
                            <span class="badge" style="background:#ede9fe;color:#4c1d95;font-size:11px">
                                <?php echo htmlspecialchars($row['tipo_servicio']); ?>
                            </span>
                        </td>
                        <td><span class="money green">$<?php echo number_format($row['valor_cobrar'],2); ?></span></td>
                        <td>
                            <?php if (!empty($row['costo_externo'])): ?>
                            <span class="money red">−$<?php echo number_format($row['costo_externo'],2); ?></span>
                            <?php if (!empty($row['descripcion_externo'])): ?>
                            <div class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($row['descripcion_externo']); ?></div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-gray-300">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['venta_producto'])): ?>
                            <div class="font-semibold text-amber-700 text-xs"><?php echo htmlspecialchars($row['venta_producto']); ?></div>
                            <div class="text-xs text-gray-500">Venta: $<?php echo number_format($row['venta_precio'],2); ?></div>
                            <div class="text-xs text-green-700 font-bold">Gan: $<?php echo number_format($row['venta_ganancia'],2); ?></div>
                            <?php else: ?>
                            <span class="text-gray-300">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="ganancia-cel" style="color:<?php echo $gn_row >= 0 ? '#166534' : '#dc2626'; ?>">
                                $<?php echo number_format($gn_row,2); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <!-- Fila totales -->
                <tfoot>
                    <tr style="background:#f0fdf4;border-top:2px solid #86efac;">
                        <td colspan="5" style="padding:12px 14px;font-weight:700;color:#166534;font-size:13px;">
                            TOTALES DEL PERÍODO
                        </td>
                        <td><span class="money green" style="font-size:15px;">$<?php echo number_format($totales['total_cobrado'],2); ?></span></td>
                        <td><span class="money red" style="font-size:15px;">−$<?php echo number_format($totales['total_colegas'],2); ?></span></td>
                        <td><span class="money amber" style="font-size:15px;">$<?php echo number_format($totales['ganancia_ventas'],2); ?></span></td>
                        <td><span class="ganancia-cel" style="font-size:17px;">$<?php echo number_format($ganancia_neta_total,2); ?></span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>
</body>
</html>
