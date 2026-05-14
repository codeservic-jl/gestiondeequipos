<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

// Obtener datos del seguimiento
$stmt = $conn->prepare("
    SELECT s.*, o.codigo, o.descripcion_problema,
           c.nombre_apellido as cliente, e.marca, e.modelo, e.numero_serial,
           u.nombre_completo as tecnico
    FROM seguimientos_orden s
    JOIN ordenes_trabajo o ON s.id_orden = o.id_orden
    JOIN clientes c ON o.id_cliente = c.id_cliente
    JOIN equipos e ON o.id_equipo = e.id_equipo
    JOIN usuarios u ON s.id_tecnico = u.id_usuario
    WHERE s.id_seguimiento = ?
");
$stmt->execute([$_GET['id']]);
$seguimiento = $stmt->fetch();

if (!$seguimiento) {
    header("Location: lista.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Servicio Técnico - <?php echo $seguimiento['codigo']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            @page {
                margin: 0.5cm;
            }

            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }

            .print-break-inside-avoid {
                break-inside: avoid;
            }
        }

        .bg-navy-blue {
            background-color: #5AC456;
        }

        .border-navy-blue {
            border-color: #2d6b2b;
        }

        .text-navy-blue {
            color: #2d6b2b;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body class="bg-white p-8">
    <div class="no-print">
        <?php include '../../includes/navbar.php'; ?>
    </div>

    <div class="main-content">
    <!-- Botones de acción -->
    <div class="flex justify-end gap-4 mb-8 no-print">
        <button onclick="window.print()" class="bg-navy-blue text-white px-6 py-2 rounded-lg hover:bg-green-700">
            <i class="fas fa-print mr-2"></i>Imprimir
        </button>
        <button onclick="window.history.back()" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600">
            <i class="fas fa-arrow-left mr-2"></i>Volver
        </button>
    </div>

    <!-- Contenido del reporte -->
    <div class="max-w-4xl mx-auto bg-white shadow-lg rounded-lg overflow-hidden print-break-inside-avoid">
        <!-- Encabezado -->
        <div class="border-b-2 border-navy-blue p-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <img src="../../assets/img/logo.png" alt="Logo" class="h-16 mr-4">
                    <div>
                        <h1 class="text-2xl font-bold text-navy-blue">Reporte de Servicio Técnico</h1>
                        <p class="text-gray-600">Orden de trabajo: <?php echo $seguimiento['codigo']; ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Fecha: <?php echo date('d/m/Y H:i', strtotime($seguimiento['fecha_registro'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Información Principal -->
        <div class="grid grid-cols-2 gap-6 p-6">
            <!-- Información del Cliente -->
            <div class="border rounded-lg p-4 bg-gray-50">
                <h2 class="text-lg font-semibold text-navy-blue mb-3">
                    <i class="fas fa-user-circle mr-2"></i>Información del Cliente
                </h2>
                <p><strong>Cliente:</strong> <?php echo $seguimiento['cliente']; ?></p>
            </div>

            <!-- Información del Equipo -->
            <div class="border rounded-lg p-4 bg-gray-50">
                <h2 class="text-lg font-semibold text-navy-blue mb-3">
                    <i class="fas fa-laptop mr-2"></i>Información del Equipo
                </h2>
                <div class="space-y-1">
                    <p><strong>Marca:</strong> <?php echo $seguimiento['marca']; ?></p>
                    <p><strong>Modelo:</strong> <?php echo $seguimiento['modelo']; ?></p>
                    <p><strong>Serial:</strong> <?php echo $seguimiento['numero_serial']; ?></p>
                </div>
            </div>
        </div>

        <!-- Detalles del Servicio -->
        <div class="p-6 bg-white">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-navy-blue mb-3">
                    <i class="fas fa-tools mr-2"></i>Detalles del Servicio
                </h2>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p><strong>Problema reportado:</strong></p>
                        <p class="text-gray-700"><?php echo $seguimiento['descripcion_problema']; ?></p>
                    </div>
                    <div>
                        <p><strong>Tipo de servicio:</strong> <?php echo $seguimiento['tipo_servicio']; ?></p>
                        <p><strong>Valor del servicio:</strong> $<?php echo number_format($seguimiento['valor_cobrar'], 2); ?></p>
                    </div>
                </div>

                <div class="mt-4">
                    <p><strong>Procedimiento realizado:</strong></p>
                    <p class="text-gray-700 whitespace-pre-line"><?php echo $seguimiento['procedimiento']; ?></p>
                </div>
            </div>
        </div>

        <!-- Firmas -->
        <div class="grid grid-cols-2 gap-6 p-6 border-t">
            <div class="text-center">
                <div class="border-t border-gray-400 pt-2 mt-16">
                    <p class="font-semibold">Técnico</p>
                    <p class="text-sm text-gray-600"><?php echo $seguimiento['tecnico']; ?></p>
                </div>
            </div>
            <div class="text-center">
                <div class="border-t border-gray-400 pt-2 mt-16">
                    <p class="font-semibold">Cliente</p>
                    <p class="text-sm text-gray-600"><?php echo $seguimiento['cliente']; ?></p>
                </div>
            </div>
        </div>

        <!-- Pie de página -->
        <div class="bg-gray-50 p-4 text-center text-sm text-gray-600 border-t">
            <p>Este documento es un comprobante oficial del servicio técnico realizado.</p>
            <p>Para cualquier consulta, por favor conserve este documento.</p>
        </div>
    </div>
    </div>
</body>

</html>