<?php
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['orden_completada'])) {
    header('Location: index.php');
    exit();
}

$orden = $_SESSION['orden_completada'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Completado - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
                            </div>
                            <h2 class="mb-2">¡Pedido creado exitosamente!</h2>
                            <p class="text-muted mb-0">Tu pedido fue registrado y está en proceso de revisión por el equipo de tienda.</p>
                        </div>

                        <div class="bg-light rounded p-3 mb-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Factura</small>
                                    <strong><?php echo htmlspecialchars($orden['numero_factura']); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Orden</small>
                                    <strong><?php echo htmlspecialchars($orden['numero_orden']); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Total</small>
                                    <strong><?php echo formatMoney($orden['total']); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Correo</small>
                                    <strong><?php echo htmlspecialchars($orden['email']); ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Puedes revisar el estado de tu pedido sin iniciar sesión, usando tu número de factura.
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="estado_pedido.php?numero_factura=<?php echo urlencode($orden['numero_factura']); ?>" class="btn btn-primary">
                                <i class="fas fa-clipboard-list me-2"></i> Ver Estado del Pedido
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-store me-2"></i> Volver a la Tienda
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
