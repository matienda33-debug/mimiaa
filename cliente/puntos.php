<?php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$mensaje = '';
$tipo_mensaje = 'info';
$cliente = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dpi = sanitize($_POST['dpi'] ?? '');

    if ($dpi === '' || !preg_match('/^[0-9]{13}$/', $dpi)) {
        $mensaje = 'Ingresa un DPI valido de 13 digitos.';
        $tipo_mensaje = 'warning';
    } else {
        $stmt = $db->prepare("SELECT nombre, apellido, dpi, puntos FROM clientes WHERE dpi = :dpi LIMIT 1");
        $stmt->bindParam(':dpi', $dpi);
        $stmt->execute();
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            $mensaje = 'No encontramos puntos asociados a ese DPI.';
            $tipo_mensaje = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntos por DPI - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="mb-3"><i class="fas fa-coins me-2"></i>Puntos acumulados</h3>
                        <p class="text-muted">Consulta tus puntos ingresando tu DPI.</p>

                        <?php if ($mensaje): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
                        <?php endif; ?>

                        <form method="POST" class="mb-4">
                            <label for="dpi" class="form-label">DPI</label>
                            <input type="text" id="dpi" name="dpi" class="form-control" maxlength="13" pattern="[0-9]{13}" required>
                            <small class="text-muted">Debe tener 13 digitos.</small>
                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-primary">Consultar puntos</button>
                            </div>
                        </form>

                        <?php if ($cliente): ?>
                            <div class="border rounded p-3 bg-light">
                                <p class="mb-1"><strong>Cliente:</strong> <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></p>
                                <p class="mb-1"><strong>DPI:</strong> <?php echo htmlspecialchars($cliente['dpi']); ?></p>
                                <p class="mb-0"><strong>Puntos:</strong> <?php echo (int)$cliente['puntos']; ?> pts (<?php echo formatMoney(valorEnPuntos((int)$cliente['puntos'])); ?>)</p>
                            </div>
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
