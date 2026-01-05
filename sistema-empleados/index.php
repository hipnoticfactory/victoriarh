<?php
session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: modules/dashboard/");
    exit();
}

// Si no está logueado, redirigir al login
header("Location: login.php");
exit();
?>