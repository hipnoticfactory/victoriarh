<?php
session_start();

// Verificar si ya está autenticado
if (isset($_SESSION['usuario_id'])) {
    // Redirigir directamente al dashboard
    header('Location: /sistema-empleados/modules/dashboard/index.php');
    exit();
} else {
    // Redirigir al login
    header('Location: /sistema-empleados/login.php');
    exit();
}
?>