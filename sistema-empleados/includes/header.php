<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: /sistema-empleados/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Sistema de Empleados'; ?></title>
    <link rel="icon" type="image/png" href="/sistema-empleados/assets/icono.png">
    <link rel="stylesheet" href="/sistema-empleados/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS TEMPORAL PARA ARREGLAR EL DISEÑO -->
    <style>
        /* Reset del logo monstruoso */
        .header .logo img {
            width: 40px !important;
            height: 40px !important;
            max-width: 40px !important;
            max-height: 40px !important;
        }
        
        /* Asegurar que el contenedor esté bien */
        .container {
            padding-top: 20px;
        }
        
        /* Arreglar el header */
        .header {
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        /* Margen para el contenido debajo del header */
        body {
            padding-top: 80px !important;
        }
        
        /* Arreglar las tarjetas del dashboard */
        .stat-card {
            min-height: 120px;
        }
        
        .welcome-section {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Header FIXED -->
    <header class="header">
        <div class="header-container">
            <div class="logo">
                <img src="/sistema-empleados/assets/icono.png" alt="Logo" style="width:40px;height:40px;">
                <div class="logo-text">
                    <h1>Sistema de Empleados</h1>
                    <p>Control y Gestión</p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li>
                        <a href="/sistema-empleados/modules/dashboard/" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="/sistema-empleados/modules/empleados/" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Empleados</span>
                        </a>
                    </li>
                    <li>
                        <a href="/sistema-empleados/modules/asistencias/" class="nav-link">
                            <i class="fas fa-clock"></i>
                            <span>Asistencias</span>
                        </a>
                    </li>
                    <li>
                        <a href="/sistema-empleados/modules/expedientes/" class="nav-link">
                            <i class="fas fa-folder"></i>
                            <span>Expedientes</span>
                        </a>
                    </li>
                    <li>
                        <a href="/sistema-empleados/modules/reportes/" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reportes</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario_usuario']); ?></div>
                    <div class="user-role"><?php echo ucfirst($_SESSION['usuario_rol']); ?></div>
                </div>
                <a href="/sistema-empleados/logout.php" class="logout-btn" title="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="container">