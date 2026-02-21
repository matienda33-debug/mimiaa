<?php
require_once '../config/config.php';

// Si ya está logueado, redirigir
if (!isset($_SESSION)) {
    session_start();
}

if (isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once '../config/database.php';
    
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Buscar usuario
        $query = "SELECT cu.*, per.nombre, per.apellido FROM clientes cu
                 INNER JOIN personas per ON cu.id_persona = per.id_persona
                 WHERE per.email = :email LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cliente && password_verify($password, $cliente['password'])) {
            // Login exitoso
            $_SESSION['id_usuario'] = $cliente['id_cliente'];
            $_SESSION['nombre'] = $cliente['nombre'] . ' ' . $cliente['apellido'];
            $_SESSION['email'] = $email;
            $_SESSION['rol'] = 4; // Cliente
            $_SESSION['rol_nombre'] = 'Cliente';
            
            // Verificar si hay un parámetro redirect
            if (isset($_GET['redirect'])) {
                $redirect_url = urldecode($_GET['redirect']);
                // Validar que sea una URL segura (no contener caracteres peligrosos)
                if (strpos($redirect_url, '://') === false && strpos($redirect_url, '//') !== 0) {
                    header('Location: ' . ltrim($redirect_url, '/'));
                } else {
                    header('Location: index.php');
                }
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'Email o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <style>
        body {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-control {
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 12px 15px;
            margin-bottom: 15px;
        }
        .form-control:focus {
            border-color: #1abc9c;
            box-shadow: 0 0 0 0.2rem rgba(26, 188, 156, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
            border: none;
            padding: 12px 30px;
            font-weight: bold;
            margin-top: 10px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #16a085 0%, #117a65 100%);
            color: white;
        }
        .login-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            text-align: center;
        }
        .login-footer a {
            color: #1abc9c;
            text-decoration: none;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
        .alert-error {
            background: #fadbd8;
            border: 1px solid #f5b7b1;
            color: #922b21;
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-user-circle" style="font-size: 3rem; margin-bottom: 10px;"></i>
            <h1>Iniciar Sesión</h1>
            <p class="text-white-50">Bienvenido a <?php echo SITE_NAME; ?></p>
        </div>
        
        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Correo Electrónico</label>
                    <input type="email" name="email" class="form-control" 
                           placeholder="tu@email.com" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" 
                           placeholder="Tu contraseña" required>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="remember">
                    <label class="form-check-label" for="remember">
                        Recuérdame
                    </label>
                </div>
                
                <button type="submit" class="btn btn-login w-100">
                    <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                </button>
                
                <a href="#" class="btn btn-link w-100 mt-2" data-bs-toggle="modal" data-bs-target="#recoveryModal">
                    ¿Olvidaste tu contraseña?
                </a>
            </form>
        </div>
        
        <div class="login-footer">
            ¿No tienes cuenta? 
            <a href="registro.php">
                <strong>Regístrate aquí</strong>
            </a>
        </div>
    </div>
    
    <!-- Modal de recuperación de contraseña -->
    <div class="modal fade" id="recoveryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Recuperar Contraseña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Ingresa tu correo electrónico para recibir un enlace de recuperación.</p>
                    <input type="email" class="form-control" placeholder="tu@email.com">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary">Enviar Enlace</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
