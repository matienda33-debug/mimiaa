<?php
// public/login.php
require_once '../app/config/config.php';
require_once '../app/config/database.php';
require_once '../app/models/Auth.php';

// Redirigir si ya está logueado
if (isLoggedIn()) {
    $accessLevel = getCurrentUserAccessLevel();
    if ($accessLevel >= 60) {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/index.php');
    }
}

$auth = new Auth();
$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            $accessLevel = getCurrentUserAccessLevel();
            if ($accessLevel >= 60) {
                redirect('/admin/dashboard.php');
            } else {
                redirect('/index.php');
            }
        } else {
            $error = $result['message'];
        }
    }
}

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $data = [
        'nombres' => sanitizeInput($_POST['nombres']),
        'apellidos' => sanitizeInput($_POST['apellidos']),
        'email' => sanitizeInput($_POST['email']),
        'telefono' => sanitizeInput($_POST['telefono']),
        'dpi' => sanitizeInput($_POST['dpi']),
        'direccion' => sanitizeInput($_POST['direccion']),
        'username' => sanitizeInput($_POST['username']),
        'password' => $_POST['password'],
        'confirm_password' => $_POST['confirm_password']
    ];
    
    // Validaciones
    if (empty($data['nombres']) || empty($data['apellidos']) || empty($data['email']) || 
        empty($data['username']) || empty($data['password'])) {
        $error = 'Por favor complete todos los campos obligatorios';
    } elseif ($data['password'] !== $data['confirm_password']) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($data['password']) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif (!empty($data['dpi']) && strlen($data['dpi']) !== 13) {
        $error = 'El DPI debe tener 13 dígitos';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido';
    } else {
        $result = $auth->register($data);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Tienda MI&MI Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 900px;
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .nav-tabs .nav-link {
            border: none;
            padding: 12px 25px;
            font-weight: 500;
            color: #666;
        }
        .nav-tabs .nav-link.active {
            background: #667eea;
            color: white;
            border-radius: 10px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #e0e0e0;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 10px;
            font-weight: bold;
            width: 100%;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            color: white;
        }
        .brand-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .brand-subtitle {
            color: #ffd166;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="brand-title">MI&MI STORE</div>
                <div class="brand-subtitle">Ajitos Kids</div>
                <p class="mb-0">Sistema de Gestión Comercial</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <ul class="nav nav-tabs nav-fill mb-4" id="authTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button">
                            <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button">
                            <i class="fas fa-user-plus me-2"></i>Registrarse
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="authTabsContent">
                    <!-- Login Form -->
                    <div class="tab-pane fade show active" id="login" role="tabpanel">
                        <form method="POST" action="">
                            <input type="hidden" name="login" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Usuario</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="username" required 
                                           placeholder="Ingrese su usuario">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" name="password" required 
                                           placeholder="Ingrese su contraseña">
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Recordarme</label>
                            </div>
                            
                            <button type="submit" class="btn btn-login mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                            </button>
                            
                            <div class="text-center">
                                <a href="#" class="text-decoration-none">¿Olvidó su contraseña?</a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Register Form -->
                    <div class="tab-pane fade" id="register" role="tabpanel">
                        <form method="POST" action="">
                            <input type="hidden" name="register" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombres *</label>
                                    <input type="text" class="form-control" name="nombres" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Apellidos *</label>
                                    <input type="text" class="form-control" name="apellidos" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" name="telefono">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">DPI (13 dígitos)</label>
                                    <input type="text" class="form-control" name="dpi" 
                                           pattern="\d{13}" maxlength="13"
                                           placeholder="Para acumular puntos">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Dirección</label>
                                <textarea class="form-control" name="direccion" rows="2"></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre de Usuario *</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contraseña *</label>
                                    <input type="password" class="form-control" name="password" required 
                                           minlength="6">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirmar Contraseña *</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-login">
                                <i class="fas fa-user-plus me-2"></i>Registrarse
                            </button>
                        </form>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <a href="/index.php" class="btn btn-outline-primary">
                        <i class="fas fa-store me-2"></i>Ir a la Tienda
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación de formularios
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                // Validación especial para DPI
                const dpiField = form.querySelector('input[name="dpi"]');
                if (dpiField && dpiField.value && !/^\d{13}$/.test(dpiField.value)) {
                    dpiField.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Validación de contraseñas coincidentes
                const passwordField = form.querySelector('input[name="password"]');
                const confirmField = form.querySelector('input[name="confirm_password"]');
                if (passwordField && confirmField && passwordField.value !== confirmField.value) {
                    confirmField.classList.add('is-invalid');
                    isValid = false;
                    alert('Las contraseñas no coinciden');
                }
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Por favor complete todos los campos requeridos correctamente.');
                }
            });
        });
        
        // Validación en tiempo real para DPI
        document.querySelector('input[name="dpi"]')?.addEventListener('input', function(e) {
            const value = e.target.value.replace(/\D/g, '');
            e.target.value = value.substring(0, 13);
        });
        
        // Cambiar pestañas automáticamente si hay error en registro
        <?php if (isset($_POST['register']) && $error): ?>
        document.getElementById('register-tab').click();
        <?php endif; ?>
    </script>
</body>
</html>