<?php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

function bindParams(PDOStatement $stmt, array $params): void {
    foreach ($params as $key => $data) {
        $stmt->bindValue($key, $data[0], $data[1]);
    }
}

function buildUrl(array $overrides = [], array $removeKeys = []): string {
    $params = $_GET;
    foreach ($removeKeys as $key) {
        unset($params[$key]);
    }
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $query = http_build_query($params);
    return 'destacados.php' . ($query ? '?' . $query : '');
}

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 12;
$offset = ($pagina - 1) * $por_pagina;

$departamento = isset($_GET['departamento']) ? (int)$_GET['departamento'] : null;
$etiqueta = isset($_GET['etiqueta']) ? $_GET['etiqueta'] : null;
$ajitos = isset($_GET['ajitos']);
$precio_min = isset($_GET['precio_min']) ? (float)$_GET['precio_min'] : null;
$precio_max = isset($_GET['precio_max']) ? (float)$_GET['precio_max'] : null;
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'vendidos';

$etiquetas_validas = ['oferta', 'nuevo', 'reingreso'];
if (!in_array($etiqueta, $etiquetas_validas, true)) {
    $etiqueta = null;
}

$ordenes_validos = ['vendidos', 'nuevos', 'precio_asc', 'precio_desc', 'nombre_asc', 'nombre_desc'];
if (!in_array($orden, $ordenes_validos, true)) {
    $orden = 'vendidos';
}

if ($precio_min !== null && $precio_max !== null && $precio_min > $precio_max) {
    $temp = $precio_min;
    $precio_min = $precio_max;
    $precio_max = $temp;
}

$where = ['pr.activo = 1', 'd.activo = 1'];
$params = [];

if ($departamento) {
    $where[] = 'pr.id_departamento = :departamento';
    $params[':departamento'] = [$departamento, PDO::PARAM_INT];
}

if ($etiqueta) {
    $where[] = 'pr.etiqueta = :etiqueta';
    $params[':etiqueta'] = [$etiqueta, PDO::PARAM_STR];
}

if ($ajitos) {
    $where[] = 'pr.es_ajitos = 1';
}

if ($precio_min !== null && $precio_min !== '') {
    $where[] = 'pr.precio_venta >= :precio_min';
    $params[':precio_min'] = [$precio_min, PDO::PARAM_STR];
}

if ($precio_max !== null && $precio_max !== '') {
    $where[] = 'pr.precio_venta <= :precio_max';
    $params[':precio_max'] = [$precio_max, PDO::PARAM_STR];
}

$where_sql = implode(' AND ', $where);

switch ($orden) {
    case 'nuevos':
        $order_by = 'pr.fecha_creacion DESC';
        break;
    case 'precio_asc':
        $order_by = 'pr.precio_venta ASC';
        break;
    case 'precio_desc':
        $order_by = 'pr.precio_venta DESC';
        break;
    case 'nombre_asc':
        $order_by = 'pr.nombre ASC';
        break;
    case 'nombre_desc':
        $order_by = 'pr.nombre DESC';
        break;
    case 'vendidos':
    default:
        $order_by = 'vendidos_mes DESC, pr.fecha_creacion DESC';
        break;
}

// Obtener productos destacados (más vendidos en el último mes)
$query = "SELECT 
          pr.id_raiz,
          pr.codigo,
          pr.nombre,
          pr.descripcion,
          pr.precio_venta,
          pr.etiqueta,
          pr.es_ajitos,
          d.nombre as departamento_nombre,
          (SELECT nombre_archivo FROM productos_raiz_fotos 
           WHERE id_producto_raiz = pr.id_raiz AND es_principal = 1 LIMIT 1) as imagen_principal,
          COALESCE((
            SELECT SUM(fd.cantidad) 
            FROM factura_detalle fd
            INNER JOIN factura_cabecera fc ON fd.id_factura = fc.id_factura
            INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
            WHERE pv.id_producto_raiz = pr.id_raiz 
            AND fc.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          ), 0) as vendidos_mes
          FROM productos_raiz pr
          INNER JOIN departamentos d ON pr.id_departamento = d.id_departamento
          WHERE $where_sql
          ORDER BY $order_by
          LIMIT :offset, :por_pagina";

$stmt = $db->prepare($query);
bindParams($stmt, $params);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':por_pagina', $por_pagina, PDO::PARAM_INT);
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar total para paginación
$total_query = "SELECT COUNT(*) as total FROM (
                    SELECT pr.id_raiz,
                    COALESCE((
                        SELECT SUM(fd.cantidad) 
                        FROM factura_detalle fd
                        INNER JOIN factura_cabecera fc ON fd.id_factura = fc.id_factura
                        INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                        WHERE pv.id_producto_raiz = pr.id_raiz 
                        AND fc.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ), 0) as vendidos_mes
                    FROM productos_raiz pr
                    INNER JOIN departamentos d ON pr.id_departamento = d.id_departamento
                    WHERE $where_sql
                ) as destacados";
$total_stmt = $db->prepare($total_query);
bindParams($total_stmt, $params);
$total_stmt->execute();
$total = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total / $por_pagina);

// Obtener departamentos para filtro
$departamentos_query = "SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre";
$departamentos_stmt = $db->prepare($departamentos_query);
$departamentos_stmt->execute();
$departamentos = $departamentos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar si hay items en el carrito
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$carrito_count = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos Destacados - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <style>
        .product-card {
            transition: transform 0.3s;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            overflow: hidden;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        .price {
            color: #1abc9c;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .badge-oferta {
            background: #ff6b6b;
            color: white;
        }
        .badge-nuevo {
            background: #1abc9c;
            color: white;
        }
        .badge-reingreso {
            background: #3498db;
            color: white;
        }
        .badge-ajitos {
            background-color: var(--ajitos-primary-deep);
            color: var(--ajitos-text);
        }
        .pagination .page-item.active .page-link {
            background-color: #1abc9c;
            border-color: #1abc9c;
        }
        .filter-sidebar {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .vendidos-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(52, 152, 219, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
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
                <li class="breadcrumb-item active">Productos Destacados</li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Sidebar de filtros -->
            <div class="col-lg-3">
                <div class="filter-sidebar sticky-top" style="top: 20px;">
                    <h5 class="mb-4">Filtros</h5>
                    
                    <!-- Filtro por departamento -->
                    <div class="mb-4">
                        <h6 class="mb-3">Departamentos</h6>
                        <div class="list-group">
                            <a href="<?php echo buildUrl(['pagina' => 1], ['departamento']); ?>" class="list-group-item list-group-item-action <?php echo !isset($_GET['departamento']) ? 'active' : ''; ?>">
                                Todos los departamentos
                            </a>
                            <?php foreach ($departamentos as $depto): ?>
                            <a href="<?php echo buildUrl(['departamento' => $depto['id_departamento'], 'pagina' => 1]); ?>" 
                               class="list-group-item list-group-item-action <?php echo (isset($_GET['departamento']) && $_GET['departamento'] == $depto['id_departamento']) ? 'active' : ''; ?>">
                                <?php echo $depto['nombre']; ?>
                                <?php if ($depto['es_ajitos']): ?>
                                    <span class="badge badge-ajitos float-end">Ajitos</span>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Filtro por precio -->
                    <div class="mb-4">
                        <h6 class="mb-3">Rango de Precio</h6>
                        <div class="input-group mb-2">
                            <span class="input-group-text">Q</span>
                            <input type="number" class="form-control" placeholder="Mínimo" id="precioMin" value="<?php echo isset($_GET['precio_min']) ? htmlspecialchars($_GET['precio_min']) : ''; ?>">
                        </div>
                        <div class="input-group">
                            <span class="input-group-text">Q</span>
                            <input type="number" class="form-control" placeholder="Máximo" id="precioMax" value="<?php echo isset($_GET['precio_max']) ? htmlspecialchars($_GET['precio_max']) : ''; ?>">
                        </div>
                        <button class="btn btn-sm btn-primary mt-2 w-100" onclick="filtrarPrecio()">
                            <i class="fas fa-filter me-1"></i> Filtrar Precio
                        </button>
                    </div>
                    
                    <!-- Filtro por etiqueta -->
                    <div class="mb-4">
                        <h6 class="mb-3">Etiquetas</h6>
                        <div class="btn-group-vertical w-100">
                            <a href="<?php echo buildUrl(['pagina' => 1], ['etiqueta']); ?>" class="btn btn-outline-secondary text-start <?php echo !isset($_GET['etiqueta']) ? 'active' : ''; ?>">
                                Todas las etiquetas
                            </a>
                            <a href="<?php echo buildUrl(['etiqueta' => 'oferta', 'pagina' => 1]); ?>" class="btn btn-outline-danger text-start <?php echo (isset($_GET['etiqueta']) && $_GET['etiqueta'] == 'oferta') ? 'active' : ''; ?>">
                                <i class="fas fa-tag me-2"></i> Ofertas
                            </a>
                            <a href="<?php echo buildUrl(['etiqueta' => 'nuevo', 'pagina' => 1]); ?>" class="btn btn-outline-success text-start <?php echo (isset($_GET['etiqueta']) && $_GET['etiqueta'] == 'nuevo') ? 'active' : ''; ?>">
                                <i class="fas fa-star me-2"></i> Nuevos
                            </a>
                            <a href="<?php echo buildUrl(['etiqueta' => 'reingreso', 'pagina' => 1]); ?>" class="btn btn-outline-primary text-start <?php echo (isset($_GET['etiqueta']) && $_GET['etiqueta'] == 'reingreso') ? 'active' : ''; ?>">
                                <i class="fas fa-redo me-2"></i> Reingresos
                            </a>
                        </div>
                    </div>
                    
                    <!-- Filtro Ajitos Kids -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="filtroAjitos" 
                                   onchange="filtrarAjitos()" <?php echo isset($_GET['ajitos']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="filtroAjitos">
                                <span class="badge badge-ajitos">Ajitos Kids</span>
                                Solo productos para bebés
                            </label>
                        </div>
                    </div>
                    
                    <!-- Ordenar -->
                    <div class="mb-4">
                        <h6 class="mb-3">Ordenar por</h6>
                        <select class="form-select" onchange="ordenarProductos(this.value)">
                            <option value="vendidos" <?php echo $orden === 'vendidos' ? 'selected' : ''; ?>>Más vendidos</option>
                            <option value="nuevos" <?php echo $orden === 'nuevos' ? 'selected' : ''; ?>>Más nuevos</option>
                            <option value="precio_asc" <?php echo $orden === 'precio_asc' ? 'selected' : ''; ?>>Precio: menor a mayor</option>
                            <option value="precio_desc" <?php echo $orden === 'precio_desc' ? 'selected' : ''; ?>>Precio: mayor a menor</option>
                            <option value="nombre_asc" <?php echo $orden === 'nombre_asc' ? 'selected' : ''; ?>>Nombre A-Z</option>
                            <option value="nombre_desc" <?php echo $orden === 'nombre_desc' ? 'selected' : ''; ?>>Nombre Z-A</option>
                        </select>
                    </div>
                    
                    <!-- Volver a inicio -->
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-arrow-left me-2"></i> Volver al inicio
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Lista de productos -->
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">
                        <i class="fas fa-fire text-danger me-2"></i>
                        Productos Destacados
                    </h1>
                    <div class="text-muted">
                        Mostrando <?php echo count($productos); ?> de <?php echo $total; ?> productos
                    </div>
                </div>
                
                <?php if (empty($productos)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay productos destacados disponibles en este momento.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($productos as $producto): 
                            $imagen = $producto['imagen_principal'] ? 
                                      '../' . IMG_DIR . 'productos/' . htmlspecialchars($producto['imagen_principal']) : 
                                      'https://via.placeholder.com/300x200';
                        ?>
                        <div class="col-md-4 mb-4">
                            <div class="product-card">
                                <div class="position-relative">
                                    <img src="<?php echo $imagen; ?>" class="product-image" alt="<?php echo $producto['nombre']; ?>">
                                    
                                    <!-- Badge de más vendidos -->
                                    <?php if ($producto['vendidos_mes'] > 0): ?>
                                        <div class="vendidos-badge">
                                            <i class="fas fa-chart-line me-1"></i>
                                            <?php echo $producto['vendidos_mes']; ?> vendidos
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Badge de etiqueta -->
                                    <?php if ($producto['etiqueta']): ?>
                                        <span class="badge badge-<?php echo $producto['etiqueta']; ?>" 
                                              style="position: absolute; top: 10px; right: 10px;">
                                            <?php echo ucfirst($producto['etiqueta']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- Badge Ajitos -->
                                    <?php if ($producto['es_ajitos']): ?>
                                        <span class="badge badge-ajitos" 
                                              style="position: absolute; bottom: 10px; left: 10px;">
                                            Ajitos Kids
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3">
                                    <h6 class="mb-2"><?php echo $producto['nombre']; ?></h6>
                                    <p class="text-muted small mb-2"><?php echo $producto['departamento_nombre']; ?></p>
                                    <p class="small mb-3 text-truncate"><?php echo substr($producto['descripcion'], 0, 80); ?>...</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="price"><?php echo formatMoney($producto['precio_venta']); ?></span>
                                        <div>
                                            <a href="producto.php?id=<?php echo $producto['id_raiz']; ?>" 
                                               class="btn btn-sm btn-outline-primary me-1">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="addToCart(<?php echo $producto['id_raiz']; ?>)">
                                                <i class="fas fa-cart-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                    <nav aria-label="Paginación" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <!-- Anterior -->
                            <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo buildUrl(['pagina' => $pagina - 1]); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <!-- Números de página -->
                            <?php for ($i = 1; $i <= $total_paginas; $i++): 
                                if ($i == 1 || $i == $total_paginas || ($i >= $pagina - 2 && $i <= $pagina + 2)): ?>
                                <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo buildUrl(['pagina' => $i]); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php elseif ($i == $pagina - 3 || $i == $pagina + 3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <!-- Siguiente -->
                            <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo buildUrl(['pagina' => $pagina + 1]); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <!-- Modal para seleccionar variante -->
    <div class="modal fade" id="varianteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seleccionar Variante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="variantesContainer">
                    <!-- Las variantes se cargarán aquí con AJAX -->
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Función para agregar al carrito
        function addToCart(productId) {
            $.ajax({
                url: 'api/get_variantes.php',
                method: 'POST',
                data: { producto_id: productId },
                success: function(response) {
                    $('#variantesContainer').html(response);
                    $('#varianteModal').modal('show');
                },
                error: function() {
                    alert('Error al cargar las variantes del producto.');
                }
            });
        }
        
        // Función para agregar variante al carrito
        function addVarianteToCart(varianteId, cantidad) {
            $.ajax({
                url: 'api/add_to_cart.php',
                method: 'POST',
                data: { 
                    variante_id: varianteId,
                    cantidad: cantidad
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        $('.cart-badge').text(result.carrito_count);
                        if (result.carrito_count > 0 && $('.cart-badge').length === 0) {
                            $('#cartDropdown').append('<span class="cart-badge">' + result.carrito_count + '</span>');
                        }

                        alert('Producto agregado al carrito');
                        $('#varianteModal').modal('hide');
                    } else {
                        alert('Error: ' + result.message);
                    }
                },
                error: function() {
                    alert('Error al agregar el producto al carrito.');
                }
            });
        }

        function updateQuery(params, resetPage) {
            const url = new URL(window.location.href);
            Object.keys(params).forEach((key) => {
                const value = params[key];
                if (value === null || value === '' || typeof value === 'undefined') {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, value);
                }
            });

            if (resetPage) {
                url.searchParams.set('pagina', '1');
            }

            window.location.href = url.pathname + '?' + url.searchParams.toString();
        }

        function filtrarPrecio() {
            const min = document.getElementById('precioMin').value;
            const max = document.getElementById('precioMax').value;

            updateQuery({
                precio_min: min || null,
                precio_max: max || null
            }, true);
        }

        function filtrarAjitos() {
            const checked = document.getElementById('filtroAjitos').checked;
            updateQuery({
                ajitos: checked ? '1' : null
            }, true);
        }

        function ordenarProductos(orden) {
            updateQuery({ orden: orden }, true);
        }
    </script>
</body>
</html>
