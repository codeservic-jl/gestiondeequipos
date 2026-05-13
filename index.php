<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'config/database.php';

// Obtener total de órdenes activas
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_trabajo WHERE estado = 'Pendiente'");
$stmt->execute();
$result = $stmt->fetch();
$total_ordenes = $result['total'];

// Obtener total de clientes
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM clientes WHERE estado = 1");
$stmt->execute();
$result = $stmt->fetch();
$total_clientes = $result['total'];

// Obtener total de equipos
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM equipos");
$stmt->execute();
$result = $stmt->fetch();
$total_equipos = $result['total'];

// Obtener últimas órdenes
$stmt = $conn->prepare("
    SELECT 
        ot.codigo,
        c.nombre_apellido as cliente,
        ot.estado,
        ot.fecha_ingreso as fecha
    FROM ordenes_trabajo ot
    JOIN clientes c ON c.id_cliente = ot.id_cliente
    ORDER BY ot.fecha_ingreso DESC
    LIMIT 5
");
$stmt->execute();
$ultimas_ordenes = $stmt->fetchAll();

// Manejo de errores para los contadores
$total_ordenes = $total_ordenes ?? 0;
$total_clientes = $total_clientes ?? 0;
$total_equipos = $total_equipos ?? 0;
$ultimas_ordenes = $ultimas_ordenes ?? [];
$base_url = "";

// Obtener datos para el gráfico de los últimos 7 días
$datos_grafico = [];
$labels_grafico = [];

for ($i = 6; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-$i days"));
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM ordenes_trabajo 
        WHERE DATE(fecha_ingreso) = ?
    ");
    $stmt->execute([$fecha]);
    $result = $stmt->fetch();

    $datos_grafico[] = $result['total'];
    $labels_grafico[] = date('D', strtotime($fecha));
}

// Convertir los días a español
$dias_espanol = [
    'Mon' => 'Lun',
    'Tue' => 'Mar',
    'Wed' => 'Mié',
    'Thu' => 'Jue',
    'Fri' => 'Vie',
    'Sat' => 'Sáb',
    'Sun' => 'Dom'
];

$labels_grafico = array_map(function ($dia) use ($dias_espanol) {
    return $dias_espanol[$dia];
}, $labels_grafico);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestión de Equipos RGE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .bg-primary {
            background-color: #0c184c;
        }

        .text-primary {
            color: #0c184c;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include 'includes/navbar.php'; ?>

    <div class="md:ml-64 p-4 md:p-8 transition-all duration-300"> <!-- Modificado esta línea -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-800">   Dashboard</h1>
            <p class="text-gray-600">Resumen de actividades</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Tarjeta de Órdenes -->
            <div class="bg-white rounded-lg shadow p-1">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Órdenes Activas</h3>
                    <span class="text-green-500">+17%</span>
                </div>
                <p class="text-3xl font-bold text-primary"><?php echo $total_ordenes; ?></p>
                <div class="mt-4">
                    <div class="h-2 bg-gray-200 rounded">
                        <div class="h-2 bg-primary rounded" style="width: 70%"></div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta de Clientes -->
            <div class="bg-white rounded-lg shadow p-1">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Total Clientes</h3>
                    <span class="text-green-500">+23%</span>
                </div>
                <p class="text-3xl font-bold text-primary"><?php echo $total_clientes; ?></p>
                <div class="mt-4">
                    <div class="h-2 bg-gray-200 rounded">
                        <div class="h-2 bg-yellow-400 rounded" style="width: 60%"></div>
                    </div>
                </div>
            </div>

            <!-- Tarjeta de Equipos -->
            <div class="bg-white rounded-lg shadow p-1">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Equipos Registrados</h3>
                    <span class="text-green-500">+15%</span>
                </div>
                <p class="text-3xl font-bold text-primary"><?php echo $total_equipos; ?></p>
                <div class="mt-4">
                    <div class="h-2 bg-gray-200 rounded">
                        <div class="h-2 bg-blue-400 rounded" style="width: 85%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de actividad semanal -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Actividad Semanal</h3>
                <div style="height: 300px;">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Estado de Órdenes</h3>
                <div style="height: 300px;">
                    <canvas id="orderStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Últimas órdenes -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-2 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Últimas Órdenes</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($ultimas_ordenes as $orden): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $orden['codigo']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $orden['cliente']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $orden['estado'] == 'Completado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo $orden['estado']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo $orden['fecha']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('activityChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels_grafico); ?>,
                datasets: [{
                    label: 'Órdenes',
                    data: <?php echo json_encode($datos_grafico); ?>,
                    borderColor: '#0c184c',
                    backgroundColor: 'rgba(12, 24, 76, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#0c184c'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        },
                        suggestedMax: Math.max(...<?php echo json_encode($datos_grafico); ?>) + 2
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                elements: {
                    point: {
                        radius: 4,
                        hoverRadius: 6
                    },
                    line: {
                        borderWidth: 2
                    }
                }
            }
        });
    </script>

    <script>
        // Gráfico de estado de órdenes
        const ctxStatus = document.getElementById('orderStatusChart').getContext('2d');
        new Chart(ctxStatus, {
            type: 'bar',
            data: {
                labels: ['Pendientes', 'En Proceso', 'Completadas'],
                datasets: [{
                    label: 'Estado de Órdenes',
                    data: [
                        <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_trabajo WHERE estado = 'Pendiente'");
                        $stmt->execute();
                        echo $stmt->fetch()['total'];
                        ?>,
                        <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_trabajo WHERE estado = 'En Proceso'");
                        $stmt->execute();
                        echo $stmt->fetch()['total'];
                        ?>,
                        <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_trabajo WHERE estado = 'Completado'");
                        $stmt->execute();
                        echo $stmt->fetch()['total'];
                        ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 159, 64, 0.7)', // Naranja para pendientes
                        'rgba(54, 162, 235, 0.7)', // Azul para en proceso
                        'rgba(75, 192, 192, 0.7)' // Verde para completadas
                    ],
                    borderColor: [
                        'rgb(255, 159, 64)',
                        'rgb(54, 162, 235)',
                        'rgb(75, 192, 192)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>

    
</body>

</html>