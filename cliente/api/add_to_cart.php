<?php
// Limpiar output buffer
if (ob_get_level()) ob_clean();

require_once '../../config/config.php';
require_once '../../config/database.php';

// Asegurar que session está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $variante_id = isset($_POST['variante_id']) ? (int)$_POST['variante_id'] : null;
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;
    
    // Validar entrada
    if (!$variante_id || $variante_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de variante no válido']);
        exit;
    }
    
    if ($cantidad <= 0) {
        echo json_encode(['success' => false, 'message' => 'Cantidad debe ser mayor a 0']);
        exit;
    }
    
    // Verificar stock disponible
    $stock_query = "SELECT pv.*, pr.nombre as producto_nombre, pr.precio_venta as precio_base,
                    pv.precio_venta, (SELECT nombre_archivo FROM productos_raiz_fotos 
                    WHERE id_producto_raiz = pr.id_raiz AND es_principal = 1 LIMIT 1) as imagen
                    FROM productos_variantes pv
                    INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                    WHERE pv.id_variante = :id AND pv.activo = 1 AND pr.activo = 1";
    
    $stmt = $db->prepare($stock_query);
    $stmt->bindParam(':id', $variante_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        $stock_disponible = $producto['stock_tienda'] + $producto['stock_bodega'];
        
        if ($cantidad <= $stock_disponible) {
            // Inicializar carrito si no existe
            if (!isset($_SESSION['carrito'])) {
                $_SESSION['carrito'] = [];
            }
            
            // Verificar si la variante ya está en el carrito
            $encontrado = false;
            foreach ($_SESSION['carrito'] as &$item) {
                if ($item['variante_id'] == $variante_id) {
                    $item['cantidad'] += $cantidad;
                    $encontrado = true;
                    break;
                }
            }
            
            // Si no está, agregarlo
            if (!$encontrado) {
                $precio_variante = isset($producto['precio_venta']) ? (float)$producto['precio_venta'] : 0;
                $precio_base = isset($producto['precio_base']) ? (float)$producto['precio_base'] : 0;
                $precio = $precio_variante > 0 ? $precio_variante : $precio_base;
                
                $_SESSION['carrito'][] = [
                    'variante_id' => $variante_id,
                    'producto_id' => $producto['id_producto_raiz'],
                    'nombre' => $producto['producto_nombre'],
                    'color' => $producto['color'],
                    'talla' => $producto['talla'],
                    'precio' => $precio,
                    'cantidad' => $cantidad,
                    'imagen' => $producto['imagen']
                ];
            }
            
            // Calcular total del carrito
            $carrito_count = 0;
            $carrito_total = 0;
            
            foreach ($_SESSION['carrito'] as $item) {
                $carrito_count += $item['cantidad'];
                $carrito_total += $item['precio'] * $item['cantidad'];
            }
            
            $_SESSION['carrito_total'] = $carrito_total;
            
            echo json_encode([
                'success' => true,
                'message' => 'Producto agregado al carrito',
                'producto_nombre' => $producto['producto_nombre'],
                'color' => $producto['color'],
                'talla' => $producto['talla'],
                'cantidad' => $cantidad,
                'carrito_count' => $carrito_count,
                'carrito_total' => $carrito_total
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Stock insuficiente']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;;
}
?>