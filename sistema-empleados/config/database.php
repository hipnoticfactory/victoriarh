<?php
/**
 * Configuración de la base de datos
 * Archivo: config/database.php
 */

// Configuración de conexión
$host = 'localhost';
$dbname = 'sistema_empleados';
$username = 'root';
$password = '';

// Intentar conexión
try {
    // Crear conexión PDO
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configurar atributos de PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Verificar conexión
    $conn->query("SELECT 1");
    
} catch(PDOException $e) {
    // Registrar error en log
    error_log("[" . date('Y-m-d H:i:s') . "] Error de conexión BD: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    
    // Mostrar mensaje genérico (sin detalles en producción)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['db_error'] = true;
    
    // Si estamos en modo desarrollo, mostrar detalles
    if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        die("<div style='padding:20px;font-family:Arial;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:5px;margin:20px;'>
            <h3>Error de Conexión a la Base de Datos</h3>
            <p><strong>Detalles:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>Host:</strong> $host</p>
            <p><strong>Base de datos:</strong> $dbname</p>
            <p><strong>Usuario:</strong> $username</p>
            <p><strong>Archivo:</strong> " . $e->getFile() . " (línea: " . $e->getLine() . ")</p>
            <hr>
            <p><small>Verifica que:</small></p>
            <ol>
                <li>MySQL esté corriendo en XAMPP</li>
                <li>La base de datos 'sistema_empleados' exista</li>
                <li>Las credenciales sean correctas</li>
                <li>El usuario 'root' tenga permisos (sin contraseña)</li>
            </ol>
        </div>");
    } else {
        // En producción, mensaje genérico
        die("<div style='padding:20px;font-family:Arial;text-align:center;'>
            <h2>Error del Sistema</h2>
            <p>Problema temporal con la base de datos. Por favor, intente nuevamente en unos minutos.</p>
            <p><small>Si el problema persiste, contacte al administrador.</small></p>
        </div>");
    }
}

// Función auxiliar para verificar tablas
function verificarTabla($conn, $tabla) {
    try {
        $stmt = $conn->query("SHOW TABLES LIKE '$tabla'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Función para obtener información de la BD
function getDBInfo($conn) {
    $info = [];
    try {
        // Versión de MySQL
        $version = $conn->query("SELECT VERSION() as version")->fetch();
        $info['version'] = $version['version'];
        
        // Tablas existentes
        $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $info['tables'] = $tables;
        
        // Tamaño de la base de datos
        $size = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
                              FROM information_schema.tables 
                              WHERE table_schema = DATABASE()")->fetch();
        $info['size_mb'] = $size['size_mb'];
        
    } catch (Exception $e) {
        // Silenciar errores
    }
    return $info;
}

// Variable global de conexión
global $conn;
?>