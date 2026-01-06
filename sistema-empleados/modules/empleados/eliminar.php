<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

// Verificar si el empleado existe
$stmt = $conn->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$empleado = $stmt->get_result()->fetch_assoc();

if (!$empleado) {
    header("Location: index.php?error=empleado_no_encontrado");
    exit();
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['confirmar'])) {
        // Usar transacción para asegurar integridad
        $conn->begin_transaction();
        
        try {
            // 1. Eliminar expedientes
            $delete_expedientes = $conn->prepare("DELETE FROM expedientes WHERE empleado_id = ?");
            $delete_expedientes->bind_param("i", $id);
            $delete_expedientes->execute();
            
            // 2. Eliminar asistencias
            $delete_asistencias = $conn->prepare("DELETE FROM asistencias WHERE empleado_id = ?");
            $delete_asistencias->bind_param("i", $id);
            $delete_asistencias->execute();
            
            // 3. Eliminar usuario asociado
            $delete_usuario = $conn->prepare("DELETE FROM usuarios WHERE empleado_id = ?");
            $delete_usuario->bind_param("i", $id);
            $delete_usuario->execute();
            
            // 4. Eliminar empleado
            $delete_empleado = $conn->prepare("DELETE FROM empleados WHERE id = ?");
            $delete_empleado->bind_param("i", $id);
            $delete_empleado->execute();
            
            $conn->commit();
            
            // Redirigir con mensaje de éxito
            header("Location: index.php?success=empleado_eliminado");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error al eliminar empleado: " . $e->getMessage();
        }
    } else {
        // Si no se confirma, redirigir
        header("Location: ver.php?id=" . $id);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Empleado - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .confirm-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: var(--radius-lg);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            text-align: center;
        }
        
        .warning-icon {
            width: 100px;
            height: 100px;
            background: #fee;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: #f56565;
            font-size: 48px;
            border: 5px solid #fed7d7;
        }
        
        .confirm-container h2 {
            color: var(--secondary-color);
            margin-bottom: 20px;
        }
        
        .confirm-container p {
            color: var(--text-light);
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .empleado-info {
            background: var(--light-gray);
            padding: 20px;
            border-radius: var(--radius-md);
            margin: 30px 0;
            text-align: left;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .consecuencias {
            text-align: left;
            background: #feebc8;
            padding: 20px;
            border-radius: var(--radius-md);
            margin: 30px 0;
            border-left: 4px solid #ed8936;
        }
        
        .consecuencias h4 {
            color: #744210;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .consecuencias ul {
            margin: 10px 0 0 20px;
            color: #744210;
        }
        
        .consecuencias li {
            margin-bottom: 8px;
        }
        
        .confirm-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="confirm-container">
            <div class="warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h2>Confirmar Eliminación</h2>
            <p>¿Estás seguro de que deseas eliminar permanentemente a este empleado?</p>
            
            <div class="empleado-info">
                <div class="info-item">
                    <span>Nombre:</span>
                    <strong><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno'] . ' ' . $empleado['apellido_materno']); ?></strong>
                </div>
                <div class="info-item">
                    <span>Puesto:</span>
                    <span><?php echo htmlspecialchars($empleado['puesto']); ?></span>
                </div>
                <div class="info-item">
                    <span>Departamento:</span>
                    <span><?php echo htmlspecialchars($empleado['departamento']); ?></span>
                </div>
                <div class="info-item">
                    <span>Fecha de Ingreso:</span>
                    <span><?php echo date('d/m/Y', strtotime($empleado['fecha_contratacion'])); ?></span>
                </div>
            </div>
            
            <div class="consecuencias">
                <h4><i class="fas fa-exclamation-circle"></i> Esta acción eliminará permanentemente:</h4>
                <ul>
                    <li>Todos los datos personales del empleado</li>
                    <li>Registros de asistencia e incidencias</li>
                    <li>Expedientes y documentos cargados</li>
                    <li>Usuario de acceso al sistema (si existe)</li>
                    <li>Reportes e historial asociado</li>
                </ul>
                <p><strong>Nota:</strong> Esta acción no se puede deshacer.</p>
            </div>
            
            <form method="POST">
                <div class="confirm-actions">
                    <button type="submit" name="confirmar" value="1" class="btn-danger">
                        <i class="fas fa-trash"></i> Sí, eliminar permanentemente
                    </button>
                    <a href="ver.php?id=<?php echo $id; ?>" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>