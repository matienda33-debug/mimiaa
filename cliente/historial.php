<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Obtener historial de compras del cliente
$query = "SELECT fc.*, u.nombre as usuario_nombre, ef.nombre as estado_nombre
         FROM factura_cabecera fc
         LEFT JOIN usuarios u ON fc.id_usuario = u.id_usuario
         LEFT JOIN estado_factura ef ON fc.id_estado = ef.id_estado
         WHERE fc.id_cliente = :id_cliente
         ORDER BY fc.fecha DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':id_cliente', $_SESSION['id_usuario'], PDO::PARAM_INT);
$stmt->execute();
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Compras - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .order-card {
            border-left: 5px solid #1abc9c;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .order-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 12px;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-aceptado { background-color: #6A73C5; }
        .status-autorizado { background-color: #515AB8; }
        .status-empacado { background-color: #434BA0; }
        .status-enviado { background-color: #363D87; }
        .status-cancelada { background-color: #8A90CD; color: #1f254d; }
        .status-default { background-color: #515AB8; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active">Mis Compras</li>
            </ol>
        </nav>
        
        <h1 class="mb-4">
            <i class="fas fa-history me-2"></i> Historial de Compras
        </h1>
        
        <?php if (!empty($compras)): ?>
        <div class="row">
            <?php foreach ($compras as $compra): ?>
            <div class="col-md-6 mb-3">
                <div class="card order-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title">Factura <?php echo $compra['numero_factura']; ?></h5>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($compra['fecha'])); ?>
                                </small>
                            </div>
                            <?php
                                $estado_actual = strtolower($compra['estado_nombre'] ?? 'aceptado');
                                $clase_estado = [
                                    'aceptado' => 'status-aceptado',
                                    'autorizado' => 'status-autorizado',
                                    'empacado' => 'status-empacado',
                                    'enviado' => 'status-enviado',
                                    'cancelada' => 'status-cancelada'
                                ][$estado_actual] ?? 'status-default';
                            ?>
                            <span class="badge status-badge <?php echo $clase_estado; ?>">
                                <?php echo ucfirst($estado_actual); ?>
                            </span>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Total:</strong><br>
                                Q<?php echo number_format($compra['total'], 2); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Artículos:</strong><br>
                                <?php 
                                $items_query = "SELECT COUNT(*) as cantidad FROM factura_detalle WHERE id_factura = :id";
                                $items_stmt = $db->prepare($items_query);
                                $items_stmt->bindParam(':id', $compra['id_factura']);
                                $items_stmt->execute();
                                $items = $items_stmt->fetch(PDO::FETCH_ASSOC);
                                echo $items['cantidad'];
                                ?>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye me-1"></i> Ver Detalles
                            </a>
                            <a href="api/enviar_factura.php?id=<?php echo $compra['id_factura']; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-envelope me-1"></i> Descargar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            No tienes compras registradas. <a href="index.php">Comienza a comprar ahora</a>.
        </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
