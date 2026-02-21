<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Verificar autenticación
if (!$auth->isLoggedIn()) {
    header('Location: /tiendaAA/index.php');
    exit();
}

// Solo admin y trabajadores pueden acceder
if ($_SESSION['rol'] == 4) { // Cliente
    header('Location: ../cliente/index.php');
    exit();
}

$mensaje_pedido = '';
$tipo_mensaje_pedido = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar_estado_pedido') {
    $id_factura = isset($_POST['id_factura']) ? (int) $_POST['id_factura'] : 0;
    $id_estado = isset($_POST['id_estado']) ? (int) $_POST['id_estado'] : 0;

    if ($id_factura > 0 && $id_estado > 0) {
        try {
            $db->beginTransaction();

            $estadoActualStmt = $db->prepare("SELECT fc.id_estado, fc.numero_factura, fc.id_cliente, fc.puntos_ganados, ef.nombre AS estado_nombre FROM factura_cabecera fc LEFT JOIN estado_factura ef ON fc.id_estado = ef.id_estado WHERE fc.id_factura = :id_factura");
            $estadoActualStmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
            $estadoActualStmt->execute();
            $estadoActual = $estadoActualStmt->fetch(PDO::FETCH_ASSOC);

            $cancelStmt = $db->prepare("SELECT id_estado FROM estado_factura WHERE LOWER(nombre) = 'cancelada' LIMIT 1");
            $cancelStmt->execute();
            $cancelRow = $cancelStmt->fetch(PDO::FETCH_ASSOC);
            $cancelId = $cancelRow ? (int)$cancelRow['id_estado'] : 0;

            if ($cancelId > 0 && $id_estado === $cancelId) {
                $estadoActualNombre = strtolower($estadoActual['estado_nombre'] ?? '');
                if ($estadoActualNombre !== 'cancelada') {
                    $detallesStmt = $db->prepare("SELECT id_producto_variante, cantidad FROM factura_detalle WHERE id_factura = :id_factura");
                    $detallesStmt->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
                    $detallesStmt->execute();
                    $detalles = $detallesStmt->fetchAll(PDO::FETCH_ASSOC);

                    $motivo = 'Cancelacion pedido #' . ($estadoActual['numero_factura'] ?? $id_factura);
                    $id_usuario = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null;

                    foreach ($detalles as $detalle) {
                        $id_variante = (int)$detalle['id_producto_variante'];
                        $cantidad = (int)$detalle['cantidad'];

                        $stockStmt = $db->prepare("UPDATE productos_variantes SET stock_bodega = stock_bodega + :cantidad WHERE id_variante = :id_variante");
                        $stockStmt->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
                        $stockStmt->bindParam(':id_variante', $id_variante, PDO::PARAM_INT);
                        $stockStmt->execute();

                        $movStmt = $db->prepare("INSERT INTO inventario_movimientos (id_producto_variante, tipo_movimiento, cantidad, ubicacion, motivo, id_usuario) VALUES (:id_variante, 'entrada', :cantidad, 'bodega', :motivo, :id_usuario)");
                        $movStmt->bindParam(':id_variante', $id_variante, PDO::PARAM_INT);
                        $movStmt->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
                        $movStmt->bindParam(':motivo', $motivo);
                        if ($id_usuario) {
                            $movStmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
                        } else {
                            $movStmt->bindValue(':id_usuario', null, PDO::PARAM_NULL);
                        }
                        $movStmt->execute();
                    }

                    $puntos_ganados = (int)($estadoActual['puntos_ganados'] ?? 0);
                    $cliente_id = isset($estadoActual['id_cliente']) ? (int)$estadoActual['id_cliente'] : 0;
                    if ($puntos_ganados > 0 && $cliente_id > 0) {
                        $puntosStmt = $db->prepare("UPDATE clientes SET puntos = GREATEST(puntos - :puntos, 0) WHERE id_cliente = :id_cliente");
                        $puntosStmt->bindParam(':puntos', $puntos_ganados, PDO::PARAM_INT);
                        $puntosStmt->bindParam(':id_cliente', $cliente_id, PDO::PARAM_INT);
                        $puntosStmt->execute();
                    }
                }
            }

            $updatePedido = $db->prepare("UPDATE factura_cabecera SET id_estado = :id_estado WHERE id_factura = :id_factura");
            $updatePedido->bindParam(':id_estado', $id_estado, PDO::PARAM_INT);
            $updatePedido->bindParam(':id_factura', $id_factura, PDO::PARAM_INT);
            $updatePedido->execute();

            $db->commit();

            $mensaje_pedido = 'Estado del pedido actualizado correctamente.';
            $tipo_mensaje_pedido = 'success';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $mensaje_pedido = 'No se pudo actualizar el estado del pedido.';
            $tipo_mensaje_pedido = 'danger';
        }
    } else {
        $mensaje_pedido = 'Datos inválidos para actualizar pedido.';
        $tipo_mensaje_pedido = 'danger';
    }
}

$estados_pedido = [];
$pedidos_recientes = [];

try {
    $estados_requeridos = [
        'aceptado' => 'Pedido aceptado por tienda',
        'autorizado' => 'Pedido autorizado para preparación',
        'empacado' => 'Pedido empacado',
        'enviado' => 'Pedido enviado',
        'cancelada' => 'Pedido cancelado'
    ];

    foreach ($estados_requeridos as $nombre_estado => $descripcion_estado) {
        $checkEstado = $db->prepare("SELECT id_estado FROM estado_factura WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1");
        $checkEstado->bindParam(':nombre', $nombre_estado);
        $checkEstado->execute();

        if (!$checkEstado->fetch(PDO::FETCH_ASSOC)) {
            $insertEstado = $db->prepare("INSERT INTO estado_factura (nombre, descripcion) VALUES (:nombre, :descripcion)");
            $insertEstado->bindParam(':nombre', $nombre_estado);
            $insertEstado->bindParam(':descripcion', $descripcion_estado);
            $insertEstado->execute();
        }
    }

    $stmtEstados = $db->prepare("SELECT id_estado, nombre FROM estado_factura WHERE LOWER(nombre) IN ('aceptado', 'autorizado', 'empacado', 'enviado', 'cancelada') ORDER BY FIELD(LOWER(nombre), 'aceptado', 'autorizado', 'empacado', 'enviado', 'cancelada')");
    $stmtEstados->execute();
    $estados_pedido = $stmtEstados->fetchAll(PDO::FETCH_ASSOC);

    $stmtPedidos = $db->prepare(
        "SELECT fc.id_factura, fc.numero_factura, fc.numero_orden, fc.fecha, fc.nombre_cliente, fc.total,
                fc.id_estado, ef.nombre AS estado_nombre
         FROM factura_cabecera fc
         LEFT JOIN estado_factura ef ON fc.id_estado = ef.id_estado
         ORDER BY fc.fecha DESC
         LIMIT 15"
    );
    $stmtPedidos->execute();
    $pedidos_recientes = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if (empty($mensaje_pedido)) {
        $mensaje_pedido = 'No fue posible cargar los pedidos del dashboard.';
        $tipo_mensaje_pedido = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --brand-primary: #D092D6;
            --brand-primary-dark: #b677be;
            --brand-ink: #ffffff;
            --sidebar-bg: #F4C7F0;
            --sidebar-bg-hover: #EFB4EA;
            --ajitos-bg: #C2EDE8;
            --ajitos-text: #1f2a29;
        }
        .sidebar {
            min-height: 100vh;
            background: var(--sidebar-bg);
        }
        .sidebar .nav-link {
            color: #4a2b4f;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 6px;
        }
        .sidebar .nav-link:hover {
            background: var(--sidebar-bg-hover);
            color: #3b1f3f;
        }
        .sidebar .nav-link.active {
            background: var(--brand-primary);
            color: var(--brand-ink);
        }
        .brand-title {
            color: #3b1f3f;
            font-weight: bold;
        }
        .ajitos-badge {
            background: var(--ajitos-bg);
            color: var(--ajitos-text);
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .stat-card {
            border-radius: 10px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            color: #fff;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 8px;
            text-transform: capitalize;
        }
        .status-aceptado { background-color: #6A73C5; }
        .status-autorizado { background-color: #515AB8; }
        .status-empacado { background-color: #434BA0; }
        .status-enviado { background-color: #363D87; }
        .status-cancelada { background-color: #8A90CD; color: #1f254d; }
        .status-default { background-color: #515AB8; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4 p-3">
                        <h4 class="brand-title"><?php echo SITE_NAME; ?></h4>
                        <span class="ajitos-badge"><?php echo AJITOS_NAME; ?></span>
                        <p class="text-white-50 small mt-2">Bienvenido, <?php echo $_SESSION['nombre']; ?></p>
                        <p class="text-white-50 small"><?php echo $_SESSION['rol_nombre']; ?></p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-home me-2"></i> Dashboard
                            </a>
                        </li>
                        
                        <?php if ($auth->hasPermission('usuarios')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="panel.php?view=usuarios">
                                <i class="fas fa-users me-2"></i> Usuarios
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('usuarios') || $auth->hasPermission('crear_clientes')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="panel.php?view=personas">
                                <i class="fas fa-address-card me-2"></i> Personas
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($auth->hasPermission('productos')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="panel.php?view=productos">
                                <i class="fas fa-tshirt me-2"></i> Productos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="panel.php?view=banners">
                                <i class="fas fa-images me-2"></i> Banners Inicio
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($auth->hasPermission('ventas')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="panel.php?view=ventas">
                                <i class="fas fa-shopping-cart me-2"></i> Ventas
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($auth->hasPermission('inventario')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="panel.php?view=inventario">
                                <i class="fas fa-boxes me-2"></i> Inventario
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($auth->hasPermission('contabilidad')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="panel.php?view=contabilidad">
                                <i class="fas fa-chart-line me-2"></i> Contabilidad
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($auth->hasPermission('reportes')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="panel.php?view=reportes">
                                <i class="fas fa-chart-bar me-2"></i> Reportes
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../../config/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Contenido Principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-primary"><?php echo date('d/m/Y H:i:s'); ?></span>
                    </div>
                </div>

                <?php if (!empty($mensaje_pedido)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje_pedido; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($mensaje_pedido); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Estadísticas -->
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Ventas Hoy</h6>
                                        <h2 class="card-title">Q 0.00</h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-shopping-cart fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Productos</h6>
                                        <h2 class="card-title">0</h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-tshirt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Clientes</h6>
                                        <h2 class="card-title">0</h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Pendientes</h6>
                                        <h2 class="card-title">0</h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficas y contenido adicional según permisos -->
                <?php if ($auth->hasPermission('reportes')): ?>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Ventas Recientes</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">No hay ventas registradas.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Productos Bajos en Stock</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">No hay productos bajos en stock.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row mt-4 mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0"><i class="fas fa-clipboard-list me-2"></i>Control de Pedidos</h5>
                                <small class="text-muted">Visible para trabajadores de tienda</small>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pedidos_recientes)): ?>
                                    <p class="text-muted mb-0">No hay pedidos registrados aún.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Factura</th>
                                                    <th>Orden</th>
                                                    <th>Cliente</th>
                                                    <th>Fecha</th>
                                                    <th>Total</th>
                                                    <th>Estado actual</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pedidos_recientes as $pedido): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($pedido['numero_factura']); ?></td>
                                                        <td><?php echo htmlspecialchars($pedido['numero_orden'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($pedido['nombre_cliente']); ?></td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></td>
                                                        <td><?php echo formatMoney($pedido['total']); ?></td>
                                                        <td>
                                                            <?php
                                                                $estado_dashboard = strtolower($pedido['estado_nombre'] ?? '');
                                                                $clase_estado = [
                                                                    'aceptado' => 'status-aceptado',
                                                                    'autorizado' => 'status-autorizado',
                                                                    'empacado' => 'status-empacado',
                                                                    'enviado' => 'status-enviado',
                                                                    'cancelada' => 'status-cancelada'
                                                                ][$estado_dashboard] ?? 'status-default';
                                                            ?>
                                                            <span class="status-badge <?php echo $clase_estado; ?>">
                                                                <?php echo htmlspecialchars(ucfirst($pedido['estado_nombre'] ?? 'Sin estado')); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-1 flex-wrap">
                                                                <a href="panel.php?view=ventas&action=detalle&id=<?php echo (int) $pedido['id_factura']; ?>" class="btn btn-sm btn-info" title="Ver detalle">
                                                                    <i class="fas fa-eye me-1"></i>Detalle
                                                                </a>
                                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalCambiarEstado<?php echo (int) $pedido['id_factura']; ?>" title="Cambiar estado">
                                                                    <i class="fas fa-sync-alt me-1"></i>Estado
                                                                </button>
                                                            </div>
                                                            
                                                            <!-- Modal para cambiar estado -->
                                                            <div class="modal fade" id="modalCambiarEstado<?php echo (int) $pedido['id_factura']; ?>" tabindex="-1">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">Cambiar estado - <?php echo htmlspecialchars($pedido['numero_factura']); ?></h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <form method="POST" id="formEstado<?php echo (int) $pedido['id_factura']; ?>">
                                                                                <input type="hidden" name="action" value="actualizar_estado_pedido">
                                                                                <input type="hidden" name="id_factura" value="<?php echo (int) $pedido['id_factura']; ?>">
                                                                                <div class="mb-3">
                                                                                    <label class="form-label">Nuevo estado:</label>
                                                                                    <select class="form-select" name="id_estado" required>
                                                                                        <option value="">-- Selecciona un estado --</option>
                                                                                        <?php foreach ($estados_pedido as $estado): ?>
                                                                                            <option value="<?php echo (int) $estado['id_estado']; ?>" <?php echo ((int)$pedido['id_estado'] === (int)$estado['id_estado']) ? 'selected' : ''; ?>>
                                                                                                <?php echo htmlspecialchars(ucfirst($estado['nombre'])); ?>
                                                                                            </option>
                                                                                        <?php endforeach; ?>
                                                                                    </select>
                                                                                </div>
                                                                            </form>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                            <button type="submit" form="formEstado<?php echo (int) $pedido['id_factura']; ?>" class="btn btn-primary">Guardar cambio</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>