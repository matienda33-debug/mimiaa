<?php
header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['id_rol'], [1, 3])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$id_producto = isset($_GET['id_producto']) ? intval($_GET['id_producto']) : 0;

if ($id_producto > 0) {
    try {
        $query = "SELECT v.*, (v.stock_tienda + v.stock_bodega) as stock_total,
                         pr.nombre as producto_nombre
                  FROM productos_variantes v
                  JOIN productos_raiz pr ON v.id_producto_raiz = pr.id_raiz
                  WHERE v.id_producto_raiz = :id_producto
                  AND v.activo = 1
                  ORDER BY v.color, v.talla";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id_producto', $id_producto, PDO::PARAM_INT);
        $stmt->execute();
        
        $variantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $variantes
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'error' => 'Error al obtener variantes: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'error' => 'ID de producto no válido'
    ]);
}
$conn = null;
?>