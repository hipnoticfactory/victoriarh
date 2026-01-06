<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh']);

// Obtener expedientes con información de empleados
$sql = "SELECT e.*, emp.nombre, emp.apellido_paterno, emp.apellido_materno, emp.puesto 
        FROM expedientes e 
        JOIN empleados emp ON e.empleado_id = emp.id 
        ORDER BY e.fecha_subida DESC";

$result = $conn->query($sql);
$expedientes = $result->fetch_all(MYSQLI_ASSOC);

// Contar documentos por tipo
$tipos_sql = "SELECT tipo_documento, COUNT(*) as total 
              FROM expedientes 
              GROUP BY tipo_documento 
              ORDER BY total DESC";
$tipos_result = $conn->query($tipos_sql);
$estadisticas_tipos = $tipos_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Expedientes - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .expedientes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 25px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .stats-bar {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: var(--radius-md);
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-color);
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-top: 8px;
        }
        
        .documentos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .documento-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid var(--medium-gray);
        }
        
        .documento-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .documento-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .documento-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .documento-info h4 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .documento-info p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .documento-body {
            padding: 20px;
        }
        
        .documento-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .documento-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-doc {
            flex: 1;
            padding: 10px;
            border-radius: var(--radius-sm);
            text-align: center;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-view {
            background: var(--light-gray);
            color: var(--text-dark);
        }
        
        .btn-download {
            background: var(--accent-color);
            color: white;
        }
        
        .btn-delete {
            background: var(--danger-color);
            color: white;
        }
        
        .tipo-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            background: var(--light-gray);
            color: var(--text-dark);
        }
        
        .empty-expedientes {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 10px 20px;
            background: var(--light-gray);
            border: 1px solid var(--medium-gray);
            border-radius: var(--radius-md);
            color: var(--text-dark);
            font-weight: 500;
            transition: var(--transition);
        }
        
        .filter-tab:hover,
        .filter-tab.active {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .upload-area {
            border: 2px dashed var(--medium-gray);
            border-radius: var(--radius-lg);
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            transition: var(--transition);
            background: var(--light-gray);
        }
        
        .upload-area:hover {
            border-color: var(--accent-color);
            background: white;
        }
        
        .upload-area i {
            font-size: 3rem;
            color: var(--accent-color);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="expedientes-header">
            <div>
                <h1><i class="fas fa-folder-open"></i> Expedientes Digitales</h1>
                <p>Gestión de documentos de empleados</p>
            </div>
            <a href="subir.php" class="btn-primary">
                <i class="fas fa-upload"></i> Subir Documento
            </a>
        </div>
        
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number"><?php echo count($expedientes); ?></div>
                <div class="stat-label">Documentos Totales</div>
            </div>
            
            <?php foreach ($estadisticas_tipos as $tipo): ?>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $tipo['total']; ?></div>
                    <div class="stat-label"><?php echo ucfirst(str_replace('_', ' ', $tipo['tipo_documento'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="upload-area" onclick="window.location.href='subir.php'">
            <i class="fas fa-cloud-upload-alt"></i>
            <h3>Arrastra documentos aquí o haz clic para subir</h3>
            <p>Formatos permitidos: PDF, DOC, DOCX, JPG, PNG (Max. 10MB)</p>
        </div>
        
        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all">Todos los documentos</button>
            <button class="filter-tab" data-filter="contrato">Contratos</button>
            <button class="filter-tab" data-filter="ine">INE</button>
            <button class="filter-tab" data-filter="curp">CURP</button>
            <button class="filter-tab" data-filter="rfc">RFC</button>
            <button class="filter-tab" data-filter="nss">NSS</button>
        </div>
        
        <?php if (count($expedientes) > 0): ?>
            <div class="documentos-grid">
                <?php foreach ($expedientes as $doc): ?>
                    <div class="documento-card" data-tipo="<?php echo $doc['tipo_documento']; ?>">
                        <div class="documento-header">
                            <div class="documento-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="documento-info">
                                <h4><?php echo htmlspecialchars($doc['nombre_documento']); ?></h4>
                                <p><?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido_paterno']); ?></p>
                            </div>
                        </div>
                        
                        <div class="documento-body">
                            <div class="documento-detail">
                                <span>Tipo:</span>
                                <span class="tipo-badge">
                                    <?php echo ucfirst(str_replace('_', ' ', $doc['tipo_documento'])); ?>
                                </span>
                            </div>
                            
                            <div class="documento-detail">
                                <span>Empleado:</span>
                                <span><?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido_paterno']); ?></span>
                            </div>
                            
                            <div class="documento-detail">
                                <span>Puesto:</span>
                                <span><?php echo htmlspecialchars($doc['puesto']); ?></span>
                            </div>
                            
                            <div class="documento-detail">
                                <span>Subido:</span>
                                <span><?php echo date('d/m/Y', strtotime($doc['fecha_subida'])); ?></span>
                            </div>
                            
                            <?php if ($doc['observaciones']): ?>
                                <div class="observaciones">
                                    <strong>Observaciones:</strong>
                                    <p><?php echo nl2br(htmlspecialchars($doc['observaciones'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="documento-actions">
                                <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                   target="_blank" 
                                   class="btn-doc btn-view">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <a href="<?php echo htmlspecialchars($doc['ruta_archivo']); ?>" 
                                   download 
                                   class="btn-doc btn-download">
                                    <i class="fas fa-download"></i> Descargar
                                </a>
                                <a href="eliminar.php?id=<?php echo $doc['id']; ?>" 
                                   class="btn-doc btn-delete" 
                                   onclick="return confirm('¿Eliminar este documento?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-expedientes">
                <i class="fas fa-file-excel" style="font-size: 4rem; color: var(--medium-gray); margin-bottom: 20px;"></i>
                <h3>No hay documentos cargados</h3>
                <p>Comienza subiendo el primer documento del expediente de un empleado.</p>
                <a href="subir.php" class="btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-upload"></i> Subir Primer Documento
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Filtrado por tipo
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                
                // Actualizar pestañas activas
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Filtrar documentos
                document.querySelectorAll('.documento-card').forEach(card => {
                    if (filter === 'all' || card.getAttribute('data-tipo') === filter) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
        
        // Previsualización de documentos
        document.querySelectorAll('.btn-view').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const url = this.href;
                const extension = url.split('.').pop().toLowerCase();
                
                // Si es PDF, abrir en nueva pestaña
                if (extension === 'pdf') {
                    e.preventDefault();
                    window.open(url, '_blank');
                }
                // Si es imagen, mostrar en modal
                else if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
                    e.preventDefault();
                    mostrarModalImagen(url);
                }
            });
        });
        
        function mostrarModalImagen(url) {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            `;
            
            modal.innerHTML = `
                <div style="position: relative; max-width: 90%; max-height: 90%;">
                    <img src="${url}" style="max-width: 100%; max-height: 90vh; border-radius: 10px;">
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="position: absolute; top: -40px; right: 0; background: #f56565; color: white; border: none; width: 40px; height: 40px; border-radius: 50%; font-size: 20px; cursor: pointer;">
                        ×
                    </button>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Cerrar al hacer clic fuera de la imagen
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.remove();
                }
            });
        }
    </script>
</body>
</html>