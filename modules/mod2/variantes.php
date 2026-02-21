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
$auth->requirePermission('productos');

// Manejar operaciones CRUD
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';
$producto_id = isset($_GET['producto']) ? $_GET['producto'] : '';

// Obtener datos para formularios
$productos_query = "SELECT id_raiz, codigo, nombre FROM productos_raiz WHERE activo = 1 ORDER BY nombre";
$productos_stmt = $db->prepare($productos_query);
$productos_stmt->execute();
$productos = $productos_stmt->fetchAll(PDO::FETCH_ASSOC);

$producto_seleccionado = null;
if ($producto_id) {
    foreach ($productos as $prod) {
        if ((string)$prod['id_raiz'] === (string)$producto_id) {
            $producto_seleccionado = $prod;
            break;
        }
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['action'] == 'create') {
        // Crear nueva variante
        $id_producto_raiz = $_POST['id_producto_raiz'] ?? '';
        if ($id_producto_raiz === '' && $producto_id) {
            $id_producto_raiz = $producto_id;
        }
        $color = sanitize($_POST['color']);
        $talla = sanitize($_POST['talla']);
        $stock_tienda = $_POST['stock_tienda'];
        $stock_bodega = $_POST['stock_bodega'];
        $usar_precio_especial = isset($_POST['precio_especial']);
        $precio_venta = $usar_precio_especial ? $_POST['precio_venta'] : null;
        
        // Generar SKU automático
        $producto_query = "SELECT codigo, precio_venta FROM productos_raiz WHERE id_raiz = :id";
        $producto_stmt = $db->prepare($producto_query);
        $producto_stmt->bindParam(':id', $id_producto_raiz);
        $producto_stmt->execute();
        $producto = $producto_stmt->fetch(PDO::FETCH_ASSOC);
        
        $sku = $producto['codigo'] . '-' . substr($color, 0, 3) . '-' . $talla;
        $sku = strtoupper(str_replace(' ', '', $sku));
        
        $precio_venta_final = $precio_venta;
        if ($precio_venta_final === null || $precio_venta_final === '' || (float)$precio_venta_final <= 0) {
            $precio_venta_final = $producto['precio_venta'] ?? 0;
        }

        $query = "INSERT INTO productos_variantes (id_producto_raiz, color, talla, stock_tienda, 
                  stock_bodega, precio_venta, sku, activo) 
                  VALUES (:id_producto_raiz, :color, :talla, :stock_tienda, :stock_bodega, 
                  :precio_venta, :sku, 1)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_producto_raiz', $id_producto_raiz);
        $stmt->bindParam(':color', $color);
        $stmt->bindParam(':talla', $talla);
        $stmt->bindParam(':stock_tienda', $stock_tienda);
        $stmt->bindParam(':stock_bodega', $stock_bodega);
        $stmt->bindParam(':precio_venta', $precio_venta_final);
        $stmt->bindParam(':sku', $sku);
        
        if ($stmt->execute()) {
            $nuevo_id_variante = (int)$db->lastInsertId();
            // Registrar movimiento de inventario
            if ($stock_tienda > 0) {
                $movimiento_query = "INSERT INTO inventario_movimientos (id_producto_variante, tipo_movimiento, 
                                    cantidad, ubicacion, motivo, id_usuario) 
                                    VALUES (:id_variante, 'entrada', :cantidad, 'tienda', 
                                    'Creación de variante', :id_usuario)";
                $movimiento_stmt = $db->prepare($movimiento_query);
                $movimiento_stmt->bindParam(':id_variante', $nuevo_id_variante, PDO::PARAM_INT);
                $movimiento_stmt->bindParam(':cantidad', $stock_tienda);
                $movimiento_stmt->bindParam(':id_usuario', $_SESSION['user_id']);
                $movimiento_stmt->execute();
            }
            
            if ($stock_bodega > 0) {
                $movimiento_query = "INSERT INTO inventario_movimientos (id_producto_variante, tipo_movimiento, 
                                    cantidad, ubicacion, motivo, id_usuario) 
                                    VALUES (:id_variante, 'entrada', :cantidad, 'bodega', 
                                    'Creación de variante', :id_usuario)";
                $movimiento_stmt = $db->prepare($movimiento_query);
                $movimiento_stmt->bindParam(':id_variante', $nuevo_id_variante, PDO::PARAM_INT);
                $movimiento_stmt->bindParam(':cantidad', $stock_bodega);
                $movimiento_stmt->bindParam(':id_usuario', $_SESSION['user_id']);
                $movimiento_stmt->execute();
            }
            
            $success = "Variante creada exitosamente.";
        } else {
            $error = "Error al crear variante. La combinación color/talla ya existe.";
        }
    }
    elseif ($_POST['action'] == 'update') {
        // Actualizar variante
        $id_variante = $_POST['id_variante'];
        $color = sanitize($_POST['color']);
        $talla = sanitize($_POST['talla']);
        $stock_tienda = $_POST['stock_tienda'];
        $stock_bodega = $_POST['stock_bodega'];
        $usar_precio_especial = isset($_POST['precio_especial']);
        $precio_venta = $usar_precio_especial ? $_POST['precio_venta'] : null;
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Obtener stock anterior para comparar
        $old_query = "SELECT stock_tienda, stock_bodega FROM productos_variantes WHERE id_variante = :id";
        $old_stmt = $db->prepare($old_query);
        $old_stmt->bindParam(':id', $id_variante);
        $old_stmt->execute();
        $old_stock = $old_stmt->fetch(PDO::FETCH_ASSOC);
        
        $precio_query = "SELECT pr.precio_venta FROM productos_variantes pv INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz WHERE pv.id_variante = :id";
        $precio_stmt = $db->prepare($precio_query);
        $precio_stmt->bindParam(':id', $id_variante);
        $precio_stmt->execute();
        $precio_row = $precio_stmt->fetch(PDO::FETCH_ASSOC);

        $precio_venta_final = $precio_venta;
        if ($precio_venta_final === null || $precio_venta_final === '' || (float)$precio_venta_final <= 0) {
            $precio_venta_final = $precio_row['precio_venta'] ?? 0;
        }

        $query = "UPDATE productos_variantes SET color = :color, talla = :talla, 
                  stock_tienda = :stock_tienda, stock_bodega = :stock_bodega, 
                  precio_venta = :precio_venta, activo = :activo WHERE id_variante = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':color', $color);
        $stmt->bindParam(':talla', $talla);
        $stmt->bindParam(':stock_tienda', $stock_tienda);
        $stmt->bindParam(':stock_bodega', $stock_bodega);
        $stmt->bindParam(':precio_venta', $precio_venta_final);
        $stmt->bindParam(':activo', $activo);
        $stmt->bindParam(':id', $id_variante);
        
        if ($stmt->execute()) {
            // Registrar movimientos de ajuste
            $diferencia_tienda = $stock_tienda - $old_stock['stock_tienda'];
            if ($diferencia_tienda != 0) {
                $tipo = $diferencia_tienda > 0 ? 'entrada' : 'salida';
                $cantidad = abs($diferencia_tienda);
                
                $movimiento_query = "INSERT INTO inventario_movimientos (id_producto_variante, tipo_movimiento, 
                                    cantidad, ubicacion, motivo, id_usuario) 
                                    VALUES (:id_variante, :tipo, :cantidad, 'tienda', 
                                    'Ajuste de inventario', :id_usuario)";
                $movimiento_stmt = $db->prepare($movimiento_query);
                $movimiento_stmt->bindParam(':id_variante', $id_variante);
                $movimiento_stmt->bindParam(':tipo', $tipo);
                $movimiento_stmt->bindParam(':cantidad', $cantidad);
                $movimiento_stmt->bindParam(':id_usuario', $_SESSION['user_id']);
                $movimiento_stmt->execute();
            }
            
            $diferencia_bodega = $stock_bodega - $old_stock['stock_bodega'];
            if ($diferencia_bodega != 0) {
                $tipo = $diferencia_bodega > 0 ? 'entrada' : 'salida';
                $cantidad = abs($diferencia_bodega);
                
                $movimiento_query = "INSERT INTO inventario_movimientos (id_producto_variante, tipo_movimiento, 
                                    cantidad, ubicacion, motivo, id_usuario) 
                                    VALUES (:id_variante, :tipo, :cantidad, 'bodega', 
                                    'Ajuste de inventario', :id_usuario)";
                $movimiento_stmt = $db->prepare($movimiento_query);
                $movimiento_stmt->bindParam(':id_variante', $id_variante);
                $movimiento_stmt->bindParam(':tipo', $tipo);
                $movimiento_stmt->bindParam(':cantidad', $cantidad);
                $movimiento_stmt->bindParam(':id_usuario', $_SESSION['user_id']);
                $movimiento_stmt->execute();
            }
            
            $success = "Variante actualizada exitosamente.";
        } else {
            $error = "Error al actualizar variante.";
        }
    }
    elseif ($_POST['action'] == 'transfer') {
        // Transferir stock entre ubicaciones
        $id_variante = $_POST['id_variante'];
        $cantidad = $_POST['cantidad'];
        $desde = $_POST['desde'];
        $hacia = $_POST['hacia'];
        $motivo = sanitize($_POST['motivo']);
        
        if ($desde == $hacia) {
            $error = "No se puede transferir a la misma ubicación.";
        } else {
            // Verificar stock disponible
            $stock_query = "SELECT stock_tienda, stock_bodega FROM productos_variantes WHERE id_variante = :id";
            $stock_stmt = $db->prepare($stock_query);
            $stock_stmt->bindParam(':id', $id_variante);
            $stock_stmt->execute();
            $stock = $stock_stmt->fetch(PDO::FETCH_ASSOC);
            
            $stock_disponible = $desde == 'tienda' ? $stock['stock_tienda'] : $stock['stock_bodega'];
            
            if ($cantidad <= $stock_disponible) {
                // Actualizar stocks
                $update_query = "UPDATE productos_variantes SET 
                                stock_tienda = stock_tienda " . ($desde == 'tienda' ? '-' : '+') . " :cantidad,
                                stock_bodega = stock_bodega " . ($desde == 'bodega' ? '-' : '+') . " :cantidad 
                                WHERE id_variante = :id";
                
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':cantidad', $cantidad);
                $update_stmt->bindParam(':id', $id_variante);
                
                if ($update_stmt->execute()) {
                    // Registrar movimiento de salida
                    $salida_query = "INSERT INTO inventario_movimientos (id_producto_variante, tipo_movimiento, 
                                    cantidad, ubicacion, motivo, id_usuario) 
                                    VALUES (:id_variante, 'salida', :cantidad, :desde, :motivo, :id_usuario)";
                    $salida_stmt = $db->prepare($salida_query);
                    $salida_stmt->bindParam(':id_variante', $id_variante);
                    $salida_stmt->bindParam(':cantidad', $cantidad);
                    $salida_stmt->bindParam(':desde', $desde);
                    $salida_stmt->bindParam(':motivo', $motivo);
                    $salida_stmt->bindParam(':id_usuario', $_SESSION['user_id']);
                    $salida_stmt->execute();
                    
                    // Registrar movimiento de entrada
                    $entrada_query = "INSERT INTO inventario_movimientos (id_producto_variante, tipo_movimiento, 
                                     cantidad, ubicacion, motivo, id_usuario) 
                                     VALUES (:id_variante, 'entrada', :cantidad, :hacia, :motivo, :id_usuario)";
                    $entrada_stmt = $db->prepare($entrada_query);
                    $entrada_stmt->bindParam(':id_variante', $id_variante);
                    $entrada_stmt->bindParam(':cantidad', $cantidad);
                    $entrada_stmt->bindParam(':hacia', $hacia);
                    $entrada_stmt->bindParam(':motivo', $motivo);
                    $entrada_stmt->bindParam(':id_usuario', $_SESSION['user_id']);
                    $entrada_stmt->execute();
                    
                    $success = "Transferencia realizada exitosamente.";
                } else {
                    $error = "Error al realizar la transferencia.";
                }
            } else {
                $error = "Stock insuficiente en " . ($desde == 'tienda' ? 'tienda' : 'bodega') . ".";
            }
        }
    }
    elseif ($_POST['action'] == 'delete_variante') {
        // Desactivar variante
        $id_variante = $_POST['id_variante'];
        $query = "UPDATE productos_variantes SET activo = 0 WHERE id_variante = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id_variante);
        if ($stmt->execute()) {
            $success = "Variante eliminada correctamente.";
        } else {
            $error = "Error al eliminar variante.";
        }
    }
}

// Obtener variantes con filtros
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filter_producto = $producto_id ?: (isset($_GET['producto_filter']) ? $_GET['producto_filter'] : '');

$query = "SELECT pv.*, pr.codigo as producto_codigo, pr.nombre as producto_nombre, 
          pr.es_ajitos, pr.precio_venta as precio_base 
          FROM productos_variantes pv 
          INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz 
          WHERE 1=1";

if ($search) {
    $query .= " AND (pv.color LIKE :search OR pv.talla LIKE :search OR pv.sku LIKE :search 
                OR pr.nombre LIKE :search OR pr.codigo LIKE :search)";
}
if ($filter_producto) {
    $query .= " AND pv.id_producto_raiz = :producto_id";
}

$query .= " ORDER BY pr.nombre, pv.color, pv.talla";

$stmt = $db->prepare($query);

if ($search) {
    $search_term = "%$search%";
    $stmt->bindParam(':search', $search_term);
}
if ($filter_producto) {
    $stmt->bindParam(':producto_id', $filter_producto);
}

$stmt->execute();
$variantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener variante específica para editar
$variante_edit = null;
if ($action == 'edit' && $id) {
    $query = "SELECT pv.*, pr.codigo as producto_codigo, pr.nombre as producto_nombre 
              FROM productos_variantes pv 
              INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz 
              WHERE pv.id_variante = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $variante_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Colores comunes para sugerencias
$colores_comunes = ['Rojo', 'Azul', 'Verde', 'Negro', 'Blanco', 'Gris', 'Amarillo', 'Naranja', 
                   'Rosa', 'Morado', 'Marrón', 'Beige', 'Celeste', 'Turquesa', 'Vino'];

// Tallas comunes
$tallas_comunes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '0-3M', '3-6M', '6-9M', '9-12M', 
                  '12-18M', '18-24M', '2T', '3T', '4T', '5T'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Variantes de Productos - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stock-bajo {
            background-color: #ffcccc !important;
        }
        .stock-medio {
            background-color: #fff3cd !important;
        }
        .stock-ok {
            background-color: #d4edda !important;
        }
        .color-box {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 3px;
            margin-right: 5px;
            vertical-align: middle;
        }
        .badge-ajitos {
            background-color: #ff6b6b;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <main class="col-12 px-2">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Variantes de Productos</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-1"></i> Nueva Variante
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

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Buscar por color, talla, SKU o producto..." 
                                       value="<?php echo $search; ?>">
                            </div>
                            <?php if (!$producto_id): ?>
                            <div class="col-md-3">
                                <select class="form-select" name="producto_filter">
                                    <option value="">Todos los productos</option>
                                    <?php foreach ($productos as $prod): ?>
                                        <option value="<?php echo $prod['id_raiz']; ?>"
                                            <?php echo $filter_producto == $prod['id_raiz'] ? 'selected' : ''; ?>>
                                            <?php echo $prod['codigo'] . ' - ' . $prod['nombre']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i> Filtrar
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="variantes.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-times me-1"></i> Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Resumen de stock -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6 class="card-title">Total Variantes</h6>
                                <h3 class="card-text"><?php echo count($variantes); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6 class="card-title">Stock en Tienda</h6>
                                <h3 class="card-text">
                                    <?php 
                                        $total_tienda = 0;
                                        foreach ($variantes as $v) {
                                            $total_tienda += $v['stock_tienda'];
                                        }
                                        echo $total_tienda;
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h6 class="card-title">Stock en Bodega</h6>
                                <h3 class="card-text">
                                    <?php 
                                        $total_bodega = 0;
                                        foreach ($variantes as $v) {
                                            $total_bodega += $v['stock_bodega'];
                                        }
                                        echo $total_bodega;
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h6 class="card-title">Stock Total</h6>
                                <h3 class="card-text"><?php echo $total_tienda + $total_bodega; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                        <?php if ($producto_id && $producto_seleccionado): ?>
                        <div class="mb-3">
                            <label class="form-label">Producto</label>
                            <input type="text" class="form-control" value="<?php echo $producto_seleccionado['codigo'] . ' - ' . $producto_seleccionado['nombre']; ?>" readonly>
                            <input type="hidden" name="id_producto_raiz" value="<?php echo $producto_seleccionado['id_raiz']; ?>">
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <label for="id_producto_raiz" class="form-label">Producto *</label>
                            <select class="form-select" id="id_producto_raiz" name="id_producto_raiz" required>
                                <option value="">Seleccionar producto</option>
                                <?php foreach ($productos as $prod): ?>
                                    <option value="<?php echo $prod['id_raiz']; ?>"
                                        <?php echo $producto_id == $prod['id_raiz'] ? 'selected' : ''; ?>>
                                        <?php echo $prod['codigo'] . ' - ' . $prod['nombre']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                                        <th>Stock Tienda</th>
                                        <th>Stock Bodega</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($variantes as $variante): 
                                        $stock_total = $variante['stock_tienda'] + $variante['stock_bodega'];
                                        $stock_class = '';
                                        if ($stock_total == 0) {
                                            $stock_class = 'stock-bajo';
                                        } elseif ($stock_total < 5) {
                                            $stock_class = 'stock-medio';
                                        } else {
                                            $stock_class = 'stock-ok';
                                        }
                                    ?>
                                    <tr class="<?php echo $stock_class; ?>">
                                        <td><?php echo $variante['id_variante']; ?></td>
                                        <td>
                                            <?php echo $variante['producto_codigo']; ?>
                                            <?php if ($variante['es_ajitos']): ?>
                                                <span class="badge badge-ajitos">Ajitos</span>
                                            <?php endif; ?>
                                            <br>
                                            <small><?php echo $variante['producto_nombre']; ?></small>
                                        </td>
                                        <td>
                                            <div class="color-box" style="background-color: <?php echo strtolower($variante['color']); ?>;"></div>
                                            <?php echo $variante['color']; ?>
                                        </td>
                                        <td><strong><?php echo $variante['talla']; ?></strong></td>
                                        <td><code><?php echo $variante['sku']; ?></code></td>
                                        <td>
                                            <?php 
                                                $precio = $variante['precio_venta'] ?: $variante['precio_base'];
                                                echo formatMoney($precio);
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $variante['stock_tienda']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><?php echo $variante['stock_bodega']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $stock_total; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $variante['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $variante['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $variante['id_variante']; ?>" 
                                               class="btn btn-sm btn-warning" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#transferModal<?php echo $variante['id_variante']; ?>"
                                                    title="Transferir">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            <button class="btn btn-sm btn-secondary" 
                                                    onclick="viewMovements(<?php echo $variante['id_variante']; ?>)" 
                                                    title="Movimientos">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="deleteVariante(<?php echo $variante['id_variante']; ?>)" 
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Modal para transferencia -->
                                    <div class="modal fade" id="transferModal<?php echo $variante['id_variante']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Transferir Stock</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="transfer">
                                                        <input type="hidden" name="id_variante" value="<?php echo $variante['id_variante']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Producto</label>
                                                            <input type="text" class="form-control" 
                                                                   value="<?php echo $variante['producto_codigo'] . ' - ' . $variante['producto_nombre']; ?>" 
                                                                   readonly>
                                                        </div>
                                                        
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Color</label>
                                                                <input type="text" class="form-control" 
                                                                       value="<?php echo $variante['color']; ?>" readonly>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Talla</label>
                                                                <input type="text" class="form-control" 
                                                                       value="<?php echo $variante['talla']; ?>" readonly>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Stock Tienda</label>
                                                                <input type="text" class="form-control" 
                                                                       value="<?php echo $variante['stock_tienda']; ?>" readonly>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Stock Bodega</label>
                                                                <input type="text" class="form-control" 
                                                                       value="<?php echo $variante['stock_bodega']; ?>" readonly>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label for="desde<?php echo $variante['id_variante']; ?>" class="form-label">Desde *</label>
                                                                <select class="form-select" id="desde<?php echo $variante['id_variante']; ?>" name="desde" required>
                                                                    <option value="tienda">Tienda</option>
                                                                    <option value="bodega">Bodega</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="hacia<?php echo $variante['id_variante']; ?>" class="form-label">Hacia *</label>
                                                                <select class="form-select" id="hacia<?php echo $variante['id_variante']; ?>" name="hacia" required>
                                                                    <option value="tienda">Tienda</option>
                                                                    <option value="bodega">Bodega</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="cantidad<?php echo $variante['id_variante']; ?>" class="form-label">Cantidad *</label>
                                                            <input type="number" class="form-control" id="cantidad<?php echo $variante['id_variante']; ?>" 
                                                                   name="cantidad" min="1" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="motivo<?php echo $variante['id_variante']; ?>" class="form-label">Motivo *</label>
                                                            <textarea class="form-control" id="motivo<?php echo $variante['id_variante']; ?>" 
                                                                      name="motivo" rows="2" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-primary">Transferir</button>
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

    <!-- Modal para crear variante -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Variante de Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label for="id_producto_raiz" class="form-label">Producto *</label>
                            <select class="form-select" id="id_producto_raiz" name="id_producto_raiz" required>
                                <option value="">Seleccionar producto</option>
                                <?php foreach ($productos as $prod): ?>
                                    <option value="<?php echo $prod['id_raiz']; ?>">
                                        <?php echo $prod['codigo'] . ' - ' . $prod['nombre']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="color" class="form-label">Color *</label>
                                <input type="text" class="form-control" id="color" name="color" 
                                       list="colores" required>
                                <datalist id="colores">
                                    <?php foreach ($colores_comunes as $color): ?>
                                        <option value="<?php echo $color; ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="talla" class="form-label">Talla *</label>
                                <input type="text" class="form-control" id="talla" name="talla" 
                                       list="tallas" required>
                                <datalist id="tallas">
                                    <?php foreach ($tallas_comunes as $talla): ?>
                                        <option value="<?php echo $talla; ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="stock_tienda" class="form-label">Stock Tienda *</label>
                                <input type="number" class="form-control" id="stock_tienda" name="stock_tienda" 
                                       min="0" value="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="stock_bodega" class="form-label">Stock Bodega *</label>
                                <input type="number" class="form-control" id="stock_bodega" name="stock_bodega" 
                                       min="0" value="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <input type="checkbox" class="btn-check" id="precio_especial" name="precio_especial" autocomplete="off">
                                <label class="btn btn-outline-primary btn-sm" for="precio_especial">Precio especial</label>
                                <small class="text-muted">Si no se activa, se usa el precio del producto raíz</small>
                            </div>
                            <label for="precio_venta" class="form-label">Precio de Venta</label>
                            <input type="number" class="form-control" id="precio_venta" name="precio_venta" 
                                   step="0.01" min="0" disabled>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Variante</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar variante -->
    <?php if ($variante_edit): ?>
    <div class="modal fade show" id="editModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Variante</h5>
                    <a href="variantes.php<?php echo $producto_id ? '?producto=' . $producto_id : ''; ?>" class="btn-close"></a>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_variante" value="<?php echo $variante_edit['id_variante']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Producto</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo $variante_edit['producto_codigo'] . ' - ' . $variante_edit['producto_nombre']; ?>" 
                                   readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_color" class="form-label">Color *</label>
                                <input type="text" class="form-control" id="edit_color" name="color" 
                                       value="<?php echo $variante_edit['color']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_talla" class="form-label">Talla *</label>
                                <input type="text" class="form-control" id="edit_talla" name="talla" 
                                       value="<?php echo $variante_edit['talla']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_stock_tienda" class="form-label">Stock Tienda *</label>
                                <input type="number" class="form-control" id="edit_stock_tienda" name="stock_tienda" 
                                       value="<?php echo $variante_edit['stock_tienda']; ?>" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_stock_bodega" class="form-label">Stock Bodega *</label>
                                <input type="number" class="form-control" id="edit_stock_bodega" name="stock_bodega" 
                                       value="<?php echo $variante_edit['stock_bodega']; ?>" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <?php $precio_especial_activo = isset($variante_edit['precio_venta']) && (float)$variante_edit['precio_venta'] > 0; ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <input type="checkbox" class="btn-check" id="edit_precio_especial" name="precio_especial" autocomplete="off" <?php echo $precio_especial_activo ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary btn-sm" for="edit_precio_especial">Precio especial</label>
                                <small class="text-muted">Si no se activa, se usa el precio del producto raíz</small>
                            </div>
                            <label for="edit_precio_venta" class="form-label">Precio de Venta</label>
                            <input type="number" class="form-control" id="edit_precio_venta" name="precio_venta" 
                                   value="<?php echo $variante_edit['precio_venta']; ?>" step="0.01" min="0" <?php echo $precio_especial_activo ? '' : 'disabled'; ?>>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_activo" name="activo" 
                                       <?php echo $variante_edit['activo'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="edit_activo">
                                    Variante activa
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="variantes.php<?php echo $producto_id ? '?producto=' . $producto_id : ''; ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Actualizar Variante</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function viewMovements(id) {
            window.location.href = 'movimientos.php?variante=' + id;
        }
        
        <?php if ($variante_edit): ?>
        // Mostrar modal de edición automáticamente
        var editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
        <?php endif; ?>
        
        // Actualizar opciones de transferencia para que no sean iguales
        document.addEventListener('DOMContentLoaded', function() {
            var transferModals = document.querySelectorAll('[id^="transferModal"]');
            transferModals.forEach(function(modal) {
                var desde = modal.querySelector('[name="desde"]');
                var hacia = modal.querySelector('[name="hacia"]');
                
                desde.addEventListener('change', function() {
                    hacia.value = this.value === 'tienda' ? 'bodega' : 'tienda';
                });
                
                hacia.addEventListener('change', function() {
                    desde.value = this.value === 'tienda' ? 'bodega' : 'tienda';
                });
            });

            var precioEspecial = document.getElementById('precio_especial');
            var precioVenta = document.getElementById('precio_venta');
            if (precioEspecial && precioVenta) {
                precioEspecial.addEventListener('change', function() {
                    precioVenta.disabled = !this.checked;
                    if (!this.checked) {
                        precioVenta.value = '';
                    }
                });
            }

            var editPrecioEspecial = document.getElementById('edit_precio_especial');
            var editPrecioVenta = document.getElementById('edit_precio_venta');
            if (editPrecioEspecial && editPrecioVenta) {
                editPrecioEspecial.addEventListener('change', function() {
                    editPrecioVenta.disabled = !this.checked;
                    if (!this.checked) {
                        editPrecioVenta.value = '';
                    }
                });
            }
        });
    </script>

    <form id="deleteVarianteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_variante">
        <input type="hidden" name="id_variante" id="delete_variante_id">
    </form>

    <script>
        function deleteVariante(id) {
            if (!confirm('¿Eliminar esta variante?')) {
                return;
            }
            document.getElementById('delete_variante_id').value = id;
            document.getElementById('deleteVarianteForm').submit();
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>