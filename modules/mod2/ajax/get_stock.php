<?php
// ajax/get_stock.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/config.php';

header('Content-Type: application/json');

// Verificar autenticación
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit();
}

if (isset($_GET['id_variante'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $stmt = $db->prepare("SELECT stock_tienda, stock_bodega FROM productos_variantes WHERE id_variante = :id");
        $stmt->bindParam(':id', $_GET['id_variante']);
        $stmt->execute();
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode([
                'success' => true,
                'stock_tienda' => intval($row['stock_tienda']),
                'stock_bodega' => intval($row['stock_bodega'])
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID de variante no proporcionado']);
}