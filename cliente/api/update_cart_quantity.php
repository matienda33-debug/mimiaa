<?php
// Actualizar cantidad de producto en el carrito
header('Content-Type: application/json');

// Iniciar sesión
if (!isset($_SESSION)) {
    session_start();
}

// Limpiar buffer de salida
if (ob_get_level()) ob_clean();

try {
    // Validar que sea POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener parámetros
    $variante_id = isset($_POST['variante_id']) ? (int)$_POST['variante_id'] : 0;
    $nueva_cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;
    
    if (!$variante_id || $nueva_cantidad < 1) {
        throw new Exception('Parámetros inválidos');
    }
    
    // Verificar que el carrito existe
    if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
        throw new Exception('Carrito no encontrado');
    }
    
    // Buscar el producto en el carrito
    $producto_encontrado = false;
    $total = 0;
    
    foreach ($_SESSION['carrito'] as $key => $item) {
        if ($item['variante_id'] == $variante_id) {
            // Actualizar cantidad
            $_SESSION['carrito'][$key]['cantidad'] = $nueva_cantidad;
            $producto_encontrado = true;
        }
        
        // Calcular total
        $total += $item['precio'] * $item['cantidad'];
    }
    
    if (!$producto_encontrado) {
        throw new Exception('Producto no encontrado en el carrito');
    }
    
    // Contar total de items
    $count = 0;
    foreach ($_SESSION['carrito'] as $item) {
        $count += $item['cantidad'];
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Cantidad actualizada',
        'count' => $count,
        'total' => number_format($total, 2, '.', '')
    ]);
    exit;
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
