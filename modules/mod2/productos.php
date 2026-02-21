<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Verificar autenticación y permiso
if (!$auth->isLoggedIn()) {
    header('Location: ../../index.php');
    exit();
}
$auth->requirePermission('productos');

$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';

// Manejar operaciones CRUD
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';

// Obtener datos para formularios
$departamentos_query = "SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre";
$departamentos_stmt = $db->prepare($departamentos_query);
$departamentos_stmt->execute();
$departamentos = $departamentos_stmt->fetchAll(PDO::FETCH_ASSOC);

$marcas_query = "SELECT * FROM marcas WHERE activo = 1 ORDER BY nombre";
$marcas_stmt = $db->prepare($marcas_query);
$marcas_stmt->execute();
$marcas = $marcas_stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['action'] == 'create') {
        // Crear nuevo producto raíz
        $codigo = sanitize($_POST['codigo']);
        $nombre = sanitize($_POST['nombre']);
        $descripcion = sanitize($_POST['descripcion']);
        $id_departamento = $_POST['id_departamento'];
        $id_marca = !empty($_POST['id_marca']) ? $_POST['id_marca'] : null;
        $tipo_ropa = sanitize($_POST['tipo_ropa']);
        $precio_compra = $auth->isAdmin() ? $_POST['precio_compra'] : 0;
        $precio_venta = $_POST['precio_venta'];
        $etiqueta = $_POST['etiqueta'];
        $es_ajitos = isset($_POST['es_ajitos']) ? 1 : 0;
        
        // Validar que el código sea único
        $check_query = "SELECT COUNT(*) as count FROM productos_raiz WHERE codigo = :codigo";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':codigo', $codigo);
        $check_stmt->execute();
        $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($check_result['count'] > 0) {
            $error = "El código '{$codigo}' ya existe. Por favor utiliza un código diferente.";
        } else {
            $query = "INSERT INTO productos_raiz (codigo, nombre, descripcion, id_departamento, id_marca, 
                      tipo_ropa, precio_compra, precio_venta, etiqueta, es_ajitos, activo) 
                      VALUES (:codigo, :nombre, :descripcion, :id_departamento, :id_marca, 
                      :tipo_ropa, :precio_compra, :precio_venta, :etiqueta, :es_ajitos, 1)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':codigo', $codigo);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':id_departamento', $id_departamento);
            $stmt->bindParam(':id_marca', $id_marca);
            $stmt->bindParam(':tipo_ropa', $tipo_ropa);
            $stmt->bindParam(':precio_compra', $precio_compra);
            $stmt->bindParam(':precio_venta', $precio_venta);
            $stmt->bindParam(':etiqueta', $etiqueta);
            $stmt->bindParam(':es_ajitos', $es_ajitos);
            
            if ($stmt->execute()) {
            $id_raiz = $db->lastInsertId();
            
            // Si la etiqueta es "oferta", crear un registro en la tabla ofertas
            if ($etiqueta == 'oferta') {
                $porcentaje = isset($_POST['porcentaje_descuento']) ? (float)$_POST['porcentaje_descuento'] : 10;
                $porcentaje = max(0, min(100, $porcentaje)); // Validar que esté entre 0 y 100
                
                $oferta_query = "INSERT INTO ofertas (id_producto_raiz, porcentaje_descuento, fecha_inicio, fecha_fin, activo) 
                                VALUES (:id_producto_raiz, :porcentaje, DATE(NOW()), DATE_ADD(DATE(NOW()), INTERVAL 30 DAY), 1)";
                $oferta_stmt = $db->prepare($oferta_query);
                $oferta_stmt->bindParam(':id_producto_raiz', $id_raiz);
                $oferta_stmt->bindParam(':porcentaje', $porcentaje);
                
                if (!$oferta_stmt->execute()) {
                    // Log del error pero continuar
                    error_log("Error al crear oferta para producto: " . $id_raiz);
                }
            }
            
            // Manejar subida de fotos
            if (!empty($_FILES['fotos']['name'][0])) {
                $fotos_count = count($_FILES['fotos']['name']);
                $foto_principal = isset($_POST['foto_principal']) ? (int)$_POST['foto_principal'] : 0;
                
                for ($i = 0; $i < $fotos_count; $i++) {
                    if ($_FILES['fotos']['error'][$i] == 0) {
                        $upload = uploadImage([
                            'name' => $_FILES['fotos']['name'][$i],
                            'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                            'size' => $_FILES['fotos']['size'][$i]
                        ], 'productos');
                        
                        if ($upload['success']) {
                            $es_principal = ($i == $foto_principal) ? 1 : 0;
                            
                            $foto_query = "INSERT INTO productos_raiz_fotos (id_producto_raiz, nombre_archivo, es_principal, orden) 
                                          VALUES (:id_raiz, :nombre_archivo, :es_principal, :orden)";
                            $foto_stmt = $db->prepare($foto_query);
                            $foto_stmt->bindParam(':id_raiz', $id_raiz);
                            $foto_stmt->bindParam(':nombre_archivo', $upload['filename']);
                            $foto_stmt->bindParam(':es_principal', $es_principal);
                            $foto_stmt->bindParam(':orden', $i);
                            $foto_stmt->execute();
                        }
                    }
                }
            }
            
            $success = "Producto creado exitosamente. ID: " . $id_raiz;
            } else {
                $error = "Error al crear producto. Intenta de nuevo.";
            }
        }
    }
    elseif ($_POST['action'] == 'update') {
        // Actualizar producto raíz
        $id_raiz = $_POST['id_raiz'];
        $nombre = sanitize($_POST['nombre']);
        $descripcion = sanitize($_POST['descripcion']);
        $id_departamento = $_POST['id_departamento'];
        $id_marca = !empty($_POST['id_marca']) ? $_POST['id_marca'] : null;
        $tipo_ropa = sanitize($_POST['tipo_ropa']);
        $precio_venta = $_POST['precio_venta'];
        $etiqueta = $_POST['etiqueta'];
        $es_ajitos = isset($_POST['es_ajitos']) ? 1 : 0;
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Solo admin puede ver/editar precio de compra
        if ($auth->isAdmin()) {
            $precio_compra = $_POST['precio_compra'];
            $query = "UPDATE productos_raiz SET nombre = :nombre, descripcion = :descripcion, 
                      id_departamento = :id_departamento, id_marca = :id_marca, tipo_ropa = :tipo_ropa,
                      precio_compra = :precio_compra, precio_venta = :precio_venta, etiqueta = :etiqueta,
                      es_ajitos = :es_ajitos, activo = :activo WHERE id_raiz = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':precio_compra', $precio_compra);
        } else {
            $query = "UPDATE productos_raiz SET nombre = :nombre, descripcion = :descripcion, 
                      id_departamento = :id_departamento, id_marca = :id_marca, tipo_ropa = :tipo_ropa,
                      precio_venta = :precio_venta, etiqueta = :etiqueta, es_ajitos = :es_ajitos,
                      activo = :activo WHERE id_raiz = :id";
            
            $stmt = $db->prepare($query);
        }
        
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':id_departamento', $id_departamento);
        $stmt->bindParam(':id_marca', $id_marca);
        $stmt->bindParam(':tipo_ropa', $tipo_ropa);
        $stmt->bindParam(':precio_venta', $precio_venta);
        $stmt->bindParam(':etiqueta', $etiqueta);
        $stmt->bindParam(':es_ajitos', $es_ajitos);
        $stmt->bindParam(':activo', $activo);
        $stmt->bindParam(':id', $id_raiz);
        
        if ($stmt->execute()) {
            // Obtener la etiqueta anterior del producto
            $check_old_etiqueta = "SELECT etiqueta FROM productos_raiz WHERE id_raiz = :id";
            $check_stmt = $db->prepare($check_old_etiqueta);
            $check_stmt->bindParam(':id', $id_raiz);
            $check_stmt->execute();
            $old_product = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $old_etiqueta = $old_product['etiqueta'];
            
            // Gestionar la tabla ofertas según cambios de etiqueta
            if ($etiqueta == 'oferta') {
                $porcentaje = isset($_POST['porcentaje_descuento']) ? (float)$_POST['porcentaje_descuento'] : 10;
                $porcentaje = max(0, min(100, $porcentaje)); // Validar que esté entre 0 y 100
                
                if ($old_etiqueta != 'oferta') {
                    // Cambió a oferta: crear registro en ofertas
                    $oferta_query = "INSERT INTO ofertas (id_producto_raiz, porcentaje_descuento, fecha_inicio, fecha_fin, activo) 
                                    VALUES (:id_producto_raiz, :porcentaje, DATE(NOW()), DATE_ADD(DATE(NOW()), INTERVAL 30 DAY), 1)";
                    $oferta_stmt = $db->prepare($oferta_query);
                    $oferta_stmt->bindParam(':id_producto_raiz', $id_raiz);
                    $oferta_stmt->bindParam(':porcentaje', $porcentaje);
                    
                    if (!$oferta_stmt->execute()) {
                        error_log("Error al crear oferta para producto: " . $id_raiz);
                    }
                } else {
                    // Ya era oferta: actualizar porcentaje
                    $oferta_query = "UPDATE ofertas SET porcentaje_descuento = :porcentaje 
                                    WHERE id_producto_raiz = :id_producto_raiz";
                    $oferta_stmt = $db->prepare($oferta_query);
                    $oferta_stmt->bindParam(':id_producto_raiz', $id_raiz);
                    $oferta_stmt->bindParam(':porcentaje', $porcentaje);
                    
                    if (!$oferta_stmt->execute()) {
                        error_log("Error al actualizar oferta para producto: " . $id_raiz);
                    }
                }
            } elseif ($etiqueta != 'oferta' && $old_etiqueta == 'oferta') {
                // Cambió de oferta a otra etiqueta: eliminar registro de ofertas
                $delete_oferta = "DELETE FROM ofertas WHERE id_producto_raiz = :id_producto_raiz";
                $delete_stmt = $db->prepare($delete_oferta);
                $delete_stmt->bindParam(':id_producto_raiz', $id_raiz);
                
                if (!$delete_stmt->execute()) {
                    error_log("Error al eliminar oferta para producto: " . $id_raiz);
                }
            }
            
            // Manejar nuevas fotos
            if (!empty($_FILES['nuevas_fotos']['name'][0])) {
                $fotos_count = count($_FILES['nuevas_fotos']['name']);
                
                for ($i = 0; $i < $fotos_count; $i++) {
                    if ($_FILES['nuevas_fotos']['error'][$i] == 0) {
                        $upload = uploadImage([
                            'name' => $_FILES['nuevas_fotos']['name'][$i],
                            'tmp_name' => $_FILES['nuevas_fotos']['tmp_name'][$i],
                            'size' => $_FILES['nuevas_fotos']['size'][$i]
                        ], 'productos');
                        
                        if ($upload['success']) {
                            $foto_query = "INSERT INTO productos_raiz_fotos (id_producto_raiz, nombre_archivo, es_principal, orden) 
                                          VALUES (:id_raiz, :nombre_archivo, 0, :orden)";
                            $foto_stmt = $db->prepare($foto_query);
                            $foto_stmt->bindParam(':id_raiz', $id_raiz);
                            $foto_stmt->bindParam(':nombre_archivo', $upload['filename']);
                            $foto_stmt->bindParam(':orden', $i);
                            $foto_stmt->execute();
                        }
                    }
                }
            }
            
            $success = "Producto actualizado exitosamente.";
        } else {
            $error = "Error al actualizar producto.";
        }
    }
    elseif ($_POST['action'] == 'delete_photo') {
        // Eliminar foto
        $id_foto = $_POST['id_foto'];
        $id_raiz = $_POST['id_raiz'];
        
        // Obtener nombre del archivo
        $query = "SELECT nombre_archivo FROM productos_raiz_fotos WHERE id_foto = :id_foto";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_foto', $id_foto);
        $stmt->execute();
        $foto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Eliminar de la base de datos
        $delete_query = "DELETE FROM productos_raiz_fotos WHERE id_foto = :id_foto";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id_foto', $id_foto);
        
        if ($delete_stmt->execute()) {
            // Eliminar archivo físico
            $file_path = UPLOAD_DIR . 'productos/' . $foto['nombre_archivo'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Si era la foto principal, asignar otra como principal
            $check_query = "SELECT COUNT(*) as total FROM productos_raiz_fotos WHERE id_producto_raiz = :id_raiz AND es_principal = 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':id_raiz', $id_raiz);
            $check_stmt->execute();
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['total'] == 0) {
                $update_query = "UPDATE productos_raiz_fotos SET es_principal = 1 
                                WHERE id_foto = (SELECT MIN(id_foto) FROM productos_raiz_fotos WHERE id_producto_raiz = :id_raiz)";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':id_raiz', $id_raiz);
                $update_stmt->execute();
            }
            
            $success = "Foto eliminada exitosamente.";
        } else {
            $error = "Error al eliminar foto.";
        }
    }
    elseif ($_POST['action'] == 'set_main_photo') {
        // Establecer foto principal
        $id_foto = $_POST['id_foto'];
        $id_raiz = $_POST['id_raiz'];
        
        // Quitar principal de todas las fotos
        $reset_query = "UPDATE productos_raiz_fotos SET es_principal = 0 WHERE id_producto_raiz = :id_raiz";
        $reset_stmt = $db->prepare($reset_query);
        $reset_stmt->bindParam(':id_raiz', $id_raiz);
        $reset_stmt->execute();
        
        // Establecer nueva foto principal
        $update_query = "UPDATE productos_raiz_fotos SET es_principal = 1 WHERE id_foto = :id_foto";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id_foto', $id_foto);
        
        if ($update_stmt->execute()) {
            $success = "Foto principal actualizada.";
        } else {
            $error = "Error al actualizar foto principal.";
        }
    }
}

// Obtener productos con búsqueda
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filter_depto = isset($_GET['departamento']) ? $_GET['departamento'] : '';
$filter_ajitos = isset($_GET['ajitos']) ? $_GET['ajitos'] : '';

$query = "SELECT pr.*, d.nombre as departamento_nombre, m.nombre as marca_nombre 
          FROM productos_raiz pr 
          LEFT JOIN departamentos d ON pr.id_departamento = d.id_departamento 
          LEFT JOIN marcas m ON pr.id_marca = m.id_marca 
          WHERE 1=1";

if ($search) {
    $query .= " AND (pr.nombre LIKE :search OR pr.codigo LIKE :search OR pr.descripcion LIKE :search)";
}
if ($filter_depto) {
    $query .= " AND pr.id_departamento = :departamento";
}
if ($filter_ajitos === '1') {
    $query .= " AND pr.es_ajitos = 1";
} elseif ($filter_ajitos === '0') {
    $query .= " AND pr.es_ajitos = 0";
}

$query .= " ORDER BY pr.id_raiz DESC";

$stmt = $db->prepare($query);

if ($search) {
    $search_term = "%$search%";
    $stmt->bindParam(':search', $search_term);
}
if ($filter_depto) {
    $stmt->bindParam(':departamento', $filter_depto);
}

$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener producto específico para editar
$producto_edit = null;
$producto_fotos = [];
$porcentaje_descuento_actual = 10; // Valor por defecto

if ($action == 'edit' && $id) {
    $query = "SELECT * FROM productos_raiz WHERE id_raiz = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $producto_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($producto_edit) {
        $fotos_query = "SELECT * FROM productos_raiz_fotos WHERE id_producto_raiz = :id ORDER BY orden";
        $fotos_stmt = $db->prepare($fotos_query);
        $fotos_stmt->bindParam(':id', $id);
        $fotos_stmt->execute();
        $producto_fotos = $fotos_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener porcentaje de descuento si es oferta
        if ($producto_edit['etiqueta'] == 'oferta') {
            $oferta_query = "SELECT porcentaje_descuento FROM ofertas WHERE id_producto_raiz = :id AND activo = 1";
            $oferta_stmt = $db->prepare($oferta_query);
            $oferta_stmt->bindParam(':id', $id);
            $oferta_stmt->execute();
            $oferta = $oferta_stmt->fetch(PDO::FETCH_ASSOC);
            if ($oferta) {
                $porcentaje_descuento_actual = $oferta['porcentaje_descuento'];
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
    <title>Productos - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }
        .etiqueta-oferta {
            background: #ff6b6b;
            color: white;
        }
        .etiqueta-nuevo {
            background: #1abc9c;
            color: white;
        }
        .etiqueta-reingreso {
            background: #3498db;
            color: white;
        }
        .photo-thumbnail {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
            border: 3px solid transparent;
        }
        .photo-thumbnail.active {
            border-color: #1abc9c;
        }
        .photo-actions {
            position: absolute;
            top: 5px;
            right: 5px;
            display: none;
        }
        .photo-container:hover .photo-actions {
            display: block;
        }
    </style>
</head>
<body>
    <?php if (!$embedded): ?>
    <?php include '../../includes/header.php'; ?>
    <?php endif; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <main class="col-12 px-2">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Productos Raíz</h1>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                            <i class="fas fa-plus me-1"></i> Nuevo Producto
                        </button>
                        <a href="variantes.php" class="btn btn-secondary">
                            <i class="fas fa-layer-group me-1"></i> Variantes
                        </a>
                    </div>
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
                                       placeholder="Buscar por nombre, código o descripción..." 
                                       value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="departamento">
                                    <option value="">Todos los departamentos</option>
                                    <?php foreach ($departamentos as $depto): ?>
                                        <option value="<?php echo $depto['id_departamento']; ?>" 
                                            <?php echo $filter_depto == $depto['id_departamento'] ? 'selected' : ''; ?>>
                                            <?php echo $depto['nombre']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="ajitos">
                                    <option value="">Todos los productos</option>
                                    <option value="1" <?php echo $filter_ajitos === '1' ? 'selected' : ''; ?>>Solo Ajitos Kids</option>
                                    <option value="0" <?php echo $filter_ajitos === '0' ? 'selected' : ''; ?>>Solo MI&MI Store</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i> Filtrar
                                </button>
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
                                        <th>Código</th>
                                        <th>Imagen</th>
                                        <th>Nombre</th>
                                        <th>Departamento</th>
                                        <th>Marca</th>
                                        <th>Precio Venta</th>
                                        <th>Etiqueta</th>
                                        <th>Ajitos</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos as $producto): 
                                        // Obtener foto principal
                                        $foto_query = "SELECT nombre_archivo FROM productos_raiz_fotos 
                                                      WHERE id_producto_raiz = :id AND es_principal = 1 LIMIT 1";
                                        $foto_stmt = $db->prepare($foto_query);
                                        $foto_stmt->bindParam(':id', $producto['id_raiz']);
                                        $foto_stmt->execute();
                                        $foto = $foto_stmt->fetch(PDO::FETCH_ASSOC);
                                        $imagen = $foto ? IMG_DIR . 'productos/' . $foto['nombre_archivo'] : 'https://via.placeholder.com/80';
                                    ?>
                                    <tr>
                                        <td><?php echo $producto['id_raiz']; ?></td>
                                        <td><strong><?php echo $producto['codigo']; ?></strong></td>
                                        <td>
                                            <img src="<?php echo $imagen; ?>" alt="Producto" class="product-image">
                                        </td>
                                        <td><?php echo $producto['nombre']; ?></td>
                                        <td><?php echo $producto['departamento_nombre']; ?></td>
                                        <td><?php echo $producto['marca_nombre'] ?: 'N/A'; ?></td>
                                        <td><?php echo formatMoney($producto['precio_venta']); ?></td>
                                        <td>
                                            <span class="badge etiqueta-<?php echo $producto['etiqueta']; ?>">
                                                <?php echo ucfirst($producto['etiqueta']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($producto['es_ajitos']): ?>
                                                <span class="badge bg-danger">Ajitos Kids</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $producto['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $producto['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="productos.php?action=edit&id=<?php echo $producto['id_raiz']; ?>" 
                                               class="btn btn-sm btn-warning" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="variantes.php?producto=<?php echo $producto['id_raiz']; ?>" 
                                               class="btn btn-sm btn-info" title="Variantes">
                                                <i class="fas fa-layer-group"></i>
                                            </a>
                                            <button class="btn btn-sm btn-secondary" 
                                                    onclick="viewDetails(<?php echo $producto['id_raiz']; ?>)" 
                                                    title="Detalles">
                                                <i class="fas fa-eye"></i>
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

    <!-- Modal para crear producto -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Producto Raíz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="codigo" class="form-label">Código *</label>
                                <input type="text" class="form-control" id="codigo" name="codigo" required>
                                <small class="text-muted">Código único del producto</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="id_departamento" class="form-label">Departamento *</label>
                                <select class="form-select" id="id_departamento" name="id_departamento" required>
                                    <option value="">Seleccionar departamento</option>
                                    <?php foreach ($departamentos as $depto): ?>
                                        <option value="<?php echo $depto['id_departamento']; ?>">
                                            <?php echo $depto['nombre']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="id_marca" class="form-label">Marca</label>
                                <select class="form-select" id="id_marca" name="id_marca">
                                    <option value="">Sin marca</option>
                                    <?php foreach ($marcas as $marca): ?>
                                        <option value="<?php echo $marca['id_marca']; ?>">
                                            <?php echo $marca['nombre']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tipo_ropa" class="form-label">Tipo de Ropa</label>
                                <input type="text" class="form-control" id="tipo_ropa" name="tipo_ropa">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="etiqueta" class="form-label">Etiqueta *</label>
                                <select class="form-select" id="etiqueta" name="etiqueta" required onchange="toggleDescuentoField()">
                                    <option value="nuevo">Nuevo</option>
                                    <option value="oferta">Oferta</option>
                                    <option value="reingreso">Reingreso</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="es_ajitos" name="es_ajitos">
                                    <label class="form-check-label" for="es_ajitos">
                                        Es Ajitos Kids
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row" id="descuento-field" style="display: none;">
                            <div class="col-md-6 mb-3">
                                <label for="porcentaje_descuento" class="form-label">Porcentaje de Descuento (%)</label>
                                <input type="number" class="form-control" id="porcentaje_descuento" name="porcentaje_descuento" 
                                       step="0.01" min="0" max="100" value="10" placeholder="Ej. 10">
                                <small class="text-muted">Ingresa el porcentaje de descuento (0-100)</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <?php if ($auth->isAdmin()): ?>
                            <div class="col-md-6 mb-3">
                                <label for="precio_compra" class="form-label">Precio de Compra *</label>
                                <input type="number" class="form-control" id="precio_compra" name="precio_compra" 
                                       step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="precio_venta" class="form-label">Precio de Venta *</label>
                                <input type="number" class="form-control" id="precio_venta" name="precio_venta" 
                                       step="0.01" min="0" required>
                            </div>
                            <?php else: ?>
                            <div class="col-md-12 mb-3">
                                <label for="precio_venta" class="form-label">Precio de Venta *</label>
                                <input type="number" class="form-control" id="precio_venta" name="precio_venta" 
                                       step="0.01" min="0" required>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="fotos" class="form-label">Fotos del Producto *</label>
                            <input type="file" class="form-control" id="fotos" name="fotos[]" 
                                   multiple accept="image/*" required>
                            <small class="text-muted">Mínimo 1 foto, máximo 5 fotos. Formatos: JPG, PNG, GIF</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Seleccionar Foto Principal</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="foto_principal" id="foto0" value="0" checked>
                                <label class="form-check-label" for="foto0">
                                    Primera foto
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="foto_principal" id="foto1" value="1">
                                <label class="form-check-label" for="foto1">
                                    Segunda foto
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="foto_principal" id="foto2" value="2">
                                <label class="form-check-label" for="foto2">
                                    Tercera foto
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para editar producto -->
    <?php if ($producto_edit): ?>
    <div class="modal fade show" id="editModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Producto: <?php echo $producto_edit['nombre']; ?></h5>
                    <a href="productos.php" class="btn-close"></a>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_raiz" value="<?php echo $producto_edit['id_raiz']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Información del producto -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Código</label>
                                        <input type="text" class="form-control" value="<?php echo $producto_edit['codigo']; ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_nombre" class="form-label">Nombre *</label>
                                        <input type="text" class="form-control" id="edit_nombre" name="nombre" 
                                               value="<?php echo $producto_edit['nombre']; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_id_departamento" class="form-label">Departamento *</label>
                                        <select class="form-select" id="edit_id_departamento" name="id_departamento" required>
                                            <?php foreach ($departamentos as $depto): ?>
                                                <option value="<?php echo $depto['id_departamento']; ?>" 
                                                    <?php echo $depto['id_departamento'] == $producto_edit['id_departamento'] ? 'selected' : ''; ?>>
                                                    <?php echo $depto['nombre']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_id_marca" class="form-label">Marca</label>
                                        <select class="form-select" id="edit_id_marca" name="id_marca">
                                            <option value="">Sin marca</option>
                                            <?php foreach ($marcas as $marca): ?>
                                                <option value="<?php echo $marca['id_marca']; ?>" 
                                                    <?php echo $marca['id_marca'] == $producto_edit['id_marca'] ? 'selected' : ''; ?>>
                                                    <?php echo $marca['nombre']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_tipo_ropa" class="form-label">Tipo de Ropa</label>
                                        <input type="text" class="form-control" id="edit_tipo_ropa" name="tipo_ropa" 
                                               value="<?php echo $producto_edit['tipo_ropa']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_etiqueta" class="form-label">Etiqueta *</label>
                                        <select class="form-select" id="edit_etiqueta" name="etiqueta" required onchange="toggleDescuentoFieldEdit()">
                                            <option value="nuevo" <?php echo $producto_edit['etiqueta'] == 'nuevo' ? 'selected' : ''; ?>>Nuevo</option>
                                            <option value="oferta" <?php echo $producto_edit['etiqueta'] == 'oferta' ? 'selected' : ''; ?>>Oferta</option>
                                            <option value="reingreso" <?php echo $producto_edit['etiqueta'] == 'reingreso' ? 'selected' : ''; ?>>Reingreso</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row" id="descuento-field-edit" style="display: <?php echo $producto_edit['etiqueta'] == 'oferta' ? 'flex' : 'none'; ?>;">
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_porcentaje_descuento" class="form-label">Porcentaje de Descuento (%)</label>
                                        <input type="number" class="form-control" id="edit_porcentaje_descuento" name="porcentaje_descuento" 
                                               step="0.01" min="0" max="100" value="<?php echo $porcentaje_descuento_actual; ?>" placeholder="Ej. 10">
                                        <small class="text-muted">Ingresa el porcentaje de descuento (0-100)</small>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <?php if ($auth->isAdmin()): ?>
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_precio_compra" class="form-label">Precio de Compra *</label>
                                        <input type="number" class="form-control" id="edit_precio_compra" name="precio_compra" 
                                               value="<?php echo $producto_edit['precio_compra']; ?>" step="0.01" min="0" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="edit_precio_venta" class="form-label">Precio de Venta *</label>
                                        <input type="number" class="form-control" id="edit_precio_venta" name="precio_venta" 
                                               value="<?php echo $producto_edit['precio_venta']; ?>" step="0.01" min="0" required>
                                    </div>
                                    <?php else: ?>
                                    <div class="col-md-12 mb-3">
                                        <label for="edit_precio_venta" class="form-label">Precio de Venta *</label>
                                        <input type="number" class="form-control" id="edit_precio_venta" name="precio_venta" 
                                               value="<?php echo $producto_edit['precio_venta']; ?>" step="0.01" min="0" required>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="edit_descripcion" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="edit_descripcion" name="descripcion" rows="3"><?php echo $producto_edit['descripcion']; ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_es_ajitos" name="es_ajitos" 
                                                   <?php echo $producto_edit['es_ajitos'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="edit_es_ajitos">
                                                Es Ajitos Kids
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_activo" name="activo" 
                                                   <?php echo $producto_edit['activo'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="edit_activo">
                                                Producto activo
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Nuevas fotos -->
                                <div class="mb-3">
                                    <label for="nuevas_fotos" class="form-label">Agregar Más Fotos</label>
                                    <input type="file" class="form-control" id="nuevas_fotos" name="nuevas_fotos[]" 
                                           multiple accept="image/*">
                                    <small class="text-muted">Formatos: JPG, PNG, GIF</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <!-- Fotos existentes -->
                                <label class="form-label">Fotos Actuales</label>
                                <div class="row">
                                    <?php foreach ($producto_fotos as $foto): ?>
                                    <div class="col-md-4 mb-3 photo-container">
                                        <img src="<?php echo IMG_DIR . 'productos/' . $foto['nombre_archivo']; ?>" 
                                             class="photo-thumbnail <?php echo $foto['es_principal'] ? 'active' : ''; ?>"
                                             onclick="setMainPhoto(<?php echo $foto['id_foto']; ?>)"
                                             title="<?php echo $foto['es_principal'] ? 'Foto Principal' : 'Hacer Principal'; ?>">
                                        <div class="photo-actions">
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="deletePhoto(<?php echo $foto['id_foto']; ?>, <?php echo $producto_edit['id_raiz']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        <?php if ($foto['es_principal']): ?>
                                            <div class="badge bg-success mt-1">Principal</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="productos.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Actualizar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Formularios ocultos para acciones -->
    <form id="deletePhotoForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_photo">
        <input type="hidden" name="id_foto" id="delete_photo_id">
        <input type="hidden" name="id_raiz" id="delete_photo_raiz">
    </form>
    
    <form id="setMainPhotoForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="set_main_photo">
        <input type="hidden" name="id_foto" id="main_photo_id">
        <input type="hidden" name="id_raiz" id="main_photo_raiz">
    </form>

    <script>
        function deletePhoto(id_foto, id_raiz) {
            if (confirm('¿Está seguro de eliminar esta foto?')) {
                document.getElementById('delete_photo_id').value = id_foto;
                document.getElementById('delete_photo_raiz').value = id_raiz;
                document.getElementById('deletePhotoForm').submit();
            }
        }
        
        function setMainPhoto(id_foto) {
            document.getElementById('main_photo_id').value = id_foto;
            document.getElementById('main_photo_raiz').value = <?php echo $producto_edit ? $producto_edit['id_raiz'] : 0; ?>;
            document.getElementById('setMainPhotoForm').submit();
        }
        
        function viewDetails(id) {
            // Redirigir a página de detalles
            window.location.href = 'detalle_producto.php?id=' + id;
        }
        
        function toggleDescuentoField() {
            const etiqueta = document.getElementById('etiqueta').value;
            const descuentoField = document.getElementById('descuento-field');
            if (etiqueta === 'oferta') {
                descuentoField.style.display = 'flex';
            } else {
                descuentoField.style.display = 'none';
            }
        }
        
        function toggleDescuentoFieldEdit() {
            const etiqueta = document.getElementById('edit_etiqueta').value;
            const descuentoField = document.getElementById('descuento-field-edit');
            if (etiqueta === 'oferta') {
                descuentoField.style.display = 'flex';
            } else {
                descuentoField.style.display = 'none';
            }
        }
        
        <?php if ($producto_edit): ?>
        // Mostrar modal de edición automáticamente
        var editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
        <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>