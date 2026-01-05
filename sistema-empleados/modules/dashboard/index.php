<?php
// INICIAR SESIÓN SI NO ESTÁ INICIADA
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Obtener la ruta base del proyecto
$base_url = '/sistema-empleados/';

// Incluir archivos necesarios
require_once '../../config/database.php';
require_once '../../includes/auth_check.php';

// Obtener datos del usuario
$userData = getUserData();

// Obtener estadísticas
$stats = [];

// Total empleados
$result = $conn->query("SELECT COUNT(*) as total FROM empleados WHERE estado = 'activo'");
$stats['empleados'] = $result->fetch_assoc()['total'];

// Asistencias hoy
$hoy = date('Y-m-d');
$result = $conn->prepare("SELECT COUNT(*) as total FROM asistencias WHERE fecha = ?");
$result->bind_param("s", $hoy);
$result->execute();
$stats['asistencias_hoy'] = $result->get_result()->fetch_assoc()['total'];

// Faltas hoy
$result = $conn->prepare("
    SELECT COUNT(*) as faltas 
    FROM empleados e 
    LEFT JOIN asistencias a ON e.id = a.empleado_id AND a.fecha = ? 
    WHERE e.estado = 'activo' AND a.id IS NULL
");
$result->bind_param("s", $hoy);
$result->execute();
$stats['faltas_hoy'] = $result->get_result()->fetch_assoc()['faltas'];

// Expedientes pendientes
$result = $conn->query("
    SELECT COUNT(DISTINCT e.id) as pendientes 
    FROM empleados e 
    WHERE e.estado = 'activo' 
    AND NOT EXISTS (
        SELECT 1 FROM expedientes ex 
        WHERE ex.empleado_id = e.id 
        AND ex.tipo_documento IN ('ine', 'curp', 'rfc', 'nss')
    )
");
$stats['expedientes_pendientes'] = $result->fetch_assoc()['pendientes'];

// Últimas asistencias
$result = $conn->query("
    SELECT a.*, e.nombre, e.apellido_paterno 
    FROM asistencias a 
    JOIN empleados e ON a.empleado_id = e.id 
    ORDER BY a.fecha DESC, a.hora_entrada DESC 
    LIMIT 10
");
$ultimas_asistencias = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Empleados</title>
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>assets/icono.png">
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .welcome-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #667eea;
        }
        
        .welcome-section h1 {
            margin: 0;
            color: #2d3748;
            font-size: 2rem;
        }
        
        .subtitle {
            color: #718096;
            margin-top: 10px;
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .stat-info h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            line-height: 1;
        }
        
        .stat-info p {
            color: #718096;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .stat-trend {
            margin-left: auto;
            padding: 5px 10px;
            background: #f7fafc;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #48bb78;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        
        .card-header {
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f7fafc;
        }
        
        .card-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-link {
            color: #667eea;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-link:hover {
            text-decoration: underline;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: #f7fafc;
        }
        
        .data-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #4a5568;
        }
        
        .data-table tbody tr {
            transition: all 0.3s;
        }
        
        .data-table tbody tr:hover {
            background: #f7fafc;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .quick-action {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f7fafc;
            border-radius: 10px;
            transition: all 0.3s;
            border: 1px solid transparent;
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action:hover {
            background: white;
            border-color: #667eea;
            transform: translateX(5px);
        }
        
        .quick-action i {
            font-size: 1.5rem;
            color: #667eea;
        }
        
        .quick-action span {
            font-weight: 500;
            color: #2d3748;
        }
        
        .calendar-widget {
            margin-top: 20px;
        }
        
        .calendar-widget h4 {
            margin-bottom: 15px;
            color: #2d3748;
            font-size: 1rem;
        }
        
        #mini-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            border-radius: 6px;
            background: #f7fafc;
            transition: all 0.3s;
        }
        
        .calendar-day.header {
            background: transparent;
            font-weight: 600;
            color: #718096;
            font-size: 0.7rem;
        }
        
        .calendar-day.today {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        
        .calendar-day:not(.header):hover {
            background: #e2e8f0;
            cursor: pointer;
        }
        
        .notifications {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .notification {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        
        .notification:hover {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .notification i {
            font-size: 1.2rem;
            color: #667eea;
            margin-top: 2px;
        }
        
        .notification p {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .notification small {
            color: #718096;
            font-size: 0.8rem;
        }
        
        .footer {
            background: #2d3748;
            color: white;
            padding: 30px 0;
            margin-top: 50px;
        }
        
        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .footer-logo img {
            width: 30px;
            height: 30px;
            border-radius: 6px;
        }
        
        .footer-info {
            text-align: right;
        }
        
        .footer-info p {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 15px;
                padding: 15px 0;
            }
            
            .nav-menu ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .nav-link {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .footer-info {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <div class="logo">
                <img src="<?php echo $base_url; ?>assets/icono.png" alt="Logo">
                <div class="logo-text">
                    <h1>Sistema de Empleados</h1>
                    <p>Control y Gestión</p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li>
                        <a href="<?php echo $base_url; ?>modules/dashboard/index.php" class="nav-link active">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>modules/empleados/index.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Empleados</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>modules/asistencias/index.php" class="nav-link">
                            <i class="fas fa-clock"></i>
                            <span>Asistencias</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>modules/expedientes/index.php" class="nav-link">
                            <i class="fas fa-folder"></i>
                            <span>Expedientes</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>modules/reportes/index.php" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reportes</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></div>
                    <div class="user-role"><?php echo isset($_SESSION['rol']) ? ucfirst($_SESSION['rol']) : 'Usuario'; ?></div>
                </div>
                <a href="<?php echo $base_url; ?>logout.php" class="logout-btn" title="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <div class="welcome-section">
            <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></h1>
            <p class="subtitle"><?php echo date('l, j F Y'); ?></p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['empleados']; ?></h3>
                    <p>Empleados Activos</p>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i> 12%
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['asistencias_hoy']; ?></h3>
                    <p>Asistencias Hoy</p>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-check-circle"></i> Actual
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['faltas_hoy']; ?></h3>
                    <p>Faltas Hoy</p>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['expedientes_pendientes']; ?></h3>
                    <p>Expedientes Pendientes</p>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Últimas Asistencias</h3>
                    <a href="<?php echo $base_url; ?>modules/asistencias/index.php" class="btn-link">Ver todas</a>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Fecha</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Horas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimas_asistencias as $asistencia): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asistencia['nombre'] . ' ' . $asistencia['apellido_paterno']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($asistencia['fecha'])); ?></td>
                                <td><?php echo $asistencia['hora_entrada'] ?: '--:--'; ?></td>
                                <td><?php echo $asistencia['hora_salida'] ?: '--:--'; ?></td>
                                <td><span class="badge badge-success"><?php echo $asistencia['horas_trabajadas'] ?: '0'; ?>h</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Acciones Rápidas</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="<?php echo $base_url; ?>modules/empleados/agregar.php" class="quick-action">
                            <i class="fas fa-user-plus"></i>
                            <span>Agregar Empleado</span>
                        </a>
                        <a href="<?php echo $base_url; ?>modules/asistencias/registrar.php" class="quick-action">
                            <i class="fas fa-clock"></i>
                            <span>Registrar Asistencia</span>
                        </a>
                        <a href="<?php echo $base_url; ?>modules/reportes/quincenal.php" class="quick-action">
                            <i class="fas fa-file-alt"></i>
                            <span>Reporte Quincenal</span>
                        </a>
                        <a href="<?php echo $base_url; ?>modules/expedientes/index.php" class="quick-action">
                            <i class="fas fa-folder"></i>
                            <span>Ver Expedientes</span>
                        </a>
                    </div>
                    
                    <div class="calendar-widget">
                        <h4>Calendario</h4>
                        <div id="mini-calendar"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bell"></i> Notificaciones</h3>
            </div>
            <div class="card-body">
                <div class="notifications">
                    <div class="notification">
                        <i class="fas fa-birthday-cake"></i>
                        <div>
                            <p>Hoy es el cumpleaños de Juan Pérez</p>
                            <small>Hace 2 horas</small>
                        </div>
                    </div>
                    <div class="notification">
                        <i class="fas fa-file-contract"></i>
                        <div>
                            <p>3 contratos próximos a vencer</p>
                            <small>Hace 1 día</small>
                        </div>
                    </div>
                    <div class="notification">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <p>5 empleados sin expediente completo</p>
                            <small>Hace 2 días</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo">
                <img src="<?php echo $base_url; ?>assets/icono.png" alt="Logo">
                <span>Sistema de Control de Empleados</span>
            </div>
            <div class="footer-info">
                <p>© <?php echo date('Y'); ?> - Todos los derechos reservados</p>
                <p>Versión 1.0.0</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Mini calendario
        function renderMiniCalendar() {
            const now = new Date();
            const month = now.getMonth();
            const year = now.getFullYear();
            
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            
            const calendar = document.getElementById('mini-calendar');
            calendar.innerHTML = '';
            
            // Días de la semana
            const days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            days.forEach(day => {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day header';
                dayElement.textContent = day;
                calendar.appendChild(dayElement);
            });
            
            // Días del mes
            let day = 1;
            for (let i = 0; i < 42; i++) {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                
                if (i >= firstDay.getDay() && day <= lastDay.getDate()) {
                    dayElement.textContent = day;
                    if (day === now.getDate()) {
                        dayElement.classList.add('today');
                    }
                    day++;
                }
                
                calendar.appendChild(dayElement);
            }
        }
        
        renderMiniCalendar();
        
        // Asegurar que los enlaces del menú funcionen correctamente
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard cargado correctamente');
            
            // Verificar que los enlaces funcionen
            const links = document.querySelectorAll('a');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    console.log('Navegando a:', this.href);
                });
            });
        });
    </script>
</body>
</html>