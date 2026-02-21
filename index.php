<?php
require_once 'config/config.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if ($auth->login($username, $password)) {
        if ($auth->isAdmin()) {
            header('Location: modules/mod1/dashboard.php');
        } else {
            header('Location: modules/mod2/productos.php');
        }
        exit();
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
        }
        .login-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .brand-title {
            color: #667eea;
            font-weight: bold;
            font-size: 2rem;
        }
        .ajitos-badge {
            background: #ff6b6b;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container h-100">
        <div class="row h-100 justify-content-center align-items-center">
            <div class="col-md-4">
                <div class="login-card p-4">
                    <div class="text-center mb-4">
                        <h1 class="brand-title"><?php echo SITE_NAME; ?></h1>
                        <span class="ajitos-badge"><?php echo AJITOS_NAME; ?></span>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Iniciar Sesión</button>
                        </div>
                    </form>
                    
                    <hr>
                    <div class="text-center">
                        <p class="text-muted mb-2">¿Eres cliente?</p>
                        <a href="cliente/index.php" class="btn btn-outline-primary">Comprar como invitado</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>