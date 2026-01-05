<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh']);

// Parámetros del reporte
$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;
$mes = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
$quincena = isset($_GET['quincena']) ? (int)$_GET['quincena'] : (date('d') <= 15 ? 1 : 2);
$generar = isset($_GET['generar']);

// Definir fechas de la quincena
if ($quincena == 1) {
    $fecha_inicio = $mes . '-01';
    $fecha_fin = $mes . '-15';
} else {
    $fecha_inicio = $mes . '-16';
    $fecha_fin = $mes . '-31';
}

// Obtener empleados para filtro
$empleados_result = $conn->query("
    SELECT id, nombre, apellido_paterno, apellido_materno, puesto, departamento 
    FROM empleados 
    WHERE estado = 'activo' 
    ORDER BY departamento, apellido_paterno
");

// Generar reporte si se solicita
if ($generar) {
    // Construir consulta
    $where = "WHERE a.fecha BETWEEN ? AND ?";
    $params = [$fecha_inicio, $fecha_fin];
    $types = "ss";
    
    if ($empleado_id > 0) {
        $where .= " AND e.id = ?";
        $params[] = $empleado_id;
        $types .= "i";
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
                COUNT(CASE WHEN a.tipo_asistencia = 'incapacidad' THEN 1 END) as incapacidades,
                COUNT(CASE WHEN a.tipo_asistencia = 'vacaciones' THEN 1 END) as vacaciones,
                COUNT(CASE WHEN a.tipo_asistencia = 'permiso' THEN 1 END) as permisos
            FROM empleados e
            LEFT JOIN asistencias a ON e.id = a.empleado_id AND a.fecha BETWEEN ? AND ?
            WHERE e.estado = 'activo'";
    
    if ($empleado_id > 0) {
        $sql .= " AND e.id = ?";
    }
    
    $sql .= " GROUP BY e.id ORDER BY e.departamento, e.apellido_paterno";
    
    $stmt = $conn->prepare($sql);
    
    if ($empleado_id > 0) {
        $stmt->bind_param("ssi", $fecha_inicio, $fecha_fin, $empleado_id);
    } else {
        $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    }
    
    $stmt->execute();
    $reporte = $stmt->get_result();
    
    // Calcular totales
    $totales = [
        'empleados' => 0,
        'horas_normales' => 0,
        'horas_extra' => 0,
        'faltas' => 0,
        'salario_total' => 0
    ];
    
    $detalles_reportes = [];
    while ($row = $reporte->fetch_assoc()) {
        $detalles_reportes[] = $row;
        
        $totales['empleados']++;
        $totales['horas_normales'] += $row['total_horas'];
        $totales['horas_extra'] += $row['total_extra'];
        $totales['faltas'] += $row['faltas'];
        
        // Calcular salario
        $salario_normal = $row['total_horas'] * ($row['salario_diario'] / 8);
        $salario_extra = $row['total_extra'] * ($row['salario_diario'] / 8 * 1.5);
        $salario_empleado = $salario_normal + $salario_extra;
        
        $totales['salario_total'] += $salario_empleado;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Quincenal - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .reporte-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
        }
        
        .reporte-header h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .reporte-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        
        .parametros-container {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
        }
        
        .totales-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .total-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border-top: 4px solid var(--accent-color);
        }
        
        .total-card h3 {
            margin: 0;
            font-size: 2.5rem;
            color: var(--secondary-color);
            line-height: 1;
        }
        
        .total-card p {
            margin: 10px 0 0;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .reporte-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
        }
        
        .reporte-table th {
            background: var(--light-gray);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid var(--medium-gray);
            position: sticky;
            top: 0;
        }
        
        .reporte-table td {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .reporte-table tr:hover {
            background: var(--light-gray);
        }
        
        .salario-cell {
            font-weight: 600;
            color: var(--success-color);
        }
        
        .footer-totales {
            background: var(--secondary-color);
            color: white;
            padding: 20px;
            border-radius: var(--radius-lg);
            margin-top: 30px;
        }
        
        .footer-totales .total {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .export-options {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-print {
            background: var(--info-color);
            color: white;
        }
        
        .btn-email {
            background: var(--warning-color);
            color: white;
        }
        
        .quincena-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn-quincena {
            flex: 1;
            padding: 15px;
            background: var(--light-gray);
            border: 2px solid var(--medium-gray);
            border-radius: var(--radius-md);
            color: var(--text-dark);
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-quincena:hover,
        .btn-quincena.active {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .periodo-info {
            text-align: center;
            padding: 20px;
            background: #f0f9ff;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border: 1px solid #bee3f8;
        }
        
        .periodo-info h3 {
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="reporte-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Reporte Quincenal de Nómina</h1>
            <p>Genera y consulta reportes de asistencia y cálculo de nómina</p>
        </div>
        
        <div class="parametros-container">
            <h3><i class="fas fa-cog"></i> Parámetros del Reporte</h3>
            <form method="GET" id="formReporte">
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
                        <label>Empleado</label>
                        <select name="empleado_id" class="form-control">
                            <option value="">Todos los empleados</option>
                            <?php while ($emp = $empleados_result->fetch_assoc()): ?>
                                <option value="<?php echo $emp['id']; ?>" 
                                    <?php echo ($empleado_id == $emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['departamento'] . ' - ' . $emp['apellido_paterno'] . ' ' . $emp['apellido_materno'] . ' ' . $emp['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="quincena-selector">
                    <label class="btn-quincena <?php echo $quincena == 1 ? 'active' : ''; ?>">
                        <input type="radio" 
                               name="quincena" 
                               value="1" 
                               <?php echo $quincena == 1 ? 'checked' : ''; ?> 
                               style="display: none;">
                        <div>Primera Quincena</div>
                        <small>1 al 15</small>
                    </label>
                    
                    <label class="btn-quincena <?php echo $quincena == 2 ? 'active' : ''; ?>">
                        <input type="radio" 
                               name="quincena" 
                               value="2" 
                               <?php echo $quincena == 2 ? 'checked' : ''; ?> 
                               style="display: none;">
                        <div>Segunda Quincena</div>
                        <small>16 al último día</small>
                    </label>
                </div>
                
                <div class="periodo-info">
                    <h3>Periodo Seleccionado</h3>
                    <p>
                        <strong>Desde:</strong> <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> 
                        <strong>Hasta:</strong> <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
                    </p>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="generar" value="1" class="btn-primary">
                        <i class="fas fa-chart-bar"></i> Generar Reporte
                    </button>
                    <button type="button" class="btn-secondary" onclick="imprimirReporte()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <button type="button" class="btn-success" onclick="exportarExcel()">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                </div>
            </form>
        </div>
        
        <?php if ($generar): ?>
            <?php if (count($detalles_reportes) > 0): ?>
                <div class="totales-container">
                    <div class="total-card">
                        <h3><?php echo $totales['empleados']; ?></h3>
                        <p>Empleados</p>
                    </div>
                    <div class="total-card">
                        <h3><?php echo number_format($totales['horas_normales'], 1); ?></h3>
                        <p>Horas Normales</p>
                    </div>
                    <div class="total-card">
                        <h3><?php echo number_format($totales['horas_extra'], 1); ?></h3>
                        <p>Horas Extra</p>
                    </div>
                    <div class="total-card">
                        <h3><?php echo $totales['faltas']; ?></h3>
                        <p>Faltas</p>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="reporte-table">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Departamento</th>
                                <th>Días Trab.</th>
                                <th>Horas Norm.</th>
                                <th>Horas Ext.</th>
                                <th>Faltas</th>
                                <th>Salario Diario</th>
                                <th>Salario Normal</th>
                                <th>Salario Extra</th>
                                <th>Total a Pagar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles_reportes as $row): ?>
                                <?php 
                                // Cálculos de salario
                                $salario_normal = $row['total_horas'] * ($row['salario_diario'] / 8);
                                $salario_extra = $row['total_extra'] * ($row['salario_diario'] / 8 * 1.5);
                                $total_pagar = $salario_normal + $salario_extra;
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
                                    <td>$<?php echo number_format($row['salario_diario'], 2); ?></td>
                                    <td>$<?php echo number_format($salario_normal, 2); ?></td>
                                    <td>$<?php echo number_format($salario_extra, 2); ?></td>
                                    <td class="salario-cell">$<?php echo number_format($total_pagar, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="footer-totales">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; color: white;">Total General</h3>
                            <p style="margin: 5px 0 0; opacity: 0.9;">
                                Periodo: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
                            </p>
                        </div>
                        <div class="total">$<?php echo number_format($totales['salario_total'], 2); ?></div>
                    </div>
                </div>
                
                <div class="export-options">
                    <button class="btn btn-print" onclick="imprimirReporte()">
                        <i class="fas fa-print"></i> Imprimir Reporte
                    </button>
                    <button class="btn btn-email" onclick="enviarPorEmail()">
                        <i class="fas fa-envelope"></i> Enviar por Email
                    </button>
                    <a href="exportar.php?tipo=excel&mes=<?php echo $mes; ?>&quincena=<?php echo $quincena; ?>&empleado_id=<?php echo $empleado_id; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Descargar Excel
                    </a>
                    <a href="exportar.php?tipo=pdf&mes=<?php echo $mes; ?>&quincena=<?php echo $quincena; ?>&empleado_id=<?php echo $empleado_id; ?>" 
                       class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Descargar PDF
                    </a>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line" style="font-size: 4rem; color: var(--medium-gray); margin-bottom: 20px;"></i>
                    <h3>No hay datos para el reporte</h3>
                    <p>No se encontraron registros de asistencia para el periodo seleccionado.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Instrucciones</h3>
                </div>
                <div class="card-body">
                    <p>Para generar un reporte quincenal:</p>
                    <ol>
                        <li>Selecciona el mes y la quincena deseada</li>
                        <li>Elige un empleado específico o deja "Todos los empleados" para reporte general</li>
                        <li>Haz clic en "Generar Reporte"</li>
                        <li>Exporta el resultado en el formato que necesites</li>
                    </ol>
                    <p><strong>Nota:</strong> El cálculo de salario incluye horas normales y horas extra al 150%.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Manejo de botones de quincena
        document.querySelectorAll('.btn-quincena').forEach(btn => {
            btn.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                document.querySelectorAll('.btn-quincena').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Actualizar período mostrado
                actualizarPeriodoInfo();
            });
        });
        
        function actualizarPeriodoInfo() {
            const mes = document.querySelector('input[name="mes"]').value;
            const quincena = document.querySelector('input[name="quincena"]:checked').value;
            
            if (!mes) return;
            
            const año = mes.split('-')[0];
            const mesNum = mes.split('-')[1];
            
            let inicio, fin;
            if (quincena == 1) {
                inicio = `${año}-${mesNum}-01`;
                fin = `${año}-${mesNum}-15`;
            } else {
                inicio = `${año}-${mesNum}-16`;
                // Último día del mes
                const ultimoDia = new Date(año, mesNum, 0).getDate();
                fin = `${año}-${mesNum}-${ultimoDia}`;
            }
            
            // Formatear fechas
            const options = { day: '2-digit', month: '2-digit', year: 'numeric' };
            const inicioFormatted = new Date(inicio).toLocaleDateString('es-MX', options);
            const finFormatted = new Date(fin).toLocaleDateString('es-MX', options);
            
            // Actualizar texto
            const periodoInfo = document.querySelector('.periodo-info');
            if (periodoInfo) {
                periodoInfo.innerHTML = `
                    <h3>Periodo Seleccionado</h3>
                    <p>
                        <strong>Desde:</strong> ${inicioFormatted} 
                        <strong>Hasta:</strong> ${finFormatted}
                    </p>
                `;
            }
        }
        
        // Auto-actualizar al cambiar mes
        document.querySelector('input[name="mes"]').addEventListener('change', actualizarPeriodoInfo);
        
        // Imprimir reporte
        function imprimirReporte() {
            window.print();
        }
        
        // Exportar a Excel
        function exportarExcel() {
            const params = new URLSearchParams(window.location.search);
            params.set('tipo', 'excel');
            window.location.href = 'exportar.php?' + params.toString();
        }
        
        // Enviar por email (simulado)
        function enviarPorEmail() {
            const email = prompt('Ingresa el email para enviar el reporte:');
            if (email && email.includes('@')) {
                alert(`Reporte enviado a ${email}. Esta es una simulación.`);
            }
        }
        
        // Inicializar
        actualizarPeriodoInfo();
    </script>
</body>
</html>