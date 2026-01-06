<?php
session_start();

// Verificar si el usuario NO está autenticado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_nombre'])) {
    // Guardar la página actual para redirigir después del login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirigir al login
    header('Location: /sistema-empleados/login.php');
    exit();
}

// Verificar si la sesión ha expirado (30 minutos de inactividad)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: /sistema-empleados/login.php?expired=1');
    exit();
}

// Actualizar timestamp de última actividad
$_SESSION['LAST_ACTIVITY'] = time();

// Verificar rol de usuario si es necesario
$pagina_actual = basename($_SERVER['PHP_SELF']);
$paginas_admin = ['usuarios/index.php', 'usuarios/agregar.php', 'reportes/index.php'];

// Si la página requiere admin y el usuario no es admin
if (in_array($pagina_actual, $paginas_admin) && $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: /sistema-empleados/modules/dashboard/index.php?error=permiso');
    exit();
}
?>