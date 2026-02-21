<?php
// public/ajax/carrito.php
require_once '../../app/config/database.php';
require_once '../../app/models/Carrito.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$carritoModel = new Carrito($db);

// Iniciar sesión si no existe
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['cart_session_id'])) {
    $_SESSION['cart_session_id'] = session_id() . '_' . uniqid();
}

$session_id = $_SESSION['cart_session_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        $id_variante = $_POST['id_variante'] ?? 0;
        $cantidad = $_POST['cantidad'] ?? 1;
        
        if ($id_variante > 0) {
            $result = $carritoModel->agregarAlCarrito($session_id, $id_variante, $cantidad);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de variante inválido']);
        }
        break;
        
    case 'update':
        $id_variante = $_POST['id_variante'] ?? 0;
        $cantidad = $_POST['cantidad'] ?? 1;
        
        if ($id_variante > 0) {
            $result = $carritoModel->actualizarCantidad($session_id, $id_variante, $cantidad);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de variante inválido']);
        }
        break;
        
    case 'remove':
        $id_variante = $_POST['id_variante'] ?? 0;
        
        if ($id_variante > 0) {
            $result = $carritoModel->eliminarDelCarrito($session_id, $id_variante);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de variante inválido']);
        }
        break;
        
    case 'clear':
        $result = $carritoModel->vaciarCarrito($session_id);
        echo json_encode($result);
        break;
        
    case 'get':
        $carrito = $carritoModel->obtenerCarrito($session_id);
        $total = $carritoModel->obtenerTotalCarrito($session_id);
        
        echo json_encode([
            'success' => true,
            'items' => $carrito,
            'total' => $total['total'] ?? 0,
            'count' => $total['items'] ?? 0
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>