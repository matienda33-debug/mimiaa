<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isset($_SESSION)) {
    session_start();
}

if (isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Validaciones
    if (empty($nombre) || empty($apellido) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Por favor complete todos los campos.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Verificar si el email ya existe
        $check_query = "SELECT id_persona FROM personas WHERE email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = 'Este email ya está registrado.';
        } else {
            try {
                // Crear persona
                $person_query = "INSERT INTO personas (nombre, apellido, email, fecha_registro) 
                                VALUES (:nombre, :apellido, :email, NOW())";
                $person_stmt = $db->prepare($person_query);
                $person_stmt->bindParam(':nombre', $nombre);
                $person_stmt->bindParam(':apellido', $apellido);
                $person_stmt->bindParam(':email', $email);
                $person_stmt->execute();
                
                $id_persona = $db->lastInsertId();
                
                // Crear cliente
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $client_query = "INSERT INTO clientes (id_persona, password, fecha_registro, activo) 
                               VALUES (:id_persona, :password, NOW(), 1)";
                $client_stmt = $db->prepare($client_query);
                $client_stmt->bindParam(':id_persona', $id_persona, PDO::PARAM_INT);
                $client_stmt->bindParam(':password', $hashed_password);
                $client_stmt->execute();
                
                $success = 'Registro exitoso. Ya puedes iniciar sesión.';
                
                // Limpiar formulario
                $_POST = [];
            } catch (Exception $e) {
                $error = 'Ocurrió un error al registrar. Intenta de nuevo.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - <?php echo SITE_NAME; ?></title>
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
            padding: 20px 0;
        }
        .register-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .register-header {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .register-header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        .register-body {
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
        .btn-register {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
            border: none;
            padding: 12px 30px;
            font-weight: bold;
            margin-top: 10px;
        }
        .btn-register:hover {
            background: linear-gradient(135deg, #16a085 0%, #117a65 100%);
            color: white;
        }
        .register-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            text-align: center;
        }
        .register-footer a {
            color: #1abc9c;
            text-decoration: none;
        }
        .register-footer a:hover {
            text-decoration: underline;
        }
        .alert-custom {
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <i class="fas fa-user-plus" style="font-size: 3rem; margin-bottom: 10px;"></i>
            <h1>Crear Cuenta</h1>
            <p class="text-white-50">Únete a <?php echo SITE_NAME; ?></p>
        </div>
        
        <div class="register-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-custom" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-custom" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <br><small><a href="login.php">Ir a Iniciar Sesión</a></small>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" 
                                   placeholder="Tu nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Apellido</label>
                            <input type="text" name="apellido" class="form-control" 
                                   placeholder="Tu apellido" value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Correo Electrónico</label>
                    <input type="email" name="email" class="form-control" 
                           placeholder="tu@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" 
                           placeholder="Al menos 6 caracteres" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Confirmar Contraseña</label>
                    <input type="password" name="confirm_password" class="form-control" 
                           placeholder="Repite tu contraseña" required>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        Acepto los <a href="terminos.php" target="_blank">términos y condiciones</a>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-register w-100">
                    <i class="fas fa-user-plus me-2"></i> Crear Cuenta
                </button>
            </form>
        </div>
        
        <div class="register-footer">
            ¿Ya tienes cuenta? 
            <a href="login.php">
                <strong>Inicia sesión aquí</strong>
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
