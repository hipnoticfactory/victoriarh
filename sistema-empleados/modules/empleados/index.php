<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh']);

// Paginación
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Búsqueda y filtros
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$departamento = isset($_GET['departamento']) ? $_GET['departamento'] : '';
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Construir consulta
$where = "WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $where .= " AND (nombre LIKE ? OR apellido_paterno LIKE ? OR puesto LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

if (!empty($departamento)) {
    $where .= " AND departamento = ?";
    $params[] = $departamento;
    $types .= 's';
}

if (!empty($estado)) {
    $where .= " AND estado = ?";
    $params[] = $estado;
    $types .= 's';
}

// Obtener total de registros
$count_sql = "SELECT COUNT(*) as total FROM empleados $where";
$count_stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Obtener empleados
$sql = "SELECT * FROM empleados $where ORDER BY apellido_paterno, nombre LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$empleados = $stmt->get_result();

// Obtener departamentos únicos para filtro
$dept_result = $conn->query("SELECT DISTINCT departamento FROM empleados WHERE departamento IS NOT NULL ORDER BY departamento");
$departamentos = $dept_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Empleados - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filtros-container {
            background: white;
            padding: 25px;
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
        }
        
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .search-box {
            position: relative;
            grid-column: span 2;
        }
        
        .search-box input {
            padding-left: 45px;
            width: 100%;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }
        
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .btn-add {
            background: var(--success-color);
            color: white;
            padding: 12px 25px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .empleados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .empleado-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid var(--medium-gray);
        }
        
        .empleado-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent-color);
        }
        
        .empleado-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .empleado-avatar {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .empleado-info h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .empleado-info p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .empleado-body {
            padding: 20px;
        }
        
        .empleado-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .detail-label {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .empleado-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-action {
            flex: 1;
            padding: 10px;
            border-radius: var(--radius-sm);
            text-align: center;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-view {
            background: var(--light-gray);
            color: var(--text-dark);
        }
        
        .btn-edit {
            background: var(--info-color);
            color: white;
        }
        
        .btn-delete {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-action:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-inactive {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .status-vacaciones {
            background: #feebc8;
            color: #744210;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .page-link {
            padding: 10px 15px;
            background: white;
            border: 1px solid var(--medium-gray);
            border-radius: var(--radius-sm);
            color: var(--text-dark);
            transition: var(--transition);
        }
        
        .page-link:hover,
        .page-link.active {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Gestión de Empleados</h1>
            <p>Administra la información de los empleados de la empresa</p>
        </div>
        
        <div class="filtros-container">
            <form method="GET" class="filtros-form">
                <div class="filtros-grid">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" 
                               name="search" 
                               placeholder="Buscar por nombre, apellido o puesto..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Departamento</label>
                        <select name="departamento" class="form-control">
                            <option value="">Todos los departamentos</option>
                            <?php foreach ($departamentos as $dept): ?>
                                <option value="<?php echo $dept['departamento']; ?>" 
                                    <?php echo ($departamento == $dept['departamento']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['departamento']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="estado" class="form-control">
                            <option value="">Todos los estados</option>
                            <option value="activo" <?php echo ($estado == 'activo') ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo ($estado == 'inactivo') ? 'selected' : ''; ?>>Inactivo</option>
                            <option value="vacaciones" <?php echo ($estado == 'vacaciones') ? 'selected' : ''; ?>>Vacaciones</option>
                            <option value="baja" <?php echo ($estado == 'baja') ? 'selected' : ''; ?>>Baja</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <a href="index.php" class="btn-secondary">
                        <i class="fas fa-redo"></i> Limpiar filtros
                    </a>
                </div>
            </form>
        </div>
        
        <div class="actions-bar">
            <div class="results-info">
                <p>Mostrando <?php echo $empleados->num_rows; ?> de <?php echo $total_rows; ?> empleados</p>
            </div>
            <a href="agregar.php" class="btn-add">
                <i class="fas fa-user-plus"></i> Agregar Empleado
            </a>
        </div>
        
        <?php if ($empleados->num_rows > 0): ?>
            <div class="empleados-grid">
                <?php while ($empleado = $empleados->fetch_assoc()): ?>
                    <div class="empleado-card">
                        <div class="empleado-header">
                            <div class="empleado-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="empleado-info">
                                <h3><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno']); ?></h3>
                                <p><?php echo htmlspecialchars($empleado['puesto']); ?></p>
                            </div>
                        </div>
                        
                        <div class="empleado-body">
                            <div class="empleado-detail">
                                <span class="detail-label">Departamento:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($empleado['departamento']); ?></span>
                            </div>
                            
                            <div class="empleado-detail">
                                <span class="detail-label">Fecha Ingreso:</span>
                                <span class="detail-value"><?php echo date('d/m/Y', strtotime($empleado['fecha_contratacion'])); ?></span>
                            </div>
                            
                            <div class="empleado-detail">
                                <span class="detail-label">Salario Diario:</span>
                                <span class="detail-value">$<?php echo number_format($empleado['salario_diario'], 2); ?></span>
                            </div>
                            
                            <div class="empleado-detail">
                                <span class="detail-label">Estado:</span>
                                <span class="status-badge status-<?php echo $empleado['estado']; ?>">
                                    <?php echo ucfirst($empleado['estado']); ?>
                                </span>
                            </div>
                            
                            <div class="empleado-actions">
                                <a href="ver.php?id=<?php echo $empleado['id']; ?>" class="btn-action btn-view">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <a href="editar.php?id=<?php echo $empleado['id']; ?>" class="btn-action btn-edit">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <a href="eliminar.php?id=<?php echo $empleado['id']; ?>" 
                                   class="btn-action btn-delete" 
                                   onclick="return confirm('¿Estás seguro de eliminar este empleado?')">
                                    <i class="fas fa-trash"></i> Eliminar
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&search=<?php echo $search; ?>&departamento=<?php echo $departamento; ?>&estado=<?php echo $estado; ?>" class="page-link">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&departamento=<?php echo $departamento; ?>&estado=<?php echo $estado; ?>" class="page-link">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&departamento=<?php echo $departamento; ?>&estado=<?php echo $estado; ?>" 
                           class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&departamento=<?php echo $departamento; ?>&estado=<?php echo $estado; ?>" class="page-link">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?>&search=<?php echo $search; ?>&departamento=<?php echo $departamento; ?>&estado=<?php echo $estado; ?>" class="page-link">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-users-slash"></i>
                </div>
                <h3>No se encontraron empleados</h3>
                <p>No hay empleados que coincidan con los criterios de búsqueda.</p>
                <a href="agregar.php" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Agregar primer empleado
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Auto-submit al cambiar filtros (opcional)
        document.querySelector('select[name="departamento"]').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.querySelector('select[name="estado"]').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>