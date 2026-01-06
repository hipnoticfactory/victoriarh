<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();

$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : $_SESSION['empleado_id'];
$mes = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 30;
$offset = ($page - 1) * $limit;

// Verificar permisos
if ($_SESSION['rol'] == 'empleado' && $empleado_id != $_SESSION['empleado_id']) {
    header("Location: historial.php?empleado_id=" . $_SESSION['empleado_id']);
    exit();
}

// Obtener datos del empleado
$empleado_stmt = $conn->prepare("SELECT * FROM empleados WHERE id = ?");
$empleado_stmt->bind_param("i", $empleado_id);
$empleado_stmt->execute();
$empleado = $empleado_stmt->get_result()->fetch_assoc();

if (!$empleado) {
    header("Location: ../empleados/");
    exit();
}

// Obtener historial
$where = "WHERE empleado_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?";
$params = [$empleado_id, $mes];
$types = "is";

// Contar total
$count_sql = "SELECT COUNT(*) as total FROM asistencias $where";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Obtener registros
$sql = "SELECT * FROM asistencias $where ORDER BY fecha DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$historial = $stmt->get_result();

// Calcular totales del mes
$totales_sql = "SELECT 
    COUNT(*) as total_dias,
    SUM(horas_trabajadas) as total_horas,
    SUM(horas_extra) as total_extra,
    SUM(retardo_minutos) as total_retardo,
    COUNT(CASE WHEN tipo_asistencia = 'falta' THEN 1 END) as total_faltas
    FROM asistencias 
    WHERE empleado_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?";

$totales_stmt = $conn->prepare($totales_sql);
$totales_stmt->bind_param("is", $empleado_id, $mes);
$totales_stmt->execute();
$totales = $totales_stmt->get_result()->fetch_assoc();

// Obtener meses disponibles
$meses_stmt = $conn->prepare("
    SELECT DISTINCT DATE_FORMAT(fecha, '%Y-%m') as mes 
    FROM asistencias 
    WHERE empleado_id = ? 
    ORDER BY mes DESC
");
$meses_stmt->bind_param("i", $empleado_id);
$meses_stmt->execute();
$meses_disponibles = $meses_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Asistencias - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .historial-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .empleado-summary h2 {
            margin: 0;
            color: var(--secondary-color);
        }
        
        .empleado-summary p {
            margin: 5px 0 0;
            color: var(--text-light);
        }
        
        .mes-selector {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .mes-nav {
            display: flex;
            gap: 10px;
        }
        
        .btn-mes {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-gray);
            border: 1px solid var(--medium-gray);
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .btn-mes:hover {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .resumen-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        
        .resumen-card h3 {
            margin: 0;
            font-size: 2.5rem;
            color: var(--secondary-color);
            line-height: 1;
        }
        
        .resumen-card p {
            margin: 10px 0 0;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .resumen-card .icon {
            font-size: 2rem;
            color: var(--accent-color);
            margin-bottom: 15px;
        }
        
        .calendar-view {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            border: 1px solid var(--medium-gray);
            border-radius: var(--radius-sm);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .calendar-day.header {
            border: none;
            font-weight: 600;
            color: var(--text-light);
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        
        .calendar-day.today {
            background: var(--accent-color);
            color: white;
            font-weight: 600;
        }
        
        .day-number {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .day-status {
            font-size: 0.7rem;
            margin-top: 5px;
            padding: 2px 6px;
            border-radius: 10px;
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
        
        .historial-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .historial-table th {
            background: var(--light-gray);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid var(--medium-gray);
        }
        
        .historial-table td {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .historial-table tr:hover {
            background: var(--light-gray);
        }
        
        .export-options {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
        }
        
        .btn-export {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--light-gray);
            border: 1px solid var(--medium-gray);
            border-radius: var(--radius-md);
            color: var(--text-dark);
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-export:hover {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="historial-header">
            <div class="empleado-summary">
                <h2><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno']); ?></h2>
                <p><?php echo htmlspecialchars($empleado['puesto'] . ' • ' . $empleado['departamento']); ?></p>
            </div>
            
            <div class="mes-selector">
                <div class="mes-nav">
                    <?php 
                    $prev_mes = date('Y-m', strtotime($mes . ' -1 month'));
                    $next_mes = date('Y-m', strtotime($mes . ' +1 month'));
                    $hoy_mes = date('Y-m');
                    ?>
                    <a href="?empleado_id=<?php echo $empleado_id; ?>&mes=<?php echo $prev_mes; ?>" class="btn-mes">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="?empleado_id=<?php echo $empleado_id; ?>&mes=<?php echo $next_mes; ?>" 
                       class="btn-mes <?php echo $next_mes > $hoy_mes ? 'disabled' : ''; ?>"
                       <?php echo $next_mes > $hoy_mes ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                
                <select class="form-control" onchange="window.location.href='?empleado_id=<?php echo $empleado_id; ?>&mes=' + this.value">
                    <?php while ($mes_row = $meses_disponibles->fetch_assoc()): ?>
                        <option value="<?php echo $mes_row['mes']; ?>" 
                            <?php echo $mes_row['mes'] == $mes ? 'selected' : ''; ?>>
                            <?php echo date('F Y', strtotime($mes_row['mes'] . '-01')); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <div class="resumen-grid">
            <div class="resumen-card">
                <div class="icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3><?php echo $totales['total_dias'] ?: '0'; ?></h3>
                <p>Días Registrados</p>
            </div>
            
            <div class="resumen-card">
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo number_format($totales['total_horas'] ?: 0, 1); ?></h3>
                <p>Horas Trabajadas</p>
            </div>
            
            <div class="resumen-card">
                <div class="icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <h3><?php echo number_format($totales['total_extra'] ?: 0, 1); ?></h3>
                <p>Horas Extra</p>
            </div>
            
            <div class="resumen-card">
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3><?php echo $totales['total_faltas'] ?: '0'; ?></h3>
                <p>Faltas</p>
            </div>
            
            <div class="resumen-card">
                <div class="icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <h3><?php echo $totales['total_retardo'] ?: '0'; ?> min</h3>
                <p>Retardo Total</p>
            </div>
        </div>
        
        <div class="calendar-view">
            <div class="calendar-header">
                <h3><?php echo date('F Y', strtotime($mes . '-01')); ?></h3>
                <div class="legend">
                    <span class="legend-item"><span class="status-completa" style="padding: 4px 8px; border-radius: 4px;">Completa</span></span>
                    <span class="legend-item"><span class="status-media" style="padding: 4px 8px; border-radius: 4px;">Media Jornada</span></span>
                    <span class="legend-item"><span class="status-falta" style="padding: 4px 8px; border-radius: 4px;">Falta</span></span>
                </div>
            </div>
            
            <div class="calendar-grid" id="calendar-container">
                <!-- Se llena con JavaScript -->
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Historial Detallado</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="historial-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Horas</th>
                                <th>Extra</th>
                                <th>Retardo</th>
                                <th>Tipo</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($historial->num_rows > 0): ?>
                                <?php while ($registro = $historial->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($registro['fecha'])); ?></td>
                                        <td><?php echo $registro['hora_entrada'] ?: '--:--'; ?></td>
                                        <td><?php echo $registro['hora_salida'] ?: '--:--'; ?></td>
                                        <td><?php echo $registro['horas_trabajadas'] ?: '0'; ?></td>
                                        <td><?php echo $registro['horas_extra'] ?: '0'; ?></td>
                                        <td><?php echo $registro['retardo_minutos'] ? $registro['retardo_minutos'] . ' min' : '0 min'; ?></td>
                                        <td>
                                            <span class="status-<?php echo $registro['tipo_asistencia']; ?>" 
                                                  style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                                                <?php 
                                                $estados = [
                                                    'completa' => 'Completa',
                                                    'media_jornada' => 'Media',
                                                    'falta' => 'Falta',
                                                    'incapacidad' => 'Incapacidad',
                                                    'vacaciones' => 'Vacaciones',
                                                    'permiso' => 'Permiso'
                                                ];
                                                echo $estados[$registro['tipo_asistencia']];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo nl2br(htmlspecialchars($registro['observaciones'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--medium-gray); margin-bottom: 20px;"></i>
                                        <p>No hay registros de asistencia para este mes</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="margin-top: 30px;">
                        <?php if ($page > 1): ?>
                            <a href="?empleado_id=<?php echo $empleado_id; ?>&mes=<?php echo $mes; ?>&page=1" class="page-link">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?empleado_id=<?php echo $empleado_id; ?>&mes=<?php echo $mes; ?>&page=<?php echo $page - 1; ?>" class="page-link">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++): 
                        ?>
                            <a href="?empleado_id=<?php echo $empleado_id; ?>&mes=<?php echo $mes; ?>&page=<?php echo $i; ?>" 
                               class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?empleado_id=<?php echo $empleado_id; ?>&mes=<?php echo $mes; ?>&page=<?php echo $page + 1; ?>" class="page-link">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?empleado_id=<?php echo $empleado_id; ?>&mes=<?php echo $mes; ?>&page=<?php echo $total_pages; ?>" class="page-link">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="export-options">
            <a href="../reportes/exportar.php?empleado_id=<?php echo $empleado_id; ?>&mes=<?php echo $mes; ?>&tipo=pdf" class="btn-export">
                <i class="fas fa-file-pdf"></i> Exportar a PDF
            </a>
            <a href="../reportes/exportar.php?empleado_id=<?php echo $empleado_id; ?>&mes=<?php echo $mes; ?>&tipo=excel" class="btn-export">
                <i class="fas fa-file-excel"></i> Exportar a Excel
            </a>
            <a href="../reportes/quincenal.php?empleado_id=<?php echo $empleado_id; ?>" class="btn-export">
                <i class="fas fa-file-invoice-dollar"></i> Reporte Quincenal
            </a>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Generar calendario
        function generarCalendario(mes) {
            const container = document.getElementById('calendar-container');
            container.innerHTML = '';
            
            // Días de la semana
            const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            diasSemana.forEach(dia => {
                const div = document.createElement('div');
                div.className = 'calendar-day header';
                div.textContent = dia;
                container.appendChild(div);
            });
            
            // Obtener datos del mes desde PHP (simplificado)
            // En realidad esto debería venir de una API
            const hoy = new Date();
            const mesActual = mes.split('-')[1];
            const anoActual = mes.split('-')[0];
            
            const primerDia = new Date(anoActual, mesActual - 1, 1);
            const ultimoDia = new Date(anoActual, mesActual, 0);
            const diasMes = ultimoDia.getDate();
            const primerDiaSemana = primerDia.getDay();
            
            // Espacios vacíos al inicio
            for (let i = 0; i < primerDiaSemana; i++) {
                const div = document.createElement('div');
                div.className = 'calendar-day empty';
                container.appendChild(div);
            }
            
            // Días del mes
            for (let dia = 1; dia <= diasMes; dia++) {
                const div = document.createElement('div');
                div.className = 'calendar-day';
                
                const fechaStr = `${anoActual}-${mesActual.padStart(2, '0')}-${dia.toString().padStart(2, '0')}`;
                
                // Marcar hoy
                if (hoy.getFullYear() == anoActual && 
                    hoy.getMonth() + 1 == mesActual && 
                    hoy.getDate() == dia) {
                    div.classList.add('today');
                }
                
                div.innerHTML = `
                    <div class="day-number">${dia}</div>
                    <div class="day-status status-completa">8h</div>
                `;
                
                container.appendChild(div);
            }
        }
        
        // Inicializar calendario
        generarCalendario('<?php echo $mes; ?>');
    </script>
</body>
</html>