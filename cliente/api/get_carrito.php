<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json; charset=utf-8');

$carrito = isset($_SESSION['carrito']) ? $_SESSION['carrito'] : [];
$total = isset($_SESSION['carrito_total']) ? $_SESSION['carrito_total'] : 0;

if (!is_array($carrito)) {
    $carrito = [];
}

// Calcular totales
$carrito_count = 0;
$carrito_total = 0;

$actualizado = false;
foreach ($carrito as $index => $item) {
    $precio_item = isset($item['precio']) ? (float)$item['precio'] : 0;
    if ($precio_item <= 0 && isset($item['variante_id'])) {
        $precio_stmt = $db->prepare("SELECT pv.precio_venta as precio_variante, pr.precio_venta as precio_base FROM productos_variantes pv INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz WHERE pv.id_variante = :id LIMIT 1");
        $precio_stmt->bindValue(':id', (int)$item['variante_id'], PDO::PARAM_INT);
        $precio_stmt->execute();
        $precio_row = $precio_stmt->fetch(PDO::FETCH_ASSOC);
        if ($precio_row) {
            $precio_variante = isset($precio_row['precio_variante']) ? (float)$precio_row['precio_variante'] : 0;
            $precio_base = isset($precio_row['precio_base']) ? (float)$precio_row['precio_base'] : 0;
            $precio_item = $precio_variante > 0 ? $precio_variante : $precio_base;
            $_SESSION['carrito'][$index]['precio'] = $precio_item;
            $actualizado = true;
        }
    }

    $carrito_count += $item['cantidad'];
    $carrito_total += $precio_item * $item['cantidad'];
}

if ($actualizado) {
    $carrito = $_SESSION['carrito'];
}

echo json_encode([
    'success' => true,
    'items' => $carrito,
    'count' => $carrito_count,
    'total' => $carrito_total
]);
exit;
?>
