<?php
require_once 'config/config.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: index.php');
    exit();
}

// Obtener logs
$logs = [];
$log_dir = 'logs/';
if (is_dir($log_dir)) {
    $log_files = glob($log_dir . 'correos_*.log');
    rsort($log_files);
    
    foreach ($log_files as $file) {
        $lines = file($file);
        $logs = array_merge($logs, $lines);
    }
    rsort($logs);
    $logs = array_slice($logs, 0, 100); // Últimos 100 registros
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Correos - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .container-max { max-width: 1000px; }
        .log-entry {
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
            font-family: monospace;
            font-size: 12px;
        }
        .log-entry.enviado { background: #d4edda; }
        .log-entry.error { background: #f8d7da; }
        .log-entry.info { background: #d1ecf1; }
        .header-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container container-max">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-envelope me-2"></i> Logs de Correos</h1>
            <a href="modules/mod1/dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Volver
            </a>
        </div>

        <div class="header-card">
            <h4 class="mb-3">Información</h4>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Total de registros:</strong> <?php echo count($logs); ?></p>
                    <p><strong>Directorio de logs:</strong> <code>logs/</code></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Último log:</strong> 
                        <?php 
                            if (!empty($logs)) {
                                $first_line = reset($logs);
                                preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $first_line, $matches);
                                echo $matches[1] ?? 'Desconocido';
                            } else {
                                echo 'Sin registros';
                            }
                        ?>
                    </p>
                    <a href="diagnostico_correos.php" class="btn btn-sm btn-info mt-2">
                        <i class="fas fa-wrench me-1"></i> Ir a Diagnóstico
                    </a>
                </div>
            </div>
        </div>

        <?php if (empty($logs)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Sin registros:</strong> Aún no se han enviado correos. Intenta enviar un comprobante para generar logs.
            </div>
        <?php else: ?>
            <div class="header-card">
                <h5 class="mb-3">Últimos Registros</h5>
                <div style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                    <?php foreach ($logs as $log): 
                        $log = trim($log);
                        if (empty($log)) continue;
                        
                        if (strpos($log, 'ENVIADO') !== false) {
                            $class = 'enviado';
                            $icon = '<i class="fas fa-check-circle text-success"></i>';
                        } elseif (strpos($log, 'ERROR') !== false) {
                            $class = 'error';
                            $icon = '<i class="fas fa-times-circle text-danger"></i>';
                        } else {
                            $class = 'info';
                            $icon = '<i class="fas fa-info-circle text-info"></i>';
                        }
                    ?>
                        <div class="log-entry <?php echo $class; ?>">
                            <?php echo $icon; ?> <?php echo htmlspecialchars($log); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="header-card mt-3">
            <h5 class="mb-3">Acciones</h5>
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="fas fa-sync me-2"></i> Actualizar
            </button>
            <button class="btn btn-warning" onclick="limpiarLogs()">
                <i class="fas fa-trash me-2"></i> Limpiar Logs
            </button>
            <a href="diagnostico_correos.php" class="btn btn-info">
                <i class="fas fa-tools me-2"></i> Configurar SMTP
            </a>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function limpiarLogs() {
            if (confirm('¿Estás seguro de que deseas eliminar todos los logs?')) {
                // Aquí irría una llamada a un API para limpiar logs
                alert('Función no implementada. Elimina manualmente los archivos en /logs/');
            }
        }
    </script>
</body>
</html>
