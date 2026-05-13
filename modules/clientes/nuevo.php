<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../config/database.php';

$base_url = "../../";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validaciones mejoradas de campos
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

        // Registrar el nuevo cliente
        $stmt = $conn->prepare("INSERT INTO clientes (
            identificacion, nombre_apellido, empresa, id_tipo_cliente, telefono, 
            direccion, email, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");

        $stmt->execute([
            $_POST['identificacion'],
            $_POST['nombre_apellido'],
            $_POST['empresa'],
            $_POST['id_tipo_cliente'],
            $_POST['telefono'],
            $_POST['direccion'],
            $_POST['email']
        ]);

        header("Location: lista.php");
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Cliente - Gestión de Equipos RGE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bg-navy-blue {
            background-color: #000080;
        }

        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
            animation: fadeIn 0.3s ease-in;
        }

        .input-error {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .input-success {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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
    </style>
</head>

<body class="bg-gray-100">
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content">
        <div class="container mx-auto px-4 py-8">
            <div class="max-w-3xl mx-auto">
                <div class="card p-8">
                    <!-- Header -->
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full mb-4">
                            <i class="fas fa-user-plus text-white text-2xl"></i>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">Nuevo Cliente</h2>
                        <p class="text-gray-600">Complete la información del cliente</p>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-r-lg">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-red-700"><?php echo $error; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6" onsubmit="return validarFormulario()" id="clienteForm">
                        <!-- Tipo de Cliente -->
                        <div class="bg-gray-50 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-users mr-2 text-blue-500"></i>
                                Tipo de Cliente
                            </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition-colors">
                                    <input type="radio" name="id_tipo_cliente" value="1" class="mr-3" onchange="actualizarValidaciones()">
                            <div>
                                        <div class="font-medium text-gray-800">Personal</div>
                                        <div class="text-sm text-gray-600">Cédula de 10 dígitos</div>
                            </div>
                                </label>
                                <label class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition-colors">
                                    <input type="radio" name="id_tipo_cliente" value="2" class="mr-3" onchange="actualizarValidaciones()">
                            <div>
                                        <div class="font-medium text-gray-800">Empresa</div>
                                        <div class="text-sm text-gray-600">RUC de 13 dígitos</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Información Personal -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group">
                                <input type="text" name="identificacion" id="identificacion" required
                                    class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder=" "
                                    oninput="validarIdentificacion(this)"
                                    onkeypress="return soloNumeros(event)"
                                    onpaste="manejarPegado(event)"
                                    maxlength="13">
                                <i class="fas fa-id-card form-icon"></i>
                                <label class="floating-label">Identificación *</label>
                                <span class="error-message" id="error-identificacion"></span>
                            </div>

                            <div class="form-group">
                                <input type="text" name="nombre_apellido" id="nombre_apellido" required
                                    class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder=" "
                                oninput="validarNombre(this)">
                                <i class="fas fa-user form-icon"></i>
                                <label class="floating-label">Nombre y Apellido *</label>
                                <span class="error-message" id="error-nombre"></span>
                        </div>

                            <div class="form-group">
                                <input type="text" name="empresa" id="empresa"
                                    class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder=" ">
                                <i class="fas fa-building form-icon"></i>
                                <label class="floating-label">Empresa</label>
                        </div>

                            <div class="form-group">
                                <input type="tel" name="telefono" id="telefono" required
                                    class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder=" "
                                    oninput="validarTelefono(this)"
                                    onkeypress="return soloNumeros(event)"
                                    onpaste="manejarPegado(event)"
                                    maxlength="12">
                                <i class="fas fa-phone form-icon"></i>
                                <label class="floating-label">Teléfono *</label>
                                <span class="error-message" id="error-telefono"></span>
                        </div>

                            <div class="form-group md:col-span-2">
                                <input type="text" name="direccion" id="direccion"
                                    class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder=" ">
                                <i class="fas fa-map-marker-alt form-icon"></i>
                                <label class="floating-label">Dirección</label>
                        </div>

                            <div class="form-group md:col-span-2">
                                <input type="email" name="email" id="email"
                                    class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                    placeholder=" "
                                oninput="validarEmail(this)">
                                <i class="fas fa-envelope form-icon"></i>
                                <label class="floating-label">Email</label>
                                <span class="error-message" id="error-email"></span>
                        </div>
                </div>

                        <!-- Botones -->
                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="lista.php" class="btn-secondary">
                                <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save mr-2"></i>Guardar Cliente
                    </button>
                </div>
                </form>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
        let tipoClienteActual = '1'; // Por defecto Personal

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

        function mostrarError(input, errorId, mostrar, mensaje = '') {
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

        function limpiarValidacion(input, errorId) {
            input.classList.remove('input-error', 'input-success');
            const errorSpan = document.getElementById(errorId);
            errorSpan.style.display = 'none';
        }

        function actualizarValidaciones() {
            const tipoCliente = document.querySelector('input[name="id_tipo_cliente"]:checked');
            if (tipoCliente) {
                tipoClienteActual = tipoCliente.value;
                const identificacion = document.getElementById('identificacion');
                
                // Actualizar maxlength y placeholder según tipo
                if (tipoClienteActual === '2') { // Empresa
                    identificacion.maxLength = 13;
                    identificacion.placeholder = 'Ingrese RUC (13 dígitos)';
                    document.querySelector('label[for="identificacion"]').textContent = 'RUC *';
                } else { // Personal
                    identificacion.maxLength = 10;
                    identificacion.placeholder = 'Ingrese cédula (10 dígitos)';
                    document.querySelector('label[for="identificacion"]').textContent = 'Cédula *';
                }
                
                // Limpiar validación anterior
                limpiarValidacion(identificacion, 'error-identificacion');
                
                // Revalidar si hay contenido
                if (identificacion.value) {
                    validarIdentificacion(identificacion);
                }
            }
        }

        function validarIdentificacion(input) {
            // Limpiar caracteres no numéricos en tiempo real
            limpiarCaracteresNoNumericos(input);
            
            const valor = input.value.trim();
            if (valor === '') {
                limpiarValidacion(input, 'error-identificacion');
                return false;
            }

            let valido = false;
            let mensaje = '';

            if (tipoClienteActual === '2') { // Empresa
                valido = /^\d{13}$/.test(valor);
                mensaje = 'El RUC debe contener exactamente 13 dígitos';
            } else { // Personal
                valido = /^\d{10}$/.test(valor);
                mensaje = 'La cédula debe contener exactamente 10 dígitos';
            }

            mostrarError(input, 'error-identificacion', !valido, mensaje);
            return valido;
        }

        function validarNombre(input) {
            const valor = input.value.trim();
            if (valor === '') {
                limpiarValidacion(input, 'error-nombre');
                return false;
            }

            const palabras = valor.split(/\s+/).filter(palabra => palabra.length > 0);
            const valido = palabras.length <= 6 && palabras.length >= 2;
            const mensaje = palabras.length < 2 ? 'Ingrese nombre y apellido' : 'Máximo 6 palabras permitidas';
            
            mostrarError(input, 'error-nombre', !valido, mensaje);
            return valido;
        }

        function validarTelefono(input) {
            // Limpiar caracteres no numéricos en tiempo real
            limpiarCaracteresNoNumericos(input);
            
            const valor = input.value.trim();
            if (valor === '') {
                limpiarValidacion(input, 'error-telefono');
                return false;
            }

            const valido = /^\d{7,12}$/.test(valor);
            const mensaje = 'El teléfono debe contener entre 7 y 12 dígitos';
            
            mostrarError(input, 'error-telefono', !valido, mensaje);
            return valido;
        }

        function validarEmail(input) {
            const valor = input.value.trim();
            if (valor === '') {
                limpiarValidacion(input, 'error-email');
                return true; // Email es opcional
            }

            const valido = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor);
            const mensaje = 'El formato del email no es válido';
            
            mostrarError(input, 'error-email', !valido, mensaje);
            return valido;
        }

        function validarFormulario() {
            const tipoCliente = document.querySelector('input[name="id_tipo_cliente"]:checked');
            if (!tipoCliente) {
                alert('Por favor, seleccione el tipo de cliente');
                return false;
            }

            const identificacion = document.getElementById('identificacion');
            const nombre = document.getElementById('nombre_apellido');
            const telefono = document.getElementById('telefono');
            const email = document.getElementById('email');

            const validaciones = [
                validarIdentificacion(identificacion),
                validarNombre(nombre),
                validarTelefono(telefono),
                validarEmail(email)
            ];

            return validaciones.every(v => v);
        }
        
        // Validación AJAX para identificación
        let timeoutId;
        document.getElementById('identificacion').addEventListener('input', function(e) {
            clearTimeout(timeoutId);
            const identificacion = e.target.value.trim();
            
            // Solo validar si la identificación es válida según el tipo
            let esValida = false;
            if (tipoClienteActual === '2') {
                esValida = /^\d{13}$/.test(identificacion);
            } else {
                esValida = /^\d{10}$/.test(identificacion);
            }
            
            if (!esValida || identificacion === '') {
                return;
            }

            timeoutId = setTimeout(() => {
                const formData = new FormData();
                formData.append('identificacion', identificacion);

                fetch('validar_identificacion.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        mostrarError(e.target, 'error-identificacion', true, 'Esta identificación ya existe en el sistema');
                    }
                })
                .catch(error => console.error('Error:', error));
            }, 500);
        });

        // Inicializar validaciones al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            actualizarValidaciones();
        });
    </script>
</body>

</html>