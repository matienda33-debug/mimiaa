<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/email_config.php';

// Asegurar JSON y evitar errores HTML
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['factura_id'])) {
        throw new Exception('Solicitud inválida');
    }

    $factura_id = (int)$_POST['factura_id'];
    $correo_destino = isset($_POST['correo']) ? trim($_POST['correo']) : null;
    $guardar_correo = isset($_POST['guardar']) && $_POST['guardar'] === '1';
    
    // Obtener información de la factura
    $query = "SELECT fc.*, c.email as cliente_email, c.nombre as cliente_nombre,
              c.apellido as cliente_apellido, c.id_cliente
              FROM factura_cabecera fc
              LEFT JOIN clientes c ON fc.id_cliente = c.id_cliente
              WHERE fc.id_factura = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $factura_id, PDO::PARAM_INT);
    $stmt->execute();
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        throw new Exception('Factura no encontrada');
    }

    // Determinar correo de destino
    $email_final = $correo_destino ?: ($factura['cliente_email'] ?? null);
    
    if (!$email_final) {
        throw new Exception('No hay correo disponible. Por favor ingrese un correo.');
    }

    // Validar formato del correo
    if (!filter_var($email_final, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Formato de correo inválido');
    }

    // Guardar correo en cliente si se solicita y es un cliente registrado
    if ($guardar_correo && $factura['id_cliente']) {
        $update_cliente = "UPDATE clientes SET email = :correo WHERE id_cliente = :id";
        $update_cliente_stmt = $db->prepare($update_cliente);
        $update_cliente_stmt->bindParam(':correo', $correo_destino);
        $update_cliente_stmt->bindParam(':id', $factura['id_cliente'], PDO::PARAM_INT);
        $update_cliente_stmt->execute();
    }
    
    // Obtener detalles de la factura
    $detalle_query = "SELECT fd.*, pv.color, pv.talla, pr.codigo, pr.nombre as producto_nombre
                     FROM factura_detalle fd
                     INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                     INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                     WHERE fd.id_factura = :id_factura";
    $detalle_stmt = $db->prepare($detalle_query);
    $detalle_stmt->bindParam(':id_factura', $factura_id, PDO::PARAM_INT);
    $detalle_stmt->execute();
    $detalles = $detalle_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generar contenido HTML del correo
    $html = generarContenidoCorreo($factura, $detalles);
    
    // Enviar correo usando EmailConfig (PHPMailer)
    $nombre_cliente = trim(($factura['cliente_nombre'] ?? 'Cliente') . ' ' . ($factura['cliente_apellido'] ?? ''));
    if (!$nombre_cliente || $nombre_cliente === 'Cliente') {
        $nombre_cliente = 'Cliente Ocasional';
    }

    $emailConfig = new EmailConfig();
    $correo_enviado = $emailConfig->sendEmail(
        $email_final,
        $nombre_cliente,
        'Factura #' . $factura['numero_factura'] . ' - ' . SITE_NAME,
        $html
    );
    
    // Registrar intento en log
    $log_dir = dirname(__DIR__) . '/../../logs/';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $log_file = $log_dir . 'correos_' . date('Y-m-d') . '.log';
    if ($correo_enviado) {
        $log_content = date('Y-m-d H:i:s') . " | Para: $email_final ($nombre_cliente) | Factura: " . $factura['numero_factura'] . " | Estado: ENVIADO\n";
    } else {
        $log_content = date('Y-m-d H:i:s') . " | Para: $email_final ($nombre_cliente) | Factura: " . $factura['numero_factura'] . " | Error: " . $emailConfig->getLastError() . "\n";
    }
    file_put_contents($log_file, $log_content, FILE_APPEND | LOCK_EX);
    
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    if ($correo_enviado) {
        echo json_encode([
            'success' => true, 
            'message' => '✓ Comprobante enviado a ' . htmlspecialchars($email_final)
        ]);
    } else {
        // Si falla el envío, mostrar el error de PHPMailer
        echo json_encode([
            'success' => false,
            'message' => 'Error al enviar: ' . $emailConfig->getLastError()
        ]);
    }
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

exit();

function generarContenidoCorreo($factura, $detalles) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Factura #' . $factura['numero_factura'] . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 800px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; border-bottom: 2px solid #1abc9c; padding-bottom: 20px; margin-bottom: 30px; }
            .logo { color: #1abc9c; font-size: 28px; font-weight: bold; }
            .factura-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .table th { background: #2c3e50; color: white; padding: 10px; text-align: left; }
            .table td { padding: 10px; border-bottom: 1px solid #ddd; }
            .totales { background: #f8f9fa; padding: 15px; border-radius: 5px; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 14px; }
            .badge { background: #ff6b6b; color: white; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">' . SITE_NAME . ' <span class="badge">' . AJITOS_NAME . '</span></div>
                <p>Dirección: Ciudad de Guatemala | Tel: (502) 1234-5678</p>
            </div>
            
            <div class="factura-info">
                <h3>Factura #' . $factura['numero_factura'] . '</h3>
                <p><strong>Fecha:</strong> ' . date('d/m/Y H:i', strtotime($factura['fecha'])) . '</p>
                <p><strong>Cliente:</strong> ' . htmlspecialchars($factura['nombre_cliente'] ?? 'Cliente Ocasional') . '</p>
                <p><strong>Estado:</strong> ' . ($factura['id_estado'] == 2 ? 'Pagada' : 'Pendiente') . '</p>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Color/Talla</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>';
                
                foreach ($detalles as $detalle) {
                    $html .= '
                    <tr>
                        <td>' . htmlspecialchars($detalle['producto_nombre']) . '</td>
                        <td>' . htmlspecialchars($detalle['color'] ?? '-') . ' / ' . htmlspecialchars($detalle['talla'] ?? '-') . '</td>
                        <td>' . $detalle['cantidad'] . '</td>
                        <td>Q' . number_format($detalle['precio_unitario'], 2) . '</td>
                        <td>Q' . number_format($detalle['subtotal'], 2) . '</td>
                    </tr>';
                }
                
                $html .= '
                </tbody>
            </table>
            
            <div class="totales">
                <div style="float: right; width: 300px;">
                    <p><strong>Subtotal:</strong> Q' . number_format($factura['subtotal'], 2) . '</p>';
                    
                    if ($factura['descuento'] > 0) {
                        $html .= '<p><strong>Descuento:</strong> -Q' . number_format($factura['descuento'], 2) . '</p>';
                    }
                    
                    $html .= '
                    <hr>
                    <h3>Total: Q' . number_format($factura['total'], 2) . '</h3>';
                    
                    if ($factura['puntos_ganados'] > 0) {
                        $html .= '<p><strong>Puntos ganados:</strong> ' . $factura['puntos_ganados'] . '</p>';
                    }
                    
                    $html .= '
                </div>
                <div style="clear: both;"></div>
            </div>
            
            <div class="footer">
                <p>¡Gracias por su compra!</p>
                <p>Esta es una copia electrónica de su factura.</p>
                <p>Para cualquier consulta, contacte a: info@tiendamm.com</p>
                <p>Visite nuestra tienda en línea: www.tiendamm.com</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>
