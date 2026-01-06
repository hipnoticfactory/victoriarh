<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();

$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;

if ($empleado_id <= 0) {
    header("Location: ../empleados/");
    exit();
}

// Obtener datos del empleado
$empleado_stmt = $conn->prepare("SELECT * FROM empleados WHERE id = ?");
$empleado_stmt->bind_param("i", $empleado_id);
$empleado_stmt->execute();
$empleado = $empleado_stmt->get_result()->fetch_assoc();

if (!$empleado) {
    header("Location: ../empleados/?error=empleado_no_encontrado");
    exit();
}

// Obtener todos los documentos del empleado
$documentos_stmt = $conn->prepare("
    SELECT * FROM expedientes 
    WHERE empleado_id = ? 
    ORDER BY 
        CASE tipo_documento 
            WHEN 'acta_nacimiento' THEN 1
            WHEN 'curp' THEN 2
            WHEN 'rfc' THEN 3
            WHEN 'nss' THEN 4
            WHEN 'ine' THEN 5
            WHEN 'comprobante_domicilio' THEN 6
            WHEN 'contrato' THEN 7
            ELSE 8
        END,
        fecha_subida DESC
");
$documentos_stmt->bind_param("i", $empleado_id);
$documentos_stmt->execute();
$documentos = $documentos_stmt->get_result();

// Documentos requeridos
$documentos_requeridos = [
    'acta_nacimiento' => 'Acta de Nacimiento',
    'curp' => 'CURP',
    'rfc' => 'RFC',
    'nss' => 'NSS',
    'ine' => 'INE/Identificación',
    'comprobante_domicilio' => 'Comprobante de Domicilio',
    'contrato' => 'Contrato Laboral'
];

// Verificar documentos faltantes
$documentos_existentes = [];
while ($doc = $documentos->fetch_assoc()) {
    $documentos_existentes[$doc['tipo_documento']] = $doc;
}

$documentos_faltantes = array_diff_key($documentos_requeridos, $documentos_existentes);
$completitud = round((count($documentos_existentes) / count($documentos_requeridos)) * 100);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Expediente - <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno']); ?></title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .expediente-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-left: 5px solid var(--accent-color);
        }
        
        .empleado-info h2 {
            margin: 0;
            color: var(--secondary-color);
            font-size: 1.8rem;
        }
        
        .empleado-info p {
            margin: 5px 0 0;
            color: var(--text-light);
        }
        
        .completitud-container {
            text-align: center;
            min-width: 150px;
        }
        
        .completitud-circular {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 10px;
        }
        
        .circular-bg {
            fill: none;
            stroke: var(--light-gray);
            stroke-width: 8;
        }
        
        .circular-progress {
            fill: none;
            stroke: var(--accent-color);
            stroke-width: 8;
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
            transition: stroke-dashoffset 0.5s ease;
        }
        
        .completitud-porcentaje {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--secondary-color);
        }
        
        .completitud-texto {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .documentos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .documento-categoria {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .categoria-header {
            padding: 20px;
            background: var(--light-gray);
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .categoria-header h3 {
            margin: 0;
            color: var(--secondary-color);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .categoria-body {
            padding: 20px;
        }
        
        .documento-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: white;
            border: 1px solid var(--medium-gray);
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            transition: var(--transition);
        }
        
        .documento-item:hover {
            border-color: var(--accent-color);
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
            flex-shrink: 0;
        }
        
        .documento-detalles h4 {
            margin: 0;
            font-size: 0.95rem;
            color: var(--text-dark);
        }
        
        .documento-detalles p {
            margin: 3px 0 0;
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .documento-acciones {
            display: flex;
            gap: 8px;
        }
        
        .btn-doc {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: var(--transition);
        }
        
        .btn-view {
            background: var(--info-color);
        }
        
        .btn-download {
            background: var(--success-color);
        }
        
        .btn-delete {
            background: var(--danger-color);
        }
        
        .btn-doc:hover {
            transform: scale(1.1);
        }
        
        .documento-faltante {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #fed7d7;
            border: 1px dashed #f56565;
            border-radius: var(--radius-md);
            margin-bottom: 10px;
        }
        
        .documento-faltante i {
            color: #742a2a;
            font-size: 1.2rem;
        }
        
        .faltante-info h4 {
            margin: 0;
            color: #742a2a;
            font-size: 0.95rem;
        }
        
        .faltante-info p {
            margin: 3px 0 0;
            color: #974a4a;
            font-size: 0.8rem;
        }
        
        .btn-upload-faltante {
            margin-left: auto;
            padding: 6px 12px;
            background: var(--accent-color);
            color: white;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .btn-upload-faltante:hover {
            background: #5a67d8;
        }
        
        .resumen-expediente {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-top: 30px;
            box-shadow: var(--shadow-sm);
        }
        
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .resumen-item {
            text-align: center;
            padding: 20px;
            background: var(--light-gray);
            border-radius: var(--radius-md);
        }
        
        .resumen-item h4 {
            margin: 0;
            font-size: 2rem;
            color: var(--accent-color);
        }
        
        .resumen-item p {
            margin: 5px 0 0;
            color: var(--text-light);
            font-size: 0.9rem;
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
        <div class="expediente-header">
            <div class="empleado-info">
                <h2><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno'] . ' ' . $empleado['apellido_materno']); ?></h2>
                <p><?php echo htmlspecialchars($empleado['puesto'] . ' • ' . $empleado['departamento']); ?></p>
                <small>ID: <?php echo $empleado['id']; ?> | Contratación: <?php echo date('d/m/Y', strtotime($empleado['fecha_contratacion'])); ?></small>
            </div>
            
            <div class="completitud-container">
                <div class="completitud-circular">
                    <svg width="100" height="100" viewBox="0 0 100 100">
                        <circle class="circular-bg" cx="50" cy="50" r="42"></circle>
                        <circle class="circular-progress" 
                                cx="50" cy="50" r="42" 
                                stroke-dasharray="264" 
                                stroke-dashoffset="<?php echo 264 - (264 * $completitud / 100); ?>">
                        </circle>
                    </svg>
                    <div class="completitud-porcentaje"><?php echo $completitud; ?>%</div>
                </div>
                <div class="completitud-texto">Completitud del Expediente</div>
            </div>
        </div>
        
        <div class="documentos-grid">
            <?php foreach ($documentos_requeridos as $tipo => $nombre): ?>
                <div class="documento-categoria">
                    <div class="categoria-header">
                        <h3>
                            <i class="fas fa-file-alt"></i>
                            <?php echo $nombre; ?>
                        </h3>
                    </div>
                    <div class="categoria-body">
                        <?php if (isset($documentos_existentes[$tipo])): 
                            $doc = $documentos_existentes[$tipo];
                        ?>
                            <div class="documento-item">
                                <div class="documento-info">
                                    <div class="documento-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="documento-detalles">
                                        <h4><?php echo htmlspecialchars($doc['nombre_documento']); ?></h4>
                                        <p>Subido: <?php echo date('d/m/Y', strtotime($doc['fecha_subida'])); ?></p>
                                        <?php if ($doc['observaciones']): ?>
                                            <small><?php echo htmlspecialchars($doc['observaciones']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="documento-acciones">
                                    <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                       target="_blank" 
                                       class="btn-doc btn-view"
                                       title="Ver documento">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                       download 
                                       class="btn-doc btn-download"
                                       title="Descargar">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <a href="eliminar.php?id=<?php echo $doc['id']; ?>&empleado_id=<?php echo $empleado_id; ?>" 
                                       class="btn-doc btn-delete"
                                       title="Eliminar"
                                       onclick="return confirm('¿Eliminar este documento?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="documento-faltante">
                                <i class="fas fa-exclamation-circle"></i>
                                <div class="faltante-info">
                                    <h4>Documento Faltante</h4>
                                    <p><?php echo $nombre; ?> no ha sido cargado</p>
                                </div>
                                <a href="subir.php?empleado_id=<?php echo $empleado_id; ?>&tipo=<?php echo $tipo; ?>" 
                                   class="btn-upload-faltante">
                                    <i class="fas fa-upload"></i> Subir
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Documentos adicionales -->
            <?php
            // Obtener documentos que no están en la lista de requeridos
            $documentos_adicionales = array_filter($documentos_existentes, function($doc) use ($documentos_requeridos) {
                return !array_key_exists($doc['tipo_documento'], $documentos_requeridos);
            });
            
            if (!empty($documentos_adicionales)):
            ?>
                <div class="documento-categoria">
                    <div class="categoria-header">
                        <h3>
                            <i class="fas fa-folder-plus"></i>
                            Documentos Adicionales
                        </h3>
                    </div>
                    <div class="categoria-body">
                        <?php foreach ($documentos_adicionales as $doc): ?>
                            <div class="documento-item">
                                <div class="documento-info">
                                    <div class="documento-icon">
                                        <i class="fas fa-file"></i>
                                    </div>
                                    <div class="documento-detalles">
                                        <h4><?php echo htmlspecialchars($doc['nombre_documento']); ?></h4>
                                        <p><?php echo ucfirst(str_replace('_', ' ', $doc['tipo_documento'])); ?></p>
                                        <small>Subido: <?php echo date('d/m/Y', strtotime($doc['fecha_subida'])); ?></small>
                                    </div>
                                </div>
                                <div class="documento-acciones">
                                    <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                       target="_blank" 
                                       class="btn-doc btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                       download 
                                       class="btn-doc btn-download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="resumen-expediente">
            <h3><i class="fas fa-chart-bar"></i> Resumen del Expediente</h3>
            
            <div class="resumen-grid">
                <div class="resumen-item">
                    <h4><?php echo count($documentos_existentes); ?></h4>
                    <p>Documentos Cargados</p>
                </div>
                <div class="resumen-item">
                    <h4><?php echo count($documentos_faltantes); ?></h4>
                    <p>Documentos Faltantes</p>
                </div>
                <div class="resumen-item">
                    <h4><?php echo $completitud; ?>%</h4>
                    <p>Completitud</p>
                </div>
                <div class="resumen-item">
                    <h4><?php echo count($documentos_adicionales); ?></h4>
                    <p>Documentos Adicionales</p>
                </div>
            </div>
            
            <?php if (!empty($documentos_faltantes)): ?>
                <div style="margin-top: 20px; padding: 15px; background: #feebc8; border-radius: var(--radius-md); border-left: 4px solid #ed8936;">
                    <h4 style="margin-top: 0; color: #744210;">
                        <i class="fas fa-exclamation-triangle"></i> Documentos Requeridos Faltantes
                    </h4>
                    <ul style="color: #744210; margin: 10px 0 0 20px;">
                        <?php foreach ($documentos_faltantes as $tipo => $nombre): ?>
                            <li><?php echo $nombre; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="action-buttons">
            <a href="subir.php?empleado_id=<?php echo $empleado_id; ?>" class="btn-primary">
                <i class="fas fa-upload"></i> Subir Nuevo Documento
            </a>
            <a href="../empleados/ver.php?id=<?php echo $empleado_id; ?>" class="btn-secondary">
                <i class="fas fa-user"></i> Ver Perfil del Empleado
            </a>
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Expedientes
            </a>
            <button onclick="imprimirExpediente()" class="btn-success">
                <i class="fas fa-print"></i> Imprimir Expediente
            </button>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        function imprimirExpediente() {
            // Crear ventana de impresión
            const ventana = window.open('', '_blank');
            const contenido = document.querySelector('.container').innerHTML;
            
            ventana.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Expediente - <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno']); ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                        .documento-item { margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; }
                        .completitud { text-align: center; margin: 20px 0; }
                        .btn { display: none; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Expediente Digital</h1>
                        <h2><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido_paterno'] . ' ' . $empleado['apellido_materno']); ?></h2>
                        <p><?php echo htmlspecialchars($empleado['puesto'] . ' • ' . $empleado['departamento']); ?></p>
                        <p>ID: <?php echo $empleado['id']; ?> | Fecha: <?php echo date('d/m/Y'); ?></p>
                    </div>
                    <div class="completitud">
                        <h3>Completitud: <?php echo $completitud; ?>%</h3>
                    </div>
                    ${contenido}
                </body>
                </html>
            `);
            
            ventana.document.close();
            ventana.print();
        }
        
        // Animación de porcentaje
        document.addEventListener('DOMContentLoaded', function() {
            const porcentaje = <?php echo $completitud; ?>;
            const progressCircle = document.querySelector('.circular-progress');
            const radius = progressCircle.r.baseVal.value;
            const circumference = 2 * Math.PI * radius;
            
            progressCircle.style.strokeDasharray = `${circumference} ${circumference}`;
            progressCircle.style.strokeDashoffset = circumference - (porcentaje / 100) * circumference;
        });
    </script>
</body>
</html>