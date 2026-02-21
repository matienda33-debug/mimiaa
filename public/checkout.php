<?php
// public/checkout.php
require_once '../app/config/database.php';
require_once '../app/models/Auth.php';
require_once '../app/models/Carrito.php';
require_once '../app/models/Cliente.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$carritoModel = new Carrito($db);
$clienteModel = new Cliente($db);

// Verificar que haya items en el carrito
if (!isset($_SESSION['cart_session_id'])) {
    header('Location: index.php');
    exit();
}

$cart_session_id = $_SESSION['cart_session_id'];
$carrito = $carritoModel->obtenerCarrito($cart_session_id);
$total_carrito = $carritoModel->obtenerTotalCarrito($cart_session_id);

if (empty($carrito)) {
    header('Location: carrito.php');
    exit();
}

// Obtener información del cliente si está logueado
$cliente_info = null;
$puntos_disponibles = 0;

if ($auth->isLoggedIn()) {
    $user_info = $auth->getUserInfo();
    $cliente_info = $clienteModel->obtenerClientePorUsuario($user_info['id']);
    
    if ($cliente_info && $cliente_info['dpi']) {
        $puntos_info = $clienteModel->obtenerPuntosCliente($cliente_info['dpi']);
        $puntos_disponibles = $puntos_info['puntos_disponibles'] ?? 0;
    }
}

// Procesar checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'session_id' => $cart_session_id,
        'tipo_venta' => $_POST['tipo_venta'],
        'tipo_entrega' => $_POST['tipo_entrega'],
        'id_vendedor' => $auth->isLoggedIn() ? $user_info['id'] : null,
        'id_cliente' => $cliente_info ? $cliente_info['id_persona'] : null,
        'nombre_cliente' => $_POST['nombre_cliente'],
        'dpi_cliente' => $_POST['dpi_cliente'] ?: null,
        'direccion_entrega' => $_POST['direccion_entrega'] ?: null,
        'telefono_cliente' => $_POST['telefono_cliente'],
        'referencia_envio' => $_POST['referencia_envio'] ?: null,
        'subtotal' => $total_carrito['total'],
        'descuento' => $_POST['descuento_puntos'] ?? 0,
        'total' => $_POST['total_final'],
        'puntos_generados' => $_POST['puntos_generados'] ?? 0,
        'puntos_usados' => $_POST['puntos_usados'] ?? 0,
        'estado' => 'pendiente',
        'metodo_pago' => $_POST['metodo_pago'],
        'numero_orden' => 'ORD-' . date('Ymd-His')
    ];
    
    $result = $carritoModel->procesarVenta($data);
    
    if ($result['success']) {
        // Redirigir a confirmación
        $_SESSION['factura_numero'] = $result['numero_factura'];
        header('Location: confirmacion.php?factura=' . $result['id_factura']);
        exit();
    } else {
        $error_message = $result['message'];
    }
}

// Calcular valores
$subtotal = $total_carrito['total'];
$envio = $_POST['tipo_entrega'] == 'envio' ? 35.00 : 0;
$descuento_puntos = 0;
$puntos_usados = 0;

// Calcular descuento por puntos si se usan
if (isset($_POST['usar_puntos']) && $puntos_disponibles > 0) {
    // Cada 30 puntos = Q1
    $puntos_usados = min($puntos_disponibles, $_POST['puntos_usados'] ?? 0);
    $descuento_puntos = floor($puntos_usados / 30);
}

$total = $subtotal + $envio - $descuento_puntos;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Tienda MI&MI Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .checkout-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 50px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            border: 3px solid white;
        }
        .step.active .step-circle {
            background: #667eea;
            color: white;
        }
        .step.completed .step-circle {
            background: #10b981;
            color: white;
        }
        .step-label {
            font-size: 14px;
            color: #666;
        }
        .step.active .step-label {
            color: #667eea;
            font-weight: bold;
        }
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        .summary-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .summary-total {
            border-top: 2px solid #dee2e6;
            padding-top: 15px;
            margin-top: 15px;
            font-size: 18px;
            font-weight: bold;
        }
        .payment-method {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-method:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .payment-method.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .payment-method input[type="radio"] {
            margin-right: 10px;
        }
        .product-checkout {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .product-checkout img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            margin-right: 15px;
        }
        .points-info {
            background: linear-gradient(135deg, #ffd166, #ffb347);
            color: #333;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .btn-checkout {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            width: 100%;
            transition: transform 0.3s;
        }
        .btn-checkout:hover {
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="checkout-container">
                    <div class="step-indicator">
                        <div class="step completed">
                            <div class="step-circle">1</div>
                            <div class="step-label">Carrito</div>
                        </div>
                        <div class="step active">
                            <div class="step-circle">2</div>
                            <div class="step-label">Información</div>
                        </div>
                        <div class="step">
                            <div class="step-circle">3</div>
                            <div class="step-label">Pago</div>
                        </div>
                        <div class="step">
                            <div class="step-circle">4</div>
                            <div class="step-label">Confirmación</div>
                        </div>
                    </div>
                    
                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="checkoutForm">
                        <!-- Información del Cliente -->
                        <div class="form-section">
                            <h4 class="mb-4">Información del Cliente</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre Completo *</label>
                                    <input type="text" class="form-control" name="nombre_cliente" 
                                           value="<?php echo $cliente_info ? htmlspecialchars($cliente_info['nombres'] . ' ' . $cliente_info['apellidos']) : ''; ?>"
                                           required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Teléfono *</label>
                                    <input type="tel" class="form-control" name="telefono_cliente" 
                                           value="<?php echo $cliente_info ? htmlspecialchars($cliente_info['telefono'] ?? '') : ''; ?>"
                                           required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">DPI (13 dígitos)</label>
                                    <input type="text" class="form-control" name="dpi_cliente" 
                                           value="<?php echo $cliente_info ? htmlspecialchars($cliente_info['dpi'] ?? '') : ''; ?>"
                                           pattern="\d{13}" maxlength="13"
                                           placeholder="Para acumular puntos">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email_cliente" 
                                           value="<?php echo $auth->isLoggedIn() ? htmlspecialchars($_SESSION['email']) : ''; ?>"
                                           required>
                                </div>
                            </div>
                            
                            <?php if ($puntos_disponibles > 0): ?>
                            <div class="points-info">
                                <h5><i class="fas fa-gift me-2"></i>Tienes <?php echo $puntos_disponibles; ?> puntos disponibles</h5>
                                <p class="mb-2">Cada 30 puntos equivalen a Q1 de descuento</p>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="usar_puntos" id="usarPuntos">
                                    <label class="form-check-label" for="usarPuntos">
                                        Usar puntos para esta compra
                                    </label>
                                </div>
                                <div id="puntosContainer" style="display: none; margin-top: 10px;">
                                    <label class="form-label">Puntos a usar (máx: <?php echo $puntos_disponibles; ?>)</label>
                                    <input type="range" class="form-range" id="puntosSlider" 
                                           min="0" max="<?php echo $puntos_disponibles; ?>" 
                                           step="30" value="0">
                                    <div class="d-flex justify-content-between">
                                        <span id="puntosValue">0 puntos</span>
                                        <span id="descuentoValue">Q0.00 de descuento</span>
                                    </div>
                                    <input type="hidden" name="puntos_usados" id="puntosUsados" value="0">
                                    <input type="hidden" name="descuento_puntos" id="descuentoPuntos" value="0">
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Método de Entrega -->
                        <div class="form-section">
                            <h4 class="mb-4">Método de Entrega</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="payment-method" onclick="selectEntrega('recoger')">
                                        <input type="radio" name="tipo_entrega" value="recoger" id="recoger" 
                                               <?php echo (!isset($_POST['tipo_entrega']) || $_POST['tipo_entrega'] == 'recoger') ? 'checked' : ''; ?>>
                                        <label for="recoger" class="form-check-label w-100">
                                            <strong><i class="fas fa-store me-2"></i>Recoger en Tienda</strong>
                                            <p class="mb-0 text-muted">Sin costo adicional</p>
                                            <small>Dirección: Ciudad, Zona, Dirección</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="payment-method" onclick="selectEntrega('envio')">
                                        <input type="radio" name="tipo_entrega" value="envio" id="envio"
                                               <?php echo (isset($_POST['tipo_entrega']) && $_POST['tipo_entrega'] == 'envio') ? 'checked' : ''; ?>>
                                        <label for="envio" class="form-check-label w-100">
                                            <strong><i class="fas fa-truck me-2"></i>Envío a Domicilio</strong>
                                            <p class="mb-0 text-muted">Costo: Q35.00</p>
                                            <small>Entrega en 2-3 días hábiles</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="direccionContainer" style="display: <?php echo (isset($_POST['tipo_entrega']) && $_POST['tipo_entrega'] == 'envio') ? 'block' : 'none'; ?>;">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Dirección de Envío *</label>
                                        <textarea class="form-control" name="direccion_entrega" rows="3"></textarea>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Referencias</label>
                                        <textarea class="form-control" name="referencia_envio" rows="2" 
                                                  placeholder="Puntos de referencia, color de casa, etc."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Método de Pago -->
                        <div class="form-section">
                            <h4 class="mb-4">Método de Pago</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="payment-method" onclick="selectPago('efectivo')">
                                        <input type="radio" name="metodo_pago" value="efectivo" id="efectivo" checked>
                                        <label for="efectivo" class="form-check-label w-100">
                                            <strong><i class="fas fa-money-bill-wave me-2"></i>Efectivo</strong>
                                            <p class="mb-0 text-muted">Paga al momento de recibir</p>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="payment-method" onclick="selectPago('tarjeta')">
                                        <input type="radio" name="metodo_pago" value="tarjeta" id="tarjeta">
                                        <label for="tarjeta" class="form-check-label w-100">
                                            <strong><i class="fas fa-credit-card me-2"></i>Tarjeta de Crédito/Débito</strong>
                                            <p class="mb-0 text-muted">Pago seguro en línea</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="tarjetaContainer" style="display: none;">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Número de Tarjeta</label>
                                        <input type="text" class="form-control" name="numero_tarjeta" 
                                               placeholder="1234 5678 9012 3456">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Fecha de Expiración</label>
                                        <input type="text" class="form-control" name="expiracion_tarjeta" 
                                               placeholder="MM/AA">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">CVV</label>
                                        <input type="text" class="form-control" name="cvv_tarjeta" 
                                               placeholder="123">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Nombre en la Tarjeta</label>
                                        <input type="text" class="form-control" name="nombre_tarjeta">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tipo de Venta (oculto, definido por sistema) -->
                        <input type="hidden" name="tipo_venta" value="<?php echo $auth->isLoggedIn() && $_SESSION['nivel_acceso'] >= 60 ? 'tienda' : 'online'; ?>">
                        <input type="hidden" name="subtotal" value="<?php echo $subtotal; ?>">
                        <input type="hidden" name="puntos_generados" id="puntosGenerados" value="0">
                        <input type="hidden" name="total_final" id="totalFinal" value="<?php echo $total; ?>">
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="carrito.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Volver al Carrito
                            </a>
                            <button type="submit" class="btn btn-checkout">
                                <i class="fas fa-lock me-2"></i>Finalizar Compra
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Resumen del Pedido -->
            <div class="col-lg-4">
                <div class="checkout-container">
                    <h4 class="mb-4">Resumen del Pedido</h4>
                    
                    <div class="summary-card">
                        <h5>Productos (<?php echo $total_carrito['items'] ?? 0; ?>)</h5>
                        <?php foreach ($carrito as $item): ?>
                        <div class="product-checkout">
                            <img src="assets/img/productos/<?php echo $item['foto'] ?? 'default.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($item['producto_nombre']); ?>">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($item['producto_nombre']); ?></h6>
                                <p class="mb-1 text-muted">
                                    <small>Talla: <?php echo $item['talla']; ?> | Color: <?php echo $item['color']; ?></small>
                                </p>
                                <p class="mb-0">
                                    <?php echo $item['cantidad']; ?> x Q<?php echo number_format($item['precio_unitario'], 2); ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-item">
                            <span>Subtotal:</span>
                            <span>Q<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="summary-item" id="envioItem">
                            <span>Envío:</span>
                            <span>Q<?php echo number_format($envio, 2); ?></span>
                        </div>
                        <div class="summary-item" id="descuentoItem" style="display: none;">
                            <span>Descuento por puntos:</span>
                            <span class="text-success">-Q<span id="descuentoDisplay">0.00</span></span>
                        </div>
                        <div class="summary-item summary-total">
                            <span>Total:</span>
                            <span class="text-primary">Q<span id="totalDisplay"><?php echo number_format($total, 2); ?></span></span>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>Al finalizar la compra recibirás un correo con los detalles de tu pedido y las instrucciones para seguimiento.</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <small>Los pedidos en línea deben ser confirmados dentro de 72 horas. Pasado este tiempo, la reserva se cancelará automáticamente.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Seleccionar método de entrega
        function selectEntrega(tipo) {
            document.getElementById(tipo).checked = true;
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            const direccionContainer = document.getElementById('direccionContainer');
            const envioItem = document.getElementById('envioItem');
            
            if (tipo === 'envio') {
                direccionContainer.style.display = 'block';
                // Mostrar costo de envío
                envioItem.querySelector('span:last-child').textContent = 'Q35.00';
                calcularTotal();
            } else {
                direccionContainer.style.display = 'none';
                // Ocultar costo de envío
                envioItem.querySelector('span:last-child').textContent = 'Q0.00';
                calcularTotal();
            }
        }
        
        // Seleccionar método de pago
        function selectPago(metodo) {
            document.getElementById(metodo).checked = true;
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            const tarjetaContainer = document.getElementById('tarjetaContainer');
            tarjetaContainer.style.display = metodo === 'tarjeta' ? 'block' : 'none';
        }
        
        // Manejar puntos
        document.getElementById('usarPuntos')?.addEventListener('change', function() {
            const puntosContainer = document.getElementById('puntosContainer');
            puntosContainer.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                document.getElementById('puntosUsados').value = 0;
                document.getElementById('descuentoPuntos').value = 0;
                calcularTotal();
            }
        });
        
        document.getElementById('puntosSlider')?.addEventListener('input', function() {
            const puntos = parseInt(this.value);
            const descuento = Math.floor(puntos / 30);
            
            document.getElementById('puntosValue').textContent = puntos + ' puntos';
            document.getElementById('descuentoValue').textContent = 'Q' + descuento.toFixed(2) + ' de descuento';
            document.getElementById('puntosUsados').value = puntos;
            document.getElementById('descuentoPuntos').value = descuento;
            
            calcularTotal();
        });
        
        // Calcular total
        function calcularTotal() {
            const subtotal = <?php echo $subtotal; ?>;
            const envio = document.querySelector('input[name="tipo_entrega"]:checked').value === 'envio' ? 35 : 0;
            const descuento = parseFloat(document.getElementById('descuentoPuntos').value) || 0;
            
            const total = subtotal + envio - descuento;
            
            // Actualizar display
            document.getElementById('totalDisplay').textContent = total.toFixed(2);
            document.getElementById('totalFinal').value = total;
            
            // Mostrar/ocultar descuento
            const descuentoItem = document.getElementById('descuentoItem');
            const descuentoDisplay = document.getElementById('descuentoDisplay');
            
            if (descuento > 0) {
                descuentoItem.style.display = 'flex';
                descuentoDisplay.textContent = descuento.toFixed(2);
            } else {
                descuentoItem.style.display = 'none';
            }
            
            // Calcular puntos que se generarán (20 puntos por cada Q20)
            const puntosPorCompra = Math.floor(total / 20);
            document.getElementById('puntosGenerados').value = puntosPorCompra;
        }
        
        // Inicializar selecciones
        document.querySelectorAll('.payment-method').forEach(el => {
            const input = el.querySelector('input[type="radio"]');
            if (input.checked) {
                el.classList.add('selected');
            }
        });
        
        // Calcular total inicial
        calcularTotal();
        
        // Validar formulario
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const tipoEntrega = document.querySelector('input[name="tipo_entrega"]:checked').value;
            const metodoPago = document.querySelector('input[name="metodo_pago"]:checked').value;
            
            if (tipoEntrega === 'envio') {
                const direccion = document.querySelector('textarea[name="direccion_entrega"]').value.trim();
                if (!direccion) {
                    e.preventDefault();
                    alert('Por favor ingresa la dirección de envío');
                    return false;
                }
            }
            
            if (metodoPago === 'tarjeta') {
                const numero = document.querySelector('input[name="numero_tarjeta"]').value.trim();
                const expiracion = document.querySelector('input[name="expiracion_tarjeta"]').value.trim();
                const cvv = document.querySelector('input[name="cvv_tarjeta"]').value.trim();
                const nombre = document.querySelector('input[name="nombre_tarjeta"]').value.trim();
                
                if (!numero || !expiracion || !cvv || !nombre) {
                    e.preventDefault();
                    alert('Por favor completa todos los datos de la tarjeta');
                    return false;
                }
            }
            
            return true;
        });
    </script>
</body>
</html>