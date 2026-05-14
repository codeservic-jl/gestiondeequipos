<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: lista.php");
    exit();
}
require_once '../../config/database.php';

$id_seguimiento = (int)$_GET['id'];
$base_url = "../../";

// Cargar seguimiento + venta existente
$stmt = $conn->prepare("
    SELECT so.*,
           v.id_venta, v.producto as v_producto,
           v.valor_compra as v_compra, v.valor_venta as v_precio, v.ganancia_neta as v_ganancia
    FROM seguimientos_orden so
    LEFT JOIN ventas_orden v ON v.id_seguimiento = so.id_seguimiento
    WHERE so.id_seguimiento = ?
");
$stmt->execute([$id_seguimiento]);
$seg = $stmt->fetch();

if (!$seg) { header("Location: lista.php"); exit(); }

$id_orden = $seg['id_orden'];
$error = null;
$success = null;

// Tipos de servicio
$tipos_servicio = $conn->query("SELECT * FROM tipos_servicio WHERE estado = 1 ORDER BY nombre")->fetchAll();

// Técnicos
$tecnicos = $conn->query("
    SELECT u.id_usuario, u.nombre_completo, tu.nombre as tipo
    FROM usuarios u INNER JOIN tipos_usuario tu ON u.id_tipo = tu.id_tipo
    WHERE u.estado = 1 AND u.id_tipo = 2
")->fetchAll();

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        $tipo_servicio        = trim($_POST['tipo_servicio'] ?? '');
        $procedimiento        = trim($_POST['procedimiento'] ?? '');
        $valor_cobrar         = floatval($_POST['valor_cobrar'] ?? 0);
        $id_tecnico           = (int)($_POST['id_tecnico'] ?? 0);

        // Colega
        $costo_externo        = null;
        $descripcion_externo  = null;
        if (!empty($_POST['envio_colega']) && $_POST['envio_colega'] === 'si') {
            $descripcion_externo = trim($_POST['descripcion_colega'] ?? '');
            $costo_externo       = floatval($_POST['costo_colega'] ?? 0);
        }

        if (empty($tipo_servicio)) throw new Exception("Selecciona el tipo de servicio.");
        if (empty($procedimiento)) throw new Exception("El procedimiento no puede estar vacío.");
        if ($id_tecnico === 0)    throw new Exception("Selecciona un técnico.");

        // Actualizar seguimiento
        $stmt = $conn->prepare("
            UPDATE seguimientos_orden
            SET tipo_servicio=?, procedimiento=?, valor_cobrar=?,
                id_tecnico=?, costo_externo=?, descripcion_externo=?
            WHERE id_seguimiento=?
        ");
        $stmt->execute([$tipo_servicio, $procedimiento, $valor_cobrar,
                        $id_tecnico, $costo_externo, $descripcion_externo,
                        $id_seguimiento]);

        // Venta
        $vendio = !empty($_POST['vendio_algo']) && $_POST['vendio_algo'] === 'si';
        if ($vendio) {
            $producto     = trim($_POST['producto_vendido'] ?? '');
            $valor_compra = floatval($_POST['valor_compra'] ?? 0);
            $valor_venta  = floatval($_POST['valor_venta'] ?? 0);
            $ganancia     = $valor_venta - $valor_compra;

            if (!empty($producto)) {
                if ($seg['id_venta']) {
                    // Actualizar venta existente
                    $stmt = $conn->prepare("
                        UPDATE ventas_orden
                        SET producto=?, valor_compra=?, valor_venta=?, ganancia_neta=?
                        WHERE id_venta=?
                    ");
                    $stmt->execute([$producto, $valor_compra, $valor_venta, $ganancia, $seg['id_venta']]);
                } else {
                    // Insertar nueva venta
                    $stmt = $conn->prepare("
                        INSERT INTO ventas_orden (id_orden, id_seguimiento, producto, valor_compra, valor_venta, ganancia_neta, id_usuario_registro)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$id_orden, $id_seguimiento, $producto, $valor_compra, $valor_venta, $ganancia, $_SESSION['user_id']]);
                }
            }
        } else {
            // Si desmarcó "vendió algo" y había venta, eliminarla
            if ($seg['id_venta']) {
                $conn->prepare("DELETE FROM ventas_orden WHERE id_venta=?")->execute([$seg['id_venta']]);
            }
        }

        $conn->commit();
        header("Location: ver.php?id=$id_orden&msg=seguimiento_actualizado");
        exit();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Editar Seguimiento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: 'Segoe UI', system-ui, sans-serif; }
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 8px rgba(0,0,0,.07); }
        .card-header {
            padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 700; color: #166534;
            text-transform: uppercase; letter-spacing: .5px;
        }
        label { font-size: 13px; font-weight: 600; color: #374151; display: block; margin-bottom: 5px; }
        input, select, textarea {
            width: 100%; padding: 10px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 14px; color: #1e293b; outline: none; transition: border-color .15s;
            background: white;
        }
        input:focus, select:focus, textarea:focus { border-color: #16a34a; }
        .input-prefix {
            position: relative;
        }
        .input-prefix span {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            color: #64748b; font-weight: 600; font-size: 14px; pointer-events: none;
        }
        .input-prefix input { padding-left: 24px; }
        .dashed-box {
            border: 2px dashed; border-radius: 14px; padding: 18px 20px;
        }
    </style>
</head>
<body>
<?php include '../../includes/navbar.php'; ?>

<div class="main-content">
<div class="container mx-auto px-4 py-8 max-w-3xl">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                <i class="fas fa-edit text-amber-500 mr-2"></i>Editar Seguimiento
            </h1>
            <p class="text-sm text-gray-500 mt-1">Seguimiento #<?php echo $id_seguimiento; ?> · Orden <?php echo htmlspecialchars($seg['id_orden']); ?></p>
        </div>
        <a href="ver.php?id=<?php echo $id_orden; ?>"
            class="flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-semibold">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-lg mb-5 flex gap-3">
        <i class="fas fa-exclamation-triangle text-red-400 mt-0.5"></i>
        <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></p>
    </div>
    <?php endif; ?>

    <form method="POST">
        <!-- Datos principales -->
        <div class="card mb-5">
            <div class="card-header"><i class="fas fa-tools"></i> Datos del servicio</div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">

                <div>
                    <label>Tipo de servicio *</label>
                    <select name="tipo_servicio" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($tipos_servicio as $ts): ?>
                        <option value="<?php echo htmlspecialchars($ts['nombre']); ?>"
                            <?php echo $seg['tipo_servicio'] === $ts['nombre'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ts['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Técnico responsable *</label>
                    <select name="id_tecnico" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($tecnicos as $tec): ?>
                        <option value="<?php echo $tec['id_usuario']; ?>"
                            <?php echo $seg['id_tecnico'] == $tec['id_usuario'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tec['nombre_completo']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Valor a cobrar</label>
                    <div class="input-prefix">
                        <span>$</span>
                        <input type="number" name="valor_cobrar" min="0" step="0.01"
                               oninput="calcGananciaOrden()"
                               value="<?php echo $seg['valor_cobrar']; ?>"
                               placeholder="0.00">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label>Procedimiento realizado *</label>
                    <textarea name="procedimiento" rows="4" required
                              placeholder="Describa el trabajo realizado…"><?php echo htmlspecialchars($seg['procedimiento']); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Venta de producto -->
        <div class="card mb-5">
            <div class="p-5">
                <div class="dashed-box" style="border-color:#fcd34d;background:#fffbeb;">
                    <label class="flex items-center gap-3 cursor-pointer select-none" style="display:flex;margin-bottom:0;">
                        <input type="checkbox" name="vendio_algo" id="vendio_algo" value="si"
                               onchange="toggleVenta(this)"
                               <?php echo $seg['id_venta'] ? 'checked' : ''; ?>
                               class="w-5 h-5 accent-green-600 cursor-pointer">
                        <span class="font-semibold text-gray-800">
                            <i class="fas fa-shopping-bag text-amber-500 mr-1"></i>
                            ¿Se vendió algún producto en esta visita?
                        </span>
                    </label>
                    <div id="seccion_venta" class="mt-4 space-y-3" style="display:<?php echo $seg['id_venta'] ? 'block' : 'none'; ?>">
                        <div>
                            <label>Producto vendido *</label>
                            <input type="text" name="producto_vendido" id="producto_vendido"
                                   value="<?php echo htmlspecialchars($seg['v_producto'] ?? ''); ?>"
                                   placeholder="Ej: Memoria RAM 8GB…">
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label>Valor de compra *</label>
                                <div class="input-prefix">
                                    <span>$</span>
                                    <input type="number" name="valor_compra" id="valor_compra"
                                           min="0" step="0.01" oninput="calcGananciaVenta()"
                                           value="<?php echo $seg['v_compra'] ?? '0'; ?>">
                                </div>
                            </div>
                            <div>
                                <label>Valor de venta *</label>
                                <div class="input-prefix">
                                    <span>$</span>
                                    <input type="number" name="valor_venta" id="valor_venta"
                                           min="0" step="0.01" oninput="calcGananciaVenta()"
                                           value="<?php echo $seg['v_precio'] ?? '0'; ?>">
                                </div>
                            </div>
                            <div>
                                <label>Ganancia <span style="font-size:11px;font-weight:400;color:#6b7280">(auto)</span></label>
                                <div class="input-prefix">
                                    <span>$</span>
                                    <input type="number" id="ganancia_display" readonly
                                           style="background:#f1f5f9;color:#166534;font-weight:700;"
                                           value="<?php echo $seg['v_ganancia'] ?? '0'; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colega / externo -->
        <div class="card mb-6">
            <div class="p-5">
                <div class="dashed-box" style="border-color:#bfdbfe;background:#eff6ff;">
                    <label class="flex items-center gap-3 cursor-pointer select-none" style="display:flex;margin-bottom:0;">
                        <input type="checkbox" name="envio_colega" id="envio_colega" value="si"
                               onchange="toggleColega(this)"
                               <?php echo !empty($seg['costo_externo']) ? 'checked' : ''; ?>
                               class="w-5 h-5 accent-blue-600 cursor-pointer">
                        <span class="font-semibold text-gray-800">
                            <i class="fas fa-people-carry text-blue-500 mr-1"></i>
                            ¿Se envió a un colega o taller externo?
                        </span>
                    </label>
                    <div id="seccion_colega" class="mt-4 grid grid-cols-2 gap-3"
                         style="display:<?php echo !empty($seg['costo_externo']) ? 'grid' : 'none'; ?>">
                        <div>
                            <label>Descripción</label>
                            <input type="text" name="descripcion_colega"
                                   value="<?php echo htmlspecialchars($seg['descripcion_externo'] ?? ''); ?>"
                                   placeholder="Ej: Taller de pantallas Juan…">
                        </div>
                        <div>
                            <label>Lo que nos cobran</label>
                            <div class="input-prefix">
                                <span>$</span>
                                <input type="number" name="costo_colega" id="costo_colega"
                                       min="0" step="0.01" oninput="calcGananciaOrden()"
                                       value="<?php echo $seg['costo_externo'] ?? '0'; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones -->
        <div class="flex justify-end gap-3">
            <a href="ver.php?id=<?php echo $id_orden; ?>"
                class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-semibold text-sm">
                Cancelar
            </a>
            <button type="submit"
                class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl font-semibold text-sm flex items-center gap-2">
                <i class="fas fa-save"></i> Guardar cambios
            </button>
        </div>
    </form>
</div>
</div>

<script>
function toggleVenta(cb) {
    var s = document.getElementById('seccion_venta');
    s.style.display = cb.checked ? 'block' : 'none';
    ['producto_vendido','valor_compra','valor_venta'].forEach(function(id){
        var el = document.getElementById(id);
        if (el) el.required = cb.checked;
    });
}
function toggleColega(cb) {
    var s = document.getElementById('seccion_colega');
    s.style.display = cb.checked ? 'grid' : 'none';
}
function calcGananciaVenta() {
    var c = parseFloat(document.getElementById('valor_compra').value) || 0;
    var v = parseFloat(document.getElementById('valor_venta').value)  || 0;
    var d = document.getElementById('ganancia_display');
    d.value = (v - c).toFixed(2);
    d.style.color = (v - c) >= 0 ? '#166534' : '#dc2626';
}
function calcGananciaOrden() {
    // Placeholder — visual feedback en el form de edición
}
// Inicializar required si venta ya marcada
(function(){
    var cb = document.getElementById('vendio_algo');
    if (cb && cb.checked) {
        ['producto_vendido','valor_compra','valor_venta'].forEach(function(id){
            var el = document.getElementById(id);
            if (el) el.required = true;
        });
    }
})();
</script>
</body>
</html>
