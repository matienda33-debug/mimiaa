<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['factura_id']) && isset($_POST['nuevo_estado'])) {
    $factura_id = $_POST['factura_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    $comentario = isset($_POST['comentario']) ? sanitize($_POST['comentario']) : '';
    
    try {
        // Verificar que la factura existe
        $query = "SELECT id_estado, id_cliente, total, puntos_ganados 
                  FROM factura_cabecera 
                  WHERE id_factura = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $factura_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
            exit();
        }
        
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        $estado_actual = $factura['id_estado'];
        
        // Obtener nombre del estado actual
        $estado_query = "SELECT nombre FROM estado_factura WHERE id_estado = :id";
        $estado_stmt = $db->prepare($estado_query);
        $estado_stmt->bindParam(':id', $estado_actual);
        $estado_stmt->execute();
        $estado_actual_data = $estado_stmt->fetch(PDO::FETCH_ASSOC);
        $estado_actual_nombre = strtolower($estado_actual_data['nombre'] ?? 'desconocido');
        
        // Validar cambios después de empaque
        // La factura NO puede ser modificada (retrocedida) después de alcanzar "empacado"
        $estados_no_editables = ['empacado', 'enviado', 'entregada', 'cancelada'];
        
        if (in_array($estado_actual_nombre, $estados_no_editables)) {
            // Solo permitir cambio a cancelada (para cancelaciones) 
            // y movimiento dentro del flujo normal (empacado→enviado→entregada)
            $nuevo_estado_query = "SELECT nombre FROM estado_factura WHERE id_estado = :id";
            $nuevo_estado_stmt = $db->prepare($nuevo_estado_query);
            $nuevo_estado_stmt->bindParam(':id', $nuevo_estado);
            $nuevo_estado_stmt->execute();
            $nuevo_estado_data = $nuevo_estado_stmt->fetch(PDO::FETCH_ASSOC);
            $nuevo_estado_nombre = strtolower($nuevo_estado_data['nombre'] ?? 'desconocido');
            
            // Flujo permitido después de empaque: empacado → enviado → entregada
            $flujo_permitido = [
                'empacado' => ['enviado', 'cancelada'],
                'enviado' => ['entregada', 'cancelada'],
                'entregada' => ['cancelada'],
                'cancelada' => []  // No hay cambios permitidos después de cancelada
            ];
            
            if (!isset($flujo_permitido[$estado_actual_nombre]) || 
                !in_array($nuevo_estado_nombre, $flujo_permitido[$estado_actual_nombre])) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'No se puede modificar esta factura. Ya fue empacada y solo se permite continuar en el flujo normal (Enviado → Entregada) o cancelarla.'
                ]);
                exit();
            }
        }
        
        // Si cambia de pendiente a pagada, asignar puntos
        if ($estado_actual == 1 && $nuevo_estado == 2 && $factura['id_cliente']) {
            $puntos_ganados = calcularPuntos($factura['total']);
            
            // Actualizar puntos del cliente
            $update_puntos = "UPDATE clientes SET puntos = puntos + :puntos 
                            WHERE id_cliente = :id_cliente";
            $stmt = $db->prepare($update_puntos);
            $stmt->bindParam(':puntos', $puntos_ganados);
            $stmt->bindParam(':id_cliente', $factura['id_cliente']);
            $stmt->execute();
            
            // Actualizar factura con puntos ganados
            $update_factura = "UPDATE factura_cabecera SET puntos_ganados = :puntos 
                             WHERE id_factura = :id";
            $stmt = $db->prepare($update_factura);
            $stmt->bindParam(':puntos', $puntos_ganados);
            $stmt->bindParam(':id', $factura_id);
            $stmt->execute();
        }
        
        // Actualizar estado de la factura
        $update_query = "UPDATE factura_cabecera SET id_estado = :estado 
                        WHERE id_factura = :id";
        $stmt = $db->prepare($update_query);
        $stmt->bindParam(':estado', $nuevo_estado);
        $stmt->bindParam(':id', $factura_id);
        $stmt->execute();
        
        // Registrar cambio de estado en log
        $log_query = "INSERT INTO factura_estado_log (id_factura, estado_anterior, estado_nuevo, 
                      comentario, id_usuario, fecha) 
                      VALUES (:id_factura, :estado_anterior, :estado_nuevo, 
                      :comentario, :id_usuario, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->bindParam(':id_factura', $factura_id);
        $log_stmt->bindParam(':estado_anterior', $estado_actual);
        $log_stmt->bindParam(':estado_nuevo', $nuevo_estado);
        $log_stmt->bindParam(':comentario', $comentario);
        $log_stmt->bindParam(':id_usuario', $_SESSION['user_id']);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
}
?>