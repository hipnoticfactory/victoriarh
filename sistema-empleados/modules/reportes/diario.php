<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh', 'supervisor']);

// Parámetros del reporte
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$departamento = isset($_GET['departamento']) ? $_GET['departamento'] : '';

// Obtener datos del reporte diario
$where = "WHERE a.fecha = ?";
$params = [$fecha];
$types = "s";

if (!empty($departamento)) {
    $where .= " AND e.departamento = ?";
    $params[] = $departamento;
    $types .= "s";
}

$sql = "SELECT 
            a.*,
            e.nombre,
            e.apellido_paterno,
            e.apellido_materno,
            e.puesto,
            e.departamento,
            e.salario_diario
        FROM asistencias a
        JOIN empleados e ON a.empleado_id = e.id
        $where
        ORDER BY e.departamento, e.apellido_paterno";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$asistencias = $stmt->get_result();

// Calcular totales
$totales = [
    'empleados' => 0,
    'horas_trabajadas' => 0,
    'horas_extra' => 0,
    'retardos' => 0,
    'faltas' => 0
];

$detalle_asistencias = [];
while ($row = $asistencias->fetch_assoc()) {
    $detalle_asistencias[] = $row;
    $totales['empleados']++;
    $totales['horas_trabajadas'] += $row['horas_trabajadas'];
    $totales['horas_extra'] += $row['horas_extra'];
    if ($row['retardo_minutos'] > 0) $totales['retardos']++;
    if ($row['tipo_asistencia'] == 'falta') $totales['faltas']++;
}

// Obtener empleados que faltaron
$faltas_sql = "SELECT e.* FROM empleados e 
               LEFT JOIN asistencias a ON e.id = a.empleado_id AND a.fecha = ?
               WHERE e.estado = 'activo' AND a.id IS NULL";

if (!empty($departamento)) {
    $faltas_sql .= " AND e.departamento = ?";
}

$faltas_sql .= " ORDER BY e.apellido_paterno";

$faltas_stmt = $conn->prepare($faltas_sql);

if (!empty($departamento)) {
    $faltas_stmt->bind_param("ss", $fecha, $departamento);
} else {
    $faltas_stmt->bind_param("s", $fecha);
}

$faltas_stmt->execute();
$empleados_faltas = $faltas_stmt->get_result();

// Obtener departamentos para filtro
$dept_result = $conn->query("SELECT DISTINCT departamento FROM empleados WHERE departamento IS NOT NULL ORDER BY departamento");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Diario - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .reporte-diario-header {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
            padding: 30px;
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
        }
        
        .fecha-seleccion {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .btn-fecha {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-md);
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-fecha:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-fecha.active {
            background: white;
            color: #4299e1;
        }
        
        .estadisticas-diarias {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .estadistica-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border-top: 4px solid;
        }
        
        .estadistica-card.asistencias {
            border-color: #48bb78;
        }
        
        .estadistica-card.horas {
            border-color: #4299e1;
        }
        
        .estadistica-card.retardos {
            border-color: #ed8936;
        }
        
        .estadistica-card.faltas {
            border-color: #f56565;
        }
        
        .estadistica-card h3 {
            margin: 0;
            font-size: 2.5rem;
            color: var(--secondary-color);
            line-height: 1;
        }
        
        .estadistica-card p {
            margin: 10px 0 0;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .detalle-reporte {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 1024px) {
            .detalle-reporte {
                grid-template-columns: 1fr;
            }
        }
        
        .seccion-reporte {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
        }
        
        .seccion-reporte h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--secondary-color);
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .lista-asistencias {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .item-asistencia {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: var(--light-gray);
            border-radius: var(--radius-md);
            transition: var(--transition);
        }
        
        .item-asistencia:hover {
            background: #f0f9ff;
            transform: translateX(5px);
        }
        
        .info-empleado h4 {
            margin: 0;
            color: var(--secondary-color);
            font-size: 1rem;
        }
        
        .info-empleado p {
            margin: 5px 0 0;
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        .horas-info {
            text-align: right;
        }
        
        .horas-info .total {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--accent-color);
        }
        
        .horas-info .extra {
            font-size: 0.85rem;
            color: var(--success-color);
        }
        
        .lista-faltas {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .item-falta {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #fed7d7;
            border-radius: var(--radius-md);
            border-left: 4px solid #f56565;
        }
        
        .item-falta i {
            color: #742a2a;
        }
        
        .info-falta h4 {
            margin: 0;
            color: #742a2a;
            font-size: 0.95rem;
        }
        
        .info-falta p {
            margin: 3px 0 0;
            color: #974a4a;
            font-size: 0.8rem;
        }
        
        .resumen-departamentos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .dept-resumen {
            text-align: center;
            padding: 15px;
            background: var(--light-gray);
            border-radius: var(--radius-md);
        }
        
        .dept-resumen h4 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--secondary-color);
        }
        
        .dept-resumen p {
            margin: 5px 0 0;
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .hora-promedio {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            border-radius: var(--radius-lg);
            margin-top: 20px;
        }
        
        .hora-promedio h4 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .hora-promedio .promedio {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="reporte-diario-header">
            <h1><i class="fas fa-calendar-day"></i> Reporte Diario de Asistencias</h1>
            <p>Consulta el resumen de asistencias para una fecha específica</p>
            
            <div class="fecha-seleccion">
                <a href="?fecha=<?php echo date('Y-m-d'); ?>&departamento=<?php echo $departamento; ?>" 
                   class="btn-fecha <?php echo $fecha == date('Y-m-d') ? 'active' : ''; ?>">
                    Hoy
                </a>
                <a href="?fecha=<?php echo date('Y-m-d', strtotime('-1 day')); ?>&departamento=<?php echo $departamento; ?>" 
                   class="btn-fecha <?php echo $fecha == date('Y-m-d', strtotime('-1 day')) ? 'active' : ''; ?>">
                    Ayer
                </a>
                <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                    <input type="date" 
                           name="fecha" 
                           class="form-control" 
                           value="<?php echo $fecha; ?>" 
                           max="<?php echo date('Y-m-d'); ?>"
                           style="background: rgba(255, 255, 255, 0.9);">
                    
                    <select name="departamento" class="form-control" style="background: rgba(255, 255, 255, 0.9);">
                        <option value="">Todos los departamentos</option>
                        <?php while ($dept = $dept_result->fetch_assoc()): ?>
                            <option value="<?php echo $dept['departamento']; ?>" 
                                <?php echo ($departamento == $dept['departamento']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['departamento']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    
                    <button type="submit" class="btn-fecha">
                        <i class="fas fa-search"></i> Ver
                    </button>
                </form>
            </div>
        </div>
        
        <div class="estadisticas-diarias">
            <div class="estadistica-card asistencias">
                <h3><?php echo $totales['empleados']; ?></h3>
                <p>Asistencias Registradas</p>
            </div>
            <div class="estadistica-card horas">
                <h3><?php echo number_format($totales['horas_trabajadas'], 1); ?></h3>
                <p>Horas Trabajadas</p>
            </div>
            <div class="estadistica-card retardos">
                <h3><?php echo $totales['retardos']; ?></h3>
                <p>Retardos</p>
            </div>
            <div class="estadistica-card faltas">
                <h3><?php echo $totales['faltas']; ?></h3>
                <p>Faltas</p>
            </div>
        </div>
        
        <div class="detalle-reporte">
            <div class="seccion-reporte">
                <h3><i class="fas fa-list-check"></i> Asistencias del Día</h3>
                
                <?php if (count($detalle_asistencias) > 0): ?>
                    <div class="lista-asistencias">
                        <?php foreach ($detalle_asistencias as $asistencia): ?>
                            <div class="item-asistencia">
                                <div class="info-empleado">
                                    <h4><?php echo htmlspecialchars($asistencia['apellido_paterno'] . ' ' . $asistencia['apellido_materno'] . ' ' . $asistencia['nombre']); ?></h4>
                                    <p><?php echo htmlspecialchars($asistencia['departamento'] . ' • ' . $asistencia['puesto']); ?></p>
                                    <small>
                                        Entrada: <?php echo $asistencia['hora_entrada'] ?: '--:--'; ?> | 
                                        Salida: <?php echo $asistencia['hora_salida'] ?: '--:--'; ?>
                                        <?php if ($asistencia['retardo_minutos'] > 0): ?>
                                            | <span style="color: #ed8936;">Retardo: <?php echo $asistencia['retardo_minutos']; ?> min</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="horas-info">
                                    <div class="total"><?php echo $asistencia['horas_trabajadas']; ?>h</div>
                                    <?php if ($asistencia['horas_extra'] > 0): ?>
                                        <div class="extra">+<?php echo $asistencia['horas_extra']; ?>h extra</div>
                                    <?php endif; ?>
                                    <small style="color: var(--text-light);">
                                        <?php echo ucfirst($asistencia['tipo_asistencia']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="text-align: center; padding: 40px;">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--medium-gray);"></i>
                        <p>No hay asistencias registradas para esta fecha</p>
                    </div>
                <?php endif; ?>
                
                <?php 
                // Calcular promedio de horas
                if ($totales['empleados'] > 0) {
                    $promedio_horas = $totales['horas_trabajadas'] / $totales['empleados'];
                } else {
                    $promedio_horas = 0;
                }
                ?>
                
                <div class="hora-promedio">
                    <h4>Promedio de Horas por Empleado</h4>
                    <div class="promedio"><?php echo number_format($promedio_horas, 1); ?>h</div>
                    <small>Jornada completa: 8.0h</small>
                </div>
            </div>
            
            <div class="seccion-reporte">
                <h3><i class="fas fa-user-slash"></i> Empleados sin Registrar</h3>
                
                <?php if ($empleados_faltas->num_rows > 0): ?>
                    <div class="lista-faltas">
                        <?php while ($falta = $empleados_faltas->fetch_assoc()): ?>
                            <div class="item-falta">
                                <i class="fas fa-exclamation-circle"></i>
                                <div class="info-falta">
                                    <h4><?php echo htmlspecialchars($falta['apellido_paterno'] . ' ' . $falta['apellido_materno'] . ' ' . $falta['nombre']); ?></h4>
                                    <p><?php echo htmlspecialchars($falta['puesto'] . ' • ' . $falta['departamento']); ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 30px; color: var(--success-color);">
                        <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>Todos los empleados tienen registro de asistencia</p>
                    </div>
                <?php endif; ?>
                
                <h3 style="margin-top: 30px;"><i class="fas fa-building"></i> Resumen por Departamento</h3>
                
                <?php
                // Calcular por departamento
                $dept_stats = [];
                foreach ($detalle_asistencias as $asistencia) {
                    $dept = $asistencia['departamento'];
                    if (!isset($dept_stats[$dept])) {
                        $dept_stats[$dept] = ['empleados' => 0, 'horas' => 0];
                    }
                    $dept_stats[$dept]['empleados']++;
                    $dept_stats[$dept]['horas'] += $asistencia['horas_trabajadas'];
                }
                ?>
                
                <?php if (!empty($dept_stats)): ?>
                    <div class="resumen-departamentos">
                        <?php foreach ($dept_stats as $dept => $stats): ?>
                            <div class="dept-resumen">
                                <h4><?php echo $stats['empleados']; ?></h4>
                                <p><?php echo htmlspecialchars($dept); ?></p>
                                <small><?php echo number_format($stats['horas'] / $stats['empleados'], 1); ?>h prom.</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 30px; padding: 20px; background: var(--light-gray); border-radius: var(--radius-md);">
                    <h4 style="margin-top: 0;"><i class="fas fa-chart-pie"></i> Distribución del Día</h4>
                    <canvas id="distribucionChart" height="150"></canvas>
                </div>
            </div>
        </div>
        
        <div class="form-actions" style="margin-top: 30px;">
            <button onclick="imprimirReporte()" class="btn-primary">
                <i class="fas fa-print"></i> Imprimir Reporte
            </button>
            <button onclick="exportarExcel()" class="btn-success">
                <i class="fas fa-file-excel"></i> Exportar a Excel
            </button>
            <a href="../asistencias/registrar.php?fecha=<?php echo $fecha; ?>" class="btn-secondary">
                <i class="fas fa-clock"></i> Registrar Asistencias
            </a>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Gráfico de distribución
        const ctx = document.getElementById('distribucionChart').getContext('2d');
        
        // Datos para el gráfico
        const datosDistribucion = {
            labels: ['Horas Normales', 'Horas Extra', 'Tiempo Perdido'],
            datasets: [{
                data: [
                    <?php echo $totales['horas_trabajadas']; ?>,
                    <?php echo $totales['horas_extra']; ?>,
                    <?php echo ($totales['empleados'] * 8) - $totales['horas_trabajadas']; ?>
                ],
                backgroundColor: [
                    '#48bb78',
                    '#ed8936',
                    '#e2e8f0'
                ],
                borderWidth: 1
            }]
        };
        
        const distribucionChart = new Chart(ctx, {
            type: 'doughnut',
            data: datosDistribucion,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        function imprimirReporte() {
            window.print();
        }
        
        function exportarExcel() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.location.href = 'exportar.php?' + params.toString();
        }
        
        // Auto-submit al cambiar fecha
        document.querySelector('input[name="fecha"]').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>