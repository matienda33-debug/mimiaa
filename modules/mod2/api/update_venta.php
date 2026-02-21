<?php
// modules/mod2/api/update_venta.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/config.php';

// Verificar autenticación
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !$auth->hasPermission('ventas')) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['accion'])) {
        try {
            switch ($data['accion']) {
                case 'actualizar_estado':
                    if (isset($data['id_factura'], $data['id_estado'])) {
                        $stmt = $db->prepare("UPDATE factura_cabecera SET id_estado = :estado WHERE id_factura = :id");
                        $stmt->bindParam(':estado', $data['id_estado']);
                        $stmt->bindParam(':id', $data['id_factura']);
                        
                        if ($stmt->execute()) {
                            // Registrar movimiento si cambia a entregado
                            if ($data['id_estado'] == 4) { // 4 = entregada
                                // Obtener detalles de la factura
                                $stmt_detalles = $db->prepare("
                                    SELECT id_producto, cantidad 
                                    FROM factura_detalle 
                                    WHERE id_factura = :id
                                ");
                                $stmt_detalles->bindParam(':id', $data['id_factura']);
                                $stmt_detalles->execute();
                                $detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Registrar movimientos de salida
                                foreach ($detalles as $detalle) {
                                    $stmt_movimiento = $db->prepare("
                                        INSERT INTO inventario_movimientos 
                                        (id_producto_variante, tipo_movimiento, cantidad, ubicacion, motivo, id_usuario, fecha_movimiento) 
                                        VALUES (:id_variante, 'salida', :cantidad, 'tienda', 'Venta entregada', :id_usuario, NOW())
                                    ");
                                    $stmt_movimiento->bindParam(':id_variante', $detalle['id_producto']);
                                    $stmt_movimiento->bindParam(':cantidad', $detalle['cantidad']);
                                    $stmt_movimiento->bindParam(':id_usuario', $_SESSION['user_id']);
                                    $stmt_movimiento->execute();
                                    
                                    // Actualizar stock
                                    $stmt_stock = $db->prepare("
                                        UPDATE productos_variantes 
                                        SET stock_tienda = stock_tienda - :cantidad 
                                        WHERE id_variante = :id_variante
                                    ");
                                    $stmt_stock->bindParam(':id_variante', $detalle['id_producto']);
                                    $stmt_stock->bindParam(':cantidad', $detalle['cantidad']);
                                    $stmt_stock->execute();
                                }
                            }
                            
                            echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
                        }
                    }
                    break;
                    
                case 'agregar_producto':
                    if (isset($data['id_factura'], $data['id_variante'], $data['cantidad'], $data['precio'])) {
                        // Verificar stock disponible
                        $stmt_stock = $db->prepare("
                            SELECT stock_tienda FROM productos_variantes 
                            WHERE id_variante = :id_variante
                        ");
                        $stmt_stock->bindParam(':id_variante', $data['id_variante']);
                        $stmt_stock->execute();
                        $stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
                        
                        if ($stock['stock_tienda'] >= $data['cantidad']) {
                            // Agregar producto al detalle
                            $subtotal = $data['cantidad'] * $data['precio'];
                            
                            $stmt = $db->prepare("
                                INSERT INTO factura_detalle 
                                (id_factura, id_producto, cantidad, precio_unitario, subtotal) 
                                VALUES (:id_factura, :id_producto, :cantidad, :precio, :subtotal)
                            ");
                            $stmt->bindParam(':id_factura', $data['id_factura']);
                            $stmt->bindParam(':id_producto', $data['id_variante']);
                            $stmt->bindParam(':cantidad', $data['cantidad']);
                            $stmt->bindParam(':precio', $data['precio']);
                            $stmt->bindParam(':subtotal', $subtotal);
                            
                            if ($stmt->execute()) {
                                // Actualizar subtotal en cabecera
                                $stmt_update = $db->prepare("
                                    UPDATE factura_cabecera 
                                    SET subtotal = subtotal + :subtotal 
                                    WHERE id_factura = :id_factura
                                ");
                                $stmt_update->bindParam(':subtotal', $subtotal);
                                $stmt_update->bindParam(':id_factura', $data['id_factura']);
                                $stmt_update->execute();
                                
                                echo json_encode(['success' => true, 'message' => 'Producto agregado']);
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Stock insuficiente']);
                        }
                    }
                    break;
                    
                case 'eliminar_producto':
                    if (isset($data['id_detalle'])) {
                        // Obtener información del detalle
                        $stmt_get = $db->prepare("
                            SELECT id_factura, subtotal FROM factura_detalle 
                            WHERE id_detalle = :id
                        ");
                        $stmt_get->bindParam(':id', $data['id_detalle']);
                        $stmt_get->execute();
                        $detalle = $stmt_get->fetch(PDO::FETCH_ASSOC);
                        
                        if ($detalle) {
                            // Eliminar detalle
                            $stmt_delete = $db->prepare("DELETE FROM factura_detalle WHERE id_detalle = :id");
                            $stmt_delete->bindParam(':id', $data['id_detalle']);
                            
                            if ($stmt_delete->execute()) {
                                // Actualizar subtotal en cabecera
                                $stmt_update = $db->prepare("
                                    UPDATE factura_cabecera 
                                    SET subtotal = subtotal - :subtotal 
                                    WHERE id_factura = :id_factura
                                ");
                                $stmt_update->bindParam(':subtotal', $detalle['subtotal']);
                                $stmt_update->bindParam(':id_factura', $detalle['id_factura']);
                                $stmt_update->execute();
                                
                                echo json_encode(['success' => true, 'message' => 'Producto eliminado']);
                            }
                        }
                    }
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no especificada']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}