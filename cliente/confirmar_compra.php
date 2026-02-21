<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $id_producto = (int)$_POST['id_producto'];
    $cantidad = (int)$_POST['cantidad'];
    $variante = (int)$_POST['variante'] ?? 0;
    $calle = trim($_POST['calle'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');
    $notas = trim($_POST['notas'] ?? '');
    $metodo_pago = trim($_POST['metodo_pago'] ?? 'tarjeta');
    
    // Validar datos
    if ($cantidad < 1 || empty($calle) || empty($numero) || empty($ciudad)) {
        throw new Exception('Datos incompletos');
    }
    
    // Obtener precio del producto
    $producto_query = "SELECT precio_venta FROM productos_raiz WHERE id_raiz = :id";
    $producto_stmt = $db->prepare($producto_query);
    $producto_stmt->bindParam(':id', $id_producto);
    $producto_stmt->execute();
    $producto = $producto_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }
    
    // Calcular montos
    $subtotal = $producto['precio_venta'] * $cantidad;
    $impuesto = $subtotal * 0.12;
    $total = $subtotal + $impuesto;
    
    // Generar número de factura
    $numero_factura = 'FAC-' . date('Ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    // Crear factura
    $factura_query = "INSERT INTO factura_cabecera 
                     (numero_factura, id_cliente, subtotal, impuesto, total, estado_factura, 
                      metodo_pago, direccion_envio, notas_envio, fecha)
                     VALUES (:numero, :cliente, :subtotal, :impuesto, :total, 'pendiente',
                             :metodo, :direccion, :notas, NOW())";
    
    $factura_stmt = $db->prepare($factura_query);
    $factura_stmt->bindParam(':numero', $numero_factura);
    $factura_stmt->bindParam(':cliente', $_SESSION['id_usuario']);
    $factura_stmt->bindParam(':subtotal', $subtotal);
    $factura_stmt->bindParam(':impuesto', $impuesto);
    $factura_stmt->bindParam(':total', $total);
    $factura_stmt->bindParam(':metodo', $metodo_pago);
    
    $direccion_completa = $calle . ' #' . $numero . ', ' . $ciudad;
    $factura_stmt->bindParam(':direccion', $direccion_completa);
    $factura_stmt->bindParam(':notas', $notas);
    
    $factura_stmt->execute();
    $id_factura = $db->lastInsertId();
    
    // Agregar detalle de factura
    if ($variante > 0) {
        // Obtener precio y stock de la variante
        $variante_query = "SELECT precio_venta, stock_tienda, stock_bodega FROM productos_variantes WHERE id_variante = :id";
        $variante_stmt = $db->prepare($variante_query);
        $variante_stmt->bindParam(':id', $variante);
        $variante_stmt->execute();
        $var_data = $variante_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$var_data) {
            throw new Exception('Variante no encontrada');
        }

        $stock_total = (int)$var_data['stock_tienda'] + (int)$var_data['stock_bodega'];
        if ($stock_total < $cantidad) {
            throw new Exception('Stock insuficiente');
        }

        $precio_variante = (float)$var_data['precio_venta'];
        if ($precio_variante <= 0) {
            $precio_variante = (float)$producto['precio_venta'];
        }
        
        // Insertar detalle
        $detalle_query = "INSERT INTO factura_detalle 
                         (id_factura, id_producto_variante, cantidad, precio_unitario, subtotal)
                         VALUES (:factura, :variante, :cantidad, :precio, :subtotal)";
        
        $detalle_stmt = $db->prepare($detalle_query);
        $detalle_stmt->bindParam(':factura', $id_factura);
        $detalle_stmt->bindParam(':variante', $variante);
        $detalle_stmt->bindParam(':cantidad', $cantidad);
        $detalle_stmt->bindParam(':precio', $precio_variante);
        $subtotal_item = $precio_variante * $cantidad;
        $detalle_stmt->bindParam(':subtotal', $subtotal_item);
        $detalle_stmt->execute();

        // Actualizar stock (bodega primero)
        $stock_bodega = (int)$var_data['stock_bodega'];
        $stock_tienda = (int)$var_data['stock_tienda'];
        $restante = $cantidad;

        if ($stock_bodega >= $restante) {
            $stock_bodega -= $restante;
            $restante = 0;
        } else {
            $restante -= $stock_bodega;
            $stock_bodega = 0;
        }

        if ($restante > 0) {
            $stock_tienda = max(0, $stock_tienda - $restante);
        }

        $stock_query = "UPDATE productos_variantes SET stock_tienda = :stock_tienda, stock_bodega = :stock_bodega WHERE id_variante = :id";
        $stock_stmt = $db->prepare($stock_query);
        $stock_stmt->bindParam(':stock_tienda', $stock_tienda);
        $stock_stmt->bindParam(':stock_bodega', $stock_bodega);
        $stock_stmt->bindParam(':id', $variante);
        $stock_stmt->execute();
    } else {
        // Sin variante
        $detalle_query = "INSERT INTO factura_detalle 
                         (id_factura, id_producto_variante, cantidad, precio_unitario, subtotal)
                         VALUES (:factura, NULL, :cantidad, :precio, :subtotal)";
        
        $detalle_stmt = $db->prepare($detalle_query);
        $detalle_stmt->bindParam(':factura', $id_factura);
        $detalle_stmt->bindParam(':cantidad', $cantidad);
        $detalle_stmt->bindParam(':precio', $producto['precio_venta']);
        $subtotal_item = $producto['precio_venta'] * $cantidad;
        $detalle_stmt->bindParam(':subtotal', $subtotal_item);
        $detalle_stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Compra completada',
        'factura' => $numero_factura,
        'id_factura' => $id_factura
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
