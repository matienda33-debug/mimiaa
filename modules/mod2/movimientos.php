<?php
// movimientos.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/config.php';

// Verificar autenticación y permisos
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    header('Location: ../../index.php');
    exit();
}

// Verificar permiso específico para movimientos
$auth->requirePermission('inventario');

$titulo = "Movimientos de Inventario";

// Procesar acciones
$mensaje = '';
$tipoMensaje = '';

if (isset($_POST['accion'])) {
    switch ($_POST['accion']) {
        case 'crear_movimiento':
            try {
                $stmt = $db->prepare("INSERT INTO inventario_movimientos 
                                     (id_producto_variante, tipo_movimiento, cantidad, ubicacion, motivo, id_usuario, fecha_movimiento) 
                                     VALUES (:id_variante, :tipo, :cantidad, :ubicacion, :motivo, :id_usuario, NOW())");
                
                $stmt->bindParam(':id_variante', $_POST['id_variante']);
                $stmt->bindParam(':tipo', $_POST['tipo_movimiento']);
                $stmt->bindParam(':cantidad', $_POST['cantidad']);
                $stmt->bindParam(':ubicacion', $_POST['ubicacion']);
                $stmt->bindParam(':motivo', $_POST['motivo']);
                $stmt->bindParam(':id_usuario', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // Actualizar stock según el tipo de movimiento
                    $tipo = $_POST['tipo_movimiento'];
                    $campo_stock = ($_POST['ubicacion'] == 'tienda') ? 'stock_tienda' : 'stock_bodega';
                    
                    if ($tipo == 'entrada' || $tipo == 'ajuste_entrada' || $tipo == 'devolucion') {
                        $sql_update = "UPDATE productos_variantes SET $campo_stock = $campo_stock + :cantidad WHERE id_variante = :id_variante";
                    } else if ($tipo == 'salida' || $tipo == 'ajuste_salida' || $tipo == 'traslado') {
                        $sql_update = "UPDATE productos_variantes SET $campo_stock = $campo_stock - :cantidad WHERE id_variante = :id_variante";
                    }
                    
                    if (isset($sql_update)) {
                        $stmt_update = $db->prepare($sql_update);
                        $stmt_update->bindParam(':cantidad', $_POST['cantidad']);
                        $stmt_update->bindParam(':id_variante', $_POST['id_variante']);
                        $stmt_update->execute();
                    }
                    
                    $mensaje = "Movimiento registrado exitosamente";
                    $tipoMensaje = 'success';
                }
            } catch (PDOException $e) {
                $mensaje = "Error al registrar movimiento: " . $e->getMessage();
                $tipoMensaje = 'danger';
            }
            break;
            
        case 'eliminar_movimiento':
            if (isset($_POST['id_movimiento'])) {
                try {
                    // Primero obtenemos los datos del movimiento para revertir el stock
                    $stmt_get = $db->prepare("SELECT * FROM inventario_movimientos WHERE id_movimiento = :id");
                    $stmt_get->bindParam(':id', $_POST['id_movimiento']);
                    $stmt_get->execute();
                    $movimiento = $stmt_get->fetch(PDO::FETCH_ASSOC);
                    
                    if ($movimiento) {
                        // Revertir el stock
                        $campo_stock = ($movimiento['ubicacion'] == 'tienda') ? 'stock_tienda' : 'stock_bodega';
                        
                        if ($movimiento['tipo_movimiento'] == 'entrada' || $movimiento['tipo_movimiento'] == 'ajuste_entrada' || $movimiento['tipo_movimiento'] == 'devolucion') {
                            $sql_revertir = "UPDATE productos_variantes SET $campo_stock = $campo_stock - :cantidad WHERE id_producto_variante = :id_variante";
                        } else if ($movimiento['tipo_movimiento'] == 'salida' || $movimiento['tipo_movimiento'] == 'ajuste_salida' || $movimiento['tipo_movimiento'] == 'traslado') {
                            $sql_revertir = "UPDATE productos_variantes SET $campo_stock = $campo_stock + :cantidad WHERE id_producto_variante = :id_variante";
                        }
                        
                        if (isset($sql_revertir)) {
                            $stmt_revertir = $db->prepare($sql_revertir);
                            $stmt_revertir->bindParam(':cantidad', $movimiento['cantidad']);
                            $stmt_revertir->bindParam(':id_variante', $movimiento['id_producto_variante']);
                            $stmt_revertir->execute();
                        }
                        
                        // Eliminar el movimiento
                        $stmt_delete = $db->prepare("DELETE FROM inventario_movimientos WHERE id_movimiento = :id");
                        $stmt_delete->bindParam(':id', $_POST['id_movimiento']);
                        $stmt_delete->execute();
                        
                        $mensaje = "Movimiento eliminado exitosamente";
                        $tipoMensaje = 'success';
                    }
                } catch (PDOException $e) {
                    $mensaje = "Error al eliminar movimiento: " . $e->getMessage();
                    $tipoMensaje = 'danger';
                }
            }
            break;
    }
}

// Obtener movimientos con filtros
$where_conditions = [];
$params = [];

if (isset($_GET['filtrar'])) {
    if (!empty($_GET['filtro_tipo'])) {
        $where_conditions[] = "m.tipo_movimiento = :tipo";
        $params[':tipo'] = $_GET['filtro_tipo'];
    }
    
    if (!empty($_GET['filtro_producto'])) {
        $where_conditions[] = "(pr.nombre LIKE :producto OR pv.sku LIKE :producto)";
        $params[':producto'] = '%' . $_GET['filtro_producto'] . '%';
    }
    
    if (!empty($_GET['filtro_fecha_desde'])) {
        $where_conditions[] = "DATE(m.fecha_movimiento) >= :fecha_desde";
        $params[':fecha_desde'] = $_GET['filtro_fecha_desde'];
    }
    
    if (!empty($_GET['filtro_fecha_hasta'])) {
        $where_conditions[] = "DATE(m.fecha_movimiento) <= :fecha_hasta";
        $params[':fecha_hasta'] = $_GET['filtro_fecha_hasta'];
    }
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "SELECT m.*, 
                 pr.nombre as producto_nombre,
                 pv.color,
                 pv.talla,
                 pv.sku,
                 u.username as usuario_nombre,
                 CASE 
                     WHEN m.tipo_movimiento = 'entrada' THEN 'Entrada'
                     WHEN m.tipo_movimiento = 'salida' THEN 'Salida'
                     WHEN m.tipo_movimiento = 'ajuste_entrada' THEN 'Ajuste Entrada'
                     WHEN m.tipo_movimiento = 'ajuste_salida' THEN 'Ajuste Salida'
                     WHEN m.tipo_movimiento = 'traslado' THEN 'Traslado'
                     WHEN m.tipo_movimiento = 'devolucion' THEN 'Devolución'
                     ELSE m.tipo_movimiento
                 END as tipo_nombre
          FROM inventario_movimientos m
          INNER JOIN productos_variantes pv ON m.id_producto_variante = pv.id_variante
          INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
          INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
          $where_sql
          ORDER BY m.fecha_movimiento DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos para el select
$stmt_productos = $db->query("SELECT pv.id_variante, pr.nombre, pv.color, pv.talla, pv.sku 
                              FROM productos_variantes pv
                              INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                              WHERE pv.activo = 1
                              ORDER BY pr.nombre, pv.color, pv.talla");
$productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo . ' - ' . SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Bootstrap Datepicker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    
    <style>
        .movimiento-entrada { color: #198754; }
        .movimiento-salida { color: #dc3545; }
        .movimiento-ajuste { color: #0dcaf0; }
        .movimiento-traslado { color: #6f42c1; }
        .movimiento-devolucion { color: #fd7e14; }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .badge-tipo {
            font-size: 0.8em;
            padding: 5px 10px;
        }
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Main content -->
            <main class="col-12 px-2">
                <!-- Header -->
                <?php include $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/includes/header.php'; ?>
                
                <div class="container-fluid mt-4">
                    <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipoMensaje; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3">
                            <i class="fas fa-exchange-alt me-2"></i><?php echo $titulo; ?>
                        </h1>
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoMovimientoModal">
                                <i class="fas fa-plus me-2"></i>Nuevo Movimiento
                            </button>
                            <button type="button" class="btn btn-success" onclick="exportarExcel()">
                                <i class="fas fa-file-excel me-2"></i>Exportar
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filtros -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-filter me-2"></i>Filtros de Búsqueda
                        </div>
                        <div class="card-body">
                            <form method="GET" id="formFiltros">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="filtro_tipo" class="form-label">Tipo de Movimiento</label>
                                        <select class="form-select" id="filtro_tipo" name="filtro_tipo">
                                            <option value="">Todos los tipos</option>
                                            <option value="entrada" <?php echo (isset($_GET['filtro_tipo']) && $_GET['filtro_tipo'] == 'entrada') ? 'selected' : ''; ?>>Entrada</option>
                                            <option value="salida" <?php echo (isset($_GET['filtro_tipo']) && $_GET['filtro_tipo'] == 'salida') ? 'selected' : ''; ?>>Salida</option>
                                            <option value="ajuste_entrada" <?php echo (isset($_GET['filtro_tipo']) && $_GET['filtro_tipo'] == 'ajuste_entrada') ? 'selected' : ''; ?>>Ajuste Entrada</option>
                                            <option value="ajuste_salida" <?php echo (isset($_GET['filtro_tipo']) && $_GET['filtro_tipo'] == 'ajuste_salida') ? 'selected' : ''; ?>>Ajuste Salida</option>
                                            <option value="traslado" <?php echo (isset($_GET['filtro_tipo']) && $_GET['filtro_tipo'] == 'traslado') ? 'selected' : ''; ?>>Traslado</option>
                                            <option value="devolucion" <?php echo (isset($_GET['filtro_tipo']) && $_GET['filtro_tipo'] == 'devolucion') ? 'selected' : ''; ?>>Devolución</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filtro_producto" class="form-label">Producto</label>
                                        <input type="text" class="form-control" id="filtro_producto" name="filtro_producto" 
                                               placeholder="Nombre o SKU" value="<?php echo $_GET['filtro_producto'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="filtro_fecha_desde" class="form-label">Desde</label>
                                        <input type="date" class="form-control" id="filtro_fecha_desde" name="filtro_fecha_desde"
                                               value="<?php echo $_GET['filtro_fecha_desde'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="filtro_fecha_hasta" class="form-label">Hasta</label>
                                        <input type="date" class="form-control" id="filtro_fecha_hasta" name="filtro_fecha_hasta"
                                               value="<?php echo $_GET['filtro_fecha_hasta'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <div class="btn-group w-100">
                                            <button type="submit" name="filtrar" class="btn btn-primary">
                                                <i class="fas fa-search me-2"></i>Filtrar
                                            </button>
                                            <a href="movimientos.php" class="btn btn-secondary">
                                                <i class="fas fa-undo me-2"></i>Limpiar
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Tabla de movimientos -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-list me-2"></i>Historial de Movimientos
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="tablaMovimientos">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Fecha</th>
                                            <th>Producto</th>
                                            <th>Variante</th>
                                            <th>Tipo</th>
                                            <th>Cantidad</th>
                                            <th>Ubicación</th>
                                            <th>Motivo</th>
                                            <th>Usuario</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($movimientos as $mov): ?>
                                        <tr>
                                            <td><?php echo $mov['id_movimiento']; ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                            <td><?php echo htmlspecialchars($mov['producto_nombre']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($mov['color']); ?></span>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($mov['talla']); ?></span>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($mov['sku']); ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $clase_tipo = '';
                                                switch ($mov['tipo_movimiento']) {
                                                    case 'entrada': $clase_tipo = 'movimiento-entrada'; break;
                                                    case 'salida': $clase_tipo = 'movimiento-salida'; break;
                                                    case 'ajuste_entrada': 
                                                    case 'ajuste_salida': $clase_tipo = 'movimiento-ajuste'; break;
                                                    case 'traslado': $clase_tipo = 'movimiento-traslado'; break;
                                                    case 'devolucion': $clase_tipo = 'movimiento-devolucion'; break;
                                                }
                                                ?>
                                                <span class="<?php echo $clase_tipo; ?> fw-bold">
                                                    <?php echo $mov['tipo_nombre']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="fw-bold <?php echo ($mov['tipo_movimiento'] == 'entrada' || $mov['tipo_movimiento'] == 'ajuste_entrada' || $mov['tipo_movimiento'] == 'devolucion') ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo ($mov['tipo_movimiento'] == 'entrada' || $mov['tipo_movimiento'] == 'ajuste_entrada' || $mov['tipo_movimiento'] == 'devolucion') ? '+' : '-'; ?>
                                                    <?php echo $mov['cantidad']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $mov['ubicacion'] == 'tienda' ? 'bg-warning' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($mov['ubicacion']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($mov['motivo']); ?></td>
                                            <td><?php echo htmlspecialchars($mov['usuario_nombre']); ?></td>
                                            <td>
                                                <?php if ($auth->hasPermission('inventario')): ?>
                                                <button class="btn btn-sm btn-danger" onclick="confirmarEliminar(<?php echo $mov['id_movimiento']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($movimientos)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center">No hay movimientos registrados</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Nuevo Movimiento -->
    <div class="modal fade" id="nuevoMovimientoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Movimiento de Inventario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formMovimiento">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="crear_movimiento">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="id_variante" class="form-label">Producto *</label>
                                <select class="form-select" id="id_variante" name="id_variante" required>
                                    <option value="">Seleccionar producto</option>
                                    <?php foreach ($productos as $prod): ?>
                                    <option value="<?php echo $prod['id_variante']; ?>">
                                        <?php echo htmlspecialchars($prod['nombre']) . ' - ' . $prod['color'] . ' (' . $prod['talla'] . ') - ' . $prod['sku']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="tipo_movimiento" class="form-label">Tipo de Movimiento *</label>
                                <select class="form-select" id="tipo_movimiento" name="tipo_movimiento" required>
                                    <option value="">Seleccionar tipo</option>
                                    <option value="entrada">Entrada</option>
                                    <option value="salida">Salida</option>
                                    <option value="ajuste_entrada">Ajuste Entrada</option>
                                    <option value="ajuste_salida">Ajuste Salida</option>
                                    <option value="traslado">Traslado</option>
                                    <option value="devolucion">Devolución</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="cantidad" class="form-label">Cantidad *</label>
                                <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="ubicacion" class="form-label">Ubicación *</label>
                                <select class="form-select" id="ubicacion" name="ubicacion" required>
                                    <option value="tienda">Tienda</option>
                                    <option value="bodega">Bodega</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="motivo" class="form-label">Motivo *</label>
                                <input type="text" class="form-control" id="motivo" name="motivo" required 
                                       placeholder="Ej: Compra, Venta, Ajuste...">
                            </div>
                            
                            <div class="col-md-12" id="infoStock" style="display: none;">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Stock actual: <span id="stockTienda">0</span> en tienda, <span id="stockBodega">0</span> en bodega
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Registrar Movimiento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para eliminar -->
    <form method="POST" id="formEliminar" style="display: none;">
        <input type="hidden" name="accion" value="eliminar_movimiento">
        <input type="hidden" name="id_movimiento" id="id_movimiento_eliminar">
    </form>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.es.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Inicializar DataTable
        $('#tablaMovimientos').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
            },
            order: [[1, 'desc']],
            pageLength: 25
        });
        
        // Cargar información de stock cuando se selecciona un producto
        $('#id_variante').change(function() {
            var idVariante = $(this).val();
            if (idVariante) {
                $.ajax({
                    url: 'ajax/get_stock.php',
                    type: 'GET',
                    data: { id_variante: idVariante },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            $('#stockTienda').text(data.stock_tienda);
                            $('#stockBodega').text(data.stock_bodega);
                            $('#infoStock').show();
                        }
                    }
                });
            } else {
                $('#infoStock').hide();
            }
        });
        
        // Datepicker
        $('#filtro_fecha_desde, #filtro_fecha_hasta').datepicker({
            format: 'yyyy-mm-dd',
            language: 'es',
            autoclose: true
        });
    });
    
    function confirmarEliminar(id) {
        if (confirm('¿Está seguro de eliminar este movimiento? Esta acción no se puede deshacer.')) {
            $('#id_movimiento_eliminar').val(id);
            $('#formEliminar').submit();
        }
    }
    
    function exportarExcel() {
        // Crear tabla HTML para exportar
        var table = document.getElementById('tablaMovimientos');
        var html = table.outerHTML;
        
        // Crear Blob con los datos
        var blob = new Blob([html], { type: 'application/vnd.ms-excel' });
        
        // Crear enlace para descargar
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'movimientos_inventario_' + new Date().toISOString().slice(0,10) + '.xls';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // Validar formulario de movimiento
    document.getElementById('formMovimiento').addEventListener('submit', function(e) {
        var tipo = document.getElementById('tipo_movimiento').value;
        var cantidad = document.getElementById('cantidad').value;
        var ubicacion = document.getElementById('ubicacion').value;
        var stockTienda = parseInt(document.getElementById('stockTienda').textContent) || 0;
        var stockBodega = parseInt(document.getElementById('stockBodega').textContent) || 0;
        
        // Validar que haya suficiente stock para salidas
        if (tipo === 'salida' || tipo === 'ajuste_salida' || tipo === 'traslado') {
            var stockDisponible = ubicacion === 'tienda' ? stockTienda : stockBodega;
            if (parseInt(cantidad) > stockDisponible) {
                e.preventDefault();
                alert('No hay suficiente stock disponible en ' + ubicacion + 
                      '. Stock disponible: ' + stockDisponible);
                return false;
            }
        }
        
        return true;
    });
    </script>
</body>
</html>