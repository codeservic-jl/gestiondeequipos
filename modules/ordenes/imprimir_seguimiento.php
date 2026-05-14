<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$stmt = $conn->prepare("
    SELECT s.*, o.codigo, o.descripcion_problema, o.id_orden,
           c.nombre_apellido as cliente, c.telefono as cliente_telefono, c.identificacion,
           e.marca, e.modelo, e.numero_serial,
           u.nombre_completo as tecnico,
           v.producto as venta_producto, v.valor_compra as venta_compra,
           v.valor_venta as venta_precio, v.ganancia_neta as venta_ganancia
    FROM seguimientos_orden s
    JOIN ordenes_trabajo o ON s.id_orden = o.id_orden
    JOIN clientes c ON o.id_cliente = c.id_cliente
    JOIN equipos e ON o.id_equipo = e.id_equipo
    JOIN usuarios u ON s.id_tecnico = u.id_usuario
    LEFT JOIN ventas_orden v ON v.id_seguimiento = s.id_seguimiento
    WHERE s.id_seguimiento = ?
");
$stmt->execute([$_GET['id']]);
$seg = $stmt->fetch();

if (!$seg) {
    header("Location: lista.php");
    exit();
}

$stmtEmp = $conn->prepare("SELECT nombre_empresa, slogan, leyenda1, leyenda2 FROM empresa ORDER BY nombre_empresa ASC LIMIT 1");
$stmtEmp->execute();
$emp = $stmtEmp->fetch();

$hay_venta  = !empty($seg['venta_producto']);
$hay_colega = !empty($seg['costo_externo']);
$total_cobrado = floatval($seg['valor_cobrar']) + ($hay_venta ? floatval($seg['venta_precio']) : 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reporte Técnico — <?php echo htmlspecialchars($seg['codigo']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f0f4f8;
            color: #1e293b;
        }

        /* ── Pantalla: botones de acción ── */
        .action-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 32px;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        .action-bar span {
            color: #94a3b8;
            font-size: 14px;
            margin-right: auto;
        }
        .btn-print {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: opacity .2s;
        }
        .btn-back {
            background: #334155;
            color: #cbd5e1;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-print:hover { opacity: .85; }
        .btn-back:hover  { background: #475569; }

        /* ── Contenedor del reporte ── */
        .report-wrap {
            max-width: 860px;
            margin: 90px auto 48px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(0,0,0,0.12);
        }

        /* ── Cabecera ── */
        .report-header {
            background: linear-gradient(135deg, #14532d 0%, #166534 50%, #15803d 100%);
            padding: 32px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }
        .report-header img { height: 56px; filter: brightness(0) invert(1); }
        .report-header .title-block h1 {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -.3px;
        }
        .report-header .title-block p {
            font-size: 13px;
            color: #bbf7d0;
            margin-top: 4px;
        }
        .report-header .meta-block {
            text-align: right;
        }
        .report-header .meta-block .order-num {
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            font-family: monospace;
            letter-spacing: 1px;
        }
        .report-header .meta-block .order-date {
            font-size: 13px;
            color: #86efac;
            margin-top: 4px;
        }

        /* ── Tira de estado ── */
        .status-strip {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 40px;
            display: flex;
            align-items: center;
            gap: 24px;
            font-size: 13px;
        }
        .status-strip .chip {
            background: #dcfce7;
            color: #166534;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 12px;
        }
        .status-strip .lbl { color: #64748b; }
        .status-strip .val { font-weight: 600; color: #1e293b; }

        /* ── Cuerpo ── */
        .report-body { padding: 32px 40px; }

        /* Info cards */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 28px;
        }
        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px 24px;
        }
        .info-card .card-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #16a34a;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .info-card .row {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .info-card .row .lbl { color: #64748b; min-width: 64px; }
        .info-card .row .val { font-weight: 600; color: #1e293b; }

        /* Sección procedimiento */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #16a34a;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .procedure-box {
            background: #f1f5f9;
            border-left: 4px solid #16a34a;
            border-radius: 0 12px 12px 0;
            padding: 18px 20px;
            font-size: 14px;
            line-height: 1.65;
            color: #334155;
            margin-bottom: 28px;
            white-space: pre-wrap;
        }
        .problem-box {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 0 12px 12px 0;
            padding: 14px 18px;
            font-size: 13px;
            line-height: 1.6;
            color: #78350f;
            margin-bottom: 20px;
        }
        .problem-box .plbl { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; color: #b45309; }

        /* Resumen financiero */
        .financial-box {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1.5px solid #86efac;
            border-radius: 16px;
            padding: 22px 28px;
            margin-bottom: 28px;
        }
        .financial-box .fin-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #166534;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .fin-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            padding: 6px 0;
            border-bottom: 1px dashed #bbf7d0;
        }
        .fin-row:last-child { border-bottom: none; }
        .fin-row .fin-lbl { color: #374151; }
        .fin-row .fin-lbl small { display: block; font-size: 11px; color: #6b7280; }
        .fin-row .fin-val { font-weight: 700; color: #15803d; font-size: 16px; }
        .fin-row.total {
            margin-top: 8px;
            padding-top: 14px;
            border-top: 2px solid #4ade80;
            border-bottom: none;
        }
        .fin-row.total .fin-lbl { font-size: 15px; font-weight: 700; color: #166534; }
        .fin-row.total .fin-val { font-size: 22px; color: #166534; }
        .fin-row.colega .fin-val { color: #dc2626; }

        /* Venta badge */
        .venta-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fef3c7;
            color: #92400e;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        /* Firmas */
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 8px;
        }
        .sig-block { text-align: center; }
        .sig-line {
            border-top: 1.5px solid #94a3b8;
            padding-top: 10px;
            margin-top: 52px;
        }
        .sig-line .sig-name { font-weight: 700; font-size: 14px; color: #1e293b; }
        .sig-line .sig-role { font-size: 12px; color: #64748b; margin-top: 2px; }

        /* Pie */
        .report-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 16px 40px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
        }

        /* ── Print ── */
        @media print {
            @page { size: A4; margin: 0.6cm; }
            body { background: white; }
            .action-bar { display: none !important; }
            .report-wrap {
                max-width: 100%;
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .report-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .financial-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .info-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<!-- Barra de acciones (solo pantalla) -->
<div class="action-bar">
    <span>Reporte de Servicio — <?php echo htmlspecialchars($seg['codigo']); ?></span>
    <button class="btn-back" onclick="window.history.back()">
        <i class="fas fa-arrow-left"></i> Volver
    </button>
    <button class="btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimir / PDF
    </button>
</div>

<!-- Reporte -->
<div class="report-wrap">

    <!-- Cabecera verde -->
    <div class="report-header">
        <div style="display:flex;align-items:center;gap:20px;">
            <img src="../../assets/img/logo.png" alt="Logo">
            <div class="title-block">
                <h1>Reporte de Servicio Técnico</h1>
                <p><?php echo htmlspecialchars($emp['slogan'] ?? 'Soluciones tecnológicas de calidad'); ?></p>
            </div>
        </div>
        <div class="meta-block">
            <div class="order-num"><?php echo htmlspecialchars($seg['codigo']); ?></div>
            <div class="order-date">
                <i class="fas fa-calendar-alt" style="margin-right:4px;"></i>
                <?php echo date('d/m/Y H:i', strtotime($seg['fecha_registro'])); ?>
            </div>
        </div>
    </div>

    <!-- Tira de estado -->
    <div class="status-strip">
        <span class="chip"><i class="fas fa-tools" style="margin-right:5px;"></i><?php echo htmlspecialchars($seg['tipo_servicio']); ?></span>
        <span class="lbl">Técnico:</span>
        <span class="val"><?php echo htmlspecialchars($seg['tecnico']); ?></span>
        <span class="lbl" style="margin-left:12px;">Servicio Nro:</span>
        <span class="val">#<?php echo htmlspecialchars($seg['id_seguimiento']); ?></span>
    </div>

    <!-- Cuerpo -->
    <div class="report-body">

        <!-- Cliente + Equipo -->
        <div class="info-grid">
            <div class="info-card">
                <div class="card-title">
                    <i class="fas fa-user"></i> Información del Cliente
                </div>
                <div class="row"><span class="lbl">Nombre</span><span class="val"><?php echo htmlspecialchars($seg['cliente']); ?></span></div>
                <?php if (!empty($seg['identificacion'])): ?>
                <div class="row"><span class="lbl">RUC/CI</span><span class="val"><?php echo htmlspecialchars($seg['identificacion']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($seg['cliente_telefono'])): ?>
                <div class="row"><span class="lbl">Teléfono</span><span class="val"><?php echo htmlspecialchars($seg['cliente_telefono']); ?></span></div>
                <?php endif; ?>
            </div>
            <div class="info-card">
                <div class="card-title">
                    <i class="fas fa-laptop"></i> Información del Equipo
                </div>
                <div class="row"><span class="lbl">Marca</span><span class="val"><?php echo htmlspecialchars($seg['marca']); ?></span></div>
                <div class="row"><span class="lbl">Modelo</span><span class="val"><?php echo htmlspecialchars($seg['modelo']); ?></span></div>
                <div class="row"><span class="lbl">Serial</span><span class="val" style="font-family:monospace;font-size:12px;"><?php echo htmlspecialchars($seg['numero_serial']); ?></span></div>
            </div>
        </div>

        <!-- Problema reportado -->
        <?php if (!empty($seg['descripcion_problema'])): ?>
        <div class="problem-box">
            <div class="plbl"><i class="fas fa-exclamation-triangle" style="margin-right:5px;"></i>Problema reportado</div>
            <?php echo nl2br(htmlspecialchars($seg['descripcion_problema'])); ?>
        </div>
        <?php endif; ?>

        <!-- Procedimiento -->
        <div class="section-title">
            <i class="fas fa-clipboard-check"></i> Procedimiento realizado
        </div>
        <div class="procedure-box"><?php echo nl2br(htmlspecialchars($seg['procedimiento'])); ?></div>

        <!-- Resumen financiero -->
        <div class="financial-box">
            <div class="fin-title">
                <i class="fas fa-receipt"></i> Resumen Financiero
            </div>

            <div class="fin-row">
                <span class="fin-lbl">
                    Valor del servicio
                    <small><?php echo htmlspecialchars($seg['tipo_servicio']); ?></small>
                </span>
                <span class="fin-val">$<?php echo number_format($seg['valor_cobrar'], 2); ?></span>
            </div>

            <?php if ($hay_venta): ?>
            <div class="fin-row">
                <span class="fin-lbl">
                    Producto vendido
                    <small>
                        <?php echo htmlspecialchars($seg['venta_producto']); ?>
                        &nbsp;·&nbsp; Compra: $<?php echo number_format($seg['venta_compra'], 2); ?>
                        <span class="venta-badge">
                            <i class="fas fa-shopping-bag"></i>
                            Ganancia: $<?php echo number_format($seg['venta_ganancia'], 2); ?>
                        </span>
                    </small>
                </span>
                <span class="fin-val">$<?php echo number_format($seg['venta_precio'], 2); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($hay_colega): ?>
            <div class="fin-row colega">
                <span class="fin-lbl">
                    Costo externo / colega
                    <small><?php echo htmlspecialchars($seg['descripcion_externo']); ?></small>
                </span>
                <span class="fin-val">−$<?php echo number_format($seg['costo_externo'], 2); ?></span>
            </div>
            <?php endif; ?>

            <div class="fin-row total">
                <span class="fin-lbl">Total a cobrar al cliente</span>
                <span class="fin-val">$<?php echo number_format($total_cobrado, 2); ?></span>
            </div>
        </div>

        <!-- Firmas -->
        <div class="signatures">
            <div class="sig-block">
                <div class="sig-line">
                    <div class="sig-name"><?php echo htmlspecialchars($seg['tecnico']); ?></div>
                    <div class="sig-role">Técnico Responsable</div>
                </div>
            </div>
            <div class="sig-block">
                <div class="sig-line">
                    <div class="sig-name"><?php echo htmlspecialchars($seg['cliente']); ?></div>
                    <div class="sig-role">Cliente — Conforme con el servicio</div>
                </div>
            </div>
        </div>

    </div><!-- /body -->

    <!-- Pie -->
    <div class="report-footer">
        <p><?php echo htmlspecialchars($emp['leyenda1'] ?? 'Este documento es un comprobante oficial del servicio técnico realizado.'); ?></p>
        <?php if (!empty($emp['leyenda2'])): ?>
        <p><?php echo htmlspecialchars($emp['leyenda2']); ?></p>
        <?php endif; ?>
    </div>

</div><!-- /report-wrap -->

</body>
</html>
