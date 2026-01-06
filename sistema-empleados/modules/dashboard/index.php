<?php
// Incluir verificación de autenticación
require_once '../../includes/auth_check.php';

// Establecer título de página
$page_title = 'Dashboard';

// Conexión a la base de datos para estadísticas
require_once '../../config/database.php';

// Obtener estadísticas
$empleados_count = $asistencias_hoy = $faltas_hoy = $expedientes_count = 0;
$ultimas_asistencias = $cumpleanos = [];
$contratos_proximos = $sin_expediente = 0;

try {
    // Empleados totales
    $stmt = $conn->prepare("SELECT COUNT(*) FROM empleados");
    $stmt->execute();
    $empleados_count = $stmt->fetchColumn();
    
    // Asistencias hoy
    $stmt = $conn->prepare("SELECT COUNT(*) FROM asistencias WHERE DATE(fecha) = CURDATE()");
    $stmt->execute();
    $asistencias_hoy = $stmt->fetchColumn();
    
    // Faltas hoy
    $stmt = $conn->prepare("SELECT COUNT(*) FROM asistencias WHERE DATE(fecha) = CURDATE() AND (tipo = 'falta' OR hora_entrada IS NULL)");
    $stmt->execute();
    $faltas_hoy = $stmt->fetchColumn();
    
    // Expedientes
    $stmt = $conn->prepare("SELECT COUNT(*) FROM expedientes");
    $stmt->execute();
    $expedientes_count = $stmt->fetchColumn();
    
    // Últimas asistencias
    $stmt = $conn->prepare("
        SELECT e.nombre, e.apellido, a.fecha, a.hora_entrada, a.hora_salida, a.horas_trabajadas 
        FROM asistencias a 
        LEFT JOIN empleados e ON a.empleado_id = e.id 
        WHERE a.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY a.fecha DESC, a.id DESC 
        LIMIT 8
    ");
    $stmt->execute();
    $ultimas_asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cumpleaños próximos (próximos 7 días)
    $stmt = $conn->prepare("
        SELECT nombre, apellido, fecha_nacimiento,
               DAY(fecha_nacimiento) as dia,
               MONTH(fecha_nacimiento) as mes
        FROM empleados 
        WHERE CONCAT(MONTH(fecha_nacimiento), '-', DAY(fecha_nacimiento)) 
              BETWEEN CONCAT(MONTH(CURDATE()), '-', DAY(CURDATE())) 
              AND CONCAT(MONTH(DATE_ADD(CURDATE(), INTERVAL 7 DAY)), '-', DAY(DATE_ADD(CURDATE(), INTERVAL 7 DAY)))
        ORDER BY MONTH(fecha_nacimiento), DAY(fecha_nacimiento)
        LIMIT 3
    ");
    $stmt->execute();
    $cumpleanos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Empleados sin expediente
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT e.id) as count 
        FROM empleados e 
        LEFT JOIN expedientes exp ON e.id = exp.empleado_id 
        WHERE exp.id IS NULL
    ");
    $stmt->execute();
    $sin_expediente = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    // Error silencioso - usar valores por defecto
    error_log("Error en dashboard: " . $e->getMessage());
}

// Incluir header
include '../../includes/header.php';
?>

<!-- Dashboard Content -->
<div class="dashboard-content">
    
    <!-- Sección de bienvenida con animación -->
    <section class="welcome-section fade-in">
        <div class="welcome-header">
            <h1>Bienvenido, <span class="user-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario_usuario']); ?></span></h1>
            <p class="subtitle"><?php 
                setlocale(LC_TIME, 'es_ES.UTF-8');
                echo strftime('%A, %d de %B de %Y'); 
            ?></p>
        </div>
        <div class="welcome-actions">
            <button class="btn btn-primary" onclick="location.href='../asistencias/marcar.php'">
                <i class="fas fa-fingerprint"></i> Marcar Asistencia
            </button>
            <button class="btn btn-secondary" onclick="location.href='../reportes/diario.php'">
                <i class="fas fa-file-alt"></i> Reporte Diario
            </button>
        </div>
    </section>
    
    <!-- Grid de estadísticas con animaciones escalonadas -->
    <div class="stats-grid">
        <div class="stat-card fade-in" style="animation-delay: 0.1s;">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3 class="counter" data-target="<?php echo $empleados_count; ?>">0</h3>
                <p>Empleados</p>
            </div>
            <div class="stat-trend up">
                <i class="fas fa-arrow-up"></i> 12%
            </div>
        </div>
        
        <div class="stat-card fade-in" style="animation-delay: 0.2s;">
            <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3 class="counter" data-target="<?php echo $asistencias_hoy; ?>">0</h3>
                <p>Asistencias Hoy</p>
            </div>
            <span class="stat-percentage live">En tiempo real</span>
        </div>
        
        <div class="stat-card fade-in" style="animation-delay: 0.3s;">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-info">
                <h3 class="counter" data-target="<?php echo $faltas_hoy; ?>">0</h3>
                <p>Faltas Hoy</p>
            </div>
        </div>
        
        <div class="stat-card fade-in" style="animation-delay: 0.4s;">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                <i class="fas fa-folder-open"></i>
            </div>
            <div class="stat-info">
                <h3 class="counter" data-target="<?php echo $expedientes_count; ?>">0</h3>
                <p>Expedientes</p>
            </div>
            <div class="stat-trend up">
                <i class="fas fa-arrow-up"></i> 8%
            </div>
        </div>
    </div>
    
    <!-- Contenido principal -->
    <div class="dashboard-grid">
        <!-- Columna izquierda -->
        <div class="left-column">
            <!-- Últimas asistencias con animación -->
            <div class="card fade-in" style="animation-delay: 0.5s;">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Últimas Asistencias</h3>
                    <div class="card-actions">
                        <button class="btn-refresh" onclick="refreshAsistencias()" title="Actualizar">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="../asistencias/" class="btn-link">Ver todas</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>EMPLEADO</th>
                                    <th>FECHA</th>
                                    <th>ENTRADA</th>
                                    <th>SALIDA</th>
                                    <th>HORAS</th>
                                    <th>ESTADO</th>
                                </tr>
                            </thead>
                            <tbody id="asistencias-body">
                                <?php if (!empty($ultimas_asistencias)): ?>
                                    <?php foreach ($ultimas_asistencias as $index => $asistencia): ?>
                                    <tr class="fade-in" style="animation-delay: <?php echo 0.6 + ($index * 0.1); ?>s;">
                                        <td>
                                            <div class="employee-info">
                                                <div class="employee-avatar">
                                                    <?php echo strtoupper(substr($asistencia['nombre'] ?? 'E', 0, 1) . substr($asistencia['apellido'] ?? 'M', 0, 1)); ?>
                                                </div>
                                                <div class="employee-name">
                                                    <?php echo htmlspecialchars(($asistencia['nombre'] ?? '') . ' ' . ($asistencia['apellido'] ?? '')); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo !empty($asistencia['fecha']) ? date('d/m/Y', strtotime($asistencia['fecha'])) : '--/--/----'; ?></td>
                                        <td>
                                            <span class="time-badge <?php echo !empty($asistencia['hora_entrada']) ? 'success' : 'danger'; ?>">
                                                <?php echo !empty($asistencia['hora_entrada']) ? substr($asistencia['hora_entrada'], 0, 5) : '--:--'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="time-badge <?php echo !empty($asistencia['hora_salida']) ? 'success' : 'warning'; ?>">
                                                <?php echo !empty($asistencia['hora_salida']) ? substr($asistencia['hora_salida'], 0, 5) : '--:--'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="hours-progress">
                                                <div class="progress-bar" style="width: <?php echo min(100, ((float)($asistencia['horas_trabajadas'] ?? 0) / 8) * 100); ?>%"></div>
                                                <span><?php echo number_format($asistencia['horas_trabajadas'] ?? 0, 2); ?>h</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $estado = 'normal';
                                            $icon = 'check-circle';
                                            if (empty($asistencia['hora_entrada'])) {
                                                $estado = 'danger';
                                                $icon = 'times-circle';
                                            } elseif (empty($asistencia['hora_salida'])) {
                                                $estado = 'warning';
                                                $icon = 'clock';
                                            }
                                            ?>
                                            <span class="status-badge badge-<?php echo $estado; ?>">
                                                <i class="fas fa-<?php echo $icon; ?>"></i>
                                                <?php echo ucfirst($estado); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>No hay asistencias registradas en la última semana</p>
                                            <button class="btn btn-primary" onclick="location.href='../asistencias/marcar.php'">
                                                Registrar primera asistencia
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Acciones rápidas con animación -->
            <div class="card fade-in" style="animation-delay: 0.7s; margin-top: 20px;">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Acciones Rápidas</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions-grid">
                        <a href="../empleados/agregar.php" class="quick-action-btn" data-action="add-employee">
                            <div class="action-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="action-info">
                                <h4>Agregar Empleado</h4>
                                <p>Registrar nuevo personal</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                        
                        <a href="../asistencias/marcar.php" class="quick-action-btn" data-action="mark-attendance">
                            <div class="action-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                                <i class="fas fa-fingerprint"></i>
                            </div>
                            <div class="action-info">
                                <h4>Registrar Asistencia</h4>
                                <p>Marcar entrada/salida</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                        
                        <a href="../expedientes/subir.php" class="quick-action-btn" data-action="upload-file">
                            <div class="action-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                                <i class="fas fa-upload"></i>
                            </div>
                            <div class="action-info">
                                <h4>Subir Expediente</h4>
                                <p>Documentación empleado</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                        
                        <a href="../reportes/diario.php" class="quick-action-btn" data-action="generate-report">
                            <div class="action-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="action-info">
                                <h4>Generar Reporte</h4>
                                <p>Reporte diario/quincenal</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Columna derecha -->
        <div class="right-column">
            <!-- Notificaciones con animación -->
            <div class="card fade-in" style="animation-delay: 0.6s;">
                <div class="card-header">
                    <h3><i class="fas fa-bell"></i> Notificaciones</h3>
                    <span class="notification-count"><?php echo count($cumpleanos) + ($sin_expediente > 0 ? 1 : 0); ?></span>
                </div>
                <div class="card-body">
                    <div class="notifications-list">
                        <?php if (!empty($cumpleanos)): ?>
                            <?php foreach ($cumpleanos as $index => $cumple): ?>
                            <div class="notification-item fade-in" style="animation-delay: <?php echo 0.7 + ($index * 0.1); ?>s;">
                                <div class="notification-icon birthday">
                                    <i class="fas fa-birthday-cake"></i>
                                </div>
                                <div class="notification-content">
                                    <h4>¡Cumpleaños!</h4>
                                    <p><?php echo htmlspecialchars($cumple['nombre'] . ' ' . $cumple['apellido']); ?> cumple años <?php echo date('d/m', strtotime($cumple['fecha_nacimiento'])); ?></p>
                                    <small>Hoy</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notification-item fade-in" style="animation-delay: 0.7s;">
                                <div class="notification-icon birthday">
                                    <i class="fas fa-birthday-cake"></i>
                                </div>
                                <div class="notification-content">
                                    <h4>¡Cumpleaños!</h4>
                                    <p>Juan Pérez cumple años hoy</p>
                                    <small>Hace 2 horas</small>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($sin_expediente > 0): ?>
                            <div class="notification-item fade-in" style="animation-delay: 0.8s;">
                                <div class="notification-icon warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="notification-content">
                                    <h4>Expedientes Incompletos</h4>
                                    <p><?php echo $sin_expediente; ?> empleados sin expediente completo</p>
                                    <small>Requiere atención</small>
                                </div>
                                <a href="../expedientes/" class="notification-action">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="notification-item fade-in" style="animation-delay: 0.8s;">
                                <div class="notification-icon warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="notification-content">
                                    <h4>Expedientes Incompletos</h4>
                                    <p>5 empleados sin expediente completo</p>
                                    <small>Hace 2 días</small>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="notification-item fade-in" style="animation-delay: 0.9s;">
                            <div class="notification-icon info">
                                <i class="fas fa-file-contract"></i>
                            </div>
                            <div class="notification-content">
                                <h4>Contratos por Vencer</h4>
                                <p>3 contratos próximos a vencer este mes</p>
                                <small>Revisar</small>
                            </div>
                            <a href="../empleados/" class="notification-action">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Calendario con animación -->
            <div class="card fade-in" style="animation-delay: 0.8s; margin-top: 20px;">
                <div class="card-header">
                    <h3><i class="far fa-calendar"></i> Calendario</h3>
                    <button class="btn-refresh" onclick="updateCalendar()" title="Actualizar">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <button class="calendar-nav" onclick="prevMonth()">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <h4 id="current-month"><?php echo date('F Y'); ?></h4>
                            <button class="calendar-nav" onclick="nextMonth()">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        
                        <div class="calendar-grid" id="mini-calendar">
                            <!-- Días se generan con JavaScript -->
                        </div>
                        
                        <div class="calendar-events">
                            <h5>Eventos Hoy</h5>
                            <div class="event-item">
                                <div class="event-dot success"></div>
                                <span>Reunión RH - 10:00 AM</span>
                            </div>
                            <div class="event-item">
                                <div class="event-dot primary"></div>
                                <span>Entrega de nómina</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS adicional para funcionalidad -->
<style>
    /* Animaciones mejoradas */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .fade-in {
        animation: fadeInUp 0.6s ease-out forwards;
        opacity: 0;
    }
    
    /* Contadores animados */
    .counter {
        transition: all 0.5s ease-out;
    }
    
    /* Botones interactivos */
    .btn-refresh {
        background: none;
        border: none;
        color: var(--text-light);
        cursor: pointer;
        padding: 5px;
        border-radius: 50%;
        transition: var(--transition);
    }
    
    .btn-refresh:hover {
        background: var(--light-gray);
        color: var(--accent-color);
        transform: rotate(180deg);
    }
    
    /* Quick actions mejoradas */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .quick-action-btn {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: white;
        border: 1px solid var(--medium-gray);
        border-radius: var(--radius-md);
        transition: var(--transition);
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }
    
    .quick-action-btn:hover {
        transform: translateX(5px);
        border-color: var(--accent-color);
        box-shadow: var(--shadow-md);
    }
    
    .action-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--accent-color), #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }
    
    .action-info h4 {
        font-size: 1rem;
        margin-bottom: 3px;
        color: var(--text-dark);
    }
    
    .action-info p {
        font-size: 0.85rem;
        color: var(--text-light);
        margin: 0;
    }
    
    .action-arrow {
        margin-left: auto;
        color: var(--text-light);
        transition: var(--transition);
    }
    
    .quick-action-btn:hover .action-arrow {
        color: var(--accent-color);
        transform: translateX(3px);
    }
    
    /* Notificaciones mejoradas */
    .notification-count {
        background: var(--danger-color);
        color: white;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .notifications-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .notification-item {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        padding: 12px;
        background: var(--light-gray);
        border-radius: var(--radius-md);
        border-left: 4px solid var(--accent-color);
        transition: var(--transition);
    }
    
    .notification-item:hover {
        background: white;
        box-shadow: var(--shadow-sm);
    }
    
    .notification-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1rem;
    }
    
    .notification-icon.birthday {
        background: linear-gradient(135deg, #ed64a6, #d53f8c);
    }
    
    .notification-icon.warning {
        background: linear-gradient(135deg, #ed8936, #dd6b20);
    }
    
    .notification-icon.info {
        background: linear-gradient(135deg, #4299e1, #3182ce);
    }
    
    .notification-content h4 {
        font-size: 0.9rem;
        margin-bottom: 3px;
        color: var(--text-dark);
    }
    
    .notification-content p {
        font-size: 0.85rem;
        margin-bottom: 3px;
        color: var(--text-dark);
    }
    
    .notification-content small {
        font-size: 0.75rem;
        color: var(--text-light);
    }
    
    .notification-action {
        margin-left: auto;
        color: var(--text-light);
        opacity: 0;
        transition: var(--transition);
    }
    
    .notification-item:hover .notification-action {
        opacity: 1;
    }
    
    /* Calendario interactivo */
    .calendar-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .calendar-nav {
        background: none;
        border: none;
        color: var(--text-light);
        cursor: pointer;
        padding: 5px 10px;
        border-radius: var(--radius-sm);
        transition: var(--transition);
    }
    
    .calendar-nav:hover {
        background: var(--light-gray);
        color: var(--accent-color);
    }
    
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 5px;
        text-align: center;
    }
    
    .calendar-day {
        padding: 8px 5px;
        border-radius: var(--radius-sm);
        background: var(--light-gray);
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .calendar-day:hover {
        background: var(--medium-gray);
    }
    
    .calendar-day.header {
        background: transparent;
        font-weight: 600;
        color: var(--text-light);
        font-size: 0.8rem;
        text-transform: uppercase;
        cursor: default;
    }
    
    .calendar-day.today {
        background: var(--accent-color);
        color: white;
        font-weight: bold;
    }
    
    .calendar-day.selected {
        background: var(--secondary-color);
        color: white;
    }
    
    .calendar-events {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid var(--medium-gray);
    }
    
    .calendar-events h5 {
        font-size: 0.9rem;
        margin-bottom: 10px;
        color: var(--text-dark);
    }
    
    .event-item {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
        font-size: 0.85rem;
    }
    
    .event-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    
    .event-dot.success {
        background: var(--success-color);
    }
    
    .event-dot.primary {
        background: var(--accent-color);
    }
    
    /* Estados de asistencia */
    .time-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 500;
        min-width: 50px;
        text-align: center;
    }
    
    .time-badge.success {
        background: #c6f6d5;
        color: #22543d;
    }
    
    .time-badge.warning {
        background: #feebc8;
        color: #744210;
    }
    
    .time-badge.danger {
        background: #fed7d7;
        color: #742a2a;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        
        .quick-actions-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .quick-actions-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .welcome-actions {
            flex-direction: column;
        }
    }
</style>

<!-- JavaScript para funcionalidad -->
<script>
// Contadores animados
document.addEventListener('DOMContentLoaded', function() {
    // Animar contadores
    const counters = document.querySelectorAll('.counter');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        const increment = target / 50;
        let current = 0;
        
        const updateCounter = () => {
            if (current < target) {
                current += increment;
                counter.textContent = Math.ceil(current);
                setTimeout(updateCounter, 20);
            } else {
                counter.textContent = target;
            }
        };
        
        // Iniciar con retraso para animación escalonada
        setTimeout(updateCounter, 300);
    });
    
    // Inicializar calendario
    generateCalendar(new Date().getFullYear(), new Date().getMonth());
    
    // Agregar interactividad a las notificaciones
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-action')) {
                this.classList.toggle('expanded');
            }
        });
    });
});

// Funciones del calendario
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

function generateCalendar(year, month) {
    const calendarGrid = document.getElementById('mini-calendar');
    const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                       'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    // Actualizar título
    document.getElementById('current-month').textContent = `${monthNames[month]} ${year}`;
    
    // Días de la semana
    const daysOfWeek = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    
    // Limpiar calendario
    calendarGrid.innerHTML = '';
    
    // Agregar días de la semana
    daysOfWeek.forEach(day => {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day header';
        dayElement.textContent = day;
        calendarGrid.appendChild(dayElement);
    });
    
    // Primer día del mes
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();
    
    // Días vacíos al inicio
    for (let i = 0; i < firstDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day empty';
        calendarGrid.appendChild(emptyDay);
    }
    
    // Días del mes
    for (let day = 1; day <= daysInMonth; day++) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        dayElement.textContent = day;
        
        // Marcar hoy
        if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
            dayElement.classList.add('today');
        }
        
        // Agregar evento click
        dayElement.addEventListener('click', function() {
            document.querySelectorAll('.calendar-day.selected').forEach(d => {
                d.classList.remove('selected');
            });
            this.classList.add('selected');
            
            // Aquí puedes agregar funcionalidad para ver eventos del día
            console.log(`Día seleccionado: ${day}/${month + 1}/${year}`);
        });
        
        calendarGrid.appendChild(dayElement);
    }
}

function prevMonth() {
    currentMonth--;
    if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    }
    generateCalendar(currentYear, currentMonth);
}

function nextMonth() {
    currentMonth++;
    if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    }
    generateCalendar(currentYear, currentMonth);
}

function updateCalendar() {
    const today = new Date();
    currentMonth = today.getMonth();
    currentYear = today.getFullYear();
    generateCalendar(currentYear, currentMonth);
    
    // Efecto de actualización
    const refreshBtn = event.target.closest('.btn-refresh');
    if (refreshBtn) {
        refreshBtn.style.transform = 'rotate(360deg)';
        setTimeout(() => {
            refreshBtn.style.transform = '';
        }, 300);
    }
}

// Función para actualizar asistencias
function refreshAsistencias() {
    const refreshBtn = event.target.closest('.btn-refresh');
    if (refreshBtn) {
        refreshBtn.style.transform = 'rotate(360deg)';
        setTimeout(() => {
            refreshBtn.style.transform = '';
            // Aquí podrías hacer una petición AJAX para actualizar los datos
            alert('Asistencias actualizadas (simulado)');
        }, 500);
    }
}

// Interactividad de botones
document.querySelectorAll('.quick-action-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const action = this.getAttribute('data-action');
        console.log(`Acción: ${action}`);
        // Aquí podrías agregar tracking o animaciones específicas
    });
});

// Actualizar hora en tiempo real
function updateLiveTime() {
    const liveElements = document.querySelectorAll('.live');
    liveElements.forEach(el => {
        if (el.classList.contains('live')) {
            const now = new Date();
            el.textContent = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        }
    });
}

// Actualizar cada minuto
setInterval(updateLiveTime, 60000);
updateLiveTime(); // Ejecutar inmediatamente
</script>

<?php
// Incluir footer
include '../../includes/footer.php';
?>