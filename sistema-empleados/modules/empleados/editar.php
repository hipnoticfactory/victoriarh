<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

// Obtener datos actuales del empleado
$stmt = $conn->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$empleado = $stmt->get_result()->fetch_assoc();

if (!$empleado) {
    header("Location: index.php?error=empleado_no_encontrado");
    exit();
}

// Procesar actualización
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellido_paterno = trim($_POST['apellido_paterno']);
    $apellido_materno = trim($_POST['apellido_materno']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $rfc = strtoupper(trim($_POST['rfc']));
    $curp = strtoupper(trim($_POST['curp']));
    $nss = trim($_POST['nss']);
    
    // Verificar RFC único (excepto para el mismo empleado)
    $check_rfc = $conn->prepare("SELECT id FROM empleados WHERE rfc = ? AND id != ?");
    $check_rfc->bind_param("si", $rfc, $id);
    $check_rfc->execute();
    
    if ($check_rfc->get_result()->num_rows > 0) {
        $error = "El RFC ya está registrado para otro empleado";
    } else {
        $stmt = $conn->prepare("UPDATE empleados SET 
            nombre = ?, 
            apellido_paterno = ?, 
            apellido_materno = ?, 
            fecha_nacimiento = ?,
            genero = ?,
            rfc = ?,
            curp = ?,
            nss = ?,
            puesto = ?,
            departamento = ?,
            salario_diario = ?,
            estado = ?,
            telefono = ?,
            email = ?,
            direccion = ?
            WHERE id = ?");
        
        $stmt->bind_param(
            "ssssssssssdssssi",
            $nombre,
            $apellido_paterno,
            $apellido_materno,
            $fecha_nacimiento,
            $_POST['genero'],
            $rfc,
            $curp,
            $nss,
            $_POST['puesto'],
            $_POST['departamento'],
            $_POST['salario_diario'],
            $_POST['estado'],
            $_POST['telefono'],
            $_POST['email'],
            $_POST['direccion'],
            $id
        );
        
        if ($stmt->execute()) {
            $success = "Empleado actualizado exitosamente";
            // Actualizar datos en variable $empleado
            $empleado = array_merge($empleado, $_POST);
        } else {
            $error = "Error al actualizar empleado: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Empleado - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .form-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent-color), #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
        }
        
        .form-title h1 {
            margin: 0;
            color: var(--secondary-color);
        }
        
        .form-title p {
            margin: 5px 0 0;
            color: var(--text-light);
        }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--medium-gray);
        }
        
        .tab {
            padding: 15px 30px;
            background: none;
            border: none;
            font-weight: 500;
            color: var(--text-light);
            cursor: pointer;
            position: relative;
            transition: var(--transition);
        }
        
        .tab:hover {
            color: var(--accent-color);
        }
        
        .tab.active {
            color: var(--accent-color);
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--accent-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
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
        <div class="form-container">
            <div class="form-header">
                <div class="form-avatar">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div class="form-title">
                    <h1>Editar Empleado</h1>
                    <p><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno']); ?></p>
                </div>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="tabs">
                <button type="button" class="tab active" data-tab="personal">
                    <i class="fas fa-user"></i> Información Personal
                </button>
                <button type="button" class="tab" data-tab="laboral">
                    <i class="fas fa-briefcase"></i> Información Laboral
                </button>
                <button type="button" class="tab" data-tab="contacto">
                    <i class="fas fa-address-book"></i> Contacto
                </button>
            </div>
            
            <form method="POST" id="empleadoForm">
                <div class="tab-content active" id="tab-personal">
                    <h3>Datos Personales</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre(s)*</label>
                            <input type="text" name="nombre" class="form-control" 
                                   value="<?php echo htmlspecialchars($empleado['nombre']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Apellido Paterno*</label>
                            <input type="text" name="apellido_paterno" class="form-control" 
                                   value="<?php echo htmlspecialchars($empleado['apellido_paterno']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Apellido Materno</label>
                            <input type="text" name="apellido_materno" class="form-control" 
                                   value="<?php echo htmlspecialchars($empleado['apellido_materno']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha de Nacimiento*</label>
                            <input type="date" name="fecha_nacimiento" class="form-control" 
                                   value="<?php echo $empleado['fecha_nacimiento']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Género</label>
                            <select name="genero" class="form-control">
                                <option value="M" <?php echo $empleado['genero'] == 'M' ? 'selected' : ''; ?>>Masculino</option>
                                <option value="F" <?php echo $empleado['genero'] == 'F' ? 'selected' : ''; ?>>Femenino</option>
                                <option value="Otro" <?php echo $empleado['genero'] == 'Otro' ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>RFC*</label>
                            <input type="text" name="rfc" class="form-control" 
                                   value="<?php echo htmlspecialchars($empleado['rfc']); ?>" required
                                   pattern="^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$">
                            <small>Formato: 4 letras, 6 números, 3 caracteres alfanuméricos</small>
                        </div>
                        
                        <div class="form-group">
                            <label>CURP*</label>
                            <input type="text" name="curp" class="form-control" 
                                   value="<?php echo htmlspecialchars($empleado['curp']); ?>" required
                                   pattern="^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9]{2}$">
                        </div>
                        
                        <div class="form-group">
                            <label>NSS*</label>
                            <input type="text" name="nss" class="form-control" 
                                   value="<?php echo htmlspecialchars($empleado['nss']); ?>" required
                                   pattern="^\d{11}$">
                            <small>11 dígitos numéricos</small>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content" id="tab-laboral">
                    <h3>Información Laboral</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Puesto*</label>
                            <input type="text" name="puesto" class="form-control" 
                                   value="<?php echo htmlspecialchars($empleado['puesto']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Departamento*</label>
                            <select name="departamento" class="form-control" required>
                                <option value="">Seleccionar...</option>
                                <option value="Administración" <?php echo $empleado['departamento'] == 'Administración' ? 'selected' : ''; ?>>Administración</option>
                                <option value="Recursos Humanos" <?php echo $empleado['departamento'] == 'Recursos Humanos' ? 'selected' : ''; ?>>Recursos Humanos</option>
                                <option value="Contabilidad" <?php echo $empleado['departamento'] == 'Contabilidad' ? 'selected' : ''; ?>>Contabilidad</option>
                                <option value="Ventas" <?php echo $empleado['departamento'] == 'Ventas' ? 'selected' : ''; ?>>Ventas</option>
                                <option value="Producción" <?php echo $empleado['departamento'] == 'Producción' ? 'selected' : ''; ?>>Producción</option>
                                <option value="TI" <?php echo $empleado['departamento'] == 'TI' ? 'selected' : ''; ?>>Tecnologías de Información</option>
                                <option value="Almacén" <?php echo $empleado['departamento'] == 'Almacén' ? 'selected' : ''; ?>>Almacén</option>
                                <option value="Otro" <?php echo $empleado['departamento'] == 'Otro' ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Salario Diario*</label>
                            <input type="number" name="salario_diario" class="form-control" 
                                   value="<?php echo $empleado['salario_diario']; ?>" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Estado*</label>
                            <select name="estado" class="form-control" required>
                                <option value="activo" <?php echo $empleado['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?php echo $empleado['estado'] == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                <option value="vacaciones" <?php echo $empleado['estado'] == 'vacaciones' ? 'selected' : ''; ?>>Vacaciones</option>
                                <option value="baja" <?php echo $empleado['estado'] == 'baja' ? 'selected' : ''; ?>>Baja</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content" id="tab-contacto">
                    <h3>Información de Contacto</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="tel" name="telefono" class="form-control" 
                                   value="<?php echo htmlspecialchars($empleado['telefono']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($empleado['email']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Dirección</label>
                        <textarea name="direccion" class="form-control" rows="4"><?php echo htmlspecialchars($empleado['direccion']); ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <a href="ver.php?id=<?php echo $id; ?>" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Manejo de pestañas
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remover clase active de todas las pestañas
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Agregar clase active a la pestaña seleccionada
                this.classList.add('active');
                document.getElementById(`tab-${tabId}`).classList.add('active');
            });
        });
        
        // Validación del formulario
        document.getElementById('empleadoForm').addEventListener('submit', function(e) {
            const rfc = document.querySelector('input[name="rfc"]').value;
            const curp = document.querySelector('input[name="curp"]').value;
            const nss = document.querySelector('input[name="nss"]').value;
            
            const rfcRegex = /^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
            const curpRegex = /^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9]{2}$/;
            const nssRegex = /^\d{11}$/;
            
            if (!rfcRegex.test(rfc)) {
                alert('RFC no válido. Verifique el formato.');
                e.preventDefault();
                return false;
            }
            
            if (!curpRegex.test(curp)) {
                alert('CURP no válido. Verifique el formato.');
                e.preventDefault();
                return false;
            }
            
            if (!nssRegex.test(nss)) {
                alert('NSS no válido. Debe tener 11 dígitos numéricos.');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>