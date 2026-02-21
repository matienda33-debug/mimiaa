<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isset($_SESSION)) {
    session_start();
}

// Requiere estar logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Obtener datos personales del cliente
$query = "SELECT cu.*, per.nombre, per.apellido, per.email FROM clientes cu
         INNER JOIN personas per ON cu.id_persona = per.id_persona
         WHERE cu.id_cliente = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $_SESSION['id_usuario'], PDO::PARAM_INT);
$stmt->execute();
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Actualizar perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_perfil'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    
    if (empty($nombre) || empty($apellido)) {
        $error = 'El nombre y apellido son requeridos.';
    } else {
        try {
            $update_query = "UPDATE personas SET nombre = :nombre, apellido = :apellido 
                            WHERE id_persona = :id_persona";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':nombre', $nombre);
            $update_stmt->bindParam(':apellido', $apellido);
            $update_stmt->bindParam(':id_persona', $cliente['id_persona']);
            $update_stmt->execute();
            
            $success = 'Perfil actualizado correctamente.';
            
            // Actualizar sesión
            $_SESSION['nombre'] = $nombre . ' ' . $apellido;
        } catch (Exception $e) {
            $error = 'Error al actualizar el perfil.';
        }
    }
}

// Cambiar contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_contrasena'])) {
    $password_actual = trim($_POST['password_actual'] ?? '');
    $password_nueva = trim($_POST['password_nueva'] ?? '');
    $password_confirmar = trim($_POST['password_confirmar'] ?? '');
    
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $error = 'Por favor complete todos los campos.';
    } elseif (!password_verify($password_actual, $cliente['password'])) {
        $error = 'La contraseña actual es incorrecta.';
    } elseif ($password_nueva !== $password_confirmar) {
        $error = 'Las contraseñas nuevas no coinciden.';
    } elseif (strlen($password_nueva) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        try {
            $hashed = password_hash($password_nueva, PASSWORD_DEFAULT);
            $pwd_query = "UPDATE clientes SET password = :password WHERE id_cliente = :id";
            $pwd_stmt = $db->prepare($pwd_query);
            $pwd_stmt->bindParam(':password', $hashed);
            $pwd_stmt->bindParam(':id', $_SESSION['id_usuario']);
            $pwd_stmt->execute();
            
            $success = 'Contraseña actualizada correctamente.';
        } catch (Exception $e) {
            $error = 'Error al actualizar la contraseña.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .profile-header {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 3rem;
            color: #1abc9c;
        }
        .nav-tabs .nav-link.active {
            border-bottom-color: #1abc9c;
            color: #1abc9c;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active">Mi Perfil</li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h2><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></h2>
                        <p class="text-white-50"><?php echo htmlspecialchars($cliente['email']); ?></p>
                        <p class="text-white-50 small"><i class="fas fa-calendar me-2"></i>
                            Cliente desde <?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?>
                        </p>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-4">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#datos">
                                    <i class="fas fa-user-circle me-2"></i> Datos Personales
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#seguridad">
                                    <i class="fas fa-lock me-2"></i> Seguridad
                                </a>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content">
                            <!-- Datos Personales -->
                            <div id="datos" class="tab-pane fade show active">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Nombre</label>
                                                <input type="text" name="nombre" class="form-control" 
                                                       value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Apellido</label>
                                                <input type="text" name="apellido" class="form-control" 
                                                       value="<?php echo htmlspecialchars($cliente['apellido']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" 
                                               value="<?php echo htmlspecialchars($cliente['email']); ?>" disabled>
                                        <small class="text-muted">*(No se puede cambiar)</small>
                                    </div>
                                    
                                    <button type="submit" name="actualizar_perfil" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Guardar Cambios
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Seguridad -->
                            <div id="seguridad" class="tab-pane fade">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Contraseña Actual</label>
                                        <input type="password" name="password_actual" class="form-control" 
                                               placeholder="Ingresa tu contraseña actual" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Contraseña Nueva</label>
                                        <input type="password" name="password_nueva" class="form-control" 
                                               placeholder="Mínimo 6 caracteres" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Confirmar Contraseña</label>
                                        <input type="password" name="password_confirmar" class="form-control" 
                                               placeholder="Repite tu nueva contraseña" required>
                                    </div>
                                    
                                    <button type="submit" name="cambiar_contrasena" class="btn btn-warning">
                                        <i class="fas fa-key me-2"></i> Cambiar Contraseña
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
