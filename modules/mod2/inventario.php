<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Verificar autenticación y permiso
if (!$auth->isLoggedIn()) {
    header('Location: /tiendaAA/index.php');
    exit();
}
$auth->requirePermission('inventario');

$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';

// Obtener estadísticas
$stats_query = "SELECT 
                SUM(stock_tienda + stock_bodega) as total_stock,
                COUNT(DISTINCT pv.id_producto_raiz) as total_productos,
                COUNT(*) as total_variantes,
                SUM(CASE WHEN (stock_tienda + stock_bodega) = 0 THEN 1 ELSE 0 END) as sin_stock,
                SUM(CASE WHEN (stock_tienda + stock_bodega) < 5 THEN 1 ELSE 0 END) as stock_bajo
                FROM productos_variantes pv
                INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                WHERE pv.activo = 1 AND pr.activo = 1";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Obtener productos bajos en stock
$low_stock_query = "SELECT pv.*, pr.codigo, pr.nombre as producto_nombre, 
                    (pv.stock_tienda + pv.stock_bodega) as total_stock
                    FROM productos_variantes pv
                    INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                    WHERE pv.activo = 1 AND pr.activo = 1 
                    AND (pv.stock_tienda + pv.stock_bodega) < 5
                    ORDER BY total_stock ASC
                    LIMIT 10";
$low_stock_stmt = $db->prepare($low_stock_query);
$low_stock_stmt->execute();
$low_stock = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener movimientos recientes
$movimientos_query = "SELECT im.*, pv.color, pv.talla, pr.codigo, pr.nombre as producto_nombre,
                      u.nombre as usuario_nombre
                      FROM inventario_movimientos im
                      INNER JOIN productos_variantes pv ON im.id_producto_variante = pv.id_variante
                      INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                      LEFT JOIN usuarios u ON im.id_usuario = u.id_usuario
                      ORDER BY im.fecha_movimiento DESC
                      LIMIT 20";
$movimientos_stmt = $db->prepare($movimientos_query);
$movimientos_stmt->execute();
$movimientos = $movimientos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener stock por departamento
$depto_stock_query = "SELECT d.nombre, 
                      SUM(pv.stock_tienda + pv.stock_bodega) as total_stock,
                      COUNT(DISTINCT pv.id_variante) as total_variantes
                      FROM departamentos d
                      INNER JOIN productos_raiz pr ON d.id_departamento = pr.id_departamento
                      INNER JOIN productos_variantes pv ON pr.id_raiz = pv.id_producto_raiz
                      WHERE d.activo = 1 AND pr.activo = 1 AND pv.activo = 1
                      GROUP BY d.id_departamento
                      ORDER BY total_stock DESC";
$depto_stock_stmt = $db->prepare($depto_stock_query);
$depto_stock_stmt->execute();
$depto_stock = $depto_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php if (!$embedded): ?>
    <?php include '../../includes/header.php'; ?>
    <?php endif; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <main class="col-12 px-2">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Inventario</h1>
                    <div>
                        <a href="productos.php" class="btn btn-secondary">
                            <i class="fas fa-tshirt me-1"></i> Productos
                        </a>
                        <a href="variantes.php" class="btn btn-primary">
                            <i class="fas fa-boxes me-1"></i> Variantes
                        </a>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6 class="card-title">Stock Total</h6>
                                <h3 class="card-text"><?php echo $stats['total_stock'] ?: 0; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6 class="card-title">Productos Activos</h6>
                                <h3 class="card-text"><?php echo $stats['total_productos'] ?: 0; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h6 class="card-title">Variantes Activas</h6>
                                <h3 class="card-text"><?php echo $stats['total_variantes'] ?: 0; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h6 class="card-title">Sin Stock / Bajo</h6>
                                <h3 class="card-text"><?php echo $stats['sin_stock'] ?: 0; ?> / <?php echo $stats['stock_bajo'] ?: 0; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Productos bajos en stock -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    Productos Bajos en Stock
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Color/Talla</th>
                                                <th>Stock Total</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($low_stock as $item): ?>
                                            <tr class="<?php echo $item['total_stock'] == 0 ? 'table-danger' : 'table-warning'; ?>">
                                                <td>
                                                    <small><?php echo $item['codigo']; ?></small><br>
                                                    <?php echo substr($item['producto_nombre'], 0, 30); ?>
                                                </td>
                                                <td>
                                                    <?php echo $item['color']; ?><br>
                                                    <strong><?php echo $item['talla']; ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $item['total_stock'] == 0 ? 'danger' : 'warning'; ?>">
                                                        <?php echo $item['total_stock']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="variantes.php?action=edit&id=<?php echo $item['id_variante']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock por departamento -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie text-info me-2"></i>
                                    Stock por Departamento
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Departamento</th>
                                                <th>Variantes</th>
                                                <th>Stock Total</th>
                                                <th>%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                                $total_stock_depto = 0;
                                                foreach ($depto_stock as $depto) {
                                                    $total_stock_depto += $depto['total_stock'];
                                                }
                                                
                                                foreach ($depto_stock as $depto):
                                                    $percentage = $total_stock_depto > 0 ? ($depto['total_stock'] / $total_stock_depto * 100) : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo $depto['nombre']; ?></td>
                                                <td><?php echo $depto['total_variantes']; ?></td>
                                                <td><?php echo $depto['total_stock']; ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-info" role="progressbar" 
                                                             style="width: <?php echo $percentage; ?>%;" 
                                                             aria-valuenow="<?php echo $percentage; ?>" 
                                                             aria-valuemin="0" aria-valuemax="100">
                                                            <?php echo round($percentage, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Movimientos recientes -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>
                            Movimientos Recientes de Inventario
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Producto</th>
                                        <th>Color/Talla</th>
                                        <th>Tipo</th>
                                        <th>Cantidad</th>
                                        <th>Ubicación</th>
                                        <th>Motivo</th>
                                        <th>Usuario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movimientos as $mov): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                        <td>
                                            <small><?php echo $mov['codigo']; ?></small><br>
                                            <?php echo substr($mov['producto_nombre'], 0, 30); ?>
                                        </td>
                                        <td>
                                            <?php echo $mov['color']; ?><br>
                                            <strong><?php echo $mov['talla']; ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($mov['tipo_movimiento'] == 'entrada'): ?>
                                                <span class="badge bg-success">Entrada</span>
                                            <?php elseif ($mov['tipo_movimiento'] == 'salida'): ?>
                                                <span class="badge bg-danger">Salida</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Ajuste</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $mov['cantidad']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $mov['ubicacion'] == 'tienda' ? 'info' : 'warning text-dark'; ?>">
                                                <?php echo ucfirst($mov['ubicacion']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $mov['motivo']; ?></td>
                                        <td><?php echo $mov['usuario_nombre'] ?: 'Sistema'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="movimientos.php" class="btn btn-sm btn-outline-primary">
                                Ver todos los movimientos
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Gráfico de stock por departamento
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('stockChart');
            if (ctx) {
                const labels = <?php echo json_encode(array_column($depto_stock, 'nombre')); ?>;
                const data = <?php echo json_encode(array_column($depto_stock, 'total_stock')); ?>;
                
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: [
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                                '#9966FF', '#FF9F40', '#8AC926', '#1982C4'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>