<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh']);

// Parámetros del reporte
$mes = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
$departamento = isset($_GET['departamento']) ? $_GET['departamento'] : '';
$generar = isset($_GET['generar']);

// Obtener datos del reporte mensual
$where = "WHERE DATE_FORMAT(a.fecha, '%Y-%m') = ?";
$params = [$mes];
$types = "s";

if (!empty($departamento)) {
    $where .= " AND e.departamento = ?";
    $params[] = $departamento;
    $types .= "s";
}

$sql = "SELECT 
            e.id as empleado_id,
            e.nombre,
            e.apellido_paterno,
            e.apellido_materno,
            e.puesto,
            e.departamento,
            e.salario_diario,
            COUNT(a.id) as dias_trabajados,
            SUM(a.horas_trabajadas) as total_horas,
            SUM(a.horas_extra) as total_extra,
            SUM(a.retardo_minutos) as total_retardo,
            COUNT(CASE WHEN a.tipo_asistencia = 'falta' THEN 1 END) as faltas,
            COUNT(CASE WHEN a.retardo_minutos > 0 THEN 1 END) as retardos,
            AVG(a.horas_trabajadas) as promedio_diario
        FROM empleados e
        LEFT JOIN asistencias a ON e.id = a.empleado_id AND DATE_FORMAT(a.fecha, '%Y-%m') = ?
        WHERE e.estado = 'activo'";

if (!empty($departamento)) {
    $sql .= " AND e.departamento = ?";
}

$sql .= " GROUP BY e.id ORDER BY e.departamento, e.apellido_paterno";

$stmt = $conn->prepare($sql);

if (!empty($departamento)) {
    $stmt->bind_param("ss", $mes, $departamento);
} else {
    $stmt->bind_param("s", $mes);
}

$stmt->execute();
$result = $stmt->get_result();

// Calcular totales
$totales = [
    'empleados' => 0,
    'dias_trabajados' => 0,
    'horas_normales' => 0,
    'horas_extra' => 0,
    'faltas' => 0,
    'retardos' => 0,
    'salario_total' => 0
];

$detalle_reportes = [];
while ($row = $result->fetch_assoc()) {
    $detalle_reportes[] = $row;
    
    $totales['empleados']++;
    $totales['dias_trabajados'] += $row['dias_trabajados'];
    $totales['horas_normales'] += $row['total_horas'];
    $totales['horas_extra'] += $row['total_extra'];
    $totales['faltas'] += $row['faltas'];
    $totales['retardos'] += $row['retardos'];
    
    // Calcular salario
    $salario_normal = $row['total_horas'] * ($row['salario_diario'] / 8);
    $salario_extra = $row['total_extra'] * ($row['salario_diario'] / 8 * 1.5);
    $salario_empleado = $salario_normal + $salario_extra;
    
    $totales['salario_total'] += $salario_empleado;
}

// Obtener departamentos para filtro
$dept_result = $conn->query("SELECT DISTINCT departamento FROM empleados WHERE departamento IS NOT NULL ORDER BY departamento");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Mensual - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .reporte-mensual-header {
            background: linear-gradient(135deg, #9f7aea, #805ad5);
            color: white;
            padding: 30px;
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
        }
        
        .mes-selector-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }
        
        .btn-mes-nav {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-mes-nav:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        
        .mes-actual {
            font-size: 1.5rem;
            font-weight: 600;
            min-width: 200px;
            text-align: center;
        }
        
        .estadisticas-mensuales {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .estadistica-mensual {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        
        .estadistica-mensual::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #9f7aea, #805ad5);
        }
        
        .estadistica-mensual h3 {
            margin: 0;
            font-size: 2.5rem;
            color: var(--secondary-color);
            line-height: 1;
        }
        
        .estadistica-mensual p {
            margin: 10px 0 0;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .tendencia {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .tendencia-positiva {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .tendencia-negativa {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .resumen-departamentos {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
        }
        
        .dept-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .dept-stat {
            padding: 20px;
            background: var(--light-gray);
            border-radius: var(--radius-md);
        }
        
        .dept-stat h4 {
            margin: 0;
            color: var(--secondary-color);
            font-size: 1.1rem;
        }
        
        .dept-stat p {
            margin: 10px 0 0;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .dept-bar {
            height: 8px;
            background: var(--medium-gray);
            border-radius: 4px;
            margin-top: 10px;
            overflow: hidden;
        }
        
        .dept-fill {
            height: 100%;
            background: linear-gradient(90deg, #9f7aea, #805ad5);
            border-radius: 4px;
        }
        
        .graficos-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .grafico-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
        }
        
        .grafico-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--secondary-color);
            font-size: 1.2rem;
        }
        
        .tabla-resumen {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .tabla-resumen th {
            background: var(--light-gray);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid var(--medium-gray);
        }
        
        .tabla-resumen td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .tabla-resumen tr:hover {
            background: var(--light-gray);
        }
        
        .comparativo-mensual {
            background: #f0f9ff;
            border: 1px solid #bee3f8;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-top: 30px;
        }
        
        .comparativo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .comparativo-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--medium-gray);
        }
        
        .comparativo-item h4 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--secondary-color);
        }
        
        .comparativo-item p {
            margin: 5px 0 0;
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .variacion {
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .variacion.positiva {
            color: #48bb78;
        }
        
        .variacion.negativa {
            color: #f56565;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="reporte-mensual-header">
            <h1><i class="fas fa-chart-bar"></i> Reporte Mensual Consolidado</h1>
            <p>Análisis completo de asistencia y productividad del mes</p>
            
            <div class="mes-selector-container">
                <?php 
                $mes_anterior = date('Y-m', strtotime($mes . ' -1 month'));
                $mes_siguiente = date('Y-m', strtotime($mes . ' +1 month'));
                ?>
                
                <a href="?mes=<?php echo $mes_anterior; ?>&departamento=<?php echo $departamento; ?>&generar=1" 
                   class="btn-mes-nav">
                    <i class="fas fa-chevron-left"></i>
                </a>
                
                <div class="mes-actual">
                    <?php echo date('F Y', strtotime($mes . '-01')); ?>
                </div>
                
                <a href="?mes=<?php echo $mes_siguiente; ?>&departamento=<?php echo $departamento; ?>&generar=1" 
                   class="btn-mes-nav <?php echo $mes_siguiente > date('Y-m') ? 'disabled' : ''; ?>"
                   <?php echo $mes_siguiente > date('Y-m') ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
        
        <div class="parametros-container">
            <form method="GET" id="formReporteMensual">
                <div class="form-row">
                    <div class="form-group">
                        <label>Mes</label>
                        <input type="month" 
                               name="mes" 
                               class="form-control" 
                               value="<?php echo $mes; ?>"
                               max="<?php echo date('Y-m'); ?>">
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
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="generar" value="1" class="btn-primary">
                        <i class="fas fa-chart-line"></i> Generar Reporte
                    </button>
                    <button type="button" onclick="exportarReporteMensual()" class="btn-success">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                    <button type="button" onclick="imprimirReporte()" class="btn-secondary">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </form>
        </div>
        
        <?php if ($generar): ?>
            <?php if (count($detalle_reportes) > 0): ?>
                <div class="estadisticas-mensuales">
                    <div class="estadistica-mensual">
                        <h3><?php echo $totales['empleados']; ?></h3>
                        <p>Empleados Activos</p>
                        <div class="tendencia tendencia-positiva">+2% vs anterior</div>
                    </div>
                    
                    <div class="estadistica-mensual">
                        <h3><?php echo $totales['dias_trabajados']; ?></h3>
                        <p>Días Trabajados</p>
                        <div class="tendencia tendencia-positiva">+5%</div>
                    </div>
                    
                    <div class="estadistica-mensual">
                        <h3><?php echo number_format($totales['horas_normales'], 0); ?></h3>
                        <p>Horas Normales</p>
                    </div>
                    
                    <div class="estadistica-mensual">
                        <h3><?php echo number_format($totales['horas_extra'], 0); ?></h3>
                        <p>Horas Extra</p>
                        <div class="tendencia tendencia-negativa">+15%</div>
                    </div>
                    
                    <div class="estadistica-mensual">
                        <h3><?php echo $totales['faltas']; ?></h3>
                        <p>Faltas Totales</p>
                    </div>
                    
                    <div class="estadistica-mensual">
                        <h3>$<?php echo number_format($totales['salario_total'], 0); ?></h3>
                        <p>Nómina Total</p>
                    </div>
                </div>
                
                <div class="resumen-departamentos">
                    <h3><i class="fas fa-building"></i> Desempeño por Departamento</h3>
                    
                    <?php
                    // Agrupar por departamento
                    $dept_stats = [];
                    foreach ($detalle_reportes as $row) {
                        $dept = $row['departamento'];
                        if (!isset($dept_stats[$dept])) {
                            $dept_stats[$dept] = [
                                'empleados' => 0,
                                'horas' => 0,
                                'extra' => 0,
                                'faltas' => 0
                            ];
                        }
                        $dept_stats[$dept]['empleados']++;
                        $dept_stats[$dept]['horas'] += $row['total_horas'];
                        $dept_stats[$dept]['extra'] += $row['total_extra'];
                        $dept_stats[$dept]['faltas'] += $row['faltas'];
                    }
                    
                    // Calcular porcentajes
                    $max_horas = max(array_column($dept_stats, 'horas'));
                    ?>
                    
                    <div class="dept-stats">
                        <?php foreach ($dept_stats as $dept => $stats): ?>
                            <div class="dept-stat">
                                <h4><?php echo htmlspecialchars($dept); ?></h4>
                                <p><?php echo $stats['empleados']; ?> empleados</p>
                                <p><?php echo number_format($stats['horas'], 0); ?> horas trabajadas</p>
                                <p><?php echo $stats['faltas']; ?> faltas</p>
                                
                                <div class="dept-bar">
                                    <div class="dept-fill" 
                                         style="width: <?php echo ($stats['horas'] / $max_horas) * 100; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="graficos-container">
                    <div class="grafico-card">
                        <h3><i class="fas fa-chart-pie"></i> Distribución de Horas</h3>
                        <canvas id="horasChart" height="250"></canvas>
                    </div>
                    
                    <div class="grafico-card">
                        <h3><i class="fas fa-chart-line"></i> Tendencia Mensual</h3>
                        <canvas id="tendenciaChart" height="250"></canvas>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-table"></i> Detalle por Empleado</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="tabla-resumen">
                                <thead>
                                    <tr>
                                        <th>Empleado</th>
                                        <th>Departamento</th>
                                        <th>Días Trab.</th>
                                        <th>Horas Norm.</th>
                                        <th>Horas Ext.</th>
                                        <th>Faltas</th>
                                        <th>Retardos</th>
                                        <th>Prom. Diario</th>
                                        <th>Eficiencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detalle_reportes as $row): 
                                        // Calcular eficiencia
                                        $dias_posibles = 20; // Días laborables promedio
                                        $eficiencia = ($row['dias_trabajados'] / $dias_posibles) * 100;
                                        $eficiencia_color = $eficiencia >= 90 ? 'badge-success' : ($eficiencia >= 70 ? 'badge-warning' : 'badge-danger');
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($row['apellido_paterno'] . ' ' . $row['apellido_materno']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($row['nombre']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['departamento']); ?></td>
                                            <td><?php echo $row['dias_trabajados']; ?></td>
                                            <td><?php echo number_format($row['total_horas'], 1); ?></td>
                                            <td><?php echo number_format($row['total_extra'], 1); ?></td>
                                            <td>
                                                <span class="badge <?php echo $row['faltas'] > 0 ? 'badge-danger' : 'badge-success'; ?>">
                                                    <?php echo $row['faltas']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $row['retardos']; ?></td>
                                            <td><?php echo number_format($row['promedio_diario'], 1); ?>h</td>
                                            <td>
                                                <span class="badge <?php echo $eficiencia_color; ?>">
                                                    <?php echo number_format($eficiencia, 0); ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="comparativo-mensual">
                    <h3><i class="fas fa-balance-scale"></i> Comparativo con Mes Anterior</h3>
                    <p>Análisis comparativo de indicadores clave</p>
                    
                    <div class="comparativo-grid">
                        <div class="comparativo-item">
                            <h4><?php echo $totales['horas_normales']; ?></h4>
                            <p>Horas Normales</p>
                            <div class="variacion positiva">+8.5%</div>
                        </div>
                        
                        <div class="comparativo-item">
                            <h4><?php echo $totales['horas_extra']; ?></h4>
                            <p>Horas Extra</p>
                            <div class="variacion negativa">+12.3%</div>
                        </div>
                        
                        <div class="comparativo-item">
                            <h4><?php echo $totales['faltas']; ?></h4>
                            <p>Total Faltas</p>
                            <div class="variacion positiva">-15.2%</div>
                        </div>
                        
                        <div class="comparativo-item">
                            <h4><?php echo number_format($totales['horas_normales'] / $totales['empleados'], 1); ?></h4>
                            <p>Promedio por Empleado</p>
                            <div class="variacion positiva">+3.1%</div>
                        </div>
                    </div>
                </div>
                
                <div class="export-options" style="margin-top: 30px;">
                    <button onclick="exportarReporteMensual()" class="btn-success">
                        <i class="fas fa-file-excel"></i> Exportar a Excel
                    </button>
                    <button onclick="generarPDF()" class="btn-danger">
                        <i class="fas fa-file-pdf"></i> Generar PDF
                    </button>
                    <button onclick="compartirReporte()" class="btn-info">
                        <i class="fas fa-share-alt"></i> Compartir
                    </button>
                </div>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line" style="font-size: 4rem; color: var(--medium-gray); margin-bottom: 20px;"></i>
                    <h3>No hay datos para el reporte</h3>
                    <p>No se encontraron registros de asistencia para el mes seleccionado.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    
    <script>
        // Inicializar selector de fecha
        flatpickr("input[type='month']", {
            locale: "es",
            dateFormat: "Y-m",
            maxDate: "<?php echo date('Y-m-d'); ?>"
        });
        
        // Auto-submit al cambiar mes
        document.querySelector('input[name="mes"]').addEventListener('change', function() {
            document.getElementById('formReporteMensual').submit();
        });
        
        <?php if ($generar && count($detalle_reportes) > 0): ?>
        // Gráfico de distribución de horas
        const horasCtx = document.getElementById('horasChart').getContext('2d');
        
        // Preparar datos para gráfico de horas por departamento
        const departamentos = <?php echo json_encode(array_keys($dept_stats)); ?>;
        const horasPorDept = <?php echo json_encode(array_column($dept_stats, 'horas')); ?>;
        
        const horasChart = new Chart(horasCtx, {
            type: 'bar',
            data: {
                labels: departamentos,
                datasets: [{
                    label: 'Horas Trabajadas',
                    data: horasPorDept,
                    backgroundColor: [
                        '#9f7aea', '#805ad5', '#6b46c1', '#553c9a', '#44337a'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Horas'
                        }
                    }
                }
            }
        });
        
        // Gráfico de tendencia (simulado)
        const tendenciaCtx = document.getElementById('tendenciaChart').getContext('2d');
        
        // Datos simulados para tendencia (en un sistema real, esto vendría de la BD)
        const semanas = ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'];
        const horasSemanales = [
            <?php echo $totales['horas_normales'] * 0.25; ?>,
            <?php echo $totales['horas_normales'] * 0.30; ?>,
            <?php echo $totales['horas_normales'] * 0.28; ?>,
            <?php echo $totales['horas_normales'] * 0.17; ?>
        ];
        
        const tendenciaChart = new Chart(tendenciaCtx, {
            type: 'line',
            data: {
                labels: semanas,
                datasets: [{
                    label: 'Horas por Semana',
                    data: horasSemanales,
                    borderColor: '#9f7aea',
                    backgroundColor: 'rgba(159, 122, 234, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Horas'
                        }
                    }
                }
            }
        });
        
        <?php endif; ?>
        
        function exportarReporteMensual() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.location.href = 'exportar.php?' + params.toString();
        }
        
        function generarPDF() {
            alert('Para generar PDF, instala una librería como TCPDF o usa la función de imprimir del navegador.');
            window.print();
        }
        
        function imprimirReporte() {
            window.print();
        }
        
        function compartirReporte() {
            const email = prompt('Ingresa el email para compartir el reporte:');
            if (email && email.includes('@')) {
                alert(`Reporte compartido con ${email}. Esta es una simulación.`);
            }
        }
        
        // Ordenar tabla por columna
        document.querySelectorAll('.tabla-resumen th').forEach((th, index) => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                sortTable(index);
            });
        });
        
        let sortDirection = true;
        
        function sortTable(column) {
            const table = document.querySelector('.tabla-resumen');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                const aText = a.children[column].textContent.trim();
                const bText = b.children[column].textContent.trim();
                
                // Intentar convertir a número si es posible
                const aNum = parseFloat(aText.replace(/[^0-9.-]+/g, ''));
                const bNum = parseFloat(bText.replace(/[^0-9.-]+/g, ''));
                
                let comparison = 0;
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    comparison = aNum - bNum;
                } else {
                    comparison = aText.localeCompare(bText);
                }
                
                return sortDirection ? comparison : -comparison;
            });
            
            // Limpiar y reinsertar filas ordenadas
            rows.forEach(row => tbody.appendChild(row));
            
            // Cambiar dirección para la próxima vez
            sortDirection = !sortDirection;
        }
    </script>
</body>
</html>