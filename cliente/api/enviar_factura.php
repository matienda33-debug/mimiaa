<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(403);
    exit('No autorizado');
}

$database = new Database();
$db = $database->getConnection();

$id_factura = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_factura == 0) {
    http_response_code(404);
    exit('Factura no encontrada');
}

// Obtener factura
$factura_query = "SELECT fc.*, per.nombre, per.apellido, per.email
                 FROM factura_cabecera fc
                 INNER JOIN clientes c ON fc.id_cliente = c.id_cliente
                 INNER JOIN personas per ON c.id_persona = per.id_persona
                 WHERE fc.id_factura = :id AND fc.id_cliente = :cliente";

$factura_stmt = $db->prepare($factura_query);
$factura_stmt->bindParam(':id', $id_factura);
$factura_stmt->bindParam(':cliente', $_SESSION['id_usuario']);
$factura_stmt->execute();
$factura = $factura_stmt->fetch(PDO::FETCH_ASSOC);

if (!$factura) {
    http_response_code(404);
    exit('Factura no encontrada');
}

// Obtener detalles
$detalles_query = "SELECT fd.*, pr.nombre
                  FROM factura_detalle fd
                  INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                  INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                  WHERE fd.id_factura = :id";

$detalles_stmt = $db->prepare($detalles_query);
$detalles_stmt->bindParam(':id', $id_factura);
$detalles_stmt->execute();
$detalles = $detalles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar PDF o enviar por email
// Por ahora, devolvemos HTML imprimible
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura <?php echo $factura['numero_factura']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1abc9c; padding-bottom: 20px; }
        .info { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .info-section { width: 45%; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #1abc9c; color: white; }
        .totales { width: 300px; float: right; }
        .total-line { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #ddd; }
        .total-final { font-size: 18px; font-weight: bold; color: #1abc9c; }
        .footer { text-align: center; margin-top: 40px; color: #999; font-size: 12px; }
        @media print {
            body { margin: 0; }
            button { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <button onclick="window.print()" style="float: right; padding: 10px 20px; background: #1abc9c; color: white; border: none; border-radius: 5px; cursor: pointer;">
            <i class="fas fa-print"></i> Imprimir
        </button>
        
        <div class="header">
            <h1><?php echo SITE_NAME; ?></h1>
            <p>FACTURA DE VENTA</p>
            <h3><?php echo $factura['numero_factura']; ?></h3>
        </div>
        
        <div class="info">
            <div class="info-section">
                <strong>Cliente:</strong><br>
                <?php echo $factura['nombre'] . ' ' . $factura['apellido']; ?><br>
                <?php echo $factura['email']; ?><br>
                <br>
                <strong>Dirección de Envío:</strong><br>
                <?php echo $factura['direccion_envio']; ?>
            </div>
            <div class="info-section" style="text-align: right;">
                <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($factura['fecha'])); ?><br>
                <strong>Hora:</strong> <?php echo date('H:i', strtotime($factura['fecha'])); ?><br>
                <strong>Estado:</strong> <span style="background: #1abc9c; color: white; padding: 5px 10px; border-radius: 3px;">
                    <?php echo ucfirst($factura['estado_factura']); ?>
                </span><br>
                <strong>Método de Pago:</strong> <?php echo ucfirst($factura['metodo_pago']); ?>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="text-align: center;">Cantidad</th>
                    <th style="text-align: right;">Precio Unitario</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                <tr>
                    <td><?php echo $detalle['nombre']; ?></td>
                    <td style="text-align: center;"><?php echo $detalle['cantidad']; ?></td>
                    <td style="text-align: right;">Q<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                    <td style="text-align: right;">Q<?php echo number_format($detalle['subtotal'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totales">
            <div class="total-line">
                <span>Subtotal:</span>
                <span>Q<?php echo number_format($factura['subtotal'], 2); ?></span>
            </div>
            <div class="total-line">
                <span>Impuesto (12%):</span>
                <span>Q<?php echo number_format($factura['impuesto'], 2); ?></span>
            </div>
            <div class="total-line total-final">
                <span>TOTAL:</span>
                <span>Q<?php echo number_format($factura['total'], 2); ?></span>
            </div>
        </div>
        
        <div style="clear: both; margin-top: 50px;">
            <?php if (!empty($factura['notas_envio'])): ?>
            <p><strong>Notas:</strong> <?php echo nl2br(htmlspecialchars($factura['notas_envio'])); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>Gracias por tu compra en <?php echo SITE_NAME; ?></p>
            <p>Para información adicional <a href="mailto:info@tienda.com">contáctanos</a></p>
        </div>
    </div>
</body>
</html>
