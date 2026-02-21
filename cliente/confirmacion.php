<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isset($_SESSION)) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

// Obtener ID de producto
$id_producto = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Si no está logueado, redirigir a login
if (!isset($_SESSION['id_usuario'])) {
    if ($id_producto == 0) {
        header('Location: index.php');
    } else {
        $redirectUrl = urlencode('confirmacion.php?id=' . $id_producto);
        header('Location: login.php?redirect=' . $redirectUrl);
    }
    exit;
}

if ($id_producto == 0) {
    header('Location: index.php');
    exit;
}

// Obtener datos del producto
$producto_query = "SELECT pr.* FROM productos_raiz pr WHERE pr.id_raiz = :id AND pr.activo = 1";
$producto_stmt = $db->prepare($producto_query);
$producto_stmt->bindParam(':id', $id_producto, PDO::PARAM_INT);
$producto_stmt->execute();
$producto = $producto_stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    header('Location: index.php');
    exit;
}

// Obtener variantes
$variantes_query = "SELECT * FROM productos_variantes 
                   WHERE id_producto_raiz = :id AND activo = 1";
$variantes_stmt = $db->prepare($variantes_query);
$variantes_stmt->bindParam(':id', $id_producto, PDO::PARAM_INT);
$variantes_stmt->execute();
$variantes = $variantes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener dirección del cliente
$cliente_query = "SELECT * FROM clientes WHERE id_cliente = :id";
$cliente_stmt = $db->prepare($cliente_query);
$cliente_stmt->bindParam(':id', $_SESSION['id_usuario']);
$cliente_stmt->execute();
$cliente = $cliente_stmt->fetch(PDO::FETCH_ASSOC);

$carrito_count = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Compra - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .checkout-header {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 40px;
        }
        .payment-option {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-option:hover {
            border-color: #1abc9c;
        }
        .payment-option.active {
            border-color: #1abc9c;
            background: #f0fffe;
        }
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        .summary-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="checkout-header text-center">
        <div class="container">
            <h1>Confirmar Compra</h1>
            <p class="text-white-50">Paso final para completar tu pedido</p>
        </div>
    </div>
    
    <div class="container py-5">
        <div class="row">
            <!-- Formulario de compra -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5><i class="fas fa-box me-2"></i> Resumen del Producto</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <img src="../uploads/productos/<?php echo $producto['imagen_principal'] ?? 'no-image.jpg'; ?>" 
                                     class="img-fluid rounded" alt="Producto">
                            </div>
                            <div class="col-md-9">
                                <h5><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 100)); ?>...</p>
                                <p class="price">
                                    Precio: <strong>Q<?php echo number_format($producto['precio_venta'], 2); ?></strong>
                                </p>
                                
                                <?php if (!empty($variantes)): ?>
                                <div class="mb-3">
                                    <label class="form-label">Selecciona Variante:</label>
                                    <select class="form-select" id="variante" required>
                                        <option value="">-- Selecciona --</option>
                                        <?php foreach ($variantes as $v): ?>
                                        <?php
                                            $stock_total = (int)$v['stock_tienda'] + (int)$v['stock_bodega'];
                                            $precio_variante = (float)$v['precio_venta'];
                                        ?>
                                        <option value="<?php echo $v['id_variante']; ?>" data-stock="<?php echo $stock_total; ?>" data-precio="<?php echo $precio_variante; ?>">
                                            <?php echo htmlspecialchars($v['nombre']); ?> 
                                            (Stock: <?php echo $stock_total; ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label">Cantidad:</label>
                                    <input type="number" class="form-control" id="cantidad" value="1" min="1">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Dirección de envío -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5><i class="fas fa-map-marker-alt me-2"></i> Dirección de Envío</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Calle</label>
                                    <input type="text" class="form-control" id="calle" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Número</label>
                                    <input type="text" class="form-control" id="numero" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ciudad</label>
                                    <input type="text" class="form-control" id="ciudad" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Código Postal</label>
                                    <input type="text" class="form-control" id="codigo_postal" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notas Adicionales</label>
                            <textarea class="form-control" id="notas" rows="3" placeholder="Instrucciones especiales de envío"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Método de pago -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5><i class="fas fa-credit-card me-2"></i> Método de Pago</h5>
                    </div>
                    <div class="card-body">
                        <div class="payment-option active" onclick="selectPayment('tarjeta')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metodo_pago" 
                                       id="tarjeta" value="tarjeta" checked>
                                <label class="form-check-label w-100">
                                    <i class="fas fa-credit-card me-2"></i>
                                    <strong>Tarjeta de Crédito/Débito</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div class="payment-option" onclick="selectPayment('transferencia')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metodo_pago" 
                                       id="transferencia" value="transferencia">
                                <label class="form-check-label w-100">
                                    <i class="fas fa-university me-2"></i>
                                    <strong>Transferencia Bancaria</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div class="payment-option" onclick="selectPayment('efectivo')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metodo_pago" 
                                       id="efectivo" value="efectivo">
                                <label class="form-check-label w-100">
                                    <i class="fas fa-money-bill me-2"></i>
                                    <strong>Efectivo al Recibir</strong>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resumen de compra -->
            <div class="col-md-4">
                <div class="order-summary position-sticky" style="top: 20px;">
                    <h5 class="mb-4">Resumen de Compra</h5>
                    
                    <div class="summary-item">
                        <span>Subtotal:</span>
                        <span id="subtotal">Q0.00</span>
                    </div>
                    
                    <div class="summary-item">
                        <span>Impuesto (12%):</span>
                        <span id="impuesto">Q0.00</span>
                    </div>
                    
                    <div class="summary-item">
                        <span>Envío:</span>
                        <span id="envio">Gratis</span>
                    </div>
                    
                    <div class="summary-item">
                        <span>Total:</span>
                        <span id="total">Q<?php echo number_format($producto['precio_venta'], 2); ?></span>
                    </div>
                    
                    <button class="btn btn-success w-100 btn-lg mt-4" onclick="confirmarCompra()">
                        <i class="fas fa-check me-2"></i> Confirmar Compra
                    </button>
                    
                    <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">
                        <i class="fas fa-arrow-left me-2"></i> Volver al Catálogo
                    </a>
                    
                    <p class="text-muted text-center mt-3 small">
                        <i class="fas fa-lock me-1"></i> Tu información está segura
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const precioBase = <?php echo $producto['precio_venta']; ?>;
        let precioActual = precioBase;
        let stockActual = null;
        
        function selectPayment(method) {
            document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('active'));
            event.target.closest('.payment-option').classList.add('active');
            document.getElementById(method).checked = true;
        }
        
        function calcularTotal() {
            const cantidad = parseInt(document.getElementById('cantidad').value) || 1;
            const subtotal = precioActual * cantidad;
            const impuesto = subtotal * 0.12;
            const total = subtotal + impuesto;
            
            document.getElementById('subtotal').textContent = 'Q' + subtotal.toFixed(2);
            document.getElementById('impuesto').textContent = 'Q' + impuesto.toFixed(2);
            document.getElementById('total').textContent = 'Q' + total.toFixed(2);
        }
        
        document.getElementById('cantidad').addEventListener('change', function() {
            if (stockActual !== null) {
                const cantidad = parseInt(this.value) || 1;
                if (cantidad > stockActual) {
                    this.value = stockActual;
                }
            }
            calcularTotal();
        });

        const varianteSelect = document.getElementById('variante');
        if (varianteSelect) {
            varianteSelect.addEventListener('change', function() {
                const selected = this.options[this.selectedIndex];
                const precioVariante = parseFloat(selected?.dataset?.precio || 0);
                stockActual = parseInt(selected?.dataset?.stock || '0', 10);
                precioActual = precioVariante > 0 ? precioVariante : precioBase;

                const cantidadInput = document.getElementById('cantidad');
                if (cantidadInput && stockActual) {
                    cantidadInput.max = stockActual;
                    if (parseInt(cantidadInput.value) > stockActual) {
                        cantidadInput.value = stockActual;
                    }
                }

                calcularTotal();
            });
        }
        
        function confirmarCompra() {
            if (stockActual !== null) {
                const cantidadActual = parseInt(document.getElementById('cantidad').value) || 1;
                if (cantidadActual > stockActual) {
                    alert('Stock insuficiente');
                    return;
                }
            }

            const forma = new FormData();
            forma.append('id_producto', <?php echo $id_producto; ?>);
            forma.append('cantidad', document.getElementById('cantidad').value);
            forma.append('variante', document.getElementById('variante')?.value || '');
            forma.append('calle', document.getElementById('calle').value);
            forma.append('numero', document.getElementById('numero').value);
            forma.append('ciudad', document.getElementById('ciudad').value);
            forma.append('codigo_postal', document.getElementById('codigo_postal').value);
            forma.append('notas', document.getElementById('notas').value);
            forma.append('metodo_pago', document.querySelector('input[name="metodo_pago"]:checked').value);
            
            fetch('confirmar_compra.php', {
                method: 'POST',
                body: forma
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Compra realizada correctamente. Número de factura: ' + data.factura);
                    window.location.href = 'historial.php';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(e => alert('Error en la transacción'));
        }
    </script>
</body>
</html>
