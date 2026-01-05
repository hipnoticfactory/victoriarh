<?php
// Función para verificar autenticación
function checkAuth() {
    // Redirigir al login si no hay sesión activa
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../login.php");
        exit();
    }
    
    return true;
}

// Función para verificar permisos
function checkPermission($requiredRoles) {
    if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], (array)$requiredRoles)) {
        header("Location: ../../modules/dashboard/?error=permission");
        exit();
    }
}

// Obtener datos del usuario actual
function getUserData() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT u.*, e.nombre, e.apellido_paterno, e.apellido_materno 
                           FROM usuarios u 
                           LEFT JOIN empleados e ON u.empleado_id = e.id 
                           WHERE u.id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}
?>