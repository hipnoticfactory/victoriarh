<?php
// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Sistema de Empleados'; ?></title>
    <link rel="icon" type="image/png" href="../assets/icono.png">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="header-container">
            <div class="logo">
                <img src="../assets/icono.png" alt="Logo">
                <div class="logo-text">
                    <h1>Sistema de Empleados</h1>
                    <p>Control y Gestión</p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <ul>
                    <li>
                        <a href="../dashboard/" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="../empleados/" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Empleados</span>
                        </a>
                    </li>
                    <li>
                        <a href="../asistencias/" class="nav-link">
                            <i class="fas fa-clock"></i>
                            <span>Asistencias</span>
                        </a>
                    </li>
                    <li>
                        <a href="../expedientes/" class="nav-link">
                            <i class="fas fa-folder"></i>
                            <span>Expedientes</span>
                        </a>
                    </li>
                    <li>
                        <a href="../reportes/" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reportes</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></div>
                    <div class="user-role"><?php echo isset($_SESSION['rol']) ? ucfirst($_SESSION['rol']) : 'Usuario'; ?></div>
                </div>
                <a href="../../logout.php" class="logout-btn" title="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>