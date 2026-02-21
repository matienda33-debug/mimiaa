<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $puntos_usar = (int)$_POST['puntos'] ?? 0;
    
    // Obtener puntos del cliente
    $cliente_query = "SELECT puntos_acumulados FROM clientes WHERE id_cliente = :id";
    $cliente_stmt = $db->prepare($cliente_query);
    $cliente_stmt->bindParam(':id', $_SESSION['id_usuario']);
    $cliente_stmt->execute();
    $cliente = $cliente_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente || $cliente['puntos_acumulados'] < $puntos_usar) {
        throw new Exception('Puntos insuficientes');
    }
    
    // Cada punto = Q0.10
    $descuento = $puntos_usar * 0.10;
    
    // Actualizar puntos
    $nuevo_puntos = $cliente['puntos_acumulados'] - $puntos_usar;
    $update_query = "UPDATE clientes SET puntos_acumulados = :puntos WHERE id_cliente = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':puntos', $nuevo_puntos);
    $update_stmt->bindParam(':id', $_SESSION['id_usuario']);
    $update_stmt->execute();
    
    echo json_encode([
        'success' => true,
        'descuento' => $descuento,
        'puntos_restantes' => $nuevo_puntos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
