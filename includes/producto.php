<?php
// public/producto.php
require_once '../app/config/config.php';
require_once '../app/models/Producto.php';
require_once '../app/models/Carrito.php';

$productoModel = new Producto();
$carritoModel = new Carrito();

// Obtener ID del producto
$productId = $_GET['id'] ?? 0;
if (!$productId) {
    redirect('/index.php');
}

// Obtener información del producto
$producto = $productoModel->getById($productId);
if (!$producto || $producto['estado'] != 'activo') {
    $_SESSION['error'] = 'Producto no encontrado o no disponible';
    redirect('/index.php');
}

// Preparar datos para la página
$page_title = htmlspecialchars($producto['nombre']) . ' - Tienda MI&MI Store';

// Obtener productos relacionados
$relatedProducts = $productoModel->getRelatedProducts($productId, 4);

// Verificar si está en oferta
$enOferta = isProductOnSale($producto);
$precioFinal = $enOferta ? $producto['precio_oferta'] : $producto['precio_venta'];
$descuentoPorcentaje = $enOferta ? getDiscountPercentage($producto['precio_venta'], $producto['precio_oferta']) : 0;

// Breadcrumbs
$breadcrumbs = [
    ['name' => 'Inicio', 'url' => '/index.php'],
    ['name' => htmlspecialchars($producto['departamento_nombre']), 'url' => '/categoria.php?id=' . $producto['id_departamento']],
    ['name' => htmlspecialchars($producto['nombre']), 'url' => '#']
];

// Verificar si está en carrito
$enCarrito = false;
$cantidadCarrito = 0;
if (isset($_SESSION['cart_session_id'])) {
    $cartItems = $carritoModel->getCartItems($_SESSION['cart_session_id']);
    foreach ($cartItems as $item) {
        // Verificar si alguna variante de este producto está en el carrito
        foreach ($producto['variantes'] as $variante) {
            if ($item['id_variante'] == $variante['id_variante']) {
                $enCarrito = true;
                $cantidadCarrito = $item['cantidad'];
                break 2;
            }
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<!-- Breadcrumbs -->
<div class="container py-3">
    <?php echo generateBreadcrumbs($breadcrumbs); ?>
</div>

<!-- Producto Detalle -->
<div class="container py-5">
    <div class="row">
        <!-- Galería de imágenes -->
        <div class="col-lg-6 mb-4">
            <div class="product-gallery">
                <!-- Imagen principal -->
                <div class="main-image mb-3">
                    <?php if (!empty($producto['fotos'])): 
                        $fotoPrincipal = null;
                        foreach ($producto['fotos'] as $foto) {
                            if ($foto['es_principal']) {
                                $fotoPrincipal = $foto;
                                break;
                            }
                        }
                        if (!$fotoPrincipal && !empty($producto['fotos'])) {
                            $fotoPrincipal = $producto['fotos'][0];
                        }
                    ?>
                    <img id="mainProductImage" 
                         src="<?php echo getProductImageUrl($fotoPrincipal['nombre_archivo']); ?>" 
                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                         class="img-fluid rounded">
                    <?php else: ?>
                    <img id="mainProductImage" 
                         src="<?php echo ASSETS_URL; ?>/img/products/default.jpg" 
                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                         class="img-fluid rounded">
                    <?php endif; ?>
                </div>
                
                <!-- Miniaturas -->
                <?php if (!empty($producto['fotos'])): ?>
                <div class="thumbnails">
                    <?php foreach ($producto['fotos'] as $index => $foto): ?>
                    <div class="thumbnail-item <?php echo $index === 0 ? 'active' : ''; ?>"
                         data-image="<?php echo getProductImageUrl($foto['nombre_archivo']); ?>">
                        <img src="<?php echo getProductImageUrl($foto['nombre_archivo'], true); ?>" 
                             alt="Miniatura <?php echo $index + 1; ?>"
                             class="img-fluid">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Compartir -->
                <div class="share-buttons mt-4">
                    <span class="me-2">Compartir:</span>
                    <a href="#" class="btn btn-outline-secondary btn-sm" title="Compartir en Facebook">
                        <i class="fab fa-facebook"></i>
                    </a>
                    <a href="#" class="btn btn-outline-secondary btn-sm" title="Compartir en Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="btn btn-outline-secondary btn-sm" title="Compartir en WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="#" class="btn btn-outline-secondary btn-sm" title="Compartir por Email">
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Información del producto -->
        <div class="col-lg-6">
            <div class="product-details">
                <!-- Categorías -->
                <div class="product-categories mb-2">
                    <a href="/categoria.php?id=<?php echo $producto['id_departamento']; ?>" class="badge bg-light text-dark">
                        <?php echo htmlspecialchars($producto['departamento_nombre']); ?>
                    </a>
                    <?php if ($producto['es_kids']): ?>
                    <span class="badge bg-warning">Ajitos Kids</span>
                    <?php endif; ?>
                    <?php if ($enOferta): ?>
                    <span class="badge bg-danger">Oferta</span>
                    <?php endif; ?>
                    <?php if ($producto['etiqueta'] == 'nuevo'): ?>
                    <span class="badge bg-success">Nuevo</span>
                    <?php endif; ?>
                </div>
                
                <!-- Nombre y código -->
                <h1 class="product-title mb-2"><?php echo htmlspecialchars($producto['nombre']); ?></h1>
                <p class="text-muted mb-3">Código: <?php echo htmlspecialchars($producto['codigo_producto']); ?></p>
                
                <!-- Precio -->
                <div class="product-price mb-4">
                    <?php if ($enOferta): ?>
                    <div class="d-flex align-items-center">
                        <span class="price-final h3 text-danger me-3">Q<?php echo number_format($precioFinal, 2); ?></span>
                        <span class="price-original h5 text-muted text-decoration-line-through">Q<?php echo number_format($producto['precio_venta'], 2); ?></span>
                        <span class="discount-percent badge bg-danger ms-2">-<?php echo $descuentoPorcentaje; ?>%</span>
                    </div>
                    <p class="text-success mb-0 price-savings">
                        <i class="fas fa-bolt me-1"></i>
                        Ahorras Q<?php echo number_format($producto['precio_venta'] - $precioFinal, 2); ?>
                    </p>
                    <?php else: ?>
                    <span class="price-final h3">Q<?php echo number_format($precioFinal, 2); ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Descripción -->
                <div class="product-description mb-4">
                    <h5>Descripción</h5>
                    <p><?php echo nl2br(htmlspecialchars($producto['descripcion'] ?? 'Sin descripción disponible')); ?></p>
                </div>
                
                <!-- Especificaciones -->
                <div class="product-specs mb-4">
                    <h5>Especificaciones</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><strong>Tipo:</strong> <?php echo htmlspecialchars($producto['tipo_nombre'] ?? 'N/A'); ?></li>
                                <li><strong>Marca:</strong> <?php echo htmlspecialchars($producto['marca_nombre'] ?? 'N/A'); ?></li>
                                <li><strong>Estado:</strong> 
                                    <?php if ($producto['stock_total'] > 0): ?>
                                    <span class="text-success">Disponible</span>
                                    <?php else: ?>
                                    <span class="text-danger">Agotado</span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><strong>Stock total:</strong> <?php echo $producto['stock_total']; ?> unidades</li>
                                <?php if ($enOferta && $producto['fecha_fin_oferta']): ?>
                                <li><strong>Oferta válida hasta:</strong> <?php echo formatDate($producto['fecha_fin_oferta']); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Variantes disponibles -->
                <?php if (!empty($producto['variantes'])): ?>
                <div class="product-variants mb-4">
                    <h5>Opciones disponibles</h5>
                    
                    <!-- Tallas -->
                    <div class="mb-3">
                        <label class="form-label">Talla</label>
                        <div class="size-options">
                            <?php 
                            $tallasDisponibles = [];
                            foreach ($producto['variantes'] as $variante) {
                                if (!in_array($variante['talla'], $tallasDisponibles) && 
                                    ($variante['stock_tienda'] + $variante['stock_bodega']) > 0) {
                                    $tallasDisponibles[] = $variante['talla'];
                                }
                            }
                            
                            foreach ($tallasDisponibles as $talla): ?>
                            <button type="button" class="btn btn-outline-secondary size-option"
                                    data-talla="<?php echo htmlspecialchars($talla); ?>">
                                <?php echo htmlspecialchars($talla); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Colores -->
                    <div class="mb-4">
                        <label class="form-label">Color</label>
                        <div class="color-options">
                            <?php 
                            $coloresDisponibles = [];
                            foreach ($producto['variantes'] as $variante) {
                                $key = $variante['color'] . '-' . $variante['color_hex'];
                                if (!isset($coloresDisponibles[$key]) && 
                                    ($variante['stock_tienda'] + $variante['stock_bodega']) > 0) {
                                    $coloresDisponibles[$key] = $variante;
                                }
                            }
                            
                            foreach ($coloresDisponibles as $variante): ?>
                            <button type="button" class="color-option"
                                    data-color="<?php echo htmlspecialchars($variante['color']); ?>"
                                    data-color-hex="<?php echo htmlspecialchars($variante['color_hex'] ?? '#000000'); ?>"
                                    title="<?php echo htmlspecialchars($variante['color']); ?>">
                                <?php if ($variante['color_hex']): ?>
                                <span class="color-swatch" style="background-color: <?php echo htmlspecialchars($variante['color_hex']); ?>;"></span>
                                <?php endif; ?>
                                <span class="color-name"><?php echo htmlspecialchars($variante['color']); ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Stock por variante -->
                    <div id="variantStockInfo" class="alert alert-info" style="display: none;">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="stockMessage"></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Cantidad y acciones -->
                <div class="product-actions mb-4">
                    <form id="addToCartForm">
                        <input type="hidden" name="product_id" value="<?php echo $producto['id_producto_raiz']; ?>">
                        <input type="hidden" name="selected_variant" id="selectedVariant" value="">
                        
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Cantidad</label>
                                <div class="input-group quantity-selector">
                                    <button type="button" class="btn btn-outline-secondary" id="decreaseQty">-</button>
                                    <input type="number" class="form-control text-center" 
                                           id="productQuantity" name="quantity" value="1" min="1" max="100">
                                    <button type="button" class="btn btn-outline-secondary" id="increaseQty">+</button>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="d-grid gap-2">
                                    <?php if ($producto['stock_total'] > 0): ?>
                                    <button type="submit" class="btn btn-primary btn-lg" id="addToCartBtn">
                                        <i class="fas fa-shopping-cart me-2"></i>
                                        <?php echo $enCarrito ? 'Actualizar en carrito' : 'Agregar al carrito'; ?>
                                    </button>
                                    
                                    <button type="button" class="btn btn-outline-primary btn-lg" id="buyNowBtn">
                                        <i class="fas fa-bolt me-2"></i>
                                        Comprar ahora
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-secondary btn-lg" disabled>
                                        <i class="fas fa-times-circle me-2"></i>
                                        Producto agotado
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-lg" id="notifyBtn">
                                        <i class="fas fa-bell me-2"></i>
                                        Notificarme cuando esté disponible
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Información adicional -->
                <div class="additional-info">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="info-card">
                                <i class="fas fa-shipping-fast"></i>
                                <div>
                                    <h6>Envío gratis</h6>
                                    <small>En compras mayores a Q200</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-card">
                                <i class="fas fa-undo"></i>
                                <div>
                                    <h6>Devoluciones</h6>
                                    <small>30 días para devolver</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-card">
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <h6>Pago seguro</h6>
                                    <small>Garantizamos tu seguridad</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="info-card">
                                <i class="fas fa-headset"></i>
                                <div>
                                    <h6>Soporte 24/7</h6>
                                    <small>Estamos para ayudarte</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Productos relacionados -->
<?php if (!empty($relatedProducts)): ?>
<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="section-title">Productos relacionados</h3>
        </div>
    </div>
    
    <div class="row">
        <?php foreach ($relatedProducts as $related): 
            $precioFinal = $related['precio_oferta'] ?? $related['precio_venta'];
        ?>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="product-card">
                <div class="product-image-container">
                    <a href="/producto.php?id=<?php echo $related['id_producto_raiz']; ?>">
                        <img src="<?php echo getProductImageUrl($related['foto_principal'] ?? '', true); ?>" 
                             alt="<?php echo htmlspecialchars($related['nombre']); ?>" 
                             class="product-image">
                    </a>
                </div>
                <div class="product-info">
                    <h6 class="product-category"><?php echo htmlspecialchars($related['departamento_nombre'] ?? ''); ?></h6>
                    <h5 class="product-title">
                        <a href="/producto.php?id=<?php echo $related['id_producto_raiz']; ?>">
                            <?php echo htmlspecialchars($related['nombre']); ?>
                        </a>
                    </h5>
                    <div class="product-price">
                        <span class="price-final">Q<?php echo number_format($precioFinal, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Galería de imágenes
    document.querySelectorAll('.thumbnail-item').forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
            const mainImage = document.getElementById('mainProductImage');
            const imageUrl = this.dataset.image;
            
            mainImage.src = imageUrl;
            
            // Actualizar clase activa
            document.querySelectorAll('.thumbnail-item').forEach(item => {
                item.classList.remove('active');
            });
            this.classList.add('active');
        });
    });
    
    // Selector de cantidad
    document.getElementById('decreaseQty').addEventListener('click', function() {
        const quantityInput = document.getElementById('productQuantity');
        let quantity = parseInt(quantityInput.value);
        if (quantity > 1) {
            quantityInput.value = quantity - 1;
        }
    });
    
    document.getElementById('increaseQty').addEventListener('click', function() {
        const quantityInput = document.getElementById('productQuantity');
        let quantity = parseInt(quantityInput.value);
        if (quantity < 100) {
            quantityInput.value = quantity + 1;
        }
    });
    
    // Selector de variantes
    const basePrice = parseFloat('<?php echo number_format($precioFinal, 2, '.', ''); ?>');
    const baseHasOffer = <?php echo $enOferta ? 'true' : 'false'; ?>;
    let selectedTalla = null;
    let selectedColor = null;
    let selectedVariantId = null;
    
    document.querySelectorAll('.size-option').forEach(button => {
        button.addEventListener('click', function() {
            selectedTalla = this.dataset.talla;
            
            // Actualizar UI
            document.querySelectorAll('.size-option').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
            
            updateVariantSelection();
        });
    });
    
    document.querySelectorAll('.color-option').forEach(button => {
        button.addEventListener('click', function() {
            selectedColor = this.dataset.color;
            
            // Actualizar UI
            document.querySelectorAll('.color-option').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
            
            updateVariantSelection();
        });
    });
    
    function updateVariantSelection() {
        if (selectedTalla && selectedColor) {
            // Buscar variante que coincida
            const variants = <?php echo json_encode($producto['variantes']); ?>;
            const variant = variants.find(v => 
                v.talla === selectedTalla && v.color === selectedColor
            );
            
            if (variant) {
                selectedVariantId = variant.id_variante;
                document.getElementById('selectedVariant').value = variant.id_variante;

                // Actualizar precio segun variante
                const variantPrice = parseFloat(variant.precio_venta || 0);
                const priceFinalEl = document.querySelector('.price-final');
                const priceOriginalEl = document.querySelector('.price-original');
                const discountEl = document.querySelector('.discount-percent');
                const savingsEl = document.querySelector('.price-savings');

                if (priceFinalEl) {
                    const displayPrice = variantPrice > 0 ? variantPrice : basePrice;
                    priceFinalEl.textContent = 'Q' + displayPrice.toFixed(2);
                }

                if (baseHasOffer) {
                    const hideOffer = variantPrice > 0;
                    if (priceOriginalEl) priceOriginalEl.style.display = hideOffer ? 'none' : '';
                    if (discountEl) discountEl.style.display = hideOffer ? 'none' : '';
                    if (savingsEl) savingsEl.style.display = hideOffer ? 'none' : '';
                }
                
                // Mostrar información de stock
                const stockTotal = variant.stock_tienda + variant.stock_bodega;
                const stockMessage = document.getElementById('stockMessage');
                const stockInfo = document.getElementById('variantStockInfo');
                
                if (stockTotal > 0) {
                    stockMessage.textContent = `Disponible: ${stockTotal} unidades (Tienda: ${variant.stock_tienda}, Bodega: ${variant.stock_bodega})`;
                    stockInfo.className = 'alert alert-info';
                    document.getElementById('addToCartBtn').disabled = false;
                    document.getElementById('buyNowBtn').disabled = false;
                } else {
                    stockMessage.textContent = 'Esta variante está agotada';
                    stockInfo.className = 'alert alert-warning';
                    document.getElementById('addToCartBtn').disabled = true;
                    document.getElementById('buyNowBtn').disabled = true;
                }
                
                stockInfo.style.display = 'block';
                
                // Actualizar cantidad máxima
                const quantityInput = document.getElementById('productQuantity');
                quantityInput.max = Math.min(stockTotal, 100);
                if (parseInt(quantityInput.value) > quantityInput.max) {
                    quantityInput.value = quantityInput.max;
                }
            }
        }
    }
    
    // Si solo hay una variante, seleccionarla automáticamente
    const variants = <?php echo json_encode($producto['variantes']); ?>;
    if (variants.length === 1) {
        const variant = variants[0];
        selectedTalla = variant.talla;
        selectedColor = variant.color;
        selectedVariantId = variant.id_variante;
        document.getElementById('selectedVariant').value = variant.id_variante;
        updateVariantSelection();
    }
    
    // Formulario de agregar al carrito
    document.getElementById('addToCartForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!selectedVariantId && variants.length > 1) {
            alert('Por favor selecciona una talla y color');
            return;
        }
        
        if (variants.length === 0) {
            alert('Este producto no tiene variantes disponibles');
            return;
        }
        
        const variantId = variants.length === 1 ? variants[0].id_variante : selectedVariantId;
        const quantity = document.getElementById('productQuantity').value;
        
        addToCart(variantId, quantity);
    });
    
    // Botón comprar ahora
    document.getElementById('buyNowBtn').addEventListener('click', function() {
        if (!selectedVariantId && variants.length > 1) {
            alert('Por favor selecciona una talla y color');
            return;
        }
        
        if (variants.length === 0) {
            alert('Este producto no tiene variantes disponibles');
            return;
        }
        
        const variantId = variants.length === 1 ? variants[0].id_variante : selectedVariantId;
        const quantity = document.getElementById('productQuantity').value;
        
        // Agregar al carrito y redirigir a checkout
        fetch('/api/carrito.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add',
                id_variante: variantId,
                cantidad: quantity,
                csrf_token: window.csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '<?php echo BASE_URL; ?>cliente/checkout.php';
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al agregar al carrito');
        });
    });
    
    // Botón de notificación
    document.getElementById('notifyBtn')?.addEventListener('click', function() {
        const email = prompt('Ingresa tu email para ser notificado cuando este producto esté disponible:');
        if (email && validateEmail(email)) {
            fetch('/api/productos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'notify_stock',
                    product_id: <?php echo $producto['id_producto_raiz']; ?>,
                    email: email,
                    csrf_token: window.csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Te notificaremos cuando este producto esté disponible');
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al registrar la notificación');
            });
        }
    });
    
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
});
</script>