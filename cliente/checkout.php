<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/email_config.php';
require_once '../config/invoice_pdf.php';

$database = new Database();
$db = $database->getConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que hay productos en el carrito
if (!isset($_SESSION['carrito']) || count($_SESSION['carrito']) == 0) {
    header('Location: carrito.php');
    exit();
}

$carrito_total = 0;
$total_items = 0;
foreach ($_SESSION['carrito'] as $item_carrito) {
    $precio_item = isset($item_carrito['precio']) ? (float)$item_carrito['precio'] : 0;
    $cantidad_item = isset($item_carrito['cantidad']) ? (int)$item_carrito['cantidad'] : 0;
    $carrito_total += ($precio_item * $cantidad_item);
    $total_items += $cantidad_item;
}

$_SESSION['carrito_total'] = $carrito_total;
$descuento_puntos = $_SESSION['carrito_descuento'] ?? 0;
$total_final = $carrito_total - $descuento_puntos;

function obtenerIdEstadoPorNombre($db, $nombre, $fallback = 1) {
    try {
        $stmt = $db->prepare("SELECT id_estado FROM estado_factura WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1");
        $stmt->bindParam(':nombre', $nombre);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id_estado'])) {
            return (int)$row['id_estado'];
        }
    } catch (Exception $e) {
    }

    return (int)$fallback;
}

// Procesar checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = sanitize($_POST['nombre']);
    $apellido = sanitize($_POST['apellido']);
    $email = sanitize($_POST['email']);
    $telefono = sanitize($_POST['telefono']);
    $dpi = sanitize($_POST['dpi']);
    $direccion = sanitize($_POST['direccion']);
    $referencia = sanitize($_POST['referencia']);
    $tipo_entrega = $_POST['tipo_entrega'];
    $metodo_pago = $_POST['metodo_pago'];
    $notas = sanitize($_POST['notas']);
    $acumular_puntos = isset($_POST['acumular_puntos']) ? 1 : 0;

    $metodos_pago_validos = ['transferencia', 'contraentrega'];
    
    try {
        if (!in_array($metodo_pago, $metodos_pago_validos, true)) {
            throw new Exception('Método de pago no válido para compras en línea.');
        }

        $db->beginTransaction();
        
        // Buscar o crear cliente solo por DPI
        $cliente_id = null;
        $cliente = null;

        if (!empty($dpi) && strlen($dpi) == 13) {
            $query = "SELECT id_cliente FROM clientes WHERE dpi = :dpi";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':dpi', $dpi);
            $stmt->execute();
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($cliente && isset($cliente['id_cliente'])) {
            $cliente_id = $cliente['id_cliente'];
        } elseif (!empty($dpi) && strlen($dpi) == 13) {
            // Crear nuevo cliente cuando hay DPI
            $insert_query = "INSERT INTO clientes (dpi, nombre, apellido, email, telefono, direccion, puntos) 
                           VALUES (:dpi, :nombre, :apellido, :email, :telefono, :direccion, 0)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':dpi', $dpi);
            $insert_stmt->bindParam(':nombre', $nombre);
            $insert_stmt->bindParam(':apellido', $apellido);
            $insert_stmt->bindParam(':email', $email);
            $insert_stmt->bindParam(':telefono', $telefono);
            $insert_stmt->bindParam(':direccion', $direccion);
            $insert_stmt->execute();
            $cliente_id = $db->lastInsertId();
        }
        
        // Generar número de factura y orden
        $numero_factura = 'ONL-' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $numero_orden = 'ORD-' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Generar llave de confirmación (válida por 72 horas)
        $llave_confirmacion = md5(uniqid() . time());
        $fecha_expiracion = date('Y-m-d H:i:s', strtotime('+72 hours'));
        
        // Insertar cabecera de factura
        $query = "INSERT INTO factura_cabecera (
            numero_factura, numero_orden, fecha, id_cliente, nombre_cliente,
            direccion, telefono, referencia_envio, tipo_venta, subtotal,
            descuento, total, puntos_usados, puntos_ganados, id_estado, llave_confirmacion,
            fecha_expiracion, correo_enviado
        ) VALUES (
            :numero_factura, :numero_orden, NOW(), :id_cliente, :nombre_cliente,
            :direccion, :telefono, :referencia, 'online', :subtotal,
            :descuento, :total, :puntos_usados, :puntos_ganados, :id_estado_inicial, :llave_confirmacion,
            :fecha_expiracion, 0
        )";
        
        $stmt = $db->prepare($query);
        $nombre_completo = $nombre . ' ' . $apellido;
        $puntos_usados = isset($_SESSION['puntos_usados']) ? $_SESSION['puntos_usados'] : 0;
        $puntos_ganados = ($cliente_id && $acumular_puntos) ? calcularPuntos($total_final) : 0;
        $id_estado_inicial = obtenerIdEstadoPorNombre($db, 'aceptado', 1);
        
        $stmt->bindParam(':numero_factura', $numero_factura);
        $stmt->bindParam(':numero_orden', $numero_orden);
        if ($cliente_id) {
            $stmt->bindValue(':id_cliente', (int)$cliente_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':id_cliente', null, PDO::PARAM_NULL);
        }
        $stmt->bindParam(':nombre_cliente', $nombre_completo);
        $stmt->bindParam(':direccion', $direccion);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':referencia', $referencia);
        $stmt->bindValue(':subtotal', $carrito_total);
        $stmt->bindValue(':descuento', $descuento_puntos);
        $stmt->bindValue(':total', $total_final);
        $stmt->bindValue(':puntos_usados', $puntos_usados);
        $stmt->bindValue(':puntos_ganados', $puntos_ganados);
        $stmt->bindValue(':id_estado_inicial', $id_estado_inicial, PDO::PARAM_INT);
        $stmt->bindParam(':llave_confirmacion', $llave_confirmacion);
        $stmt->bindParam(':fecha_expiracion', $fecha_expiracion);
        
        $stmt->execute();
        $factura_id = $db->lastInsertId();
        
        // Insertar detalles y actualizar inventario
        foreach ($_SESSION['carrito'] as $item) {
            // Verificar stock disponible
            $stock_query = "SELECT (stock_tienda + stock_bodega) as stock_total 
                          FROM productos_variantes 
                          WHERE id_variante = :id AND activo = 1";
            $stock_stmt = $db->prepare($stock_query);
            $stock_stmt->bindParam(':id', $item['variante_id']);
            $stock_stmt->execute();
            $stock = $stock_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stock['stock_total'] < $item['cantidad']) {
                throw new Exception("Stock insuficiente para: " . $item['nombre']);
            }
            
            // Insertar detalle
            $detalle_query = "INSERT INTO factura_detalle (
                id_factura, id_producto_variante, cantidad,
                precio_unitario, descuento_unitario, subtotal
            ) VALUES (
                :id_factura, :id_producto_variante, :cantidad,
                :precio_unitario, :descuento_unitario, :subtotal
            )";
            
            $detalle_stmt = $db->prepare($detalle_query);
            $detalle_subtotal = ((float)$item['precio']) * ((int)$item['cantidad']);
            $detalle_stmt->bindValue(':id_factura', (int)$factura_id, PDO::PARAM_INT);
            $detalle_stmt->bindValue(':id_producto_variante', (int)$item['variante_id'], PDO::PARAM_INT);
            $detalle_stmt->bindValue(':cantidad', (int)$item['cantidad'], PDO::PARAM_INT);
            $detalle_stmt->bindValue(':precio_unitario', (float)$item['precio']);
            $detalle_stmt->bindValue(':descuento_unitario', 0.00);
            $detalle_stmt->bindValue(':subtotal', $detalle_subtotal);
            $detalle_stmt->execute();
            
            // Reservar stock (no restar hasta confirmación)
            $reserva_query = "UPDATE productos_variantes 
                            SET stock_bodega = stock_bodega - :cantidad 
                            WHERE id_variante = :id";
            $reserva_stmt = $db->prepare($reserva_query);
            $reserva_stmt->bindParam(':cantidad', $item['cantidad']);
            $reserva_stmt->bindParam(':id', $item['variante_id']);
            $reserva_stmt->execute();
            
            // Registrar movimiento de reserva
            $movimiento_query = "INSERT INTO inventario_movimientos (
                id_producto_variante, tipo_movimiento, cantidad,
                ubicacion, motivo, id_usuario
            ) VALUES (
                :id_producto_variante, 'salida', :cantidad,
                'bodega', 'Reserva venta online #$numero_factura', NULL
            )";
            $movimiento_stmt = $db->prepare($movimiento_query);
            $movimiento_stmt->bindParam(':id_producto_variante', $item['variante_id']);
            $movimiento_stmt->bindParam(':cantidad', $item['cantidad']);
            $movimiento_stmt->execute();
        }
        
        // Actualizar puntos del cliente (restar usados y/o sumar ganados)
        if ($cliente_id && ($puntos_usados > 0 || $puntos_ganados > 0)) {
            $update_puntos = "UPDATE clientes 
                              SET puntos = GREATEST(puntos - :puntos_usados, 0) + :puntos_ganados 
                              WHERE id_cliente = :id_cliente";
            $update_stmt = $db->prepare($update_puntos);
            $update_stmt->bindValue(':puntos_usados', $puntos_usados, PDO::PARAM_INT);
            $update_stmt->bindValue(':puntos_ganados', $puntos_ganados, PDO::PARAM_INT);
            $update_stmt->bindParam(':id_cliente', $cliente_id);
            $update_stmt->execute();
        }
        
        $db->commit();
        
        // Enviar correo con factura e instrucciones de pago
        $correo_ok = enviarCorreoCompra($db, $email, $nombre_completo, $factura_id, $numero_factura, $metodo_pago, $total_final, $total_items);
        if ($correo_ok) {
            $update_email = $db->prepare("UPDATE factura_cabecera SET correo_enviado = 1 WHERE id_factura = :id");
            $update_email->bindValue(':id', (int)$factura_id, PDO::PARAM_INT);
            $update_email->execute();
        }
        
        // Limpiar carrito y redirigir a confirmación
        unset($_SESSION['carrito']);
        unset($_SESSION['carrito_total']);
        unset($_SESSION['carrito_descuento']);
        unset($_SESSION['puntos_usados']);
        
        // Guardar información para la página de confirmación
        $_SESSION['orden_completada'] = [
            'factura_id' => $factura_id,
            'numero_factura' => $numero_factura,
            'numero_orden' => $numero_orden,
            'total' => $total_final,
            'email' => $email
        ];
        
        header('Location: confirmacion_pedido.php');
        exit();
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Error al procesar la orden: " . $e->getMessage();
    }
}

function enviarCorreoCompra($db, $email, $nombre, $factura_id, $numero_factura, $metodo_pago, $total_final, $total_items) {
    $emailConfig = new EmailConfig();
    $subject = $numero_factura;

    $total_format = 'Q' . number_format($total_final, 2);
    $detalle_pago = '';

    if ($metodo_pago === 'transferencia') {
        $detalle_pago = "Por favor mandar captura de la transferencia adjuntado a este correo. Total a pagar: {$total_format}.";
    } elseif ($metodo_pago === 'contraentrega') {
        $detalle_pago = "Por favor mandar comprobante del envio adjuntado a este correo. Total a pagar: {$total_format}.";
    } else {
        $detalle_pago = "Total a pagar: {$total_format}.";
    }

    $pdf_info = generarFacturaPdf($db, $factura_id);
    $attachments = [];
    if ($pdf_info['ok'] && !empty($pdf_info['path']) && file_exists($pdf_info['path'])) {
        $attachments[] = [
            'path' => $pdf_info['path'],
            'name' => 'factura_' . $numero_factura . '.pdf'
        ];
    }

    $mensaje = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$subject}</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 700px; margin: 0 auto; padding: 20px; }
            .header { background: #1abc9c; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .badge { display: inline-block; background: #1abc9c; color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>" . SITE_NAME . "</h1>
                <p>Gracias por tu compra</p>
            </div>
            <div class='content'>
                <h2>Hola {$nombre},</h2>
                <p>Tu pedido fue registrado con la factura <span class='badge'>{$numero_factura}</span>.</p>
                <p><strong>Total de items:</strong> {$total_items}</p>
                <p><strong>Total a pagar:</strong> {$total_format}</p>
                <p>{$detalle_pago}</p>
                <p>Adjuntamos la factura en PDF con el detalle de productos.</p>
            </div>
            <div class='footer'>
                <p>" . SITE_NAME . " - " . AJITOS_NAME . "</p>
                <p>© " . date('Y') . " Todos los derechos reservados</p>
            </div>
        </div>
    </body>
    </html>";

    $ok = $emailConfig->sendEmail($email, $nombre, $subject, $mensaje, $attachments);
    if (!$ok) {
        $log_dir = '../logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        $log_file = $log_dir . 'correo_compra_' . date('Y-m-d') . '.log';
        $detalle_error = $emailConfig->getLastError();
        $log_content = date('Y-m-d H:i:s') . " | Para: {$email} | Factura: {$numero_factura} | Error: {$detalle_error}\n";
        file_put_contents($log_file, $log_content, FILE_APPEND);
    }

    return $ok;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .summary-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            position: sticky;
            top: 20px;
        }
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        .section-title {
            color: #1abc9c;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1abc9c;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        .step-number {
            width: 40px;
            height: 40px;
            background: #dee2e6;
            color: #666;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }
        .step.active .step-number {
            background: #1abc9c;
            color: white;
        }
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        .step-line {
            position: absolute;
            top: 20px;
            left: 60%;
            right: -40%;
            height: 2px;
            background: #dee2e6;
            z-index: -1;
        }
        .step:last-child .step-line {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-5">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Indicador de pasos -->
        <div class="step-indicator">
            <div class="step completed">
                <div class="step-number">1</div>
                <div>Carrito</div>
            </div>
            <div class="step active">
                <div class="step-number">2</div>
                <div>Datos</div>
                <div class="step-line"></div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div>Pago</div>
                <div class="step-line"></div>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <div>Confirmación</div>
            </div>
        </div>
        
        <div class="row">
            <!-- Formulario de datos -->
            <div class="col-lg-8">
                <form method="POST" action="">
                    <!-- Información personal -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user me-2"></i>
                            Información Personal
                        </h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="apellido" class="form-label">Apellido *</label>
                                <input type="text" class="form-control" id="apellido" name="apellido" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label">Teléfono *</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="dpi" class="form-label">DPI (13 dígitos) - Opcional</label>
                            <input type="text" class="form-control" id="dpi" name="dpi" 
                                   maxlength="13" pattern="[0-9]{13}">
                            <small class="text-muted">Ingresa tu DPI para acumular puntos</small>
                        </div>
                    </div>
                    
                    <!-- Información de entrega -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-truck me-2"></i>
                            Información de Entrega
                        </h3>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Entrega *</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="tipo_entrega" 
                                       id="recoger" value="recoger" checked>
                                <label class="form-check-label" for="recoger">
                                    <strong>Recoger en Tienda</strong><br>
                                    <small class="text-muted">Sin costo adicional. Dirección: Ciudad de Guatemala</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_entrega" 
                                       id="envio" value="envio">
                                <label class="form-check-label" for="envio">
                                    <strong>Envío a Domicilio</strong><br>
                                    <small class="text-muted">Costo: Q25. Entrega en 2-3 días hábiles (se paga al confirmar la compra, no por adelantado)</small>
                                </label>
                            </div>
                        </div>
                        <div id="direccionFields" style="display: none;">
                            <div class="mb-3">
                                <label for="direccion" class="form-label">Dirección Completa *</label>
                                <textarea class="form-control" id="direccion" name="direccion" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="referencia" class="form-label">Referencia</label>
                                <textarea class="form-control" id="referencia" name="referencia" rows="2"></textarea>
                                <small class="text-muted">Puntos de referencia para la entrega</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Método de pago -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-credit-card me-2"></i>
                            Método de Pago
                        </h3>
                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="metodo_pago" 
                                       id="transferencia" value="transferencia" checked>
                                <label class="form-check-label" for="transferencia">
                                    <strong>Transferencia Bancaria</strong><br>
                                    <small class="text-muted">Banco: Industrial | Cuenta: 123-456789-0</small>
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="metodo_pago" 
                                       id="contraentrega" value="contraentrega">
                                <label class="form-check-label" for="contraentrega">
                                    <strong>Pago Contraentrega</strong><br>
                                    <small class="text-muted">Pagas al recibir tu pedido o al recoger en tienda</small>
                                </label>
                            </div>
                            <div class="alert alert-info py-2 px-3 mb-0 small">
                                En compras en línea solo manejamos transferencia bancaria y pago contraentrega. 
                                No hay pago con tarjeta en la web; tarjeta disponible únicamente en tienda física.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notas adicionales -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-sticky-note me-2"></i>
                            Notas Adicionales
                        </h3>
                        <div class="mb-3">
                            <label for="notas" class="form-label">Instrucciones especiales para la orden</label>
                            <textarea class="form-control" id="notas" name="notas" rows="3"></textarea>
                            <small class="text-muted">Ej: Horario preferido de entrega, detalles del regalo, etc.</small>
                        </div>
                    </div>
                    
                    <!-- Términos y condiciones -->
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="terminos" required>
                        <label class="form-check-label" for="terminos">
                            Acepto los <a href="terminos.php" target="_blank">términos y condiciones</a> y la 
                            <a href="privacidad.php" target="_blank">política de privacidad</a> *
                        </label>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="acumular_puntos" name="acumular_puntos" value="1" checked>
                        <label class="form-check-label" for="acumular_puntos">
                            Quiero acumular esta factura a mis puntos
                            <small class="text-muted d-block">Para acreditar puntos debes ingresar DPI válido.</small>
                        </label>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                        <a href="carrito.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Regresar al Carrito
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-lock me-2"></i> Completar Pedido
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Resumen del pedido -->
            <div class="col-lg-4">
                <div class="summary-card">
                    <h4 class="mb-4">Resumen del Pedido</h4>
                    
                    <!-- Productos -->
                    <div class="mb-4">
                        <h6 class="mb-3">Productos</h6>
                        <?php foreach ($_SESSION['carrito'] as $item): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <small><?php echo $item['nombre']; ?></small><br>
                                <small class="text-muted">
                                    <?php echo $item['color']; ?> / <?php echo $item['talla']; ?>
                                    <?php if (isset($item['es_ajitos']) && $item['es_ajitos']): ?>
                                        <span class="badge bg-danger">Ajitos</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <small><?php echo $item['cantidad']; ?> × <?php echo formatMoney($item['precio']); ?></small><br>
                                <small><?php echo formatMoney($item['precio'] * $item['cantidad']); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Totales -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span><?php echo formatMoney($carrito_total); ?></span>
                        </div>
                        
                        <?php if ($descuento_puntos > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Descuento (puntos)</span>
                            <span>-<?php echo formatMoney($descuento_puntos); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Envío</span>
                            <span id="envioCosto">Gratis</span>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <h5>Total</h5>
                            <h4 id="totalFinal"><?php echo formatMoney($total_final); ?></h4>
                        </div>
                    </div>
                    
                    <!-- Información importante -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i> Información Importante</h6>
                        <ul class="mb-0 small">
                            <li>Los pedidos se procesan en 24-48 horas</li>
                            <li>Tienes 72 horas para confirmar tu compra</li>
                            <li>Política de devolución: 7 días con factura</li>
                            <li>Para consultas: info@tiendamm.com</li>
                        </ul>
                    </div>
                    
                    <!-- Puntos que ganarás -->
                    <?php 
                        $puntos_ganar = calcularPuntos($total_final);
                        if ($puntos_ganar > 0):
                    ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-coins me-2"></i>
                        Ganarás <strong><?php echo $puntos_ganar; ?> puntos</strong> con esta compra
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Mostrar/ocultar campos de dirección según tipo de entrega
        const tipoEntregaRadios = document.querySelectorAll('input[name="tipo_entrega"]');
        const direccionFields = document.getElementById('direccionFields');
        
        tipoEntregaRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'envio') {
                    direccionFields.style.display = 'block';
                    // Agregar costo de envío
                    document.getElementById('envioCosto').textContent = 'Q25.00';
                    // Actualizar total
                    const totalElement = document.getElementById('totalFinal');
                    const totalActual = parseFloat(totalElement.textContent.replace('Q', '').replace(',', ''));
                    const nuevoTotal = totalActual + 25;
                    totalElement.textContent = 'Q' + nuevoTotal.toFixed(2);
                } else {
                    direccionFields.style.display = 'none';
                    document.getElementById('envioCosto').textContent = 'Gratis';
                    // Restaurar total original
                    const totalElement = document.getElementById('totalFinal');
                    totalElement.textContent = '<?php echo formatMoney($total_final); ?>';
                }
            });
        });
        
        // Validar DPI
        const dpiInput = document.getElementById('dpi');
        dpiInput.addEventListener('input', function() {
            if (this.value.length === 13 && /^\d+$/.test(this.value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (this.value.length > 0) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
        
        // Validar teléfono
        const telefonoInput = document.getElementById('telefono');
        telefonoInput.addEventListener('input', function() {
            const telefonoRegex = /^[0-9\s\-\+\(\)]{8,15}$/;
            if (telefonoRegex.test(this.value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (this.value.length > 0) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>