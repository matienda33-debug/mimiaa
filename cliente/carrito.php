<?php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Iniciar sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el carrito está vacío
$carrito_vacio = true;
$carrito_total = 0;
$carrito_items = 0;

if (isset($_SESSION['carrito']) && count($_SESSION['carrito']) > 0) {
    $carrito_vacio = false;
    $carrito_items = array_sum(array_column($_SESSION['carrito'], 'cantidad'));
    $carrito_total = $_SESSION['carrito_total'] ?? 0;
}

// Procesar actualización del carrito
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'update') {
            foreach ($_POST['cantidad'] as $variante_id => $cantidad) {
                $cantidad = (int)$cantidad;
                
                // Buscar el item en el carrito
                foreach ($_SESSION['carrito'] as $key => &$item) {
                    if ($item['variante_id'] == $variante_id) {
                        if ($cantidad <= 0) {
                            // Eliminar item
                            unset($_SESSION['carrito'][$key]);
                        } else {
                            // Verificar stock
                            $stock_query = "SELECT (stock_tienda + stock_bodega) as stock_total 
                                          FROM productos_variantes 
                                          WHERE id_variante = :id";
                            $stmt = $db->prepare($stock_query);
                            $stmt->bindParam(':id', $variante_id);
                            $stmt->execute();
                            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($cantidad <= $stock['stock_total']) {
                                $item['cantidad'] = $cantidad;
                            } else {
                                $item['cantidad'] = $stock['stock_total'];
                            }
                        }
                        break;
                    }
                }
            }
            
            // Reindexar array
            $_SESSION['carrito'] = array_values($_SESSION['carrito']);
            
            // Recalcular total
            $carrito_total = 0;
            foreach ($_SESSION['carrito'] as $item) {
                $carrito_total += $item['precio'] * $item['cantidad'];
            }
            $_SESSION['carrito_total'] = $carrito_total;
            
            $success = "Carrito actualizado correctamente.";
        }
        elseif ($_POST['action'] == 'remove') {
            $variante_id = $_POST['variante_id'];
            
            // Buscar y eliminar item
            foreach ($_SESSION['carrito'] as $key => $item) {
                if ($item['variante_id'] == $variante_id) {
                    unset($_SESSION['carrito'][$key]);
                    break;
                }
            }
            
            // Reindexar array
            $_SESSION['carrito'] = array_values($_SESSION['carrito']);
            
            // Recalcular total
            $carrito_total = 0;
            foreach ($_SESSION['carrito'] as $item) {
                $carrito_total += $item['precio'] * $item['cantidad'];
            }
            $_SESSION['carrito_total'] = $carrito_total;
            
            $success = "Producto eliminado del carrito.";
        }
        elseif ($_POST['action'] == 'clear') {
            unset($_SESSION['carrito']);
            unset($_SESSION['carrito_total']);
            $carrito_vacio = true;
            $success = "Carrito vaciado correctamente.";
        }
    }
}

// Obtener puntos del cliente si está logueado
$puntos_disponibles = 0;
if (isset($_SESSION['cliente_id'])) {
    $puntos_query = "SELECT puntos FROM clientes WHERE id_cliente = :id";
    $stmt = $db->prepare($puntos_query);
    $stmt->bindParam(':id', $_SESSION['cliente_id']);
    $stmt->execute();
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    $puntos_disponibles = $cliente['puntos'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <style>
        .cart-item {
            border-bottom: 1px solid #dee2e6;
            padding: 15px 0;
        }
        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }
        .quantity-input {
            width: 70px;
        }
        .summary-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .empty-cart {
            text-align: center;
            padding: 50px 0;
        }
        .empty-cart i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-5">
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <h1 class="mb-4">Carrito de Compras</h1>
        
        <?php if ($carrito_vacio): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h3>Tu carrito está vacío</h3>
                <p class="text-muted mb-4">Agrega algunos productos para comenzar a comprar.</p>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i> Continuar Comprando
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Lista de productos -->
                <div class="col-lg-8">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update">
                        
                        <div class="card">
                            <div class="card-body">
                                <?php foreach ($_SESSION['carrito'] as $item): 
                                    $imagen = $item['imagen'] ? 
                                              '../' . IMG_DIR . 'productos/' . htmlspecialchars($item['imagen']) : 
                                              'https://via.placeholder.com/80';
                                ?>
                                <div class="cart-item">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <img src="<?php echo $imagen; ?>" alt="Producto" class="cart-item-image">
                                        </div>
                                        <div class="col-md-6">
                                            <h6><?php echo $item['nombre']; ?></h6>
                                            <p class="text-muted mb-1">
                                                Color: <?php echo $item['color']; ?> | 
                                                Talla: <?php echo $item['talla']; ?>
                                            </p>
                                            <p class="price mb-0"><?php echo formatMoney($item['precio']); ?></p>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" name="cantidad[<?php echo $item['variante_id']; ?>]" 
                                                   value="<?php echo $item['cantidad']; ?>" 
                                                   min="1" class="form-control quantity-input">
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <p class="mb-2"><strong><?php echo formatMoney($item['precio'] * $item['cantidad']); ?></strong></p>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="removeItem(<?php echo $item['variante_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-sync-alt me-2"></i> Actualizar Carrito
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="clearCart()">
                                        <i class="fas fa-trash me-2"></i> Vaciar Carrito
                                    </button>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i> Continuar Comprando
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Resumen del pedido -->
                <div class="col-lg-4">
                    <div class="summary-card sticky-top" style="top: 20px;">
                        <h5 class="mb-3">Resumen del Pedido</h5>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span><?php echo formatMoney($carrito_total); ?></span>
                            </div>
                            
                            <?php if (isset($_SESSION['carrito_descuento'])): ?>
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>Descuento por puntos</span>
                                <span>-<?php echo formatMoney($_SESSION['carrito_descuento']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Envío</span>
                                <span class="text-success">Gratis</span>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <strong>Total</strong>
                                <strong>
                                    <?php 
                                        $total_final = $carrito_total;
                                        if (isset($_SESSION['carrito_descuento'])) {
                                            $total_final -= $_SESSION['carrito_descuento'];
                                        }
                                        echo formatMoney($total_final);
                                    ?>
                                </strong>
                            </div>
                        </div>
                        
                        <!-- Uso de puntos -->
                        <?php if (isset($_SESSION['cliente_id']) && $puntos_disponibles > 0): 
                            $valor_puntos = valorEnPuntos($puntos_disponibles);
                        ?>
                        <div class="mb-4">
                            <h6 class="mb-2">
                                <i class="fas fa-coins text-warning me-2"></i>
                                Tus Puntos: <?php echo $puntos_disponibles; ?> 
                                (<?php echo formatMoney($valor_puntos); ?>)
                            </h6>
                            
                            <div class="input-group mb-2">
                                <input type="number" class="form-control" id="puntos_usar" 
                                       min="0" max="<?php echo $puntos_disponibles; ?>" 
                                       value="0" step="30">
                                <button class="btn btn-outline-warning" type="button" onclick="aplicarPuntos()">
                                    Aplicar
                                </button>
                            </div>
                            <small class="text-muted">30 puntos = Q1 de descuento</small>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Opciones de pago -->
                        <div class="mb-4">
                            <h6 class="mb-2">Método de Pago</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="metodo_pago" 
                                       id="transferencia" value="transferencia" checked>
                                <label class="form-check-label" for="transferencia">
                                    Transferencia bancaria
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="metodo_pago" 
                                       id="contraentrega" value="contraentrega">
                                <label class="form-check-label" for="contraentrega">
                                    Pago contraentrega
                                </label>
                            </div>
                            <div class="alert alert-info py-2 px-3 mb-0 small">
                                En la web solo manejamos transferencia bancaria o pago contraentrega. 
                                Se solicita pago de envío por adelantado, si es pago contra entrega. El pago con tarjeta está disponible únicamente en tienda física.
                            </div>
                        </div>
                        
                        <!-- Tipo de entrega -->
                        <div class="mb-4">
                            <h6 class="mb-2">Tipo de Entrega</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="tipo_entrega" 
                                       id="recoger" value="recoger" checked>
                                <label class="form-check-label" for="recoger">
                                    Recoger en tienda
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_entrega" 
                                       id="envio" value="envio">
                                <label class="form-check-label" for="envio">
                                    Envío a domicilio
                                </label>
                            </div>
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="d-grid gap-2">
                            <a href="checkout.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-shopping-bag me-2"></i> Proceder al Pago
                            </a>
                            <a href="comprar_rapido.php" class="btn btn-outline-primary">
                                <i class="fas fa-bolt me-2"></i> Comprar Rápido
                            </a>
                        </div>
                        
                        <!-- Puntos que ganarás -->
                        <?php 
                            $puntos_ganados = calcularPuntos($total_final);
                            if ($puntos_ganados > 0):
                        ?>
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="fas fa-coins text-warning"></i>
                                Ganarás <?php echo $puntos_ganados; ?> puntos con esta compra
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Formularios ocultos -->
    <form id="removeForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="variante_id" id="remove_variante_id">
    </form>
    
    <form id="clearForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="clear">
    </form>
    
    <script>
        function removeItem(varianteId) {
            if (confirm('¿Está seguro de eliminar este producto del carrito?')) {
                document.getElementById('remove_variante_id').value = varianteId;
                document.getElementById('removeForm').submit();
            }
        }
        
        function clearCart() {
            if (confirm('¿Está seguro de vaciar todo el carrito?')) {
                document.getElementById('clearForm').submit();
            }
        }
        
        function aplicarPuntos() {
            const puntosUsar = parseInt(document.getElementById('puntos_usar').value);
            if (puntosUsar > 0 && puntosUsar % 30 === 0) {
                // Enviar solicitud AJAX para aplicar puntos
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'api/aplicar_puntos.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert('Puntos aplicados correctamente');
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                };
                xhr.send('puntos=' + puntosUsar);
            } else {
                alert('Debe ingresar múltiplos de 30 puntos');
            }
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>