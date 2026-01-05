<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh', 'supervisor']);

$success = '';
$error = '';
$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;

// Registrar asistencia
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $empleado_id = (int)$_POST['empleado_id'];
    $fecha = $_POST['fecha'];
    $hora_entrada = $_POST['hora_entrada'];
    $hora_salida = $_POST['hora_salida'];
    $tipo = $_POST['tipo'];
    $observaciones = trim($_POST['observaciones']);
    
    // Verificar si ya existe registro para esa fecha
    $check = $conn->prepare("SELECT id FROM asistencias WHERE empleado_id = ? AND fecha = ?");
    $check->bind_param("is", $empleado_id, $fecha);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0 && !isset($_POST['sobrescribir'])) {
        $error = "Ya existe un registro de asistencia para este empleado en la fecha seleccionada. ¿Desea sobrescribirlo?";
        $show_overwrite = true;
    } else {
        // Calcular horas trabajadas
        $horas_trabajadas = 0;
        $horas_extra = 0;
        $retardo_minutos = 0;
        
        if ($tipo == 'completa' && $hora_entrada && $hora_salida) {
            // Calcular diferencia
            $entrada = new DateTime($hora_entrada);
            $salida = new DateTime($hora_salida);
            $diff = $salida->diff($entrada);
            $horas = $diff->h + ($diff->i / 60);
            
            // Restar 1 hora de comida si la jornada es mayor a 6 horas
            if ($horas > 6) {
                $horas -= 1;
            }
            
            $horas_trabajadas = round($horas, 2);
            
            // Calcular horas extra (más de 8 horas)
            if ($horas_trabajadas > 8) {
                $horas_extra = round($horas_trabajadas - 8, 2);
                $horas_trabajadas = 8;
            }
            
            // Calcular retardo (entrada después de las 9:00)
            $hora_limite = new DateTime('09:00:00');
            if ($entrada > $hora_limite) {
                $retardo = $entrada->diff($hora_limite);
                $retardo_minutos = ($retardo->h * 60) + $retardo->i;
            }
        } elseif ($tipo == 'media_jornada') {
            $horas_trabajadas = 4;
        }
        
        // Insertar o actualizar
        if (isset($_POST['sobrescribir'])) {
            $stmt = $conn->prepare("UPDATE asistencias SET 
                hora_entrada = ?, 
                hora_salida = ?, 
                horas_trabajadas = ?, 
                horas_extra = ?, 
                retardo_minutos = ?, 
                tipo_asistencia = ?, 
                observaciones = ? 
                WHERE empleado_id = ? AND fecha = ?");
            
            $stmt->bind_param(
                "ssddissis", 
                $hora_entrada, 
                $hora_salida, 
                $horas_trabajadas, 
                $horas_extra, 
                $retardo_minutos, 
                $tipo, 
                $observaciones, 
                $empleado_id, 
                $fecha
            );
        } else {
            $stmt = $conn->prepare("INSERT INTO asistencias (
                empleado_id, fecha, hora_entrada, hora_salida, 
                horas_trabajadas, horas_extra, retardo_minutos, 
                tipo_asistencia, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param(
                "isssddiss", 
                $empleado_id, 
                $fecha, 
                $hora_entrada, 
                $hora_salida, 
                $horas_trabajadas, 
                $horas_extra, 
                $retardo_minutos, 
                $tipo, 
                $observaciones
            );
        }
        
        if ($stmt->execute()) {
            $success = "Asistencia registrada exitosamente";
            $empleado_id = 0; // Resetear para nuevo registro
        } else {
            $error = "Error al registrar asistencia: " . $conn->error;
        }
    }
}

// Obtener lista de empleados activos
$empleados_result = $conn->query("
    SELECT id, nombre, apellido_paterno, apellido_materno, puesto 
    FROM empleados 
    WHERE estado = 'activo' 
    ORDER BY apellido_paterno, nombre
");
$empleados = $empleados_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Asistencia - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .registro-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        
        @media (max-width: 1024px) {
            .registro-container {
                grid-template-columns: 1fr;
            }
        }
        
        .empleado-seleccion {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow-sm);
        }
        
        .empleado-list {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
            border: 1px solid var(--medium-gray);
            border-radius: var(--radius-md);
        }
        
        .empleado-item {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .empleado-item:hover {
            background: var(--light-gray);
        }
        
        .empleado-item.selected {
            background: var(--accent-color);
            color: white;
        }
        
        .empleado-item h4 {
            margin: 0;
            font-size: 1rem;
        }
        
        .empleado-item p {
            margin: 5px 0 0;
            font-size: 0.85rem;
            opacity: 0.8;
        }
        
        .registro-form {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow-sm);
        }
        
        .info-empleado {
            background: var(--light-gray);
            padding: 20px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
        }
        
        .calculadora-horas {
            background: #f0f9ff;
            border: 1px solid #bee3f8;
            border-radius: var(--radius-md);
            padding: 20px;
            margin: 20px 0;
        }
        
        .calculadora-resultado {
            text-align: center;
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: var(--radius-sm);
            border: 2px solid var(--accent-color);
        }
        
        .resultado-horas {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-color);
        }
        
        .hoy-info {
            background: linear-gradient(135deg, var(--accent-color), #764ba2);
            color: white;
            padding: 20px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .hoy-info h3 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .hoy-info p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-clock"></i> Registrar Asistencia</h1>
            <p>Registra la entrada y salida de los empleados</p>
        </div>
        
        <div class="hoy-info">
            <h3><?php echo date('l, j F Y'); ?></h3>
            <p>Hora actual: <span id="hora-actual"><?php echo date('H:i:s'); ?></span></p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <?php if (isset($show_overwrite)): ?>
                    <form method="POST" style="margin-top: 15px;">
                        <input type="hidden" name="empleado_id" value="<?php echo $empleado_id; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $_POST['fecha']; ?>">
                        <input type="hidden" name="hora_entrada" value="<?php echo $_POST['hora_entrada']; ?>">
                        <input type="hidden" name="hora_salida" value="<?php echo $_POST['hora_salida']; ?>">
                        <input type="hidden" name="tipo" value="<?php echo $_POST['tipo']; ?>">
                        <input type="hidden" name="observaciones" value="<?php echo htmlspecialchars($_POST['observaciones']); ?>">
                        <button type="submit" name="sobrescribir" value="1" class="btn-warning">
                            <i class="fas fa-exclamation-triangle"></i> Sí, sobrescribir registro
                        </button>
                        <a href="registrar.php" class="btn-secondary">Cancelar</a>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="registro-container">
            <div class="empleado-seleccion">
                <h3><i class="fas fa-users"></i> Seleccionar Empleado</h3>
                <div class="form-group">
                    <input type="text" 
                           id="buscar-empleado" 
                           placeholder="Buscar empleado..." 
                           class="form-control">
                </div>
                
                <div class="empleado-list" id="lista-empleados">
                    <?php foreach ($empleados as $emp): ?>
                        <div class="empleado-item" 
                             data-id="<?php echo $emp['id']; ?>"
                             data-nombre="<?php echo htmlspecialchars($emp['nombre'] . ' ' . $emp['apellido_paterno'] . ' ' . $emp['apellido_materno']); ?>"
                             data-puesto="<?php echo htmlspecialchars($emp['puesto']); ?>">
                            <h4><?php echo htmlspecialchars($emp['nombre'] . ' ' . $emp['apellido_paterno'] . ' ' . $emp['apellido_materno']); ?></h4>
                            <p><?php echo htmlspecialchars($emp['puesto']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="registro-form">
                <form method="POST" id="formAsistencia">
                    <input type="hidden" name="empleado_id" id="input_empleado_id" value="<?php echo $empleado_id; ?>">
                    
                    <div class="info-empleado" id="info-empleado-seleccionado" style="<?php echo !$empleado_id ? 'display: none;' : ''; ?>">
                        <h4 id="nombre-empleado"></h4>
                        <p id="puesto-empleado"></p>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fecha*</label>
                            <input type="date" 
                                   name="fecha" 
                                   class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d'); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Asistencia*</label>
                            <select name="tipo" class="form-control" required>
                                <option value="completa">Jornada Completa</option>
                                <option value="media_jornada">Media Jornada</option>
                                <option value="falta">Falta</option>
                                <option value="incapacidad">Incapacidad</option>
                                <option value="vacaciones">Vacaciones</option>
                                <option value="permiso">Permiso</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Hora de Entrada</label>
                            <input type="time" 
                                   name="hora_entrada" 
                                   id="hora_entrada" 
                                   class="form-control"
                                   value="<?php echo date('H:i'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Hora de Salida</label>
                            <input type="time" 
                                   name="hora_salida" 
                                   id="hora_salida" 
                                   class="form-control">
                        </div>
                    </div>
                    
                    <div class="calculadora-horas">
                        <h4><i class="fas fa-calculator"></i> Calculadora de Horas</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tiempo de Descanso (minutos)</label>
                                <input type="number" 
                                       id="tiempo_descanso" 
                                       class="form-control" 
                                       value="60" 
                                       min="0" 
                                       max="180">
                            </div>
                        </div>
                        
                        <div class="calculadora-resultado">
                            <p>Horas trabajadas:</p>
                            <div class="resultado-horas" id="resultado-horas">0.00</div>
                            <div id="detalle-calculo"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea name="observaciones" 
                                  class="form-control" 
                                  rows="3" 
                                  placeholder="Notas adicionales..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="btn-registrar" disabled>
                            <i class="fas fa-save"></i> Registrar Asistencia
                        </button>
                        <button type="reset" class="btn-secondary">
                            <i class="fas fa-redo"></i> Limpiar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Actualizar hora actual cada segundo
        function actualizarHora() {
            const ahora = new Date();
            const hora = ahora.getHours().toString().padStart(2, '0');
            const minutos = ahora.getMinutes().toString().padStart(2, '0');
            const segundos = ahora.getSeconds().toString().padStart(2, '0');
            document.getElementById('hora-actual').textContent = `${hora}:${minutos}:${segundos}`;
        }
        setInterval(actualizarHora, 1000);
        
        // Seleccionar empleado
        document.querySelectorAll('.empleado-item').forEach(item => {
            item.addEventListener('click', function() {
                // Remover selección anterior
                document.querySelectorAll('.empleado-item').forEach(i => {
                    i.classList.remove('selected');
                });
                
                // Agregar selección actual
                this.classList.add('selected');
                
                // Actualizar formulario
                const empleadoId = this.getAttribute('data-id');
                const nombre = this.getAttribute('data-nombre');
                const puesto = this.getAttribute('data-puesto');
                
                document.getElementById('input_empleado_id').value = empleadoId;
                document.getElementById('nombre-empleado').textContent = nombre;
                document.getElementById('puesto-empleado').textContent = puesto;
                document.getElementById('info-empleado-seleccionado').style.display = 'block';
                document.getElementById('btn-registrar').disabled = false;
            });
        });
        
        // Buscar empleado
        document.getElementById('buscar-empleado').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const empleados = document.querySelectorAll('.empleado-item');
            
            empleados.forEach(empleado => {
                const nombre = empleado.getAttribute('data-nombre').toLowerCase();
                const puesto = empleado.getAttribute('data-puesto').toLowerCase();
                
                if (nombre.includes(searchTerm) || puesto.includes(searchTerm)) {
                    empleado.style.display = '';
                } else {
                    empleado.style.display = 'none';
                }
            });
        });
        
        // Calcular horas automáticamente
        function calcularHoras() {
            const entrada = document.getElementById('hora_entrada').value;
            const salida = document.getElementById('hora_salida').value;
            const descanso = parseInt(document.getElementById('tiempo_descanso').value) || 0;
            
            if (!entrada || !salida) {
                document.getElementById('resultado-horas').textContent = '0.00';
                document.getElementById('detalle-calculo').innerHTML = '';
                return;
            }
            
            const [h1, m1] = entrada.split(':').map(Number);
            const [h2, m2] = salida.split(':').map(Number);
            
            let horas = h2 - h1;
            let minutos = m2 - m1;
            
            if (minutos < 0) {
                horas -= 1;
                minutos += 60;
            }
            
            let totalHoras = horas + (minutos / 60);
            
            // Restar descanso
            totalHoras -= descanso / 60;
            
            if (totalHoras < 0) totalHoras = 0;
            
            // Calcular horas extra
            let horasNormales = totalHoras;
            let horasExtra = 0;
            
            if (totalHoras > 8) {
                horasExtra = totalHoras - 8;
                horasNormales = 8;
            }
            
            // Mostrar resultados
            document.getElementById('resultado-horas').textContent = totalHoras.toFixed(2);
            
            let detalle = '';
            if (horasExtra > 0) {
                detalle = `
                    <div style="margin-top: 10px; font-size: 0.9rem; color: #666;">
                        <div>Normal: ${horasNormales.toFixed(2)}h</div>
                        <div>Extra: ${horasExtra.toFixed(2)}h</div>
                    </div>
                `;
            }
            document.getElementById('detalle-calculo').innerHTML = detalle;
        }
        
        // Event listeners para cálculo automático
        document.getElementById('hora_entrada').addEventListener('change', calcularHoras);
        document.getElementById('hora_salida').addEventListener('change', calcularHoras);
        document.getElementById('tiempo_descanso').addEventListener('input', calcularHoras);
        
        // Establecer hora actual como entrada por defecto
        document.getElementById('hora_entrada').value = 
            new Date().toTimeString().substring(0, 5);
        
        // Auto-rellenar salida (8 horas después)
        document.getElementById('hora_entrada').addEventListener('change', function() {
            if (this.value && !document.getElementById('hora_salida').value) {
                const [hora, minuto] = this.value.split(':').map(Number);
                let horaSalida = hora + 9; // 8 horas + 1 hora de comida
                if (horaSalida >= 24) horaSalida -= 24;
                document.getElementById('hora_salida').value = 
                    horaSalida.toString().padStart(2, '0') + ':' + minuto.toString().padStart(2, '0');
                calcularHoras();
            }
        });
    </script>
</body>
</html>