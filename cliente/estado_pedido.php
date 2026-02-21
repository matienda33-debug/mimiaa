<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$numero_factura = isset($_GET['numero_factura']) ? trim($_GET['numero_factura']) : '';
$pedido = null;
$error = '';

if ($numero_factura !== '') {
    try {
        $query = "SELECT fc.id_factura, fc.numero_factura, fc.numero_orden, fc.fecha, fc.nombre_cliente, fc.total,
                         fc.id_estado, ef.nombre AS estado_nombre, ef.descripcion AS estado_descripcion
                  FROM factura_cabecera fc
                  LEFT JOIN estado_factura ef ON fc.id_estado = ef.id_estado
                  WHERE fc.numero_factura = :numero_factura
                  LIMIT 1";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':numero_factura', $numero_factura);
        $stmt->execute();
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            $error = 'No encontramos un pedido con ese número de factura.';
        }
    } catch (Exception $e) {
        $error = 'Ocurrió un error al consultar el estado del pedido.';
    }
}

$estado_actual = strtolower($pedido['estado_nombre'] ?? '');
$clase_estado = [
    'aceptado' => 'status-aceptado',
    'autorizado' => 'status-autorizado',
    'empacado' => 'status-empacado',
    'enviado' => 'status-enviado',
    'cancelada' => 'status-cancelada'
][$estado_actual] ?? 'status-default';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de Pedido - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .status-badge {
            color: #fff;
            font-weight: 600;
            padding: 10px 14px;
            border-radius: 8px;
            text-transform: capitalize;
            display: inline-block;
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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h3 class="mb-3"><i class="fas fa-search-location me-2"></i>Consultar Estado de Pedido</h3>
                        <p class="text-muted">No necesitas iniciar sesión. Ingresa tu número de factura para ver el estado actual.</p>

                        <form method="GET" class="row g-2 mb-3">
                            <div class="col-md-9">
                                <input type="text" name="numero_factura" class="form-control" placeholder="Ejemplo: ONL-20260220XXXX" value="<?php echo htmlspecialchars($numero_factura); ?>" required>
                            </div>
                            <div class="col-md-3 d-grid">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i>Buscar</button>
                            </div>
                        </form>

                        <?php if ($error): ?>
                            <div class="alert alert-warning mb-0"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if ($pedido): ?>
                            <hr>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Factura</small>
                                    <strong><?php echo htmlspecialchars($pedido['numero_factura']); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Orden</small>
                                    <strong><?php echo htmlspecialchars($pedido['numero_orden'] ?? '-'); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Cliente</small>
                                    <strong><?php echo htmlspecialchars($pedido['nombre_cliente']); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Fecha</small>
                                    <strong><?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Total</small>
                                    <strong><?php echo formatMoney($pedido['total']); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Estado Actual</small>
                                    <span class="status-badge <?php echo $clase_estado; ?>"><?php echo htmlspecialchars($pedido['estado_nombre'] ?? 'Sin estado'); ?></span>
                                </div>
                            </div>

                            <?php if (!empty($pedido['estado_descripcion'])): ?>
                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($pedido['estado_descripcion']); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
