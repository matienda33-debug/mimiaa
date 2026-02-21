<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['factura_id'])) {
    $factura_id = $_POST['factura_id'];
    
    try {
        // Obtener información de la factura
        $query = "SELECT fc.*, c.email as cliente_email, c.nombre as cliente_nombre,
                  c.apellido as cliente_apellido
                  FROM factura_cabecera fc
                  LEFT JOIN clientes c ON fc.id_cliente = c.id_cliente
                  WHERE fc.id_factura = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $factura_id);
        $stmt->execute();
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$factura || !$factura['cliente_email']) {
            echo json_encode(['success' => false, 'message' => 'Cliente sin correo electrónico']);
            exit();
        }
        
        // Obtener detalles de la factura
        $detalle_query = "SELECT fd.*, pv.color, pv.talla, pr.codigo, pr.nombre as producto_nombre
                         FROM factura_detalle fd
                         INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                         INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                         WHERE fd.id_factura = :id_factura";
        $detalle_stmt = $db->prepare($detalle_query);
        $detalle_stmt->bindParam(':id_factura', $factura_id);
        $detalle_stmt->execute();
        $detalles = $detalle_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generar contenido HTML del correo
        $html = generarContenidoCorreo($factura, $detalles);
        
        // Configurar y enviar correo
        if (enviarCorreoSMTP(
            $factura['cliente_email'],
            $factura['cliente_nombre'] . ' ' . $factura['cliente_apellido'],
            'Factura #' . $factura['numero_factura'] . ' - ' . SITE_NAME,
            $html
        )) {
            // Marcar factura como enviada
            $update_query = "UPDATE factura_cabecera SET correo_enviado = 1 WHERE id_factura = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':id', $factura_id);
            $update_stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Correo enviado exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al enviar el correo']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
}

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
                <p><strong>Cliente:</strong> ' . $factura['nombre_cliente'] . '</p>
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
                        <td>' . $detalle['producto_nombre'] . '</td>
                        <td>' . $detalle['color'] . ' / ' . $detalle['talla'] . '</td>
                        <td>' . $detalle['cantidad'] . '</td>
                        <td>' . formatMoney($detalle['precio_unitario']) . '</td>
                        <td>' . formatMoney($detalle['subtotal']) . '</td>
                    </tr>';
                }
                
                $html .= '
                </tbody>
            </table>
            
            <div class="totales">
                <div style="float: right; width: 300px;">
                    <p><strong>Subtotal:</strong> ' . formatMoney($factura['subtotal']) . '</p>';
                    
                    if ($factura['descuento'] > 0) {
                        $html .= '<p><strong>Descuento:</strong> -' . formatMoney($factura['descuento']) . '</p>';
                    }
                    
                    $html .= '
                    <hr>
                    <h3>Total: ' . formatMoney($factura['total']) . '</h3>';
                    
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

function enviarCorreoSMTP($to, $to_name, $subject, $html_content) {
    // En un ambiente real, usarías PHPMailer o similar
    // Esta es una implementación básica para demostración
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SITE_NAME . " <no-reply@tiendamm.com>" . "\r\n";
    $headers .= "Reply-To: info@tiendamm.com" . "\r\n";
    
    // En desarrollo, simular envío
    if (SMTP_USER == 'tu_correo@gmail.com') {
        // Guardar en log en lugar de enviar
        $log_dir = '../../../logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        
        $log_file = $log_dir . 'correos_' . date('Y-m-d') . '.log';
        $log_content = date('Y-m-d H:i:s') . " | Para: $to ($to_name) | Asunto: $subject\n";
        file_put_contents($log_file, $log_content, FILE_APPEND);
        
        return true; // Simular éxito
    }
    
    // En producción, usar PHPMailer
    return mail($to, $subject, $html_content, $headers);
}
?>