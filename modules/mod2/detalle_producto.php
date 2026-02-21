<?php
// modules/mod2/detalle_producto.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/config.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$can_delete = isset($_SESSION['rol']) && in_array((int)$_SESSION['rol'], [1, 2], true);

if (!$auth->isLoggedIn()) {
    header('Location: ../../index.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: productos.php');
    exit();
}

$id_producto = $_GET['id'];

// Obtener información del producto raíz
$stmt = $db->prepare("SELECT pr.*, d.nombre as departamento_nombre, m.nombre as marca_nombre, t.nombre as tipo_ropa_nombre
                      FROM productos_raiz pr
                      LEFT JOIN departamentos d ON pr.id_departamento = d.id_departamento
                      LEFT JOIN marcas m ON pr.id_marca = m.id_marca
                      LEFT JOIN tipos_ropa t ON pr.tipo_ropa = t.id_tipo
                      WHERE pr.id_raiz = :id");
$stmt->bindParam(':id', $id_producto);
$stmt->execute();
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    header('Location: productos.php?error=producto_no_encontrado');
    exit();
}

// Obtener variantes del producto
$stmt_variantes = $db->prepare("SELECT pv.*, pr.precio_venta as precio_base,
                               COALESCE(NULLIF(pv.precio_venta, 0), pr.precio_venta) as precio_final
                               FROM productos_variantes pv
                               INNER JOIN productos_raiz pr ON pr.id_raiz = pv.id_producto_raiz
                               WHERE pv.id_producto_raiz = :id AND pv.activo = 1
                               ORDER BY pv.color, pv.talla");
$stmt_variantes->bindParam(':id', $id_producto);
$stmt_variantes->execute();
$variantes = $stmt_variantes->fetchAll(PDO::FETCH_ASSOC);

// Obtener fotos del producto
$stmt_fotos = $db->prepare("SELECT * FROM productos_raiz_fotos WHERE id_producto_raiz = :id ORDER BY es_principal DESC");
$stmt_fotos->bindParam(':id', $id_producto);
$stmt_fotos->execute();
$fotos = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);

// Obtener movimientos de este producto
$stmt_movimientos = $db->prepare("SELECT m.*, pv.color, pv.talla, u.username as usuario_nombre
                                 FROM inventario_movimientos m
                                 INNER JOIN productos_variantes pv ON m.id_producto_variante = pv.id_variante
                                 INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                                 WHERE pv.id_producto_raiz = :id
                                 ORDER BY m.fecha_movimiento DESC
                                 LIMIT 10");
$stmt_movimientos->bindParam(':id', $id_producto);
$stmt_movimientos->execute();
$movimientos = $stmt_movimientos->fetchAll(PDO::FETCH_ASSOC);

// Procesar creación de variante desde modal
$mensaje_variante = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_variante') {
    $color = sanitize($_POST['color']);
    $talla = sanitize($_POST['talla']);
    $stock_tienda = (int)$_POST['stock_tienda'];
    $stock_bodega = (int)$_POST['stock_bodega'];
    $precio_especial = isset($_POST['precio_especial']) ? 1 : 0;
    $precio_venta = $precio_especial ? (float)$_POST['precio_venta'] : null;
    
    // Verificar si ya existe una variante con ese color y talla
    $check_query = "SELECT COUNT(*) as count FROM productos_variantes WHERE id_producto_raiz = :id_producto AND color = :color AND talla = :talla AND activo = 1";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id_producto', $id_producto);
    $check_stmt->bindParam(':color', $color);
    $check_stmt->bindParam(':talla', $talla);
    $check_stmt->execute();
    $existe = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if ($existe) {
        $mensaje_variante = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                             Ya existe una variante con color "' . htmlspecialchars($color) . '" y talla "' . htmlspecialchars($talla) . '"
                             <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                             </div>';
    } else {
        // Generar SKU automático
        $sku = strtoupper(substr($producto['codigo'], 0, 3)) . '-' . strtoupper(substr($color, 0, 2)) . '-' . strtoupper(substr($talla, 0, 2)) . '-' . time();
        
        // Si no tiene precio especial, usar NULL (usará el del producto raíz)
        if (!$precio_especial) {
            $precio_venta = null;
        }
        
        $insert_query = "INSERT INTO productos_variantes (id_producto_raiz, color, talla, sku, stock_tienda, stock_bodega, precio_venta, activo)
                        VALUES (:id_producto, :color, :talla, :sku, :stock_tienda, :stock_bodega, :precio_venta, 1)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':id_producto', $id_producto);
        $insert_stmt->bindParam(':color', $color);
        $insert_stmt->bindParam(':talla', $talla);
        $insert_stmt->bindParam(':sku', $sku);
        $insert_stmt->bindParam(':stock_tienda', $stock_tienda);
        $insert_stmt->bindParam(':stock_bodega', $stock_bodega);
        $insert_stmt->bindParam(':precio_venta', $precio_venta);
        
        if ($insert_stmt->execute()) {
            $id_variante = $db->lastInsertId();
            
            // Registrar movimiento de stock inicial
            $tipo_movimiento = 'entrada';
            $cantidad = $stock_tienda + $stock_bodega;
            $motivo = 'Variante creada - Stock inicial';
            $ubicacion = 'tienda';
            $usuario_id = $_SESSION['user_id'];
            
            $mov_query = "INSERT INTO inventario_movimientos (id_producto_variante, tipo_movimiento, cantidad, ubicacion, motivo, id_usuario, fecha_movimiento)
                         VALUES (:id_variante, :tipo, :cantidad, :ubicacion, :motivo, :id_usuario, NOW())";
            $mov_stmt = $db->prepare($mov_query);
            $mov_stmt->bindParam(':id_variante', $id_variante);
            $mov_stmt->bindParam(':tipo', $tipo_movimiento);
            $mov_stmt->bindParam(':cantidad', $cantidad);
            $mov_stmt->bindParam(':ubicacion', $ubicacion);
            $mov_stmt->bindParam(':motivo', $motivo);
            $mov_stmt->bindParam(':id_usuario', $usuario_id);
            $mov_stmt->execute();
            
            $mensaje_variante = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>Variante creada exitosamente: <strong>' . htmlspecialchars($color) . ' - ' . htmlspecialchars($talla) . '</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>';
            
            // Recargar variantes
            $stmt_variantes = $db->prepare("SELECT pv.*, pr.precio_venta as precio_base,
                                           COALESCE(NULLIF(pv.precio_venta, 0), pr.precio_venta) as precio_final
                                           FROM productos_variantes pv
                                           INNER JOIN productos_raiz pr ON pr.id_raiz = pv.id_producto_raiz
                                           WHERE pv.id_producto_raiz = :id AND pv.activo = 1
                                           ORDER BY pv.color, pv.talla");
            $stmt_variantes->bindParam(':id', $id_producto);
            $stmt_variantes->execute();
            $variantes = $stmt_variantes->fetchAll(PDO::FETCH_ASSOC);
            
            // Recargar movimientos
            $stmt_movimientos = $db->prepare("SELECT m.*, pv.color, pv.talla, u.username as usuario_nombre
                                             FROM inventario_movimientos m
                                             INNER JOIN productos_variantes pv ON m.id_producto_variante = pv.id_variante
                                             INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                                             WHERE pv.id_producto_raiz = :id
                                             ORDER BY m.fecha_movimiento DESC
                                             LIMIT 10");
            $stmt_movimientos->bindParam(':id', $id_producto);
            $stmt_movimientos->execute();
            $movimientos = $stmt_movimientos->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $mensaje_variante = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>Error al crear la variante. Intenta de nuevo.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>';
        }
    }
}
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_variante') {
    $id_variante = (int)$_POST['id_variante'];
    $color = sanitize($_POST['color']);
    $talla = sanitize($_POST['talla']);
    $stock_tienda = (int)$_POST['stock_tienda'];
    $stock_bodega = (int)$_POST['stock_bodega'];
    $precio_especial = isset($_POST['precio_especial']) ? 1 : 0;
    $precio_venta = $precio_especial ? (float)$_POST['precio_venta'] : null;
    
    $update_query = "UPDATE productos_variantes SET color = :color, talla = :talla, stock_tienda = :stock_tienda, 
                     stock_bodega = :stock_bodega, precio_venta = :precio_venta 
                     WHERE id_variante = :id_variante AND id_producto_raiz = :id_producto";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':id_variante', $id_variante);
    $update_stmt->bindParam(':id_producto', $id_producto);
    $update_stmt->bindParam(':color', $color);
    $update_stmt->bindParam(':talla', $talla);
    $update_stmt->bindParam(':stock_tienda', $stock_tienda);
    $update_stmt->bindParam(':stock_bodega', $stock_bodega);
    $update_stmt->bindParam(':precio_venta', $precio_venta);
    
    if ($update_stmt->execute()) {
        $mensaje_variante = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>Variante actualizada exitosamente
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>';
        
        // Recargar variantes
        $stmt_variantes = $db->prepare("SELECT pv.*, pr.precio_venta as precio_base,
                           COALESCE(NULLIF(pv.precio_venta, 0), pr.precio_venta) as precio_final
                           FROM productos_variantes pv
                           INNER JOIN productos_raiz pr ON pr.id_raiz = pv.id_producto_raiz
                           WHERE pv.id_producto_raiz = :id AND pv.activo = 1
                           ORDER BY pv.color, pv.talla");
        $stmt_variantes->bindParam(':id', $id_producto);
        $stmt_variantes->execute();
        $variantes = $stmt_variantes->fetchAll(PDO::FETCH_ASSOC);
        
        // Recargar movimientos
        $stmt_movimientos = $db->prepare("SELECT m.*, pv.color, pv.talla, u.username as usuario_nombre
                                         FROM inventario_movimientos m
                                         INNER JOIN productos_variantes pv ON m.id_producto_variante = pv.id_variante
                                         INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                                         WHERE pv.id_producto_raiz = :id
                                         ORDER BY m.fecha_movimiento DESC
                                         LIMIT 10");
        $stmt_movimientos->bindParam(':id', $id_producto);
        $stmt_movimientos->execute();
        $movimientos = $stmt_movimientos->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $mensaje_variante = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>Error al actualizar la variante. Intenta de nuevo.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>';
    }
}
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_variante') {
    if (!$can_delete) {
        $mensaje_variante = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            No tienes permisos para eliminar variantes.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>';
    } else {
        $id_variante = (int)$_POST['id_variante'];
        $delete_query = "UPDATE productos_variantes SET activo = 0 WHERE id_variante = :id_variante AND id_producto_raiz = :id_producto";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id_variante', $id_variante);
        $delete_stmt->bindParam(':id_producto', $id_producto);

        if ($delete_stmt->execute()) {
            $mensaje_variante = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                Variante eliminada correctamente.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>';
            $stmt_variantes = $db->prepare("SELECT pv.*, pr.precio_venta as precio_base,
                                           COALESCE(NULLIF(pv.precio_venta, 0), pr.precio_venta) as precio_final
                                           FROM productos_variantes pv
                                           INNER JOIN productos_raiz pr ON pr.id_raiz = pv.id_producto_raiz
                                           WHERE pv.id_producto_raiz = :id AND pv.activo = 1
                                           ORDER BY pv.color, pv.talla");
            $stmt_variantes->bindParam(':id', $id_producto);
            $stmt_variantes->execute();
            $variantes = $stmt_variantes->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $mensaje_variante = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Error al eliminar la variante.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>';
        }
    }
}
elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_producto') {
    if (!$can_delete) {
        $mensaje_variante = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            No tienes permisos para eliminar productos.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>';
    } else {
        $db->beginTransaction();
        try {
            $update_producto = "UPDATE productos_raiz SET activo = 0 WHERE id_raiz = :id_raiz";
            $stmt = $db->prepare($update_producto);
            $stmt->bindParam(':id_raiz', $id_producto);
            $stmt->execute();

            $update_variantes = "UPDATE productos_variantes SET activo = 0 WHERE id_producto_raiz = :id_raiz";
            $stmt = $db->prepare($update_variantes);
            $stmt->bindParam(':id_raiz', $id_producto);
            $stmt->execute();

            $db->commit();
            header('Location: productos.php?success=producto_eliminado');
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $mensaje_variante = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Error al eliminar el producto.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>';
        }
    }
}

$titulo = "Detalle de Producto: " . $producto['nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo . ' - ' . SITE_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .product-image {
            max-height: 400px;
            object-fit: contain;
        }
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .thumbnail.active {
            border-color: #007bff;
        }
        .stock-badge {
            font-size: 0.9em;
            padding: 5px 10px;
        }
        .variante-card {
            transition: transform 0.2s;
        }
        .variante-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <main class="col-12 px-2">
                <?php include $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/includes/header.php'; ?>
                
                <div class="container-fluid mt-4">
                    <?php if (!empty($mensaje_variante)): ?>
                        <?php echo $mensaje_variante; ?>
                    <?php endif; ?>
                    
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../mod2/productos.php">Productos</a></li>
                            <li class="breadcrumb-item active"><?php echo $producto['nombre']; ?></li>
                        </ol>
                    </nav>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3">
                            <i class="fas fa-tshirt me-2"></i><?php echo $producto['nombre']; ?>
                        </h1>
                        <div>
                            <a href="productos.php?action=edit&id=<?php echo $id_producto; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Editar
                            </a>
                            <a href="movimientos.php?filtro_producto=<?php echo urlencode($producto['nombre']); ?>" class="btn btn-info">
                                <i class="fas fa-exchange-alt me-2"></i>Ver Movimientos
                            </a>
                            <?php if ($can_delete): ?>
                                <form method="POST" action="detalle_producto.php?id=<?php echo $id_producto; ?>" class="d-inline" onsubmit="return confirm('¿Eliminar este producto y todas sus variantes?');">
                                    <input type="hidden" name="action" value="delete_producto">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash me-2"></i>Eliminar
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Columna de imágenes -->
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-images me-2"></i>Imágenes
                                </div>
                                <div class="card-body text-center">
                                    <?php if (!empty($fotos)): ?>
                                        <img id="mainImage" src="<?php echo '../../' . IMG_DIR; ?>productos/<?php echo htmlspecialchars($fotos[0]['nombre_archivo']); ?>" 
                                             class="img-fluid product-image mb-3" alt="<?php echo $producto['nombre']; ?>"
                                             onerror="this.src='https://via.placeholder.com/400?text=Sin+imagen'">
                                        
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <?php foreach ($fotos as $index => $foto): ?>
                                                   <img src="<?php echo '../../' . IMG_DIR; ?>productos/<?php echo htmlspecialchars($foto['nombre_archivo']); ?>" 
                                                     class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                                     onclick="changeImage(this, '<?php echo htmlspecialchars($foto['nombre_archivo']); ?>')"
                                                     alt="Miniatura <?php echo $index + 1; ?>"
                                                     onerror="this.src='https://via.placeholder.com/80?text=Error'">
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-image me-2"></i>
                                            No hay imágenes para este producto
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Información básica -->
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-info-circle me-2"></i>Información Básica
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <th>Código:</th>
                                            <td><?php echo $producto['codigo']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Departamento:</th>
                                            <td><?php echo $producto['departamento_nombre']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Marca:</th>
                                            <td><?php echo $producto['marca_nombre'] ?? 'No especificada'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Tipo de Ropa:</th>
                                            <td><?php echo $producto['tipo_ropa_nombre'] ?? 'No especificado'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Precio Compra:</th>
                                            <td>Q<?php echo number_format($producto['precio_compra'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Precio Venta:</th>
                                            <td class="fw-bold">Q<?php echo number_format($producto['precio_venta'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Etiquetas:</th>
                                            <td>
                                                <?php
                                                $etiqueta = isset($producto['etiqueta']) ? trim($producto['etiqueta']) : '';
                                                if ($etiqueta !== ''):
                                                ?>
                                                <span class="badge bg-info me-1"><?php echo $etiqueta; ?></span>
                                                <?php else: ?>
                                                <span class="text-muted">Sin etiqueta</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Fecha Creación:</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($producto['fecha_creacion'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Columna de detalles -->
                        <div class="col-md-8">
                            <!-- Descripción -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-align-left me-2"></i>Descripción
                                </div>
                                <div class="card-body">
                                    <p><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                                </div>
                            </div>
                            
                            <!-- Variantes -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-palette me-2"></i>Variantes
                                    </div>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaVariante">
                                        <i class="fas fa-plus me-1"></i>Nueva Variante
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($variantes)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Color</th>
                                                        <th>Talla</th>
                                                        <th>SKU</th>
                                                        <th>Stock Tienda</th>
                                                        <th>Stock Bodega</th>
                                                        <th>Total</th>
                                                        <th>Precio Venta</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $total_tienda = 0;
                                                    $total_bodega = 0;
                                                    foreach ($variantes as $variante): 
                                                        $total_tienda += $variante['stock_tienda'];
                                                        $total_bodega += $variante['stock_bodega'];
                                                    ?>
                                                    <tr class="variante-card">
                                                        <td>
                                                            <span class="badge" style="background-color: <?php echo $variante['color']; ?>; color: white;">
                                                                <?php echo $variante['color']; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $variante['talla']; ?></td>
                                                        <td><code><?php echo $variante['sku']; ?></code></td>
                                                        <td>
                                                            <span class="badge <?php echo $variante['stock_tienda'] > 0 ? 'bg-success' : 'bg-danger'; ?> stock-badge">
                                                                <?php echo $variante['stock_tienda']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $variante['stock_bodega'] > 0 ? 'bg-info' : 'bg-danger'; ?> stock-badge">
                                                                <?php echo $variante['stock_bodega']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-dark stock-badge">
                                                                <?php echo $variante['stock_tienda'] + $variante['stock_bodega']; ?>
                                                            </span>
                                                        </td>
                                                        <td class="fw-bold">Q<?php echo number_format($variante['precio_final'], 2); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-warning" onclick="editarVariante(this)" 
                                                                    data-id="<?php echo $variante['id_variante']; ?>"
                                                                    data-color="<?php echo htmlspecialchars($variante['color']); ?>"
                                                                    data-talla="<?php echo htmlspecialchars($variante['talla']); ?>"
                                                                    data-stock-tienda="<?php echo $variante['stock_tienda']; ?>"
                                                                    data-stock-bodega="<?php echo $variante['stock_bodega']; ?>"
                                                                    data-precio-venta="<?php echo $variante['precio_venta']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <?php if ($auth->hasPermission('inventario')): ?>
                                                            <button class="btn btn-sm btn-info" onclick="crearMovimiento(<?php echo $variante['id_variante']; ?>, '<?php echo $producto['nombre']; ?> - <?php echo $variante['color']; ?>')">
                                                                <i class="fas fa-exchange-alt"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                            <?php if ($can_delete): ?>
                                                                <form method="POST" action="detalle_producto.php?id=<?php echo $id_producto; ?>" class="d-inline" onsubmit="return confirm('¿Eliminar esta variante?');">
                                                                    <input type="hidden" name="action" value="delete_variante">
                                                                    <input type="hidden" name="id_variante" value="<?php echo $variante['id_variante']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr class="table-secondary">
                                                        <th colspan="3">TOTALES</th>
                                                        <th><?php echo $total_tienda; ?></th>
                                                        <th><?php echo $total_bodega; ?></th>
                                                        <th><?php echo $total_tienda + $total_bodega; ?></th>
                                                        <th colspan="2"></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            No hay variantes registradas para este producto
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Últimos movimientos -->
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-history me-2"></i>Últimos Movimientos
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($movimientos)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Fecha</th>
                                                        <th>Variante</th>
                                                        <th>Tipo</th>
                                                        <th>Cantidad</th>
                                                        <th>Ubicación</th>
                                                        <th>Usuario</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($movimientos as $mov): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-info"><?php echo $mov['color']; ?></span>
                                                            <span class="badge bg-secondary"><?php echo $mov['talla']; ?></span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $clase = '';
                                                            switch($mov['tipo_movimiento']) {
                                                                case 'entrada': $clase = 'text-success'; break;
                                                                case 'salida': $clase = 'text-danger'; break;
                                                                default: $clase = 'text-warning';
                                                            }
                                                            ?>
                                                            <span class="<?php echo $clase; ?>">
                                                                <?php echo ucfirst($mov['tipo_movimiento']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="fw-bold <?php echo $mov['tipo_movimiento'] == 'entrada' ? 'text-success' : 'text-danger'; ?>">
                                                                <?php echo $mov['tipo_movimiento'] == 'entrada' ? '+' : '-'; ?>
                                                                <?php echo $mov['cantidad']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-secondary"><?php echo ucfirst($mov['ubicacion']); ?></span>
                                                        </td>
                                                        <td><?php echo $mov['usuario_nombre']; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            No hay movimientos registrados para este producto
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para crear nueva variante -->
    <div class="modal fade" id="modalNuevaVariante" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Variante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="detalle_producto.php?id=<?php echo $id_producto; ?>">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_variante">
                        <input type="hidden" name="id_producto_raiz" value="<?php echo $id_producto; ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Color *</label>
                                <input type="text" class="form-control" name="color" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Talla *</label>
                                <input type="text" class="form-control" name="talla" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Tienda *</label>
                                <input type="number" class="form-control" name="stock_tienda" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Bodega *</label>
                                <input type="number" class="form-control" name="stock_bodega" min="0" required>
                            </div>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="precio_especial" name="precio_especial">
                            <label class="form-check-label" for="precio_especial">
                                Usar precio especial
                            </label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Precio Venta</label>
                            <input type="number" class="form-control" id="precio_venta" name="precio_venta" step="0.01" min="0" disabled>
                            <small class="text-muted">Si no se activa, se usa el precio del producto raiz.</small>
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

    <!-- Modal para crear movimiento rápido -->
    <div class="modal fade" id="modalMovimientoRapido">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Movimiento Rápido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="movimientos.php">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="crear_movimiento">
                        <input type="hidden" name="id_variante" id="mov_id_variante">
                        
                        <div class="mb-3">
                            <label class="form-label">Producto</label>
                            <input type="text" class="form-control" id="mov_producto_nombre" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Movimiento *</label>
                            <select class="form-select" name="tipo_movimiento" required>
                                <option value="entrada">Entrada</option>
                                <option value="salida">Salida</option>
                                <option value="ajuste_entrada">Ajuste Entrada</option>
                                <option value="ajuste_salida">Ajuste Salida</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Cantidad *</label>
                            <input type="number" class="form-control" name="cantidad" min="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ubicación *</label>
                            <select class="form-select" name="ubicacion" required>
                                <option value="tienda">Tienda</option>
                                <option value="bodega">Bodega</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Motivo *</label>
                            <input type="text" class="form-control" name="motivo" required placeholder="Ej: Ajuste de inventario">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar variante -->
    <div class="modal fade" id="modalEditarVariante" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Variante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="detalle_producto.php?id=<?php echo $id_producto; ?>">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_variante">
                        <input type="hidden" name="id_variante" id="edit_id_variante">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Color *</label>
                                <input type="text" class="form-control" id="edit_color" name="color" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Talla *</label>
                                <input type="text" class="form-control" id="edit_talla" name="talla" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Tienda *</label>
                                <input type="number" class="form-control" id="edit_stock_tienda" name="stock_tienda" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Bodega *</label>
                                <input type="number" class="form-control" id="edit_stock_bodega" name="stock_bodega" min="0" required>
                            </div>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="edit_precio_especial" name="precio_especial">
                            <label class="form-check-label" for="edit_precio_especial">
                                Usar precio especial
                            </label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Precio Venta</label>
                            <input type="number" class="form-control" id="edit_precio_venta" name="precio_venta" step="0.01" min="0" disabled>
                            <small class="text-muted">Si no se activa, se usa el precio del producto raíz.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function changeImage(element, filename) {
        // Cambiar imagen principal
        const imgPath = '<?php echo '../../' . IMG_DIR; ?>productos/' + filename;
        document.getElementById('mainImage').src = imgPath;
        document.getElementById('mainImage').onerror = function() {
            this.src = 'https://via.placeholder.com/400?text=Sin+imagen';
        };
        
        // Actualizar thumbnails activos
        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.classList.remove('active');
        });
        element.classList.add('active');
    }
    
    function crearMovimiento(idVariante, nombreProducto) {
        document.getElementById('mov_id_variante').value = idVariante;
        document.getElementById('mov_producto_nombre').value = nombreProducto;
        
        var modal = new bootstrap.Modal(document.getElementById('modalMovimientoRapido'));
        modal.show();
    }
    
    function editarVariante(button) {
        // Obtener datos del botón
        const id = button.getAttribute('data-id');
        const color = button.getAttribute('data-color');
        const talla = button.getAttribute('data-talla');
        const stockTienda = button.getAttribute('data-stock-tienda');
        const stockBodega = button.getAttribute('data-stock-bodega');
        const precioVenta = button.getAttribute('data-precio-venta');
        
        // Llenar modal con datos
        document.getElementById('edit_id_variante').value = id;
        document.getElementById('edit_color').value = color;
        document.getElementById('edit_talla').value = talla;
        document.getElementById('edit_stock_tienda').value = stockTienda;
        document.getElementById('edit_stock_bodega').value = stockBodega;
        
        // Si precioVenta es null, no marcar checkbox; si tiene valor, marcar
        const precioCheckbox = document.getElementById('edit_precio_especial');
        const precioInput = document.getElementById('edit_precio_venta');
        if (precioVenta && precioVenta !== 'null' && precioVenta !== '') {
            precioCheckbox.checked = true;
            precioInput.value = precioVenta;
            precioInput.disabled = false;
        } else {
            precioCheckbox.checked = false;
            precioInput.value = '';
            precioInput.disabled = true;
        }
        
        // Mostrar modal
        var modal = new bootstrap.Modal(document.getElementById('modalEditarVariante'));
        modal.show();
    }

    const precioEspecialCheckbox = document.getElementById('precio_especial');
    const precioVentaInput = document.getElementById('precio_venta');
    if (precioEspecialCheckbox && precioVentaInput) {
        precioEspecialCheckbox.addEventListener('change', function () {
            precioVentaInput.disabled = !this.checked;
            precioVentaInput.required = this.checked;
            if (!this.checked) {
                precioVentaInput.value = '';
            }
        });
    }
    
    // Listener para el checkbox de edición
    const editPrecioCheckbox = document.getElementById('edit_precio_especial');
    const editPrecioInput = document.getElementById('edit_precio_venta');
    if (editPrecioCheckbox && editPrecioInput) {
        editPrecioCheckbox.addEventListener('change', function () {
            editPrecioInput.disabled = !this.checked;
            editPrecioInput.required = this.checked;
            if (!this.checked) {
                editPrecioInput.value = '';
            }
        });
    }
    
    // Cerrar modal automáticamente después de crear variante exitosamente
    document.addEventListener('DOMContentLoaded', function() {
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            // Esperar 2 segundos antes de cerrar el modal
            setTimeout(function() {
                const modalElement = document.getElementById('modalNuevaVariante');
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
                // Limpiar el formulario
                document.querySelectorAll('#modalNuevaVariante input[type="text"], #modalNuevaVariante input[type="number"]').forEach(field => {
                    field.value = '';
                });
                document.getElementById('precio_especial').checked = false;
                document.getElementById('precio_venta').disabled = true;
                document.getElementById('precio_venta').required = false;
            }, 2000);
        }
    });
    </script>
</body>
</html>