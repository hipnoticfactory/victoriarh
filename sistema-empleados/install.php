<?php
require_once 'config/database.php';

echo "<h2>Instalando Sistema de Empleados...</h2>";

// Array con todas las consultas en el orden correcto
$queries = [
    // 1. Tabla empleados (primera porque otras dependen de ella)
    "CREATE TABLE IF NOT EXISTS empleados (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(100) NOT NULL,
        apellido_paterno VARCHAR(100) NOT NULL,
        apellido_materno VARCHAR(100),
        fecha_nacimiento DATE NOT NULL,
        genero ENUM('M', 'F', 'Otro'),
        rfc VARCHAR(13) UNIQUE,
        curp VARCHAR(18) UNIQUE,
        nss VARCHAR(11) UNIQUE,
        puesto VARCHAR(100),
        departamento VARCHAR(100),
        salario_diario DECIMAL(10,2),
        fecha_contratacion DATE NOT NULL,
        estado ENUM('activo', 'inactivo', 'baja', 'vacaciones') DEFAULT 'activo',
        telefono VARCHAR(15),
        email VARCHAR(100),
        direccion TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 2. Tabla usuarios (depende de empleados)
    "CREATE TABLE IF NOT EXISTS usuarios (
        id INT PRIMARY KEY AUTO_INCREMENT,
        empleado_id INT UNIQUE,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        rol ENUM('admin', 'rh', 'supervisor', 'empleado') DEFAULT 'empleado',
        ultimo_login DATETIME,
        activo BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 3. Tabla asistencias (depende de empleados)
    "CREATE TABLE IF NOT EXISTS asistencias (
        id INT PRIMARY KEY AUTO_INCREMENT,
        empleado_id INT NOT NULL,
        fecha DATE NOT NULL,
        hora_entrada TIME,
        hora_salida TIME,
        horas_trabajadas DECIMAL(5,2),
        horas_extra DECIMAL(5,2) DEFAULT 0,
        retardo_minutos INT DEFAULT 0,
        tipo_asistencia ENUM('completa', 'media_jornada', 'falta', 'incapacidad', 'vacaciones', 'permiso') DEFAULT 'completa',
        observaciones TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        UNIQUE KEY idx_empleado_fecha (empleado_id, fecha),
        INDEX idx_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 4. Tabla expedientes (depende de empleados)
    "CREATE TABLE IF NOT EXISTS expedientes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        empleado_id INT NOT NULL,
        tipo_documento ENUM(
            'acta_nacimiento',
            'curp',
            'rfc',
            'nss',
            'ine',
            'comprobante_domicilio',
            'certificados',
            'contrato',
            'fotografias',
            'otros'
        ),
        nombre_documento VARCHAR(255),
        ruta_archivo VARCHAR(500),
        fecha_subida DATE,
        observaciones TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        INDEX idx_tipo_documento (tipo_documento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 5. Tabla incidencias (depende de empleados)
    "CREATE TABLE IF NOT EXISTS incidencias (
        id INT PRIMARY KEY AUTO_INCREMENT,
        empleado_id INT NOT NULL,
        fecha_incidencia DATE,
        tipo ENUM('retardo', 'falta', 'permiso', 'vacaciones', 'incapacidad', 'otro'),
        descripcion TEXT,
        justificada BOOLEAN DEFAULT FALSE,
        evidencia VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 6. Tabla vacaciones (depende de empleados y usuarios)
    "CREATE TABLE IF NOT EXISTS vacaciones (
        id INT PRIMARY KEY AUTO_INCREMENT,
        empleado_id INT NOT NULL,
        fecha_inicio DATE NOT NULL,
        fecha_fin DATE NOT NULL,
        dias_totales INT NOT NULL,
        dias_disfrutados INT DEFAULT 0,
        dias_pendientes INT GENERATED ALWAYS AS (dias_totales - dias_disfrutados) STORED,
        anio INT NOT NULL,
        estado ENUM('pendiente', 'aprobado', 'rechazado', 'disfrutando', 'completado') DEFAULT 'pendiente',
        observaciones TEXT,
        aprobado_por INT,
        fecha_aprobacion DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        FOREIGN KEY (aprobado_por) REFERENCES usuarios(id),
        INDEX idx_empleado (empleado_id),
        INDEX idx_estado (estado),
        INDEX idx_anio (anio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 7. Tabla log_actividades (depende de usuarios)
    "CREATE TABLE IF NOT EXISTS log_actividades (
        id INT PRIMARY KEY AUTO_INCREMENT,
        usuario_id INT NOT NULL,
        accion VARCHAR(100) NOT NULL,
        detalles TEXT,
        ip VARCHAR(45),
        user_agent TEXT,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
        INDEX idx_usuario (usuario_id),
        INDEX idx_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 8. Tabla configuracion
    "CREATE TABLE IF NOT EXISTS configuracion (
        id INT PRIMARY KEY AUTO_INCREMENT,
        clave VARCHAR(100) UNIQUE NOT NULL,
        valor TEXT,
        descripcion VARCHAR(255),
        tipo ENUM('texto', 'numero', 'booleano', 'fecha', 'json') DEFAULT 'texto',
        categoria VARCHAR(50),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 9. Tabla dias_festivos
    "CREATE TABLE IF NOT EXISTS dias_festivos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        fecha DATE NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        descripcion TEXT,
        fijo BOOLEAN DEFAULT TRUE,
        activo BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY idx_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

// Ejecutar todas las consultas en orden
foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✓ Tabla creada exitosamente</p>";
    } else {
        echo "<p style='color: red;'>Error al crear tabla: " . $conn->error . "</p>";
        echo "<pre>SQL: " . htmlspecialchars($sql) . "</pre>";
    }
}

// Insertar usuario administrador por defecto
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$insert_admin = "INSERT INTO usuarios (username, password_hash, rol) 
                 VALUES ('admin', '$admin_password', 'admin')";

if ($conn->query($insert_admin)) {
    echo "<p style='color: green;'>✓ Usuario administrador creado</p>";
    echo "<p><strong>Usuario:</strong> admin</p>";
    echo "<p><strong>Contraseña:</strong> admin123</p>";
} else {
    echo "<p style='color: red;'>Error al crear usuario admin: " . $conn->error . "</p>";
}

// Insertar configuraciones iniciales
$configs = [
    "INSERT INTO configuracion (clave, valor, descripcion, tipo, categoria) VALUES
    ('nombre_empresa', 'Mi Empresa S.A. de C.V.', 'Nombre de la empresa', 'texto', 'general'),
    ('hora_entrada', '09:00:00', 'Hora oficial de entrada', 'texto', 'asistencias'),
    ('hora_salida', '18:00:00', 'Hora oficial de salida', 'texto', 'asistencias'),
    ('tiempo_descanso', '60', 'Minutos de descanso (comida)', 'numero', 'asistencias'),
    ('tolerancia_retardo', '15', 'Minutos de tolerancia para retardos', 'numero', 'asistencias'),
    ('salario_minimo', '207.44', 'Salario mínimo diario', 'numero', 'nomina'),
    ('iva', '16', 'Porcentaje de IVA', 'numero', 'nomina'),
    ('dias_vacaciones_anual', '12', 'Días de vacaciones por año', 'numero', 'rh'),
    ('notificar_cumpleanos', '1', 'Notificar cumpleaños de empleados', 'booleano', 'notificaciones');"
];

foreach ($configs as $config_sql) {
    if ($conn->query($config_sql)) {
        echo "<p style='color: green;'>✓ Configuraciones iniciales insertadas</p>";
    }
}

// Insertar días festivos básicos
$festivos = [
    "INSERT INTO dias_festivos (fecha, nombre, descripcion) VALUES
    ('2024-01-01', 'Año Nuevo', 'Primer día del año'),
    ('2024-02-05', 'Día de la Constitución', 'Conmemoración de la Constitución Mexicana'),
    ('2024-03-18', 'Natalicio de Benito Juárez', 'Natalicio de Benito Juárez'),
    ('2024-05-01', 'Día del Trabajo', 'Día Internacional de los Trabajadores'),
    ('2024-09-16', 'Día de la Independencia', 'Independencia de México'),
    ('2024-11-18', 'Revolución Mexicana', 'Conmemoración de la Revolución Mexicana'),
    ('2024-12-25', 'Navidad', 'Celebración de Navidad');"
];

foreach ($festivos as $festivo_sql) {
    if ($conn->query($festivo_sql)) {
        echo "<p style='color: green;'>✓ Días festivos insertados</p>";
    }
}

// Crear directorios necesarios
$directories = [
    'uploads',
    'uploads/empleados',
    'logs',
    'temp'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "<p style='color: green;'>✓ Directorio $dir creado</p>";
        } else {
            echo "<p style='color: red;'>Error al crear directorio $dir</p>";
        }
    }
}

// Crear .htaccess para uploads
$htaccess_content = "# Denegar ejecución de PHP en uploads
<Files *.php>
    Order Deny,Allow
    Deny from all
</Files>

# Solo permitir archivos de imágenes y documentos
<FilesMatch \"\.(jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|txt)$\">
    Order Allow,Deny
    Allow from all
</FilesMatch>";

if (file_put_contents('uploads/.htaccess', $htaccess_content)) {
    echo "<p style='color: green;'>✓ Archivo .htaccess creado en uploads/</p>";
}

// Crear archivo de log para emails
$log_content = "Log de emails del sistema\n";
$log_content .= "=========================\n\n";

if (file_put_contents('logs/emails.log', $log_content)) {
    echo "<p style='color: green;'>✓ Archivo de log creado</p>";
}

echo "<h3 style='color: green;'>¡Instalación completada!</h3>";
echo "<p>Ahora puedes <a href='login.php'>iniciar sesión</a></p>";
echo "<p><strong style='color: red;'>IMPORTANTE:</strong> Elimina este archivo (install.php) por seguridad.</p>";

$conn->close();
?>