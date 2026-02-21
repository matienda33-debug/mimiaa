<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $variante_id = isset($_POST['variante_id']) ? (int)$_POST['variante_id'] : 0;
    
    if ($variante_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de variante inválido']);
        exit;
    }
    
    if (!isset($_SESSION['carrito'])) {
        $_SESSION['carrito'] = [];
    }
    
    // Buscar y eliminar el item
    foreach ($_SESSION['carrito'] as $key => $item) {
        if ($item['variante_id'] == $variante_id) {
            unset($_SESSION['carrito'][$key]);
            break;
        }
    }
    
    // Recalcular total
    $carrito_total = 0;
    $carrito_count = 0;
    
    foreach ($_SESSION['carrito'] as $item) {
        $carrito_count += $item['cantidad'];
        $carrito_total += $item['precio'] * $item['cantidad'];
    }
    
    $_SESSION['carrito_total'] = $carrito_total;
    
    echo json_encode([
        'success' => true,
        'message' => 'Producto eliminado del carrito',
        'count' => $carrito_count,
        'total' => $carrito_total
    ]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}
?>
