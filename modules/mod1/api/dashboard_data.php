<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

// Obtener datos en tiempo real para el dashboard
$data = [];

// Ventas del día
$ventas_hoy_query = "SELECT COALESCE(SUM(total), 0) as ventas_hoy FROM factura_cabecera 
                     WHERE id_estado = 2 AND DATE(fecha) = CURDATE()";
$stmt = $db->prepare($ventas_hoy_query);
$stmt->execute();
$data['ventas_hoy'] = $stmt->fetch(PDO::FETCH_ASSOC)['ventas_hoy'];

// Clientes nuevos hoy
$clientes_hoy_query = "SELECT COUNT(*) as clientes_hoy FROM clientes 
                       WHERE DATE(fecha_registro) = CURDATE()";
$stmt = $db->prepare($clientes_hoy_query);
$stmt->execute();
$data['clientes_hoy'] = $stmt->fetch(PDO::FETCH_ASSOC)['clientes_hoy'];

// Productos bajos en stock
$bajo_stock_query = "SELECT COUNT(*) as bajo_stock FROM productos_variantes 
                     WHERE (stock_tienda + stock_bodega) < 5 AND activo = 1";
$stmt = $db->prepare($bajo_stock_query);
$stmt->execute();
$data['bajo_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['bajo_stock'];

// Facturas pendientes
$pendientes_query = "SELECT COUNT(*) as pendientes FROM factura_cabecera 
                     WHERE id_estado = 1 AND tipo_venta = 'online'";
$stmt = $db->prepare($pendientes_query);
$stmt->execute();
$data['pendientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['pendientes'];

// Últimas ventas para notificaciones
$ultimas_ventas_query = "SELECT fc.numero_factura, fc.nombre_cliente, fc.total, 
                         TIMESTAMPDIFF(MINUTE, fc.fecha, NOW()) as minutos_antes
                         FROM factura_cabecera fc
                         WHERE fc.id_estado = 2 
                         AND fc.fecha >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                         ORDER BY fc.fecha DESC
                         LIMIT 5";
$stmt = $db->prepare($ultimas_ventas_query);
$stmt->execute();
$data['ultimas_ventas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Alertas del sistema
$alertas = [];

// Productos sin stock
$sin_stock_query = "SELECT COUNT(*) as cantidad FROM productos_variantes 
                    WHERE (stock_tienda + stock_bodega) = 0 AND activo = 1";
$stmt = $db->prepare($sin_stock_query);
$stmt->execute();
$sin_stock = $stmt->fetch(PDO::FETCH_ASSOC);
if ($sin_stock['cantidad'] > 0) {
    $alertas[] = [
        'tipo' => 'danger',
        'mensaje' => $sin_stock['cantidad'] . ' productos sin stock',
        'url' => '../mod2/inventario.php'
    ];
}

// Ventas por hora actual
$hora_actual = date('G');
$ventas_hora_actual_query = "SELECT COALESCE(SUM(total), 0) as ventas FROM factura_cabecera 
                            WHERE id_estado = 2 AND HOUR(fecha) = :hora AND DATE(fecha) = CURDATE()";
$stmt = $db->prepare($ventas_hora_actual_query);
$stmt->bindParam(':hora', $hora_actual);
$stmt->execute();
$ventas_hora_actual = $stmt->fetch(PDO::FETCH_ASSOC)['ventas'];

// Comparar con hora anterior
$hora_anterior = $hora_actual > 0 ? $hora_actual - 1 : 23;
$ventas_hora_anterior_query = "SELECT COALESCE(SUM(total), 0) as ventas FROM factura_cabecera 
                              WHERE id_estado = 2 AND HOUR(fecha) = :hora AND DATE(fecha) = CURDATE()";
$stmt = $db->prepare($ventas_hora_anterior_query);
$stmt->bindParam(':hora', $hora_anterior);
$stmt->execute();
$ventas_hora_anterior = $stmt->fetch(PDO::FETCH_ASSOC)['ventas'];

if ($ventas_hora_anterior > 0) {
    $crecimiento_hora = (($ventas_hora_actual - $ventas_hora_anterior) / $ventas_hora_anterior) * 100;
    $data['crecimiento_hora'] = round($crecimiento_hora, 1);
} else {
    $data['crecimiento_hora'] = $ventas_hora_actual > 0 ? 100 : 0;
}

$data['alertas'] = $alertas;
$data['timestamp'] = date('Y-m-d H:i:s');
$data['status'] = 'success';

echo json_encode($data);
?>