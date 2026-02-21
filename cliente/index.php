<?php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Obtener productos destacados
$destacados_query = "SELECT pr.*, d.nombre as departamento_nombre,
                     (SELECT nombre_archivo FROM productos_raiz_fotos 
                      WHERE id_producto_raiz = pr.id_raiz AND es_principal = 1 LIMIT 1) as imagen_principal
                     FROM productos_raiz pr
                     INNER JOIN departamentos d ON pr.id_departamento = d.id_departamento
                     WHERE pr.activo = 1 AND d.activo = 1
                     ORDER BY pr.fecha_creacion DESC
                     LIMIT 8";
$destacados_stmt = $db->prepare($destacados_query);
$destacados_stmt->execute();
$destacados = $destacados_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos en oferta
$ofertas_query = "SELECT pr.*, d.nombre as departamento_nombre,
                  (SELECT nombre_archivo FROM productos_raiz_fotos 
                   WHERE id_producto_raiz = pr.id_raiz AND es_principal = 1 LIMIT 1) as imagen_principal
                  FROM productos_raiz pr
                  INNER JOIN departamentos d ON pr.id_departamento = d.id_departamento
                  WHERE pr.activo = 1 AND d.activo = 1 AND pr.etiqueta = 'oferta'
                  LIMIT 6";
$ofertas_stmt = $db->prepare($ofertas_query);
$ofertas_stmt->execute();
$ofertas = $ofertas_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener departamentos
$departamentos_query = "SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre";
$departamentos_stmt = $db->prepare($departamentos_query);
$departamentos_stmt->execute();
$departamentos = $departamentos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar si hay items en el carrito
$carrito_count = 0;
if (isset($_SESSION['carrito'])) {
    $carrito_count = count($_SESSION['carrito']);
}

function obtenerBannerCarrusel($slot)
{
    $extensiones = ['jpg', 'jpeg', 'png', 'webp'];
    $baseDir = __DIR__ . '/../assets/img/';

    foreach ($extensiones as $extension) {
        $archivo = 'banner' . $slot . '.' . $extension;
        if (file_exists($baseDir . $archivo)) {
            return '../assets/img/' . $archivo;
        }
    }

    return 'https://via.placeholder.com/1400x400/6B74DB/ffffff?text=Banner+' . $slot;
}

$banner1 = obtenerBannerCarrusel(1);
$banner2 = obtenerBannerCarrusel(2);
$banner3 = obtenerBannerCarrusel(3);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Tienda Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <style>
        :root {
            --brand-primary: #D092D6;
            --brand-primary-dark: #C27CC9;
            --brand-primary-light: #F5EAF6;
        }
        .navbar-brand {
            color: var(--brand-primary);
            font-weight: bold;
            font-size: 1.5rem;
        }
        .navbar .nav-link:hover,
        .navbar .nav-link.active {
            color: var(--brand-primary) !important;
        }
        .navbar .btn-primary {
            background-color: var(--brand-primary);
            border-color: var(--brand-primary);
        }
        .navbar .btn-primary:hover,
        .navbar .btn-primary:focus {
            background-color: var(--brand-primary-dark);
            border-color: var(--brand-primary-dark);
        }
        .navbar .btn-outline-primary {
            color: var(--brand-primary);
            border-color: var(--brand-primary);
        }
        .navbar .btn-outline-primary:hover,
        .navbar .btn-outline-primary:focus {
            background-color: var(--brand-primary);
            border-color: var(--brand-primary);
            color: #fff;
        }
        .ajitos-badge {
            background: var(--ajitos-primary-deep);
            color: var(--ajitos-text);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .product-card {
            transition: transform 0.3s;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            overflow: hidden;
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
            color: var(--brand-primary);
            font-weight: bold;
            font-size: 1.2rem;
        }
        .old-price {
            text-decoration: line-through;
            color: #999;
            font-size: 0.9rem;
        }
        .badge-oferta {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff6b6b;
            color: white;
        }
        .badge-nuevo {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--brand-primary);
            color: white;
        }
        .carousel-item {
            height: 400px;
        }
        .carousel-item img {
            object-fit: cover;
            height: 100%;
        }
        .carousel-caption {
            background: rgba(0,0,0,0.5);
            border-radius: 10px;
            padding: 20px;
        }
        .category-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: white;
            transition: transform 0.3s;
            border: 1px solid #dee2e6;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .category-icon {
            font-size: 3rem;
            color: var(--brand-primary);
            margin-bottom: 10px;
        }
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .footer {
            background: #2c3e50;
            color: white;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store me-2"></i>
                <?php echo SITE_NAME; ?>
                <span class="ajitos-badge"><?php echo AJITOS_NAME; ?></span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home me-1"></i> Inicio
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="departamentosDropdown" 
                           data-bs-toggle="dropdown">
                            <i class="fas fa-th-large me-1"></i> Departamentos
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($departamentos as $depto): ?>
                            <li>
                                <a class="dropdown-item" href="categoria.php?id=<?php echo $depto['id_departamento']; ?>">
                                    <?php echo $depto['nombre']; ?>
                                    <?php if ($depto['es_ajitos']): ?>
                                        <span class="badge badge-ajitos">Ajitos</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ofertas.php">
                            <i class="fas fa-tag me-1"></i> Ofertas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nuevos.php">
                            <i class="fas fa-star me-1"></i> Nuevos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ajitos.php">
                            <i class="fas fa-baby me-1"></i> Ajitos Kids
                        </a>
                    </li>
                </ul>
                
                <form class="d-flex me-3" action="busqueda.php" method="GET">
                    <div class="input-group">
                        <input type="text" class="form-control" name="q" placeholder="Buscar productos...">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="carrito.php" id="cartDropdown">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($carrito_count > 0): ?>
                                <span class="cart-badge"><?php echo $carrito_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-user me-1"></i> Mi Cuenta
                        </a>
                    </li>
                    <?php if (isset($_SESSION['cliente_id'])): ?>
                    <li class="nav-item">
                        <span class="nav-link text-muted">
                            <i class="fas fa-coins me-1"></i>
                            <?php echo $_SESSION['cliente_puntos']; ?> pts
                        </span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Carrusel -->
    <div id="mainCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="2"></button>
        </div>
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="<?php echo htmlspecialchars($banner1); ?>" class="d-block w-100" alt="Ofertas Especiales">
                <div class="carousel-caption d-none d-md-block">
                    <h3>Ofertas Especiales</h3>
                    <p>Hasta 50% de descuento en ropa de verano</p>
                    <a href="ofertas.php" class="btn btn-primary">Ver Ofertas</a>
                </div>
            </div>
            <div class="carousel-item">
                <img src="<?php echo htmlspecialchars($banner2); ?>" class="d-block w-100" alt="Nueva Colección">
                <div class="carousel-caption d-none d-md-block">
                    <h3>Nueva Colección</h3>
                    <p>Descubre las últimas tendencias en moda</p>
                    <a href="nuevos.php" class="btn btn-primary">Ver Novedades</a>
                </div>
            </div>
            <div class="carousel-item">
                <img src="<?php echo htmlspecialchars($banner3); ?>" class="d-block w-100" alt="Ajitos Kids">
                <div class="carousel-caption d-none d-md-block">
                    <h3>Ajitos Kids</h3>
                    <p>Lo mejor para los más pequeños de la casa</p>
                    <a href="ajitos.php" class="btn btn-ajitos">Ver Colección</a>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>

    <!-- Contenido Principal -->
    <div class="container">
        <!-- Categorías -->
        <section class="mb-5">
            <h2 class="text-center mb-4">Nuestros Departamentos</h2>
            <div class="row">
                <?php foreach ($departamentos as $depto): ?>
                <div class="col-md-3 mb-4">
                    <a href="categoria.php?id=<?php echo $depto['id_departamento']; ?>" class="text-decoration-none">
                        <div class="category-card">
                            <div class="category-icon">
                                <i class="fas fa-tshirt"></i>
                            </div>
                            <h5><?php echo $depto['nombre']; ?></h5>
                            <?php if ($depto['es_ajitos']): ?>
                                <span class="badge badge-ajitos">Ajitos Kids</span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Productos Destacados -->
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Productos Destacados</h2>
                <a href="destacados.php" class="btn btn-outline-primary">Ver Todos</a>
            </div>
            <div class="row">
                <?php foreach ($destacados as $producto): 
                    $imagen = $producto['imagen_principal'] ? 
                              '../' . IMG_DIR . 'productos/' . htmlspecialchars($producto['imagen_principal']) : 
                              'https://via.placeholder.com/300x200';
                ?>
                <div class="col-md-3 mb-4">
                    <a href="producto.php?id=<?php echo $producto['id_raiz']; ?>" class="text-decoration-none">
                        <div class="product-card h-100">
                            <div class="position-relative">
                                <img src="<?php echo $imagen; ?>" class="product-image" alt="<?php echo $producto['nombre']; ?>" onerror="this.src='https://via.placeholder.com/300x200'">
                                <?php if ($producto['etiqueta'] == 'oferta'): ?>
                                    <span class="badge badge-oferta">OFERTA</span>
                                <?php elseif ($producto['etiqueta'] == 'nuevo'): ?>
                                    <span class="badge badge-nuevo">NUEVO</span>
                                <?php endif; ?>
                                <?php if ($producto['es_ajitos']): ?>
                                    <span class="badge badge-ajitos" style="position: absolute; bottom: 10px; left: 10px;">Ajitos</span>
                                <?php endif; ?>
                            </div>
                            <div class="p-3">
                                <h6 class="mb-2"><?php echo substr($producto['nombre'], 0, 50); ?></h6>
                                <p class="text-muted small mb-2"><?php echo $producto['departamento_nombre']; ?></p>
                                <span class="price"><?php echo formatMoney($producto['precio_venta']); ?></span>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Ofertas Especiales -->
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-danger">Ofertas Especiales</h2>
                <a href="ofertas.php" class="btn btn-danger">Ver Todas las Ofertas</a>
            </div>
            <div class="row">
                <?php foreach ($ofertas as $producto):
                    $imagen = $producto['imagen_principal'] ? 
                              '../' . IMG_DIR . 'productos/' . htmlspecialchars($producto['imagen_principal']) : 
                              'https://via.placeholder.com/300x200';
                ?>
                <div class="col-md-2 mb-4">
                    <a href="producto.php?id=<?php echo $producto['id_raiz']; ?>" class="text-decoration-none">
                        <div class="product-card h-100">
                            <div class="position-relative">
                                <img src="<?php echo $imagen; ?>" class="product-image" alt="<?php echo $producto['nombre']; ?>" onerror="this.src='https://via.placeholder.com/300x200'">
                            </div>
                            <div class="p-2">
                                <h6 class="mb-1" style="font-size: 0.9rem;"><?php echo substr($producto['nombre'], 0, 30); ?>...</h6>
                                <span class="price" style="font-size: 1rem;"><?php echo formatMoney($producto['precio_venta']); ?></span>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Información de la tienda -->
        <section class="mb-5">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <i class="fas fa-shipping-fast fa-3x text-primary mb-3"></i>
                            <h5>Envío Gratis</h5>
                            <p class="text-muted">En compras mayores a Q200 en la ciudad</p>
                            <a href="estado_pedido.php" class="btn btn-outline-primary btn-sm">Seguimiento de pedidos</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <i class="fas fa-coins fa-3x text-warning mb-3"></i>
                            <h5>Acumula Puntos</h5>
                            <p class="text-muted">Gana 1 punto por cada Q20 de compra</p>
                            <a href="puntos.php" class="btn btn-outline-warning btn-sm">Ver puntos por DPI</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                            <h5>Compra Segura</h5>
                            <p class="text-muted">Pagos 100% seguros con encriptación SSL</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="footer py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><?php echo SITE_NAME; ?></h5>
                    <p>Tu tienda de moda favorita con los mejores precios y calidad.</p>
                    <span class="ajitos-badge"><?php echo AJITOS_NAME; ?></span>
                </div>
                <div class="col-md-4">
                    <h5>Enlaces Rápidos</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white text-decoration-none">Inicio</a></li>
                        <li><a href="ofertas.php" class="text-white text-decoration-none">Ofertas</a></li>
                        <li><a href="nuevos.php" class="text-white text-decoration-none">Nuevos</a></li>
                        <li><a href="ajitos.php" class="text-white text-decoration-none">Ajitos Kids</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contacto</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-map-marker-alt me-2"></i> Ciudad de Guatemala</li>
                        <li><i class="fas fa-phone me-2"></i> (502) 1234-5678</li>
                        <li><i class="fas fa-envelope me-2"></i> info@tiendamm.com</li>
                    </ul>
                </div>
            </div>
            <hr class="bg-white">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Modal para seleccionar variante -->
    <div class="modal fade" id="varianteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Seleccionar Variante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="variantesContainer">
                        <!-- Las variantes se cargarán aquí con AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
    
    <script>
        // Inicializar carrusel
        $(document).ready(function(){
            $('.owl-carousel').owlCarousel({
                loop: true,
                margin: 10,
                nav: true,
                responsive: {
                    0: { items: 1 },
                    600: { items: 3 },
                    1000: { items: 5 }
                }
            });
        });
        
        // Función para agregar al carrito
        function addToCart(productId) {
            $.ajax({
                url: 'api/get_variantes.php',
                method: 'POST',
                data: { producto_id: productId },
                success: function(response) {
                    $('#variantesContainer').html(response);
                    $('#varianteModal').modal('show');
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
                        // Actualizar contador del carrito
                        $('.cart-badge').text(result.carrito_count);
                        if (result.carrito_count > 0 && $('.cart-badge').length === 0) {
                            $('#cartDropdown').append('<span class="cart-badge">' + result.carrito_count + '</span>');
                        }
                        
                        // Mostrar mensaje de éxito
                        alert('Producto agregado al carrito');
                        $('#varianteModal').modal('hide');
                    } else {
                        alert('Error: ' + result.message);
                    }
                }
            });
        }
        
        // Función para ver detalles del producto
        function viewProductDetails(productId) {
            window.location.href = 'producto.php?id=' + productId;
        }
    </script>
</body>
</html>