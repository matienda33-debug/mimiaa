<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Verificar autenticación y permiso
if (!$auth->isLoggedIn()) {
    header('Location: /tiendaAA/index.php');
    exit();
}
$auth->requirePermission('usuarios');

$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';

// Manejar operaciones CRUD
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';

// Obtener lista de roles
$roles_query = "SELECT * FROM roles WHERE id_rol != 4 ORDER BY id_rol";
$roles_stmt = $db->prepare($roles_query);
$roles_stmt->execute();
$roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['action'] == 'create') {
        // Crear nuevo usuario
        $username = sanitize($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = sanitize($_POST['email']);
        $nombre = sanitize($_POST['nombre']);
        $apellido = sanitize($_POST['apellido']);
        $dpi = sanitize($_POST['dpi']);
        $telefono = sanitize($_POST['telefono']);
        $id_rol = $_POST['id_rol'];
        
        $query = "INSERT INTO usuarios (username, password, email, id_rol, nombre, apellido, dpi, telefono, activo) 
                  VALUES (:username, :password, :email, :id_rol, :nombre, :apellido, :dpi, :telefono, 1)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':id_rol', $id_rol);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':apellido', $apellido);
        $stmt->bindParam(':dpi', $dpi);
        $stmt->bindParam(':telefono', $telefono);
        
        if ($stmt->execute()) {
            $success = "Usuario creado exitosamente.";
        } else {
            $error = "Error al crear usuario.";
        }
    }
    elseif ($_POST['action'] == 'update') {
        // Actualizar usuario
        $id_usuario = $_POST['id_usuario'];
        $email = sanitize($_POST['email']);
        $nombre = sanitize($_POST['nombre']);
        $apellido = sanitize($_POST['apellido']);
        $dpi = sanitize($_POST['dpi']);
        $telefono = sanitize($_POST['telefono']);
        $id_rol = $_POST['id_rol'];
        $activo = isset($_POST['activo']) ? 1 : 0;
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

        $password_hash = null;
        if (!empty($password) || !empty($password_confirm)) {
            if ($password !== $password_confirm) {
                $error = "Las contraseñas no coinciden.";
            } elseif (strlen($password) < 6) {
                $error = "La contraseña debe tener al menos 6 caracteres.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
            }
        }

        if (isset($error)) {
            // Skip update if validation fails
        } else {
            $password_sql = $password_hash ? ", password = :password" : "";
        
            $query = "UPDATE usuarios SET email = :email, nombre = :nombre, apellido = :apellido, 
                      dpi = :dpi, telefono = :telefono, id_rol = :id_rol, activo = :activo$password_sql 
                      WHERE id_usuario = :id";
        
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':apellido', $apellido);
            $stmt->bindParam(':dpi', $dpi);
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':id_rol', $id_rol);
            $stmt->bindParam(':activo', $activo);
            $stmt->bindParam(':id', $id_usuario);
            if ($password_hash) {
                $stmt->bindParam(':password', $password_hash);
            }

            if ($stmt->execute()) {
                $success = "Usuario actualizado exitosamente.";
            } else {
                $error = "Error al actualizar usuario.";
            }
        }
    }
    elseif ($_POST['action'] == 'delete') {
        // Eliminar usuario (cambiar estado)
        $id_usuario = $_POST['id_usuario'];
        
        $query = "UPDATE usuarios SET activo = 0 WHERE id_usuario = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id_usuario);
        
        if ($stmt->execute()) {
            $success = "Usuario desactivado exitosamente.";
        } else {
            $error = "Error al desactivar usuario.";
        }
    }
}

// Obtener usuarios
$query = "SELECT u.*, r.nombre as rol_nombre 
          FROM usuarios u 
          INNER JOIN roles r ON u.id_rol = r.id_rol 
          WHERE u.id_rol != 4 
          ORDER BY u.id_usuario DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener usuario específico para editar
$usuario_edit = null;
if ($action == 'edit' && $id) {
    $query = "SELECT * FROM usuarios WHERE id_usuario = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $usuario_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php if (!$embedded): ?>
    <?php include '../../includes/header.php'; ?>
    <?php endif; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <main class="col-12 px-2">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Usuarios</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-1"></i> Nuevo Usuario
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuario</th>
                                        <th>Nombre Completo</th>
                                        <th>Email</th>
                                        <th>DPI</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Último Login</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?php echo $usuario['id_usuario']; ?></td>
                                        <td><?php echo $usuario['username']; ?></td>
                                        <td><?php echo $usuario['nombre'] . ' ' . $usuario['apellido']; ?></td>
                                        <td><?php echo $usuario['email']; ?></td>
                                        <td><?php echo $usuario['dpi']; ?></td>
                                        <td><span class="badge bg-info"><?php echo $usuario['rol_nombre']; ?></span></td>
                                        <td>
                                            <span class="badge <?php echo $usuario['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca'; ?></td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $usuario['id_usuario']; ?>" 
                                               class="btn btn-sm btn-warning" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete(<?php echo $usuario['id_usuario']; ?>, '<?php echo $usuario['nombre']; ?>')"
                                                    title="Desactivar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para crear usuario -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Usuario *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Contraseña *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="apellido" class="form-label">Apellido *</label>
                                <input type="text" class="form-control" id="apellido" name="apellido" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="dpi" class="form-label">DPI (13 dígitos)</label>
                                <input type="text" class="form-control" id="dpi" name="dpi" maxlength="13">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="id_rol" class="form-label">Rol *</label>
                                <select class="form-select" id="id_rol" name="id_rol" required>
                                    <option value="">Seleccionar rol</option>
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?php echo $rol['id_rol']; ?>"><?php echo $rol['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar usuario -->
    <?php if ($usuario_edit): ?>
    <div class="modal fade show" id="editModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <a href="usuarios.php" class="btn-close"></a>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_usuario" value="<?php echo $usuario_edit['id_usuario']; ?>">
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Usuario</label>
                                <input type="text" class="form-control" value="<?php echo $usuario_edit['username']; ?>" readonly>
                                <small class="text-muted">El usuario no se puede modificar</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="edit_nombre" name="nombre" 
                                       value="<?php echo $usuario_edit['nombre']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_apellido" class="form-label">Apellido *</label>
                                <input type="text" class="form-control" id="edit_apellido" name="apellido" 
                                       value="<?php echo $usuario_edit['apellido']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="edit_email" name="email" 
                                       value="<?php echo $usuario_edit['email']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_dpi" class="form-label">DPI (13 dígitos)</label>
                                <input type="text" class="form-control" id="edit_dpi" name="dpi" 
                                       value="<?php echo $usuario_edit['dpi']; ?>" maxlength="13">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_telefono" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="edit_telefono" name="telefono" 
                                       value="<?php echo $usuario_edit['telefono']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_id_rol" class="form-label">Rol *</label>
                                <select class="form-select" id="edit_id_rol" name="id_rol" required>
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?php echo $rol['id_rol']; ?>" 
                                            <?php echo $rol['id_rol'] == $usuario_edit['id_rol'] ? 'selected' : ''; ?>>
                                            <?php echo $rol['nombre']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_password" class="form-label">Nueva contraseña</label>
                                <input type="password" class="form-control" id="edit_password" name="password" autocomplete="new-password">
                                <small class="text-muted">Dejar en blanco para no cambiarla.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_password_confirm" class="form-label">Confirmar contraseña</label>
                                <input type="password" class="form-control" id="edit_password_confirm" name="password_confirm" autocomplete="new-password">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_activo" name="activo" 
                                       <?php echo $usuario_edit['activo'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="edit_activo">
                                    Usuario activo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('editModal').style.display = 'block';
        });
    </script>
    <?php endif; ?>

    <!-- Formulario oculto para eliminar -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id_usuario" id="delete_id">
    </form>

    <script>
        function confirmDelete(id, nombre) {
            if (confirm('¿Está seguro de desactivar al usuario "' + nombre + '"?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        <?php if ($usuario_edit): ?>
        // Mostrar modal de edición automáticamente
        var editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
        <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>