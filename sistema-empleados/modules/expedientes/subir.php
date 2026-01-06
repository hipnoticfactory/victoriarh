<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh']);

$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;
$success = '';
$error = '';

// Obtener empleados para selección
$empleados_result = $conn->query("
    SELECT id, nombre, apellido_paterno, apellido_materno, puesto 
    FROM empleados 
    WHERE estado = 'activo' 
    ORDER BY apellido_paterno, nombre
");

// Procesar subida de archivo
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $empleado_id = (int)$_POST['empleado_id'];
    $tipo_documento = $_POST['tipo_documento'];
    $observaciones = trim($_POST['observaciones']);
    
    // Validar archivo
    if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['documento'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        
        // Obtener extensión
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Extensiones permitidas
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'xls', 'xlsx'];
        
        if (!in_array($fileExt, $allowed)) {
            $error = "Tipo de archivo no permitido. Extensiones válidas: " . implode(', ', $allowed);
        } elseif ($fileSize > 10 * 1024 * 1024) { // 10MB máximo
            $error = "El archivo es demasiado grande (máximo 10MB)";
        } else {
            // Crear directorio si no existe
            $uploadDir = "../../uploads/empleados/$empleado_id/";
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generar nombre único
            $newFileName = uniqid('', true) . '.' . $fileExt;
            $fileDestination = $uploadDir . $newFileName;
            
            // Mover archivo
            if (move_uploaded_file($fileTmpName, $fileDestination)) {
                // Guardar en base de datos
                $stmt = $conn->prepare("INSERT INTO expedientes 
                                       (empleado_id, tipo_documento, nombre_documento, ruta_archivo, fecha_subida, observaciones) 
                                       VALUES (?, ?, ?, ?, CURDATE(), ?)");
                $stmt->bind_param("issss", 
                    $empleado_id, 
                    $tipo_documento, 
                    $fileName, 
                    $fileDestination, 
                    $observaciones
                );
                
                if ($stmt->execute()) {
                    $success = "Documento subido exitosamente";
                    // Limpiar formulario
                    $empleado_id = 0;
                } else {
                    $error = "Error al guardar en base de datos: " . $conn->error;
                }
            } else {
                $error = "Error al subir el archivo";
            }
        }
    } else {
        $error = "No se seleccionó ningún archivo o hubo un error en la subida";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Documento - Sistema de Control</title>
    <link rel="icon" type="image/png" href="../../assets/icono.png">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .upload-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .upload-box {
            background: white;
            border-radius: var(--radius-lg);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            border: 2px dashed var(--medium-gray);
            text-align: center;
            margin-bottom: 30px;
            transition: var(--transition);
        }
        
        .upload-box.dragover {
            border-color: var(--accent-color);
            background: #f0f9ff;
        }
        
        .upload-icon {
            font-size: 4rem;
            color: var(--accent-color);
            margin-bottom: 20px;
        }
        
        .upload-box h3 {
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        
        .upload-box p {
            color: var(--text-light);
            margin-bottom: 20px;
        }
        
        .file-input {
            display: none;
        }
        
        .btn-choose {
            display: inline-block;
            padding: 12px 30px;
            background: var(--accent-color);
            color: white;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .btn-choose:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .file-info {
            margin-top: 20px;
            padding: 15px;
            background: var(--light-gray);
            border-radius: var(--radius-md);
            display: none;
        }
        
        .file-info.show {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }
        
        .file-name {
            font-weight: 600;
            color: var(--secondary-color);
            word-break: break-all;
        }
        
        .file-size {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .preview-container {
            margin-top: 30px;
            max-height: 300px;
            overflow: hidden;
            border-radius: var(--radius-md);
            display: none;
        }
        
        .preview-container.show {
            display: block;
        }
        
        .preview-container img {
            max-width: 100%;
            border-radius: var(--radius-md);
        }
        
        .progress-bar {
            height: 6px;
            background: var(--light-gray);
            border-radius: 3px;
            margin-top: 20px;
            overflow: hidden;
            display: none;
        }
        
        .progress-bar.show {
            display: block;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--accent-color);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-cloud-upload-alt"></i> Subir Documento</h1>
            <p>Agrega documentos al expediente de un empleado</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <div style="margin-top: 15px;">
                    <a href="index.php" class="btn-secondary">Ver todos los documentos</a>
                    <a href="subir.php" class="btn-primary">Subir otro documento</a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="upload-container">
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="form-container">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Empleado*</label>
                            <select name="empleado_id" class="form-control" required>
                                <option value="">Seleccionar empleado...</option>
                                <?php while ($emp = $empleados_result->fetch_assoc()): ?>
                                    <option value="<?php echo $emp['id']; ?>" 
                                        <?php echo ($empleado_id == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['apellido_paterno'] . ' ' . $emp['apellido_materno'] . ' ' . $emp['nombre'] . ' - ' . $emp['puesto']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Documento*</label>
                            <select name="tipo_documento" class="form-control" required>
                                <option value="">Seleccionar tipo...</option>
                                <option value="acta_nacimiento">Acta de Nacimiento</option>
                                <option value="curp">CURP</option>
                                <option value="rfc">RFC</option>
                                <option value="nss">NSS</option>
                                <option value="ine">INE/Identificación</option>
                                <option value="comprobante_domicilio">Comprobante de Domicilio</option>
                                <option value="certificados">Certificados Académicos</option>
                                <option value="contrato">Contrato Laboral</option>
                                <option value="fotografias">Fotografías</option>
                                <option value="otros">Otros Documentos</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="upload-box" id="dropZone">
                        <div class="upload-icon">
                            <i class="fas fa-file-upload"></i>
                        </div>
                        <h3>Arrastra y suelta tu archivo aquí</h3>
                        <p>o haz clic para seleccionar</p>
                        <input type="file" 
                               name="documento" 
                               id="fileInput" 
                               class="file-input" 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt,.xls,.xlsx"
                               required>
                        <label for="fileInput" class="btn-choose">
                            <i class="fas fa-folder-open"></i> Seleccionar Archivo
                        </label>
                        <p style="margin-top: 15px; font-size: 0.9rem; color: var(--text-light);">
                            Tamaño máximo: 10MB • Formatos permitidos: PDF, DOC, DOCX, JPG, PNG, TXT, XLS, XLSX
                        </p>
                        
                        <div class="file-info" id="fileInfo">
                            <div class="file-name" id="fileName"></div>
                            <div class="file-size" id="fileSize"></div>
                        </div>
                        
                        <div class="progress-bar" id="progressBar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        
                        <div class="preview-container" id="previewContainer">
                            <!-- Vista previa de imagen -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea name="observaciones" 
                                  class="form-control" 
                                  rows="3" 
                                  placeholder="Agrega alguna nota o descripción del documento..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="submitBtn">
                            <i class="fas fa-upload"></i> Subir Documento
                        </button>
                        <a href="index.php" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const previewContainer = document.getElementById('previewContainer');
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');
        const submitBtn = document.getElementById('submitBtn');
        
        // Drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropZone.classList.add('dragover');
        }
        
        function unhighlight() {
            dropZone.classList.remove('dragover');
        }
        
        // Handle dropped files
        dropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                handleFiles(files);
            }
        }
        
        // Handle file selection
        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
        
        function handleFiles(files) {
            const file = files[0];
            
            if (file) {
                // Mostrar información del archivo
                fileName.textContent = file.name;
                fileSize.textContent = formatBytes(file.size);
                fileInfo.classList.add('show');
                
                // Habilitar botón de submit
                submitBtn.disabled = false;
                
                // Mostrar vista previa si es imagen
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewContainer.innerHTML = `<img src="${e.target.result}" alt="Vista previa">`;
                        previewContainer.classList.add('show');
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewContainer.classList.remove('show');
                }
                
                // Mostrar barra de progreso (simulada para upload manual)
                progressBar.classList.add('show');
                progressFill.style.width = '100%';
            }
        }
        
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
        
        // Validación antes de enviar
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const file = fileInput.files[0];
            const empleado = document.querySelector('select[name="empleado_id"]').value;
            const tipo = document.querySelector('select[name="tipo_documento"]').value;
            
            if (!file) {
                alert('Por favor selecciona un archivo');
                e.preventDefault();
                return false;
            }
            
            if (!empleado) {
                alert('Por favor selecciona un empleado');
                e.preventDefault();
                return false;
            }
            
            if (!tipo) {
                alert('Por favor selecciona el tipo de documento');
                e.preventDefault();
                return false;
            }
            
            // Validar tamaño (10MB máximo)
            if (file.size > 10 * 1024 * 1024) {
                alert('El archivo es demasiado grande (máximo 10MB)');
                e.preventDefault();
                return false;
            }
            
            // Validar extensión
            const allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'xls', 'xlsx'];
            const extension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedExtensions.includes(extension)) {
                alert('Tipo de archivo no permitido. Formatos válidos: ' + allowedExtensions.join(', '));
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Deshabilitar submit inicialmente
        submitBtn.disabled = true;
    </script>
</body>
</html>