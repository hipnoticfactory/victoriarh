<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

// Obtener datos del empleado
$stmt = $conn->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$empleado = $stmt->get_result()->fetch_assoc();

if (!$empleado) {
    header("Location: index.php?error=empleado_no_encontrado");
    exit();
}

// Obtener expedientes del empleado
$exp_stmt = $conn->prepare("SELECT * FROM expedientes WHERE empleado_id = ? ORDER BY fecha_subida DESC");
$exp_stmt->bind_param("i", $id);
$exp_stmt->execute();
$expedientes = $exp_stmt->get_result();

// Obtener asistencias del mes actual
$mes_actual = date('Y-m');
$asis_stmt = $conn->prepare("SELECT * FROM asistencias WHERE empleado_id = ? AND fecha LIKE ? ORDER BY fecha DESC LIMIT 15");
$asis_stmt->bind_param("is", $id, "$mes_actual%");
$asis_stmt->execute();
$asistencias = $asis_stmt->get_result();

// Calcular estadísticas
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_dias,
        SUM(horas_trabajadas) as total_horas,
        SUM(horas_extra) as total_extra,
        AVG(horas_trabajadas) as promedio_diario
    FROM asistencias 
    WHERE empleado_id = ? 
    AND fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats_stmt->bind_param("i", $id);
$stats_stmt->execute();
$estadisticas = $stats_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno']); ?> - Detalles</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .perfil-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 30px;
            border-left: 5px solid var(--accent-color);
        }
        
        .perfil-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--accent-color), #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            flex-shrink: 0;
        }
        
        .perfil-info h1 {
            margin: 0;
            color: var(--secondary-color);
            font-size: 2rem;
        }
        
        .perfil-info .puesto {
            font-size: 1.2rem;
            color: var(--accent-color);
            margin: 5px 0;
            font-weight: 500;
        }
        
        .perfil-meta {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            color: var(--text-light);
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
        }
        
        .info-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--secondary-color);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .info-label {
            color: var(--text-light);
            font-weight: 500;
        }
        
        .info-value {
            color: var(--text-dark);
            font-weight: 600;
            text-align: right;
            max-width: 60%;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: var(--light-gray);
            border-radius: var(--radius-md);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-color);
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 8px;
        }
        
        .documentos-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .documento-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: var(--light-gray);
            border-radius: var(--radius-md);
            transition: var(--transition);
        }
        
        .documento-item:hover {
            background: white;
            box-shadow: var(--shadow-sm);
        }
        
        .documento-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .documento-icon {
            width: 40px;
            height: 40px;
            background: var(--accent-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .asistencias-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .asistencias-table th,
        .asistencias-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .asistencias-table th {
            background: var(--light-gray);
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .btn-download {
            background: var(--accent-color);
            color: white;
            padding: 8px 15px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            transition: var(--transition);
        }
        
        .btn-download:hover {
            opacity: 0.9;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--medium-gray);
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="perfil-header">
            <div class="perfil-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="perfil-info">
                <h1><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno'] . ' ' . $empleado['apellido_materno']); ?></h1>
                <div class="puesto"><?php echo htmlspecialchars($empleado['puesto']); ?></div>
                <div class="perfil-meta">
                    <div class="meta-item">
                        <i class="fas fa-building"></i>
                        <span><?php echo htmlspecialchars($empleado['departamento']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Ingreso: <?php echo date('d/m/Y', strtotime($empleado['fecha_contratacion'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <?php 
                        $hoy = new DateTime();
                        $nacimiento = new DateTime($empleado['fecha_nacimiento']);
                        $edad = $hoy->diff($nacimiento)->y;
                        ?>
                        <i class="fas fa-birthday-cake"></i>
                        <span><?php echo $edad; ?> años</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-circle"></i>
                        <span class="status-badge status-<?php echo $empleado['estado']; ?>">
                            <?php echo ucfirst($empleado['estado']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-card">
                <h3><i class="fas fa-id-card"></i> Información Personal</h3>
                <div class="info-row">
                    <span class="info-label">Fecha de Nacimiento:</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($empleado['fecha_nacimiento'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Género:</span>
                    <span class="info-value"><?php echo $empleado['genero'] == 'M' ? 'Masculino' : ($empleado['genero'] == 'F' ? 'Femenino' : 'Otro'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">RFC:</span>
                    <span class="info-value"><?php echo htmlspecialchars($empleado['rfc']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">CURP:</span>
                    <span class="info-value"><?php echo htmlspecialchars($empleado['curp']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">NSS:</span>
                    <span class="info-value"><?php echo htmlspecialchars($empleado['nss']); ?></span>
                </div>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-briefcase"></i> Información Laboral</h3>
                <div class="info-row">
                    <span class="info-label">Departamento:</span>
                    <span class="info-value"><?php echo htmlspecialchars($empleado['departamento']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Puesto:</span>
                    <span class="info-value"><?php echo htmlspecialchars($empleado['puesto']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha Contratación:</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($empleado['fecha_contratacion'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Salario Diario:</span>
                    <span class="info-value">$<?php echo number_format($empleado['salario_diario'], 2); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Estado:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?php echo $empleado['estado']; ?>">
                            <?php echo ucfirst($empleado['estado']); ?>
                        </span>
                    </span>
                </div>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-address-book"></i> Contacto</h3>
                <div class="info-row">
                    <span class="info-label">Teléfono:</span>
                    <span class="info-value"><?php echo htmlspecialchars($empleado['telefono'] ?: 'No especificado'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($empleado['email'] ?: 'No especificado'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Dirección:</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($empleado['direccion'] ?: 'No especificada')); ?></span>
                </div>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-chart-bar"></i> Estadísticas (Últimos 30 días)</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $estadisticas['total_dias'] ?: '0'; ?></div>
                        <div class="stat-label">Días trabajados</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($estadisticas['total_horas'] ?: 0, 1); ?></div>
                        <div class="stat-label">Horas totales</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($estadisticas['promedio_diario'] ?: 0, 1); ?></div>
                        <div class="stat-label">Promedio diario</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($estadisticas['total_extra'] ?: 0, 1); ?></div>
                        <div class="stat-label">Horas extra</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-card">
                <h3><i class="fas fa-file-alt"></i> Expedientes y Documentos</h3>
                <div class="documentos-list">
                    <?php if ($expedientes->num_rows > 0): ?>
                        <?php while ($doc = $expedientes->fetch_assoc()): ?>
                            <div class="documento-item">
                                <div class="documento-info">
                                    <div class="documento-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div>
                                        <div class="documento-nombre">
                                            <?php echo htmlspecialchars($doc['nombre_documento']); ?>
                                        </div>
                                        <div class="documento-meta">
                                            <small>
                                                <?php echo ucfirst(str_replace('_', ' ', $doc['tipo_documento'])); ?> • 
                                                Subido: <?php echo date('d/m/Y', strtotime($doc['fecha_subida'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                       target="_blank" 
                                       class="btn-download">
                                        <i class="fas fa-download"></i> Descargar
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-excel"></i>
                            <p>No hay documentos cargados</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="margin-top: 20px;">
                    <a href="../expedientes/subir.php?empleado_id=<?php echo $id; ?>" class="btn-primary">
                        <i class="fas fa-upload"></i> Subir Nuevo Documento
                    </a>
                </div>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-history"></i> Últimas Asistencias</h3>
                <div class="table-responsive">
                    <table class="asistencias-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Horas</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($asistencias->num_rows > 0): ?>
                                <?php while ($asis = $asistencias->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($asis['fecha'])); ?></td>
                                        <td><?php echo $asis['hora_entrada'] ?: '--:--'; ?></td>
                                        <td><?php echo $asis['hora_salida'] ?: '--:--'; ?></td>
                                        <td>
                                            <?php if ($asis['horas_trabajadas']): ?>
                                                <span class="badge badge-success"><?php echo $asis['horas_trabajadas']; ?>h</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">0h</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge">
                                                <?php echo ucfirst($asis['tipo_asistencia']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 30px;">
                                        <i class="fas fa-calendar-times"></i>
                                        <p>No hay registros de asistencia</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 20px;">
                    <a href="../asistencias/historial.php?empleado_id=<?php echo $id; ?>" class="btn-secondary">
                        <i class="fas fa-list"></i> Ver Historial Completo
                    </a>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="editar.php?id=<?php echo $id; ?>" class="btn-primary">
                <i class="fas fa-edit"></i> Editar Empleado
            </a>
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Listado
            </a>
            <a href="../reportes/quincenal.php?empleado_id=<?php echo $id; ?>" class="btn-success">
                <i class="fas fa-file-invoice-dollar"></i> Generar Reporte Quincenal
            </a>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>