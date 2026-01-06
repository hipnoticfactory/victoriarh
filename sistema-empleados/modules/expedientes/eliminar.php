<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

// Obtener información del documento
$stmt = $conn->prepare("SELECT * FROM expedientes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$documento = $stmt->get_result()->fetch_assoc();

if (!$documento) {
    header("Location: index.php?error=documento_no_encontrado");
    exit();
}

$empleado_id = $documento['empleado_id'];

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['confirmar'])) {
        // Eliminar archivo físico
        if (file_exists($documento['ruta_archivo'])) {
            unlink($documento['ruta_archivo']);
        }
        
        // Eliminar registro de la base de datos
        $delete_stmt = $conn->prepare("DELETE FROM expedientes WHERE id = ?");
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            header("Location: ver.php?empleado_id=" . $empleado_id . "&success=documento_eliminado");
            exit();
        } else {
            $error = "Error al eliminar documento: " . $conn->error;
        }
    } else {
        // Redirigir si no se confirma
        header("Location: ver.php?empleado_id=" . $empleado_id);
        exit();
    }
}

// Obtener información del empleado
$empleado_stmt = $conn->prepare("SELECT nombre, apellido_paterno FROM empleados WHERE id = ?");
$empleado_stmt->bind_param("i", $empleado_id);
$empleado_stmt->execute();
$empleado = $empleado_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Documento - Sistema de Control</title>
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
        
        .documento-info {
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
        
        .archivo-info {
            background: #f0f9ff;
            padding: 15px;
            border-radius: var(--radius-md);
            margin: 20px 0;
            border-left: 4px solid #4299e1;
        }
        
        .confirm-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-cancel {
            background: var(--light-gray);
            color: var(--text-dark);
            border: 1px solid var(--medium-gray);
        }
        
        .file-preview {
            max-width: 200px;
            margin: 20px auto;
            text-align: center;
        }
        
        .file-preview img {
            max-width: 100%;
            border-radius: var(--radius-md);
            border: 1px solid var(--medium-gray);
        }
        
        .file-icon {
            font-size: 3rem;
            color: var(--accent-color);
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="confirm-container">
            <div class="warning-icon">
                <i class="fas fa-trash-alt"></i>
            </div>
            
            <h2>Eliminar Documento</h2>
            <p>¿Estás seguro de que deseas eliminar permanentemente este documento?</p>
            
            <div class="documento-info">
                <div class="info-item">
                    <span>Documento:</span>
                    <strong><?php echo htmlspecialchars($documento['nombre_documento']); ?></strong>
                </div>
                <div class="info-item">
                    <span>Tipo:</span>
                    <span><?php echo ucfirst(str_replace('_', ' ', $documento['tipo_documento'])); ?></span>
                </div>
                <div class="info-item">
                    <span>Empleado:</span>
                    <span><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno']); ?></span>
                </div>
                <div class="info-item">
                    <span>Subido:</span>
                    <span><?php echo date('d/m/Y', strtotime($documento['fecha_subida'])); ?></span>
                </div>
            </div>
            
            <div class="archivo-info">
                <h4><i class="fas fa-file"></i> Información del Archivo</h4>
                <p>Ruta: <?php echo htmlspecialchars(basename($documento['ruta_archivo'])); ?></p>
                
                <?php
                // Mostrar vista previa si es imagen
                $extension = strtolower(pathinfo($documento['ruta_archivo'], PATHINFO_EXTENSION));
                $esImagen = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
                ?>
                
                <?php if ($esImagen && file_exists($documento['ruta_archivo'])): ?>
                    <div class="file-preview">
                        <img src="<?php echo htmlspecialchars($documento['ruta_archivo']); ?>" 
                             alt="Vista previa"
                             onerror="this.style.display='none'">
                    </div>
                <?php else: ?>
                    <div class="file-preview">
                        <div class="file-icon">
                            <i class="fas fa-file-<?php echo $extension == 'pdf' ? 'pdf' : 'alt'; ?>"></i>
                        </div>
                        <p><?php echo strtoupper($extension); ?> File</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="background: #feebc8; padding: 15px; border-radius: var(--radius-md); margin: 20px 0; border-left: 4px solid #ed8936;">
                <p><i class="fas fa-exclamation-triangle"></i> Esta acción no se puede deshacer. El archivo será eliminado permanentemente del servidor.</p>
            </div>
            
            <form method="POST">
                <div class="confirm-actions">
                    <button type="submit" name="confirmar" value="1" class="btn-danger">
                        <i class="fas fa-trash"></i> Sí, eliminar permanentemente
                    </button>
                    <a href="ver.php?empleado_id=<?php echo $empleado_id; ?>" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
</body>
</html>