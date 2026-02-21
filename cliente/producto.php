<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Iniciar sesión (opcional para productos)
if (!isset($_SESSION)) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

// Obtener ID de producto
$id_producto = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_producto == 0) {
    header('Location: index.php');
    exit;
}

// Obtener datos del producto
$query = "SELECT pr.*, d.nombre as departamento_nombre,
                 (SELECT nombre_archivo FROM productos_raiz_fotos 
                  WHERE id_producto_raiz = pr.id_raiz AND es_principal = 1 LIMIT 1) as imagen_principal
          FROM productos_raiz pr
          INNER JOIN departamentos d ON pr.id_departamento = d.id_departamento
          WHERE pr.id_raiz = :id AND pr.activo = 1 AND d.activo = 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id_producto, PDO::PARAM_INT);
$stmt->execute();
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    header('Location: index.php');
    exit;
}

// Obtener variantes disponibles
$variantes_query = "SELECT * FROM productos_variantes 
                   WHERE id_producto_raiz = :id AND activo = 1
                   ORDER BY color, talla";
$variantes_stmt = $db->prepare($variantes_query);
$variantes_stmt->bindParam(':id', $id_producto, PDO::PARAM_INT);
$variantes_stmt->execute();
$variantes = $variantes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener fotos del producto
$fotos_query = "SELECT * FROM productos_raiz_fotos 
               WHERE id_producto_raiz = :id
               ORDER BY es_principal DESC";
$fotos_stmt = $db->prepare($fotos_query);
$fotos_stmt->bindParam(':id', $id_producto, PDO::PARAM_INT);
$fotos_stmt->execute();
$fotos = $fotos_stmt->fetchAll(PDO::FETCH_ASSOC);

$carrito_count = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($producto['nombre']); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --product-primary: #D092D6;
            --product-primary-dark: #C27CC9;
            --product-primary-light: #F5EAF6;
        }
        .product-gallery {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            overflow: hidden;
        }
        .main-image {
            max-height: 500px;
            object-fit: cover;
            width: 100%;
        }
        .thumbnails {
            margin-top: 10px;
            display: flex;
            gap: 10px;
            overflow-x: auto;
        }
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 2px solid transparent;
            border-radius: 5px;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .thumbnail:hover,
        .thumbnail.active {
            border-color: var(--product-primary);
        }
        .price-tag {
            background: var(--product-primary);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .price-value {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .btn-primary {
            background-color: var(--product-primary);
            border-color: var(--product-primary);
        }
        .btn-primary:hover,
        .btn-primary:focus {
            background-color: var(--product-primary-dark);
            border-color: var(--product-primary-dark);
        }
        .btn-outline-primary {
            color: var(--product-primary);
            border-color: var(--product-primary);
        }
        .btn-outline-primary:hover,
        .btn-outline-primary:focus {
            background-color: var(--product-primary);
            border-color: var(--product-primary);
            color: #fff;
        }
        .form-control:focus {
            border-color: var(--product-primary);
            box-shadow: 0 0 0 0.2rem rgba(208, 146, 214, 0.25);
        }
        .badge-info {
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
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
                <li class="breadcrumb-item">
                    <a href="categoria.php?id=<?php echo $producto['id_departamento']; ?>">
                        <?php echo htmlspecialchars($producto['departamento_nombre']); ?>
                    </a>
                </li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($producto['nombre']); ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Galería de imágenes -->
            <div class="col-md-6 mb-4">
                <div class="product-gallery">
                    <img src="../uploads/productos/<?php echo htmlspecialchars($producto['imagen_principal'] ?? 'no-image.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                         class="main-image" id="mainImage">
                    <?php if (!empty($fotos)): ?>
                    <div class="thumbnails p-2">
                        <?php foreach ($fotos as $foto): ?>
                        <img src="../uploads/productos/<?php echo htmlspecialchars($foto['nombre_archivo']); ?>" 
                             alt="Foto" 
                             class="thumbnail <?php echo $foto['es_principal'] ? 'active' : ''; ?>"
                             onclick="document.getElementById('mainImage').src='../uploads/productos/<?php echo htmlspecialchars($foto['nombre_archivo']); ?>'; document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active')); this.classList.add('active');">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Información del producto -->
            <div class="col-md-6">
                <h1><?php echo htmlspecialchars($producto['nombre']); ?></h1>
                
                <div class="mb-3">
                    <?php if ($producto['es_ajitos']): ?>
                        <span class="badge badge-danger badge-info">
                            <i class="fas fa-flash me-1"></i> <?php echo AJITOS_NAME; ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($producto['etiqueta'] == 'oferta'): ?>
                        <span class="badge bg-danger badge-info">
                            <i class="fas fa-tag me-1"></i> Oferta
                        </span>
                    <?php elseif ($producto['etiqueta'] == 'nuevo'): ?>
                        <span class="badge bg-success badge-info">
                            <i class="fas fa-star me-1"></i> Nuevo
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="price-tag">
                    <div class="price-value" id="precioProducto">Q<?php echo number_format($producto['precio_venta'], 2); ?></div>
                </div>
                
                <p class="text-muted mb-4">
                    <i class="fas fa-barcode me-2"></i>
                    <strong>Código:</strong> <?php echo htmlspecialchars($producto['codigo']); ?>
                </p>
                
                <h5>Descripción</h5>
                <p><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                
                <!-- Seleccionar variante si hay -->
                <?php if (!empty($variantes)): ?>
                <div class="mb-4">
                    <!-- Datos de variantes en JSON para JavaScript -->
                    <script>
                        const variantesData = <?php echo json_encode($variantes); ?>;
                    </script>
                    
                    <!-- Seleccionar Talla -->
                    <div class="mb-4">
                        <h5>Talla:</h5>
                        <div class="d-flex flex-wrap gap-2" id="tallasContainer">
                            <!-- Se llena con JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Seleccionar Color (aparece después de talla) -->
                    <div class="mb-4" id="coloresSection" style="display:none;">
                        <h5>Color:</h5>
                        <div class="d-flex flex-wrap gap-2" id="coloresContainer">
                            <!-- Se llena con JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Info de Stock -->
                    <div class="mb-4" id="stockSection" style="display:none;">
                        <div class="alert alert-info" id="stockAlert">
                            <i class="fas fa-box me-2"></i>
                            <strong id="stockLabel">Stock disponible:</strong>
                            <span id="stockCount">0</span>
                            <span id="stockUnit">unidades</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Cantidad -->
                <div class="mb-4">
                    <h5>Cantidad:</h5>
                    <div class="input-group" style="width: 150px;">
                        <button class="btn btn-outline-secondary" type="button" id="decreaseQty">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="form-control text-center" id="cantidad" value="1" min="1">
                        <button class="btn btn-outline-secondary" type="button" id="increaseQty">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Botones de acción -->
                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" onclick="verificarLoginCarrito(<?php echo $producto['id_raiz']; ?>)">
                        <i class="fas fa-shopping-cart me-2"></i> Agregar al Carrito
                    </button>
                    <button class="btn btn-outline-secondary" onclick="verificarLoginFavorito()">
                        <i class="fas fa-heart me-2"></i> Agregar a Favoritos
                    </button>
                    <button class="btn btn-success btn-lg" onclick="verificarLoginComprar(<?php echo $producto['id_raiz']; ?>)">
                        <i class="fas fa-bolt me-2"></i> Comprar Ahora
                    </button>
                </div>
                
                <!-- Información adicional -->
                <div class="mt-5 p-3 bg-light rounded">
                    <h6>Información de Envío</h6>
                    <p class="small mb-0">
                        <i class="fas fa-truck me-2"></i> Envio disponible a todo el país
                    </p>
                    <p class="small">
                        <i class="fas fa-shield-alt me-2"></i> Garantía de satisfacción
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Usuario logueado?
        const usuarioLogueado = <?php echo isset($_SESSION['id_usuario']) ? 'true' : 'false'; ?>;
        let varianteSeleccionada = null;
        const precioBaseProducto = parseFloat('<?php echo number_format($producto['precio_venta'], 2, '.', ''); ?>');
        
        // Inicializar UI de tallas y colores
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof variantesData !== 'undefined' && variantesData.length > 0) {
                inicializarTallas();
            }
        });
        
        function inicializarTallas() {
            // Obtener tallas únicas
            const tallas = [...new Set(variantesData.map(v => v.talla))].sort();
            const tallasContainer = document.getElementById('tallasContainer');
            
            tallas.forEach(talla => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-outline-primary btn-sm';
                btn.textContent = `Talla ${talla}`;
                btn.dataset.talla = talla;
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Remover clase active de otros botones
                    document.querySelectorAll('#tallasContainer .btn').forEach(b => {
                        b.classList.remove('active');
                    });
                    // Agregar clase active al botón seleccionado
                    btn.classList.add('active');
                    mostrarColores(talla);
                });
                tallasContainer.appendChild(btn);
            });
        }
        
        function mostrarColores(talla) {
            // Filtrar variantes por talla
            const variantesPorTalla = variantesData.filter(v => v.talla === talla);
            const colores = [...new Set(variantesPorTalla.map(v => v.color))].sort();
            
            const coloresContainer = document.getElementById('coloresContainer');
            const coloresSection = document.getElementById('coloresSection');
            const stockSection = document.getElementById('stockSection');
            const stockAlert = document.getElementById('stockAlert');
            const stockLabel = document.getElementById('stockLabel');
            const stockCount = document.getElementById('stockCount');
            const stockUnit = document.getElementById('stockUnit');
            
            // Limpiar colores anteriores
            coloresContainer.innerHTML = '';
            let opcionesDisponibles = 0;

            colores.forEach(color => {
                const variante = variantesPorTalla.find(v => v.color === color);
                const stockTotal = variante.stock_tienda + variante.stock_bodega;
                if (stockTotal <= 0) {
                    return;
                }
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-outline-secondary btn-sm';
                btn.textContent = color;
                btn.dataset.color = color;
                btn.dataset.variante = variante.id_variante;
                
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Remover clase active de otros botones
                    document.querySelectorAll('#coloresContainer .btn').forEach(b => {
                        b.classList.remove('active');
                    });
                    // Agregar clase active al botón seleccionado
                    btn.classList.add('active');
                    mostrarStock(variante);
                });
                coloresContainer.appendChild(btn);
                opcionesDisponibles += 1;
            });
            
            coloresSection.style.display = 'block';

            if (opcionesDisponibles === 0) {
                varianteSeleccionada = null;
                if (stockAlert) {
                    stockAlert.className = 'alert alert-danger';
                }
                if (stockLabel) {
                    stockLabel.textContent = 'Agotado';
                }
                if (stockCount) {
                    stockCount.textContent = '';
                }
                if (stockUnit) {
                    stockUnit.textContent = '';
                }
                if (stockSection) {
                    stockSection.style.display = 'block';
                }
            }
        }
        
        function mostrarStock(variante) {
            varianteSeleccionada = variante;
            const stock = variante.stock_tienda + variante.stock_bodega;
            const stockSection = document.getElementById('stockSection');
            const stockCount = document.getElementById('stockCount');
            const stockAlert = document.getElementById('stockAlert');
            const stockLabel = document.getElementById('stockLabel');
            const stockUnit = document.getElementById('stockUnit');
            const precioEl = document.getElementById('precioProducto');
            
            stockCount.textContent = stock;
            if (stockLabel) {
                stockLabel.textContent = 'Stock disponible:';
            }
            if (stockUnit) {
                stockUnit.textContent = 'unidades';
            }
            
            // Cambiar color del alert según disponibilidad
            if (stock > 10) {
                if (stockAlert) {
                    stockAlert.className = 'alert alert-success';
                }
            } else if (stock > 0) {
                if (stockAlert) {
                    stockAlert.className = 'alert alert-warning';
                }
            } else {
                if (stockAlert) {
                    stockAlert.className = 'alert alert-danger';
                }
            }
            
            stockSection.style.display = 'block';

            if (precioEl) {
                const precioVariante = parseFloat(variante.precio_venta || 0);
                const precioFinal = precioVariante > 0 ? precioVariante : precioBaseProducto;
                precioEl.textContent = 'Q' + precioFinal.toFixed(2);
            }
            
            // Actualizar cantidad máxima
            document.getElementById('cantidad').max = stock;
        }
        
        // Agregar al carrito (sin necesidad de login)
        function verificarLoginCarrito(idProducto) {
            agregarAlCarrito(idProducto);
        }
        
        // Verificar login antes de comprar ahora
        function verificarLoginComprar(idProducto) {
            if (document.getElementById('coloresSection') && document.getElementById('coloresSection').style.display !== 'none') {
                if (!varianteSeleccionada) {
                    alert('Por favor selecciona una variante (talla y color)');
                    return;
                }
            }
            
            if (!usuarioLogueado) {
                if (confirm('Necesitas iniciar sesión para realizar una compra. ¿Deseas iniciar sesión?')) {
                    const redirectUrl = encodeURIComponent('confirmacion.php?id=' + idProducto);
                    window.location.href = 'login.php?redirect=' + redirectUrl;
                }
                return;
            }
            window.location.href = 'confirmacion.php?id=' + idProducto;
        }
        
        // Verificar login para favoritos
        function verificarLoginFavorito() {
            if (!usuarioLogueado) {
                alert('Necesitas iniciar sesión para agregar a favoritos');
                window.location.href = 'login.php';
                return;
            }
            alert('Producto agregado a favoritos');
        }
        
        document.getElementById('decreaseQty').addEventListener('click', function() {
            const qty = document.getElementById('cantidad');
            if (qty.value > 1) qty.value = parseInt(qty.value) - 1;
        });
        
        document.getElementById('increaseQty').addEventListener('click', function() {
            const qty = document.getElementById('cantidad');
            qty.value = parseInt(qty.value) + 1;
        });
    </script>
</body>
</html>
