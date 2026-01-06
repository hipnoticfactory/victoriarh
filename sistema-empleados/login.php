<?php
session_start();

// Si ya está autenticado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: /sistema-empleados/modules/dashboard/index.php');
    exit();
}

// Conexión a la base de datos
require_once 'config/database.php';

$error = '';
$success = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Usuario y contraseña son requeridos';
    } else {
        try {
            // Buscar usuario en la base de datos - CORREGIDO SEGÚN TU ESTRUCTURA
            $stmt = $conn->prepare("SELECT id, username, password_hash, rol, activo, empleado_id FROM usuarios WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verificar contraseña usando password_hash
                if (password_verify($password, $user['password_hash'])) {
                    if ($user['activo'] == 1) {
                        // Crear sesión con los nombres CORRECTOS de campos
                        $_SESSION['usuario_id'] = $user['id'];
                        $_SESSION['usuario_usuario'] = $user['username'];
                        $_SESSION['usuario_rol'] = $user['rol'];
                        $_SESSION['empleado_id'] = $user['empleado_id'];
                        $_SESSION['LAST_ACTIVITY'] = time();
                        
                        // Actualizar último login
                        $update_stmt = $conn->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id");
                        $update_stmt->bindParam(':id', $user['id']);
                        $update_stmt->execute();
                        
                        // Obtener nombre del empleado si existe
                        if ($user['empleado_id']) {
                            $empleado_stmt = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre_completo FROM empleados WHERE id = :empleado_id");
                            $empleado_stmt->bindParam(':empleado_id', $user['empleado_id']);
                            $empleado_stmt->execute();
                            if ($empleado_stmt->rowCount() > 0) {
                                $empleado = $empleado_stmt->fetch(PDO::FETCH_ASSOC);
                                $_SESSION['usuario_nombre'] = $empleado['nombre_completo'];
                            } else {
                                $_SESSION['usuario_nombre'] = $user['username'];
                            }
                        } else {
                            $_SESSION['usuario_nombre'] = $user['username'];
                        }
                        
                        // Redirigir a la página solicitada o al dashboard
                        $redirect_url = $_SESSION['redirect_url'] ?? '/sistema-empleados/modules/dashboard/index.php';
                        unset($_SESSION['redirect_url']);
                        
                        header("Location: $redirect_url");
                        exit();
                    } else {
                        $error = 'Usuario inactivo. Contacte al administrador.';
                    }
                } else {
                    $error = 'Credenciales incorrectas';
                }
            } else {
                $error = 'Usuario no encontrado';
            }
        } catch (PDOException $e) {
            // Mostrar error detallado para debugging
            $error = 'Error en el sistema: ' . $e->getMessage();
            
            // También puedes revisar el log de errores
            error_log("Error en login.php: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Empleados</title>
    <link rel="stylesheet" href="/sistema-empleados/css/styles.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header img {
            max-width: 150px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .btn-login:hover {
            background: #34495e;
        }
        
        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .debug-info {
            font-size: 12px;
            color: #666;
            margin-top: 20px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
            display: none; /* Oculto por defecto */
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="/sistema-empleados/assets/logo.png" alt="Logo RH Victoria">
            <h1>Sistema de Empleados</h1>
            <p>Ingrese sus credenciales</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" class="form-control" required 
                       placeholder="Ingrese su nombre de usuario"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" class="form-control" required 
                       placeholder="Ingrese su contraseña">
            </div>
            
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
        
        <?php if (isset($_GET['expired'])): ?>
            <div class="alert alert-error" style="margin-top: 20px;">
                Su sesión ha expirado. Por favor, inicie sesión nuevamente.
            </div>
        <?php endif; ?>
        
        <!-- Información de debug (solo si hay error 500) -->
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error): ?>
            <div class="debug-info">
                <p><strong>Debug Info:</strong></p>
                <p>Usuario buscado: <?php echo htmlspecialchars($username); ?></p>
                <p>Método: POST</p>
                <p>Hora: <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-focus en el campo de usuario
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>