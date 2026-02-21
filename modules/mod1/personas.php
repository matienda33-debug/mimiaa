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
$auth->requirePermission('crear_clientes');

$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';

// Manejar operaciones CRUD
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['action'] == 'create') {
        // Crear nueva persona/cliente
        $dpi = sanitize($_POST['dpi']);
        $nombre = sanitize($_POST['nombre']);
        $apellido = sanitize($_POST['apellido']);
        $email = sanitize($_POST['email']);
        $telefono = sanitize($_POST['telefono']);
        $direccion = sanitize($_POST['direccion']);
        
        // Verificar si el DPI ya existe
        $check_query = "SELECT id_cliente FROM clientes WHERE dpi = :dpi";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':dpi', $dpi);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            // Cliente ya existe - actualizar información
            $cliente_existente = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $update_query = "UPDATE clientes SET nombre = :nombre, apellido = :apellido, 
                            email = :email, telefono = :telefono, direccion = :direccion 
                            WHERE id_cliente = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':nombre', $nombre);
            $update_stmt->bindParam(':apellido', $apellido);
            $update_stmt->bindParam(':email', $email);
            $update_stmt->bindParam(':telefono', $telefono);
            $update_stmt->bindParam(':direccion', $direccion);
            $update_stmt->bindParam(':id', $cliente_existente['id_cliente']);
            
            if ($update_stmt->execute()) {
                $success = "Cliente actualizado exitosamente. DPI ya existía.";
            } else {
                $error = "Error al actualizar cliente.";
            }
        } else {
            // Crear nuevo cliente
            $query = "INSERT INTO clientes (dpi, nombre, apellido, email, telefono, direccion, puntos, activo) 
                      VALUES (:dpi, :nombre, :apellido, :email, :telefono, :direccion, 0, 1)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':dpi', $dpi);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':apellido', $apellido);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':direccion', $direccion);
            
            if ($stmt->execute()) {
                $success = "Cliente creado exitosamente.";
            } else {
                $error = "Error al crear cliente.";
            }
        }
    }
    elseif ($_POST['action'] == 'update') {
        // Actualizar cliente
        $id_cliente = $_POST['id_cliente'];
        $dpi = sanitize($_POST['dpi']);
        $nombre = sanitize($_POST['nombre']);
        $apellido = sanitize($_POST['apellido']);
        $email = sanitize($_POST['email']);
        $telefono = sanitize($_POST['telefono']);
        $direccion = sanitize($_POST['direccion']);
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        $query = "UPDATE clientes SET dpi = :dpi, nombre = :nombre, apellido = :apellido, 
                  email = :email, telefono = :telefono, direccion = :direccion, activo = :activo 
                  WHERE id_cliente = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':dpi', $dpi);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':apellido', $apellido);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':direccion', $direccion);
        $stmt->bindParam(':activo', $activo);
        $stmt->bindParam(':id', $id_cliente);
        
        if ($stmt->execute()) {
            $success = "Cliente actualizado exitosamente.";
        } else {
            $error = "Error al actualizar cliente.";
        }
    }
    elseif ($_POST['action'] == 'add_points') {
        // Agregar puntos manualmente
        $id_cliente = $_POST['id_cliente'];
        $puntos = $_POST['puntos'];
        $razon = sanitize($_POST['razon']);
        
        $query = "UPDATE clientes SET puntos = puntos + :puntos WHERE id_cliente = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':puntos', $puntos, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id_cliente);
        
        if ($stmt->execute()) {
            // Registrar en log de puntos
            $log_query = "INSERT INTO puntos_log (id_cliente, puntos, razon, fecha) 
                         VALUES (:id_cliente, :puntos, :razon, NOW())";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->bindParam(':id_cliente', $id_cliente);
            $log_stmt->bindParam(':puntos', $puntos);
            $log_stmt->bindParam(':razon', $razon);
            $log_stmt->execute();
            
            $success = "Puntos agregados exitosamente.";
        } else {
            $error = "Error al agregar puntos.";
        }
    }
}

// Obtener clientes
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$query = "SELECT * FROM clientes WHERE 1=1";
if ($search) {
    $query .= " AND (nombre LIKE :search OR apellido LIKE :search OR dpi LIKE :search OR email LIKE :search)";
}
$query .= " ORDER BY fecha_registro DESC";

$stmt = $db->prepare($query);
if ($search) {
    $search_term = "%$search%";
    $stmt->bindParam(':search', $search_term);
}
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener cliente específico para editar
$cliente_edit = null;
if ($action == 'edit' && $id) {
    $query = "SELECT * FROM clientes WHERE id_cliente = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $cliente_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personas/Clientes - <?php echo SITE_NAME; ?></title>
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
                    <h1 class="h2">Gestión de Personas/Clientes</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-1"></i> Nuevo Cliente
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

                <!-- Barra de búsqueda -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="search" placeholder="Buscar por nombre, apellido, DPI o email..." 
                                       value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i> Buscar
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="personas.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-times me-1"></i> Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>DPI</th>
                                        <th>Nombre Completo</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Puntos</th>
                                        <th>Valor en Q</th>
                                        <th>Registro</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td><?php echo $cliente['id_cliente']; ?></td>
                                        <td><?php echo $cliente['dpi']; ?></td>
                                        <td><?php echo $cliente['nombre'] . ' ' . $cliente['apellido']; ?></td>
                                        <td><?php echo $cliente['email']; ?></td>
                                        <td><?php echo $cliente['telefono']; ?></td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo $cliente['puntos']; ?> pts
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                Q <?php echo number_format(valorEnPuntos($cliente['puntos']), 2); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $cliente['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $cliente['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $cliente['id_cliente']; ?>" 
                                               class="btn btn-sm btn-warning" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-info" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#pointsModal<?php echo $cliente['id_cliente']; ?>"
                                                    title="Agregar Puntos">
                                                <i class="fas fa-coins"></i>
                                            </button>
                                            <button class="btn btn-sm btn-secondary" 
                                                    onclick="viewHistory(<?php echo $cliente['id_cliente']; ?>)" 
                                                    title="Historial">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Modal para puntos -->
                                    <div class="modal fade" id="pointsModal<?php echo $cliente['id_cliente']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Agregar Puntos</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="add_points">
                                                        <input type="hidden" name="id_cliente" value="<?php echo $cliente['id_cliente']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Cliente</label>
                                                            <input type="text" class="form-control" 
                                                                   value="<?php echo $cliente['nombre'] . ' ' . $cliente['apellido']; ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Puntos Actuales</label>
                                                            <input type="text" class="form-control" 
                                                                   value="<?php echo $cliente['puntos']; ?> puntos" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="puntos<?php echo $cliente['id_cliente']; ?>" class="form-label">Puntos a Agregar *</label>
                                                            <input type="number" class="form-control" id="puntos<?php echo $cliente['id_cliente']; ?>" 
                                                                   name="puntos" min="1" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="razon<?php echo $cliente['id_cliente']; ?>" class="form-label">Razón *</label>
                                                            <textarea class="form-control" id="razon<?php echo $cliente['id_cliente']; ?>" 
                                                                      name="razon" rows="2" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-primary">Agregar Puntos</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para crear cliente -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label for="dpi" class="form-label">DPI (13 dígitos) *</label>
                            <input type="text" class="form-control" id="dpi" name="dpi" 
                                   pattern="[0-9]{13}" maxlength="13" required>
                            <small class="text-muted">Ingrese los 13 dígitos del DPI</small>
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
                                <label for="telefono" class="form-label">Teléfono *</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="direccion" name="direccion" rows="2"></textarea>
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

    <!-- Modal para editar cliente -->
    <?php if ($cliente_edit): ?>
    <div class="modal fade show" id="editModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Cliente</h5>
                    <a href="personas.php" class="btn-close"></a>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_cliente" value="<?php echo $cliente_edit['id_cliente']; ?>">
                        
                        <div class="mb-3">
                            <label for="edit_dpi" class="form-label">DPI (13 dígitos) *</label>
                            <input type="text" class="form-control" id="edit_dpi" name="dpi" 
                                   value="<?php echo $cliente_edit['dpi']; ?>" pattern="[0-9]{13}" maxlength="13" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="edit_nombre" name="nombre" 
                                       value="<?php echo $cliente_edit['nombre']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_apellido" class="form-label">Apellido *</label>
                                <input type="text" class="form-control" id="edit_apellido" name="apellido" 
                                       value="<?php echo $cliente_edit['apellido']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="edit_email" name="email" 
                                       value="<?php echo $cliente_edit['email']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_telefono" class="form-label">Teléfono *</label>
                                <input type="text" class="form-control" id="edit_telefono" name="telefono" 
                                       value="<?php echo $cliente_edit['telefono']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="edit_direccion" name="direccion" rows="2"><?php echo $cliente_edit['direccion']; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_activo" name="activo" 
                                       <?php echo $cliente_edit['activo'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="edit_activo">
                                    Cliente activo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="personas.php" class="btn btn-secondary">Cancelar</a>
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

    <script>
        function viewHistory(id) {
            // Aquí puedes implementar la vista del historial de compras
            window.location.href = '../mod3/historial.php?id=' + id;
        }
        
        <?php if ($cliente_edit): ?>
        // Mostrar modal de edición automáticamente
        var editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
        <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>