<?php
// Funciones generales del sistema

/**
 * Obtener la conexión a la base de datos
 */
function getConnection() {
    static $conn = null;
    
    if ($conn === null) {
        require_once __DIR__ . '/../config/database.php';
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("Error de conexión: " . $conn->connect_error);
        }
        
        $conn->set_charset(DB_CHARSET);
    }
    
    return $conn;
}

/**
 * Sanitizar entrada de usuario
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validar formato de CURP
 */
function validarCURP($curp) {
    $pattern = '/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9]{2}$/';
    return preg_match($pattern, $curp);
}

/**
 * Validar formato de RFC
 */
function validarRFC($rfc) {
    $pattern = '/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/';
    return preg_match($pattern, $rfc);
}

/**
 * Calcular edad a partir de fecha de nacimiento
 */
function calcularEdad($fecha_nacimiento) {
    $nacimiento = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($nacimiento);
    return $edad->y;
}

/**
 * Formatear fecha en español
 */
function formatFecha($fecha, $formato = 'completo') {
    $formats = [
        'completo' => 'l, j F Y',
        'corto' => 'd/m/Y',
        'hora' => 'd/m/Y H:i:s',
        'mes' => 'F Y'
    ];
    
    $format = $formats[$formato] ?? $formats['corto'];
    
    // Nombres en español
    $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    $timestamp = strtotime($fecha);
    $dia_semana = $dias[date('w', $timestamp)];
    $dia = date('j', $timestamp);
    $mes = $meses[date('n', $timestamp) - 1];
    $ano = date('Y', $timestamp);
    
    return str_replace(
        ['l', 'F', 'j', 'Y'],
        [$dia_semana, $mes, $dia, $ano],
        $format
    );
}

/**
 * Calcular horas trabajadas entre dos horas
 */
function calcularHorasTrabajadas($entrada, $salida, $descanso = 60) {
    if (!$entrada || !$salida) {
        return 0;
    }
    
    $entrada = new DateTime($entrada);
    $salida = new DateTime($salida);
    
    $diferencia = $salida->diff($entrada);
    $horas = $diferencia->h + ($diferencia->i / 60);
    
    // Restar tiempo de descanso
    $horas -= $descanso / 60;
    
    return max(0, $horas);
}

/**
 * Calcular salario quincenal
 */
function calcularSalarioQuincenal($empleado_id, $fecha_inicio, $fecha_fin) {
    $conn = getConnection();
    
    // Obtener asistencias del periodo
    $stmt = $conn->prepare("
        SELECT 
            SUM(horas_trabajadas) as horas_normales,
            SUM(horas_extra) as horas_extra
        FROM asistencias 
        WHERE empleado_id = ? 
        AND fecha BETWEEN ? AND ?
    ");
    $stmt->bind_param("iss", $empleado_id, $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // Obtener salario diario
    $stmt2 = $conn->prepare("SELECT salario_diario FROM empleados WHERE id = ?");
    $stmt2->bind_param("i", $empleado_id);
    $stmt2->execute();
    $salario_diario = $stmt2->get_result()->fetch_assoc()['salario_diario'];
    
    // Calcular salarios
    $salario_normal = ($result['horas_normales'] ?: 0) * ($salario_diario / 8);
    $salario_extra = ($result['horas_extra'] ?: 0) * ($salario_diario / 8 * 1.5);
    $total = $salario_normal + $salario_extra;
    
    return [
        'horas_normales' => $result['horas_normales'] ?: 0,
        'horas_extra' => $result['horas_extra'] ?: 0,
        'salario_normal' => round($salario_normal, 2),
        'salario_extra' => round($salario_extra, 2),
        'total' => round($total, 2)
    ];
}

/**
 * Generar código único para archivos
 */
function generarCodigoUnico($longitud = 10) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $codigo = '';
    
    for ($i = 0; $i < $longitud; $i++) {
        $codigo .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    
    return $codigo;
}

/**
 * Enviar notificación por email (simulado)
 */
function enviarNotificacion($destinatario, $asunto, $mensaje) {
    // En un sistema real, usarías PHPMailer o similar
    // Esta es una implementación simulada
    $log = "[" . date('Y-m-d H:i:s') . "] Email enviado a: $destinatario\n";
    $log .= "Asunto: $asunto\n";
    $log .= "Mensaje: $mensaje\n\n";
    
    // Guardar en archivo de log (para desarrollo)
    file_put_contents(__DIR__ . '/../logs/emails.log', $log, FILE_APPEND);
    
    return true;
}

/**
 * Registrar actividad en el sistema
 */
function registrarActividad($usuario_id, $accion, $detalles = '') {
    $conn = getConnection();
    
    $stmt = $conn->prepare("INSERT INTO log_actividades (usuario_id, accion, detalles, ip, user_agent) 
                           VALUES (?, ?, ?, ?, ?)");
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
    
    $stmt->bind_param("issss", $usuario_id, $accion, $detalles, $ip, $user_agent);
    $stmt->execute();
}

/**
 * Obtener estadísticas rápidas del dashboard
 */
function getDashboardStats() {
    $conn = getConnection();
    $stats = [];
    
    // Total empleados activos
    $result = $conn->query("SELECT COUNT(*) as total FROM empleados WHERE estado = 'activo'");
    $stats['empleados_activos'] = $result->fetch_assoc()['total'];
    
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
    
    // Cumpleaños este mes
    $mes_actual = date('m');
    $result = $conn->query("
        SELECT COUNT(*) as cumples 
        FROM empleados 
        WHERE MONTH(fecha_nacimiento) = $mes_actual 
        AND estado = 'activo'
    ");
    $stats['cumpleanos_mes'] = $result->fetch_assoc()['cumples'];
    
    // Documentos pendientes
    $result = $conn->query("
        SELECT COUNT(DISTINCT e.id) as pendientes 
        FROM empleados e 
        WHERE e.estado = 'activo' 
        AND NOT EXISTS (
            SELECT 1 FROM expedientes ex 
            WHERE ex.empleado_id = e.id 
            AND ex.tipo_documento IN ('ine', 'curp', 'rfc', 'nss', 'acta_nacimiento')
        )
    ");
    $stats['documentos_pendientes'] = $result->fetch_assoc()['pendientes'];
    
    return $stats;
}

/**
 * Formatear moneda mexicana
 */
function formatMoney($amount) {
    return '$' . number_format($amount, 2);
}

/**
 * Obtener empleados con expediente incompleto
 */
function getEmpleadosExpedienteIncompleto() {
    $conn = getConnection();
    
    $sql = "
        SELECT e.id, e.nombre, e.apellido_paterno, e.apellido_materno, e.puesto,
               GROUP_CONCAT(DISTINCT ex.tipo_documento) as documentos_existentes
        FROM empleados e
        LEFT JOIN expedientes ex ON e.id = ex.empleado_id
        WHERE e.estado = 'activo'
        GROUP BY e.id
        HAVING 
            documentos_existentes IS NULL OR
            NOT (documents_existentes LIKE '%ine%' AND 
                 documents_existentes LIKE '%curp%' AND 
                 documents_existentes LIKE '%rfc%' AND 
                 documents_existentes LIKE '%nss%' AND 
                 documents_existentes LIKE '%acta_nacimiento%')
        ORDER BY e.apellido_paterno
        LIMIT 10
    ";
    
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>