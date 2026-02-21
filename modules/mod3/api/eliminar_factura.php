<?php
// modules/mod3/api/eliminar_factura.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/config.php';

session_start();

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !$auth->hasPermission('ventas')) {
    header('Location: /tiendaAA/index.php?error=no_permission');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: ../ventas.php?error=id_no_proporcionado');
    exit();
}

$id_factura = $_GET['id'];

try {
    // Primero, obtener información de la factura para verificar el estado
    $stmt_check = $db->prepare("SELECT id_estado FROM factura_cabecera WHERE id_factura = :id");
    $stmt_check->bindParam(':id', $id_factura);
    $stmt_check->execute();
    $factura = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        header('Location: ../ventas.php?error=factura_no_encontrada');
        exit();
    }
    
    // Solo permitir eliminar facturas pendientes o canceladas
    if ($factura['id_estado'] != 1 && $factura['id_estado'] != 5) {
        header('Location: ../detalle_factura.php?id=' . $id_factura . '&error=no_se_puede_eliminar');
        exit();
    }
    
    // Devolver productos al inventario si la factura no está cancelada
    if ($factura['id_estado'] != 5) {
        // Obtener detalles de la factura
        $stmt_detalles = $db->prepare("SELECT id_producto, cantidad FROM factura_detalle WHERE id_factura = :id");
        $stmt_detalles->bindParam(':id', $id_factura);
        $stmt_detalles->execute();
        $detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);
        
        // Devolver cada producto al inventario
        foreach ($detalles as $detalle) {
            // Verificar si el producto está en tienda o bodega
            $stmt_producto = $db->prepare("SELECT stock_tienda, stock_bodega FROM productos_variantes WHERE id_variante = :id");
            $stmt_producto->bindParam(':id', $detalle['id_producto']);
            $stmt_producto->execute();
            $producto = $stmt_producto->fetch(PDO::FETCH_ASSOC);
            
            if ($producto) {
                // Aumentar stock en tienda (asumiendo que las ventas salen de tienda)
                $nuevo_stock = $producto['stock_tienda'] + $detalle['cantidad'];
                $stmt_update = $db->prepare("UPDATE productos_variantes SET stock_tienda = :stock WHERE id_variante = :id");
                $stmt_update->bindParam(':stock', $nuevo_stock);
                $stmt_update->bindParam(':id', $detalle['id_producto']);
                $stmt_update->execute();
                
                // Registrar movimiento de devolución
                $stmt_movimiento = $db->prepare("INSERT INTO inventario_movimientos 
                                                 (id_producto_variante, tipo_movimiento, cantidad, ubicacion, motivo, id_usuario, fecha_movimiento) 
                                                 VALUES (:id_producto, 'devolucion', :cantidad, 'tienda', 'Eliminación de factura #" . $id_factura . "', :usuario, NOW())");
                $stmt_movimiento->bindParam(':id_producto', $detalle['id_producto']);
                $stmt_movimiento->bindParam(':cantidad', $detalle['cantidad']);
                $stmt_movimiento->bindParam(':usuario', $_SESSION['user_id']);
                $stmt_movimiento->execute();
            }
        }
    }
    
    // Eliminar detalles primero
    $stmt_delete_detalles = $db->prepare("DELETE FROM factura_detalle WHERE id_factura = :id");
    $stmt_delete_detalles->bindParam(':id', $id_factura);
    $stmt_delete_detalles->execute();
    
    // Eliminar historial
    $stmt_delete_historial = $db->prepare("DELETE FROM factura_historial WHERE id_factura = :id");
    $stmt_delete_historial->bindParam(':id', $id_factura);
    $stmt_delete_historial->execute();
    
    // Eliminar cabecera
    $stmt_delete_cabecera = $db->prepare("DELETE FROM factura_cabecera WHERE id_factura = :id");
    $stmt_delete_cabecera->bindParam(':id', $id_factura);
    $stmt_delete_cabecera->execute();
    
    header('Location: ../ventas.php?success=Factura+eliminada+exitosamente');
    exit();
    
} catch (PDOException $e) {
    header('Location: ../detalle_factura.php?id=' . $id_factura . '&error=' . urlencode('Error al eliminar: ' . $e->getMessage()));
    exit();
}