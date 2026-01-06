<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitizar y validar datos
    $nombre = trim($_POST['nombre']);
    $apellido_paterno = trim($_POST['apellido_paterno']);
    $apellido_materno = trim($_POST['apellido_materno']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $rfc = strtoupper(trim($_POST['rfc']));
    $curp = strtoupper(trim($_POST['curp']));
    $nss = trim($_POST['nss']);
    $genero = $_POST['genero'];
    $puesto = trim($_POST['puesto']);
    $departamento = $_POST['departamento'];
    $salario_diario = (float)$_POST['salario_diario'];
    $fecha_contratacion = $_POST['fecha_contratacion'];
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    $direccion = trim($_POST['direccion']);
    
    // Validar edad (mayor de 18 años)
    $hoy = new DateTime();
    $nacimiento = new DateTime($fecha_nacimiento);
    $edad = $hoy->diff($nacimiento)->y;
    
    if ($edad < 18) {
        $error = "El empleado debe ser mayor de 18 años";
    } else {
        // Verificar RFC único
        $check_rfc = $conn->prepare("SELECT id FROM empleados WHERE rfc = ?");
        $check_rfc->bind_param("s", $rfc);
        $check_rfc->execute();
        
        if ($check_rfc->get_result()->num_rows > 0) {
            $error = "El RFC ya está registrado para otro empleado";
        } else {
            // Verificar CURP único
            $check_curp = $conn->prepare("SELECT id FROM empleados WHERE curp = ?");
            $check_curp->bind_param("s", $curp);
            $check_curp->execute();
            
            if ($check_curp->get_result()->num_rows > 0) {
                $error = "El CURP ya está registrado para otro empleado";
            } else {
                // Verificar NSS único
                $check_nss = $conn->prepare("SELECT id FROM empleados WHERE nss = ?");
                $check_nss->bind_param("s", $nss);
                $check_nss->execute();
                
                if ($check_nss->get_result()->num_rows > 0) {
                    $error = "El NSS ya está registrado para otro empleado";
                } else {
                    // Insertar empleado
                    $stmt = $conn->prepare("INSERT INTO empleados (
                        nombre, apellido_paterno, apellido_materno, fecha_nacimiento,
                        genero, rfc, curp, nss, puesto, departamento, salario_diario,
                        fecha_contratacion, telefono, email, direccion, estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')");
                    
                    $stmt->bind_param(
                        "ssssssssssdssss",
                        $nombre,
                        $apellido_paterno,
                        $apellido_materno,
                        $fecha_nacimiento,
                        $genero,
                        $rfc,
                        $curp,
                        $nss,
                        $puesto,
                        $departamento,
                        $salario_diario,
                        $fecha_contratacion,
                        $telefono,
                        $email,
                        $direccion
                    );
                    
                    if ($stmt->execute()) {
                        $empleado_id = $stmt->insert_id;
                        $success = "Empleado agregado exitosamente. ID: $empleado_id";
                        
                        // Crear usuario automático si se solicitó
                        if (isset($_POST['crear_usuario']) && $_POST['crear_usuario'] == '1') {
                            $username = strtolower(substr($nombre, 0, 1) . $apellido_paterno);
                            $username = preg_replace('/[^a-z0-9]/', '', $username);
                            $password = generarContrasenaTemporal();
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            
                            $usuario_stmt = $conn->prepare("INSERT INTO usuarios (empleado_id, username, password_hash, rol) VALUES (?, ?, ?, 'empleado')");
                            $usuario_stmt->bind_param("iss", $empleado_id, $username, $password_hash);
                            
                            if ($usuario_stmt->execute()) {
                                $success .= "<br>Usuario creado: <strong>$username</strong><br>Contraseña temporal: <strong>$password</strong>";
                            }
                        }
                        
                        // Redirigir después de 5 segundos
                        header("refresh:5;url=ver.php?id=$empleado_id");
                    } else {
                        $error = "Error al agregar empleado: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Función para generar contraseña temporal
function generarContrasenaTemporal($longitud = 8) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $contrasena = '';
    
    for ($i = 0; $i < $longitud; $i++) {
        $contrasena .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    
    return $contrasena;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Empleado - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-stepper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .form-stepper::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--medium-gray);
            z-index: 1;
        }
        
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            background: var(--light-gray);
            border: 2px solid var(--medium-gray);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            color: var(--text-light);
            transition: var(--transition);
        }
        
        .step.active .step-number {
            background: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }
        
        .step.completed .step-number {
            background: var(--success-color);
            border-color: var(--success-color);
            color: white;
        }
        
        .step-label {
            font-size: 0.9rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .step.active .step-label {
            color: var(--accent-color);
        }
        
        .step-content {
            display: none;
        }
        
        .step-content.active {
            display: block;
            animation: fadeIn 0.5s ease-out;
        }
        
        .step-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--medium-gray);
        }
        
        .calculadora-salario {
            background: #f0f9ff;
            border: 1px solid #bee3f8;
            border-radius: var(--radius-md);
            padding: 20px;
            margin-top: 20px;
        }
        
        .resultado-salario {
            text-align: center;
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: var(--radius-sm);
            border: 2px solid var(--accent-color);
        }
        
        .salario-quincenal {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent-color);
        }
        
        .salario-mensual {
            font-size: 1.2rem;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        .crear-usuario-card {
            background: #f0f9ff;
            border: 1px solid #bee3f8;
            border-radius: var(--radius-md);
            padding: 20px;
            margin-top: 20px;
        }
        
        .usuario-generado {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            border-radius: var(--radius-md);
            padding: 15px;
            margin-top: 15px;
            display: none;
        }
        
        .usuario-generado.show {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }
        
        .info-ayuda {
            background: var(--light-gray);
            padding: 15px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border-left: 4px solid var(--accent-color);
        }
        
        .info-ayuda h4 {
            margin-top: 0;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-user-plus"></i> Agregar Nuevo Empleado</h1>
            <p>Completa todos los datos requeridos para registrar un nuevo empleado</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <div style="margin-top: 15px;">
                    <p>Serás redirigido al perfil del empleado en 5 segundos...</p>
                    <a href="ver.php?id=<?php echo isset($empleado_id) ? $empleado_id : ''; ?>" class="btn-primary">
                        <i class="fas fa-user"></i> Ver Empleado Ahora
                    </a>
                    <a href="agregar.php" class="btn-secondary">
                        <i class="fas fa-plus"></i> Agregar Otro
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <div class="form-stepper">
                <div class="step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Datos Personales</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Información Laboral</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Contacto y Finalizar</div>
                </div>
            </div>
            
            <form method="POST" id="formEmpleado" enctype="multipart/form-data">
                <div class="step-content active" id="step-1">
                    <div class="info-ayuda">
                        <h4><i class="fas fa-info-circle"></i> Información Personal</h4>
                        <p>Ingresa los datos personales del empleado. Todos los campos marcados con * son obligatorios.</p>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre(s)*</label>
                            <input type="text" 
                                   name="nombre" 
                                   class="form-control" 
                                   required
                                   pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+"
                                   title="Solo letras y espacios"
                                   placeholder="Ej: Juan Carlos">
                        </div>
                        
                        <div class="form-group">
                            <label>Apellido Paterno*</label>
                            <input type="text" 
                                   name="apellido_paterno" 
                                   class="form-control" 
                                   required
                                   pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+"
                                   title="Solo letras"
                                   placeholder="Ej: Pérez">
                        </div>
                        
                        <div class="form-group">
                            <label>Apellido Materno</label>
                            <input type="text" 
                                   name="apellido_materno" 
                                   class="form-control"
                                   pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+"
                                   title="Solo letras"
                                   placeholder="Ej: López">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de Nacimiento*</label>
                            <input type="date" 
                                   name="fecha_nacimiento" 
                                   class="form-control" 
                                   required
                                   max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                            <small>Debe ser mayor de 18 años</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Género*</label>
                            <select name="genero" class="form-control" required>
                                <option value="">Seleccionar...</option>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>RFC*</label>
                            <input type="text" 
                                   name="rfc" 
                                   class="form-control" 
                                   required
                                   pattern="^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$"
                                   title="Formato: 4 letras, 6 números, 3 caracteres alfanuméricos"
                                   placeholder="Ej: PERJ800101ABC"
                                   oninput="this.value = this.value.toUpperCase()">
                            <small>Formato: 4 letras, 6 números, 3 caracteres alfanuméricos</small>
                        </div>
                        
                        <div class="form-group">
                            <label>CURP*</label>
                            <input type="text" 
                                   name="curp" 
                                   class="form-control" 
                                   required
                                   pattern="^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9]{2}$"
                                   title="Formato CURP válido"
                                   placeholder="Ej: PERJ800101HDFLRN01"
                                   oninput="this.value = this.value.toUpperCase()">
                        </div>
                        
                        <div class="form-group">
                            <label>NSS*</label>
                            <input type="text" 
                                   name="nss" 
                                   class="form-control" 
                                   required
                                   pattern="^\d{11}$"
                                   title="11 dígitos numéricos"
                                   placeholder="Ej: 12345678901">
                            <small>11 dígitos numéricos</small>
                        </div>
                    </div>
                    
                    <div class="step-navigation">
                        <button type="button" class="btn-secondary" onclick="window.history.back()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="button" class="btn-primary" onclick="siguientePaso()">
                            Siguiente <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <div class="step-content" id="step-2">
                    <div class="info-ayuda">
                        <h4><i class="fas fa-briefcase"></i> Información Laboral</h4>
                        <p>Ingresa los datos relacionados con el puesto y remuneración del empleado.</p>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Puesto*</label>
                            <input type="text" 
                                   name="puesto" 
                                   class="form-control" 
                                   required
                                   placeholder="Ej: Desarrollador Web">
                        </div>
                        
                        <div class="form-group">
                            <label>Departamento*</label>
                            <select name="departamento" class="form-control" required>
                                <option value="">Seleccionar...</option>
                                <option value="Administración">Administración</option>
                                <option value="Recursos Humanos">Recursos Humanos</option>
                                <option value="Contabilidad">Contabilidad</option>
                                <option value="Ventas">Ventas</option>
                                <option value="Producción">Producción</option>
                                <option value="TI">Tecnologías de Información</option>
                                <option value="Almacén">Almacén</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Salario Diario*</label>
                            <input type="number" 
                                   name="salario_diario" 
                                   class="form-control" 
                                   step="0.01" 
                                   min="207.44" 
                                   required
                                   placeholder="Ej: 500.00"
                                   id="salario_diario">
                            <small>Salario mínimo: $207.44</small>
                        </div>
                    </div>
                    
                    <div class="calculadora-salario">
                        <h4><i class="fas fa-calculator"></i> Calculadora de Salarios</h4>
                        <p>Proyección basada en el salario diario ingresado:</p>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Días Laborales por Quincena</label>
                                <input type="number" 
                                       id="dias_quincena" 
                                       class="form-control" 
                                       value="15" 
                                       min="1" 
                                       max="30">
                            </div>
                            
                            <div class="form-group">
                                <label>Horas Extra Estimadas</label>
                                <input type="number" 
                                       id="horas_extra" 
                                       class="form-control" 
                                       value="0" 
                                       min="0" 
                                       step="0.5">
                            </div>
                        </div>
                        
                        <div class="resultado-salario">
                            <div class="salario-quincenal" id="salario_quincenal">$0.00</div>
                            <div class="salario-mensual" id="salario_mensual">$0.00 mensual</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de Contratación*</label>
                            <input type="date" 
                                   name="fecha_contratacion" 
                                   class="form-control" 
                                   required
                                   max="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="step-navigation">
                        <button type="button" class="btn-secondary" onclick="anteriorPaso()">
                            <i class="fas fa-arrow-left"></i> Anterior
                        </button>
                        <button type="button" class="btn-primary" onclick="siguientePaso()">
                            Siguiente <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <div class="step-content" id="step-3">
                    <div class="info-ayuda">
                        <h4><i class="fas fa-address-book"></i> Información de Contacto</h4>
                        <p>Ingresa los datos de contacto del empleado. Estos campos son opcionales pero recomendados.</p>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="tel" 
                                   name="telefono" 
                                   class="form-control"
                                   pattern="[\d\s\-\(\)]{10,15}"
                                   placeholder="Ej: (55) 1234-5678">
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" 
                                   name="email" 
                                   class="form-control"
                                   placeholder="Ej: juan.perez@empresa.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Dirección</label>
                        <textarea name="direccion" 
                                  class="form-control" 
                                  rows="4"
                                  placeholder="Calle, Número, Colonia, Ciudad, C.P."></textarea>
                    </div>
                    
                    <div class="crear-usuario-card">
                        <h4><i class="fas fa-user-circle"></i> Crear Usuario del Sistema</h4>
                        <div class="form-check" style="margin-bottom: 15px;">
                            <input type="checkbox" 
                                   name="crear_usuario" 
                                   id="crear_usuario" 
                                   value="1"
                                   onchange="toggleUsuario()">
                            <label for="crear_usuario">Crear usuario de acceso al sistema</label>
                        </div>
                        
                        <div class="usuario-generado" id="usuario_generado">
                            <p><strong>Usuario generado automáticamente:</strong></p>
                            <p>Nombre de usuario: <span id="username_generado"></span></p>
                            <p>Contraseña temporal: <span id="password_generada"></span></p>
                            <small>Recomienda al empleado cambiar la contraseña en su primer acceso.</small>
                        </div>
                    </div>
                    
                    <div class="step-navigation">
                        <button type="button" class="btn-secondary" onclick="anteriorPaso()">
                            <i class="fas fa-arrow-left"></i> Anterior
                        </button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Empleado
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Control del stepper
        let pasoActual = 1;
        const totalPasos = 3;
        
        function siguientePaso() {
            if (validarPasoActual()) {
                if (pasoActual < totalPasos) {
                    // Marcar paso actual como completado
                    document.querySelector(`.step[data-step="${pasoActual}"]`).classList.remove('active');
                    document.querySelector(`.step[data-step="${pasoActual}"]`).classList.add('completed');
                    
                    pasoActual++;
                    
                    // Mostrar nuevo paso
                    document.querySelector(`.step[data-step="${pasoActual}"]`).classList.add('active');
                    document.querySelectorAll('.step-content').forEach(step => step.classList.remove('active'));
                    document.getElementById(`step-${pasoActual}`).classList.add('active');
                    
                    // En el último paso, generar usuario si está marcado
                    if (pasoActual === 3) {
                        generarUsuario();
                    }
                }
            }
        }
        
        function anteriorPaso() {
            if (pasoActual > 1) {
                // Quitar completado del paso actual
                document.querySelector(`.step[data-step="${pasoActual}"]`).classList.remove('active');
                document.querySelector(`.step[data-step="${pasoActual}"]`).classList.remove('completed');
                
                pasoActual--;
                
                // Mostrar paso anterior
                document.querySelector(`.step[data-step="${pasoActual}"]`).classList.add('active');
                document.querySelectorAll('.step-content').forEach(step => step.classList.remove('active'));
                document.getElementById(`step-${pasoActual}`).classList.add('active');
            }
        }
        
        function validarPasoActual() {
            const paso = document.getElementById(`step-${pasoActual}`);
            const inputs = paso.querySelectorAll('[required]');
            
            for (let input of inputs) {
                if (!input.value.trim()) {
                    input.focus();
                    input.style.borderColor = '#f56565';
                    
                    // Mostrar mensaje de error
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'alert alert-error';
                    errorMsg.style.marginTop = '10px';
                    errorMsg.innerHTML = `<i class="fas fa-exclamation-circle"></i> Completa todos los campos requeridos`;
                    
                    // Remover mensajes anteriores
                    const oldError = paso.querySelector('.alert-error');
                    if (oldError) oldError.remove();
                    
                    paso.insertBefore(errorMsg, paso.querySelector('.step-navigation'));
                    
                    setTimeout(() => {
                        input.style.borderColor = '';
                    }, 2000);
                    
                    return false;
                }
                
                // Validaciones específicas
                if (input.name === 'rfc') {
                    const rfcRegex = /^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
                    if (!rfcRegex.test(input.value)) {
                        alert('RFC no válido. Verifica el formato.');
                        input.focus();
                        return false;
                    }
                }
                
                if (input.name === 'curp') {
                    const curpRegex = /^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9]{2}$/;
                    if (!curpRegex.test(input.value)) {
                        alert('CURP no válido. Verifica el formato.');
                        input.focus();
                        return false;
                    }
                }
                
                if (input.name === 'nss') {
                    const nssRegex = /^\d{11}$/;
                    if (!nssRegex.test(input.value)) {
                        alert('NSS no válido. Debe tener 11 dígitos.');
                        input.focus();
                        return false;
                    }
                }
                
                if (input.name === 'fecha_nacimiento') {
                    const fecha = new Date(input.value);
                    const hoy = new Date();
                    const edad = hoy.getFullYear() - fecha.getFullYear();
                    const mes = hoy.getMonth() - fecha.getMonth();
                    
                    if (mes < 0 || (mes === 0 && hoy.getDate() < fecha.getDate())) {
                        edad--;
                    }
                    
                    if (edad < 18) {
                        alert('El empleado debe ser mayor de 18 años');
                        input.focus();
                        return false;
                    }
                }
            }
            
            return true;
        }
        
        // Calcular salarios automáticamente
        function calcularSalarios() {
            const salarioDiario = parseFloat(document.getElementById('salario_diario').value) || 0;
            const diasQuincena = parseInt(document.getElementById('dias_quincena').value) || 15;
            const horasExtra = parseFloat(document.getElementById('horas_extra').value) || 0;
            
            // Calcular salario quincenal (sin horas extra)
            const salarioQuincenal = salarioDiario * diasQuincena;
            
            // Calcular salario mensual (aproximado)
            const salarioMensual = salarioDiario * 30;
            
            // Mostrar resultados
            document.getElementById('salario_quincenal').textContent = 
                '$' + salarioQuincenal.toFixed(2);
            document.getElementById('salario_mensual').textContent = 
                '$' + salarioMensual.toFixed(2) + ' mensual';
        }
        
        // Generar usuario automático
        function generarUsuario() {
            const nombre = document.querySelector('input[name="nombre"]').value;
            const apellido = document.querySelector('input[name="apellido_paterno"]').value;
            
            if (nombre && apellido) {
                // Crear username: primera letra del nombre + apellido completo
                const username = (nombre.charAt(0) + apellido).toLowerCase().replace(/[^a-z0-9]/g, '');
                
                // Generar contraseña temporal
                const caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                let password = '';
                for (let i = 0; i < 8; i++) {
                    password += caracteres[Math.floor(Math.random() * caracteres.length)];
                }
                
                document.getElementById('username_generado').textContent = username;
                document.getElementById('password_generada').textContent = password;
            }
        }
        
        function toggleUsuario() {
            const checkbox = document.getElementById('crear_usuario');
            const usuarioGenerado = document.getElementById('usuario_generado');
            
            if (checkbox.checked) {
                generarUsuario();
                usuarioGenerado.classList.add('show');
            } else {
                usuarioGenerado.classList.remove('show');
            }
        }
        
        // Event listeners
        document.getElementById('salario_diario').addEventListener('input', calcularSalarios);
        document.getElementById('dias_quincena').addEventListener('input', calcularSalarios);
        document.getElementById('horas_extra').addEventListener('input', calcularSalarios);
        
        // Inicializar cálculos
        calcularSalarios();
        
        // Validar formulario completo antes de enviar
        document.getElementById('formEmpleado').addEventListener('submit', function(e) {
            if (!validarPasoActual()) {
                e.preventDefault();
                return false;
            }
            
            // Validar todos los pasos
            for (let i = 1; i <= totalPasos; i++) {
                const paso = document.getElementById(`step-${i}`);
                const inputs = paso.querySelectorAll('[required]');
                
                for (let input of inputs) {
                    if (!input.value.trim()) {
                        alert('Por favor completa todos los campos requeridos');
                        e.preventDefault();
                        return false;
                    }
                }
            }
            
            return true;
        });
    </script>
</body>
</html>