<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh', 'supervisor']);

// Filtros
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;
$departamento = isset($_GET['departamento']) ? $_GET['departamento'] : '';

// Construir consulta
$where = "WHERE a.fecha = ?";
$params = [$fecha];
$types = "s";

if ($empleado_id > 0) {
    $where .= " AND a.empleado_id = ?";
    $params[] = $empleado_id;
    $types .= "i";
}

if (!empty($departamento)) {
    $where .= " AND e.departamento = ?";
    $params[] = $departamento;
    $types .= "s";
}

// Obtener asistencias
$sql = "SELECT a.*, e.nombre, e.apellido_paterno, e.apellido_materno, e.puesto, e.departamento 
        FROM asistencias a 
        JOIN empleados e ON a.empleado_id = e.id 
        $where 
        ORDER BY e.apellido_paterno, e.nombre";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$asistencias = $stmt->get_result();

// Obtener totales
$totales_sql = "SELECT 
    COUNT(*) as total_empleados,
    SUM(CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END) as total_asistencias,
    SUM(a.horas_trabajadas) as total_horas,
    SUM(a.horas_extra) as total_extra
    FROM empleados e 
    LEFT JOIN asistencias a ON e.id = a.empleado_id AND a.fecha = ?
    WHERE e.estado = 'activo'";

$totales_stmt = $conn->prepare($totales_sql);
$totales_stmt->bind_param("s", $fecha);
$totales_stmt->execute();
$totales = $totales_stmt->get_result()->fetch_assoc();

// Obtener empleados para filtro
$empleados_result = $conn->query("
    SELECT id, nombre, apellido_paterno, apellido_materno 
    FROM empleados 
    WHERE estado = 'activo' 
    ORDER BY apellido_paterno, nombre
");

// Obtener departamentos
$dept_result = $conn->query("SELECT DISTINCT departamento FROM empleados WHERE departamento IS NOT NULL ORDER BY departamento");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border-top: 4px solid var(--accent-color);
        }
        
        .stat-box h3 {
            margin: 0;
            font-size: 2.5rem;
            color: var(--secondary-color);
            line-height: 1;
        }
        
        .stat-box p {
            margin: 10px 0 0;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .filtros-rapidos {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .btn-filtro {
            padding: 10px 20px;
            background: var(--light-gray);
            border: 1px solid var(--medium-gray);
            border-radius: var(--radius-md);
            color: var(--text-dark);
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-filtro:hover,
        .btn-filtro.active {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .asistencia-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--success-color);
            transition: var(--transition);
        }
        
        .asistencia-card.falta {
            border-left-color: var(--danger-color);
        }
        
        .asistencia-card.media {
            border-left-color: var(--warning-color);
        }
        
        .asistencia-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .empleado-info h4 {
            margin: 0;
            color: var(--secondary-color);
            font-size: 1.1rem;
        }
        
        .empleado-info p {
            margin: 5px 0 0;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .asistencia-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-completa {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-falta {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .status-media {
            background: #feebc8;
            color: #744210;
        }
        
        .asistencia-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-label {
            display: block;
            color: var(--text-light);
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-action {
            padding: 8px 15px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-editar {
            background: var(--info-color);
            color: white;
        }
        
        .btn-justificar {
            background: var(--warning-color);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--medium-gray);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--text-light);
            margin-bottom: 30px;
        }
        
        .calendar-picker {
            position: relative;
        }
        
        .calendar-picker input {
            padding-left: 40px;
        }
        
        .calendar-picker i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--accent-color);
            pointer-events: none;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> Control de Asistencias</h1>
            <p>Consulta y gestiona los registros de asistencia</p>
        </div>
        
        <div class="stats-overview">
            <div class="stat-box">
                <h3><?php echo $totales['total_empleados']; ?></h3>
                <p>Empleados Activos</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $totales['total_asistencias']; ?></h3>
                <p>Asistencias Hoy</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $totales['total_empleados'] - $totales['total_asistencias']; ?></h3>
                <p>Faltas Hoy</p>
            </div>
            <div class="stat-box">
                <h3><?php echo number_format($totales['total_horas'] ?: 0, 1); ?></h3>
                <p>Horas Trabajadas</p>
            </div>
            <div class="stat-box">
                <h3><?php echo number_format($totales['total_extra'] ?: 0, 1); ?></h3>
                <p>Horas Extra</p>
            </div>
        </div>
        
        <div class="filtros-container">
            <form method="GET" class="filtros-form">
                <div class="filtros-grid">
                    <div class="form-group calendar-picker">
                        <i class="fas fa-calendar-alt"></i>
                        <label>Fecha</label>
                        <input type="date" 
                               name="fecha" 
                               class="form-control" 
                               value="<?php echo $fecha; ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Empleado</label>
                        <select name="empleado_id" class="form-control">
                            <option value="">Todos los empleados</option>
                            <?php while ($emp = $empleados_result->fetch_assoc()): ?>
                                <option value="<?php echo $emp['id']; ?>" 
                                    <?php echo ($empleado_id == $emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['apellido_paterno'] . ' ' . $emp['apellido_materno'] . ' ' . $emp['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Departamento</label>
                        <select name="departamento" class="form-control">
                            <option value="">Todos los departamentos</option>
                            <?php while ($dept = $dept_result->fetch_assoc()): ?>
                                <option value="<?php echo $dept['departamento']; ?>" 
                                    <?php echo ($departamento == $dept['departamento']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['departamento']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                    <a href="index.php" class="btn-secondary">
                        <i class="fas fa-redo"></i> Limpiar
                    </a>
                    <a href="registrar.php" class="btn-success">
                        <i class="fas fa-plus"></i> Nuevo Registro
                    </a>
                </div>
            </form>
        </div>
        
        <div class="filtros-rapidos">
            <a href="index.php?fecha=<?php echo date('Y-m-d'); ?>" 
               class="btn-filtro <?php echo $fecha == date('Y-m-d') ? 'active' : ''; ?>">
                <i class="fas fa-sun"></i> Hoy
            </a>
            <a href="index.php?fecha=<?php echo date('Y-m-d', strtotime('-1 day')); ?>" 
               class="btn-filtro">
                <i class="fas fa-calendar-day"></i> Ayer
            </a>
            <a href="index.php?fecha=<?php echo date('Y-m-d', strtotime('monday this week')); ?>" 
               class="btn-filtro">
                <i class="fas fa-calendar-week"></i> Esta Semana
            </a>
            <a href="index.php?fecha=<?php echo date('Y-m-01'); ?>" 
               class="btn-filtro">
                <i class="fas fa-calendar"></i> Este Mes
            </a>
        </div>
        
        <?php if ($asistencias->num_rows > 0): ?>
            <?php while ($asistencia = $asistencias->fetch_assoc()): ?>
                <div class="asistencia-card <?php echo $asistencia['tipo_asistencia']; ?>">
                    <div class="card-header">
                        <div class="empleado-info">
                            <h4><?php echo htmlspecialchars($asistencia['nombre'] . ' ' . $asistencia['apellido_paterno'] . ' ' . $asistencia['apellido_materno']); ?></h4>
                            <p><?php echo htmlspecialchars($asistencia['puesto'] . ' • ' . $asistencia['departamento']); ?></p>
                        </div>
                        <div class="asistencia-status status-<?php echo $asistencia['tipo_asistencia']; ?>">
                            <?php 
                            $estados = [
                                'completa' => 'Completa',
                                'media_jornada' => 'Media Jornada',
                                'falta' => 'Falta',
                                'incapacidad' => 'Incapacidad',
                                'vacaciones' => 'Vacaciones',
                                'permiso' => 'Permiso'
                            ];
                            echo $estados[$asistencia['tipo_asistencia']];
                            ?>
                        </div>
                    </div>
                    
                    <div class="asistencia-details">
                        <div class="detail-item">
                            <span class="detail-label">Entrada</span>
                            <span class="detail-value"><?php echo $asistencia['hora_entrada'] ?: '--:--'; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Salida</span>
                            <span class="detail-value"><?php echo $asistencia['hora_salida'] ?: '--:--'; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Horas Trabajadas</span>
                            <span class="detail-value"><?php echo $asistencia['horas_trabajadas'] ?: '0'; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Horas Extra</span>
                            <span class="detail-value"><?php echo $asistencia['horas_extra'] ?: '0'; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Retardo</span>
                            <span class="detail-value"><?php echo $asistencia['retardo_minutos'] ? $asistencia['retardo_minutos'] . ' min' : '0 min'; ?></span>
                        </div>
                    </div>
                    
                    <?php if ($asistencia['observaciones']): ?>
                        <div class="observaciones">
                            <strong>Observaciones:</strong>
                            <p><?php echo nl2br(htmlspecialchars($asistencia['observaciones'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-actions">
                        <a href="editar.php?id=<?php echo $asistencia['id']; ?>" class="btn-action btn-editar">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <?php if ($asistencia['tipo_asistencia'] == 'falta'): ?>
                            <a href="#" class="btn-action btn-justificar">
                                <i class="fas fa-file-alt"></i> Justificar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No hay registros de asistencia</h3>
                <p>No se encontraron asistencias para la fecha seleccionada.</p>
                <a href="registrar.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Registrar Primera Asistencia
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Auto-submit al cambiar fecha
        document.querySelector('input[name="fecha"]').addEventListener('change', function() {
            this.form.submit();
        });
        
        // Resaltar asistencias con problemas
        document.querySelectorAll('.asistencia-card').forEach(card => {
            const horas = parseFloat(card.querySelector('.detail-value:nth-child(3)').textContent);
            const retardo = parseInt(card.querySelector('.detail-value:nth-child(5)').textContent);
            
            if (horas < 8 || retardo > 15) {
                card.style.borderLeftColor = '#f56565';
            }
        });
    </script>
</body>
</html>