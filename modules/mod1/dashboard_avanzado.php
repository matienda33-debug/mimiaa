<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Verificar autenticación
if (!$auth->isLoggedIn()) {
    header('Location: /tiendaAA/index.php');
    exit();
}

// Obtener estadísticas en tiempo real
$stats_query = "SELECT 
                -- Ventas del día
                (SELECT COALESCE(SUM(total), 0) FROM factura_cabecera 
                 WHERE id_estado = 2 AND DATE(fecha) = CURDATE()) as ventas_hoy,
                
                -- Ventas de la semana
                (SELECT COALESCE(SUM(total), 0) FROM factura_cabecera 
                 WHERE id_estado = 2 AND WEEK(fecha) = WEEK(CURDATE())) as ventas_semana,
                
                -- Ventas del mes
                (SELECT COALESCE(SUM(total), 0) FROM factura_cabecera 
                 WHERE id_estado = 2 AND MONTH(fecha) = MONTH(CURDATE())) as ventas_mes,
                
                -- Clientes nuevos hoy
                (SELECT COUNT(*) FROM clientes 
                 WHERE DATE(fecha_registro) = CURDATE()) as clientes_nuevos_hoy,
                
                -- Productos bajos en stock
                (SELECT COUNT(*) FROM productos_variantes 
                 WHERE (stock_tienda + stock_bodega) < 5 AND activo = 1) as productos_bajo_stock,
                
                -- Facturas pendientes
                (SELECT COUNT(*) FROM factura_cabecera 
                 WHERE id_estado = 1 AND tipo_venta = 'online') as facturas_pendientes,
                
                -- Puntos generados hoy
                (SELECT COALESCE(SUM(puntos_ganados), 0) FROM factura_cabecera 
                 WHERE id_estado = 2 AND DATE(fecha) = CURDATE()) as puntos_generados_hoy,
                
                -- Utilidad estimada del mes
                (SELECT COALESCE(SUM(total - (
                    SELECT SUM(fd.cantidad * COALESCE(pr.precio_compra, pr.precio_venta * 0.6))
                    FROM factura_detalle fd
                    INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                    INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                    WHERE fd.id_factura = fc.id_factura
                )), 0) FROM factura_cabecera fc
                 WHERE id_estado = 2 AND MONTH(fecha) = MONTH(CURDATE())) as utilidad_mes";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Ventas por hora del día actual
$ventas_hora_query = "SELECT 
                      HOUR(fecha) as hora,
                      COUNT(*) as cantidad,
                      SUM(total) as total
                      FROM factura_cabecera
                      WHERE id_estado = 2 AND DATE(fecha) = CURDATE()
                      GROUP BY HOUR(fecha)
                      ORDER BY hora";

$ventas_hora_stmt = $db->prepare($ventas_hora_query);
$ventas_hora_stmt->execute();
$ventas_hora = $ventas_hora_stmt->fetchAll(PDO::FETCH_ASSOC);

// Productos más vendidos del mes
$top_productos_query = "SELECT 
                        pr.nombre as producto,
                        SUM(fd.cantidad) as vendidos,
                        SUM(fd.subtotal) as total_ventas
                        FROM factura_detalle fd
                        INNER JOIN factura_cabecera fc ON fd.id_factura = fc.id_factura
                        INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                        INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                        WHERE fc.id_estado = 2 AND MONTH(fc.fecha) = MONTH(CURDATE())
                        GROUP BY pr.id_raiz
                        ORDER BY vendidos DESC
                        LIMIT 5";

$top_productos_stmt = $db->prepare($top_productos_query);
$top_productos_stmt->execute();
$top_productos = $top_productos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Mejores vendedores del mes
$top_vendedores_query = "SELECT 
                         CONCAT(u.nombre, ' ', u.apellido) as vendedor,
                         COUNT(fc.id_factura) as ventas,
                         SUM(fc.total) as total_ventas
                         FROM factura_cabecera fc
                         INNER JOIN usuarios u ON fc.id_usuario = u.id_usuario
                         WHERE fc.id_estado = 2 AND MONTH(fc.fecha) = MONTH(CURDATE())
                         GROUP BY u.id_usuario
                         ORDER BY total_ventas DESC
                         LIMIT 5";

$top_vendedores_stmt = $db->prepare($top_vendedores_query);
$top_vendedores_stmt->execute();
$top_vendedores = $top_vendedores_stmt->fetchAll(PDO::FETCH_ASSOC);

// Últimas ventas
$ultimas_ventas_query = "SELECT 
                         fc.numero_factura,
                         fc.nombre_cliente,
                         fc.total,
                         fc.fecha,
                         u.nombre as vendedor_nombre,
                         fc.tipo_venta
                         FROM factura_cabecera fc
                         LEFT JOIN usuarios u ON fc.id_usuario = u.id_usuario
                         WHERE fc.id_estado = 2
                         ORDER BY fc.fecha DESC
                         LIMIT 10";

$ultimas_ventas_stmt = $db->prepare($ultimas_ventas_query);
$ultimas_ventas_stmt->execute();
$ultimas_ventas = $ultimas_ventas_stmt->fetchAll(PDO::FETCH_ASSOC);

// Métricas de crecimiento
$crecimiento_query = "SELECT 
                      -- Crecimiento ventas vs mes anterior
                      (SELECT COALESCE(SUM(total), 0) FROM factura_cabecera 
                       WHERE id_estado = 2 AND MONTH(fecha) = MONTH(CURDATE())) as ventas_actual,
                      
                      (SELECT COALESCE(SUM(total), 0) FROM factura_cabecera 
                       WHERE id_estado = 2 AND MONTH(fecha) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) as ventas_anterior,
                      
                      -- Crecimiento clientes vs mes anterior
                      (SELECT COUNT(*) FROM clientes 
                       WHERE MONTH(fecha_registro) = MONTH(CURDATE())) as clientes_actual,
                      
                      (SELECT COUNT(*) FROM clientes 
                       WHERE MONTH(fecha_registro) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))) as clientes_anterior";

$crecimiento_stmt = $db->prepare($crecimiento_query);
$crecimiento_stmt->execute();
$crecimiento = $crecimiento_stmt->fetch(PDO::FETCH_ASSOC);

// Calcular porcentajes de crecimiento
$crecimiento_ventas = $crecimiento['ventas_anterior'] > 0 ? 
                     (($crecimiento['ventas_actual'] - $crecimiento['ventas_anterior']) / $crecimiento['ventas_anterior']) * 100 : 0;

$crecimiento_clientes = $crecimiento['clientes_anterior'] > 0 ? 
                       (($crecimiento['clientes_actual'] - $crecimiento['clientes_anterior']) / $crecimiento['clientes_anterior']) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Avanzado - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        .stats-card {
            border-radius: 10px;
            margin-bottom: 20px;
            transition: transform 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        .dashboard-widget {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .widget-header {
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .real-time-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .growth-positive {
            color: #28a745;
        }
        .growth-negative {
            color: #dc3545;
        }
        .metric-change {
            font-size: 0.8rem;
            font-weight: bold;
        }
        .quick-actions {
            margin-top: 20px;
        }
        .action-button {
            width: 100%;
            margin-bottom: 10px;
            text-align: left;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Dashboard Avanzado
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="btn btn-sm btn-outline-success real-time-badge">
                                <i class="fas fa-sync-alt me-1"></i> Actualizando en tiempo real
                            </span>
                            <button class="btn btn-sm btn-outline-primary" onclick="actualizarDashboard()">
                                <i class="fas fa-redo me-1"></i> Actualizar
                            </button>
                        </div>
                        <span class="text-muted">
                            Última actualización: <span id="lastUpdate"><?php echo date('H:i:s'); ?></span>
                        </span>
                    </div>
                </div>

                <!-- Métricas principales -->
                <div class="row">
                    <div class="col-xl-3 col-lg-6">
                        <div class="stats-card text-white" style="background: linear-gradient(45deg, #1abc9c, #16a085);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">VENTAS HOY</h6>
                                        <h2 class="card-text"><?php echo formatMoney($stats['ventas_hoy']); ?></h2>
                                        <p class="card-text mb-0">
                                            <small>
                                                <i class="fas fa-arrow-up me-1 growth-<?php echo $crecimiento_ventas >= 0 ? 'positive' : 'negative'; ?>"></i>
                                                <span class="growth-<?php echo $crecimiento_ventas >= 0 ? 'positive' : 'negative'; ?>">
                                                    <?php echo number_format($crecimiento_ventas, 1); ?>% vs mes anterior
                                                </span>
                                            </small>
                                        </p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-shopping-cart fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-6">
                        <div class="stats-card text-white" style="background: linear-gradient(45deg, #3498db, #2980b9);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">CLIENTES NUEVOS</h6>
                                        <h2 class="card-text"><?php echo number_format($stats['clientes_nuevos_hoy']); ?></h2>
                                        <p class="card-text mb-0">
                                            <small>
                                                <i class="fas fa-arrow-up me-1 growth-<?php echo $crecimiento_clientes >= 0 ? 'positive' : 'negative'; ?>"></i>
                                                <span class="growth-<?php echo $crecimiento_clientes >= 0 ? 'positive' : 'negative'; ?>">
                                                    <?php echo number_format($crecimiento_clientes, 1); ?>% vs mes anterior
                                                </span>
                                            </small>
                                        </p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-user-plus fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-6">
                        <div class="stats-card text-white" style="background: linear-gradient(45deg, #e74c3c, #c0392b);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">BAJO STOCK</h6>
                                        <h2 class="card-text"><?php echo number_format($stats['productos_bajo_stock']); ?></h2>
                                        <p class="card-text mb-0">
                                            <small>Productos con menos de 5 unidades</small>
                                        </p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-6">
                        <div class="stats-card text-white" style="background: linear-gradient(45deg, #f39c12, #e67e22);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">UTILIDAD MES</h6>
                                        <h2 class="card-text"><?php echo formatMoney($stats['utilidad_mes']); ?></h2>
                                        <p class="card-text mb-0">
                                            <small>Margen estimado del mes actual</small>
                                        </p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-line fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficas y widgets -->
                <div class="row">
                    <!-- Ventas por hora -->
                    <div class="col-lg-8">
                        <div class="dashboard-widget">
                            <div class="widget-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Ventas por Hora - Hoy
                                </h5>
                            </div>
                            <div class="chart-container">
                                <canvas id="ventasHoraChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Métricas rápidas -->
                    <div class="col-lg-4">
                        <div class="dashboard-widget">
                            <div class="widget-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-bolt me-2"></i>
                                    Métricas Rápidas
                                </h5>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <div class="text-center">
                                        <h3 class="text-primary"><?php echo formatMoney($stats['ventas_semana']); ?></h3>
                                        <small class="text-muted">Ventas Semana</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="text-center">
                                        <h3 class="text-success"><?php echo formatMoney($stats['ventas_mes']); ?></h3>
                                        <small class="text-muted">Ventas Mes</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="text-center">
                                        <h3 class="text-warning"><?php echo number_format($stats['facturas_pendientes']); ?></h3>
                                        <small class="text-muted">Facturas Pendientes</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="text-center">
                                        <h3 class="text-info"><?php echo number_format($stats['puntos_generados_hoy']); ?></h3>
                                        <small class="text-muted">Puntos Hoy</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Acciones rápidas -->
                            <div class="quick-actions">
                                <h6 class="mb-3">Acciones Rápidas</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <a href="../mod3/ventas.php" class="btn btn-primary action-button">
                                            <i class="fas fa-cash-register me-2"></i> Nueva Venta
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="../mod2/inventario.php" class="btn btn-warning action-button">
                                            <i class="fas fa-boxes me-2"></i> Ver Inventario
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="../mod3/historial_ventas.php" class="btn btn-success action-button">
                                            <i class="fas fa-history me-2"></i> Historial Ventas
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="../mod4/reportes.php" class="btn btn-info action-button">
                                            <i class="fas fa-chart-bar me-2"></i> Generar Reporte
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Productos y vendedores top -->
                <div class="row">
                    <!-- Productos más vendidos -->
                    <div class="col-lg-6">
                        <div class="dashboard-widget">
                            <div class="widget-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-star me-2"></i>
                                    Top 5 Productos del Mes
                                </h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Vendidos</th>
                                            <th>Total Ventas</th>
                                            <th>Promedio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_productos as $producto): 
                                            $promedio = $producto['vendidos'] > 0 ? 
                                                       $producto['total_ventas'] / $producto['vendidos'] : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo substr($producto['producto'], 0, 30); ?>...</td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo number_format($producto['vendidos']); ?></span>
                                            </td>
                                            <td><?php echo formatMoney($producto['total_ventas']); ?></td>
                                            <td><?php echo formatMoney($promedio); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mejores vendedores -->
                    <div class="col-lg-6">
                        <div class="dashboard-widget">
                            <div class="widget-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-trophy me-2"></i>
                                    Top 5 Vendedores del Mes
                                </h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Vendedor</th>
                                            <th>Ventas</th>
                                            <th>Total</th>
                                            <th>Promedio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_vendedores as $vendedor): 
                                            $promedio = $vendedor['ventas'] > 0 ? 
                                                       $vendedor['total_ventas'] / $vendedor['ventas'] : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo $vendedor['vendedor']; ?></td>
                                            <td>
                                                <span class="badge bg-success"><?php echo number_format($vendedor['ventas']); ?></span>
                                            </td>
                                            <td><?php echo formatMoney($vendedor['total_ventas']); ?></td>
                                            <td><?php echo formatMoney($promedio); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Últimas ventas -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Últimas Ventas
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Factura</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Tipo</th>
                                    <th>Total</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimas_ventas as $venta): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $venta['numero_factura']; ?></strong>
                                    </td>
                                    <td><?php echo $venta['nombre_cliente']; ?></td>
                                    <td><?php echo $venta['vendedor_nombre'] ?: 'Online'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $venta['tipo_venta'] == 'online' ? 'info' : ($venta['tipo_venta'] == 'recoger' ? 'warning text-dark' : 'secondary'); ?>">
                                            <?php echo ucfirst($venta['tipo_venta']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatMoney($venta['total']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                    <td>
                                        <a href="../mod3/comprobante.php?id=<?php echo $venta['numero_factura']; ?>" 
                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Alertas y notificaciones -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="dashboard-widget">
                            <div class="widget-header">
                                <h5 class="mb-0 text-warning">
                                    <i class="fas fa-bell me-2"></i>
                                    Alertas del Sistema
                                </h5>
                            </div>
                            <div class="list-group">
                                <?php if ($stats['productos_bajo_stock'] > 0): ?>
                                <a href="../mod2/inventario.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 text-danger">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Productos Bajos en Stock
                                        </h6>
                                        <small>Ahora</small>
                                    </div>
                                    <p class="mb-1"><?php echo $stats['productos_bajo_stock']; ?> productos tienen menos de 5 unidades.</p>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($stats['facturas_pendientes'] > 0): ?>
                                <a href="../mod3/historial_ventas.php?estado=1" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 text-warning">
                                            <i class="fas fa-clock me-2"></i>
                                            Facturas Pendientes
                                        </h6>
                                        <small>Ahora</small>
                                    </div>
                                    <p class="mb-1"><?php echo $stats['facturas_pendientes']; ?> facturas online pendientes de confirmación.</p>
                                </a>
                                <?php endif; ?>
                                
                                <?php 
                                // Verificar inventarios próximos a vencer (simulado)
                                $inventario_vencer_query = "SELECT COUNT(*) as cantidad FROM productos_variantes 
                                                           WHERE (stock_tienda + stock_bodega) > 0 
                                                           AND activo = 1 LIMIT 1";
                                $inventario_vencer_stmt = $db->prepare($inventario_vencer_query);
                                $inventario_vencer_stmt->execute();
                                $inventario_vencer = $inventario_vencer_stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($inventario_vencer['cantidad'] > 50): ?>
                                <a href="../mod2/productos.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 text-info">
                                            <i class="fas fa-box-open me-2"></i>
                                            Revisión de Inventario
                                        </h6>
                                        <small>Pendiente</small>
                                    </div>
                                    <p class="mb-1">Es recomendable realizar revisión física del inventario.</p>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Objetivos del mes -->
                    <div class="col-lg-6">
                        <div class="dashboard-widget">
                            <div class="widget-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-bullseye me-2"></i>
                                    Objetivos del Mes
                                </h5>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Meta de Ventas</label>
                                <?php 
                                $meta_ventas = 100000; // Q100,000
                                $progreso_ventas = ($stats['ventas_mes'] / $meta_ventas) * 100;
                                ?>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo min($progreso_ventas, 100); ?>%;"
                                         aria-valuenow="<?php echo $progreso_ventas; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo number_format($progreso_ventas, 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo formatMoney($stats['ventas_mes']); ?> / <?php echo formatMoney($meta_ventas); ?>
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Meta de Clientes Nuevos</label>
                                <?php 
                                $meta_clientes = 50;
                                $progreso_clientes = ($crecimiento['clientes_actual'] / $meta_clientes) * 100;
                                ?>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-info" role="progressbar" 
                                         style="width: <?php echo min($progreso_clientes, 100); ?>%;"
                                         aria-valuenow="<?php echo $progreso_clientes; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo number_format($progreso_clientes, 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $crecimiento['clientes_actual']; ?> / <?php echo $meta_clientes; ?> clientes nuevos
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Meta de Puntos Generados</label>
                                <?php 
                                $meta_puntos = 5000;
                                $puntos_mes_query = "SELECT COALESCE(SUM(puntos_ganados), 0) as puntos FROM factura_cabecera 
                                                   WHERE id_estado = 2 AND MONTH(fecha) = MONTH(CURDATE())";
                                $puntos_mes_stmt = $db->prepare($puntos_mes_query);
                                $puntos_mes_stmt->execute();
                                $puntos_mes = $puntos_mes_stmt->fetch(PDO::FETCH_ASSOC);
                                $progreso_puntos = ($puntos_mes['puntos'] / $meta_puntos) * 100;
                                ?>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo min($progreso_puntos, 100); ?>%;"
                                         aria-valuenow="<?php echo $progreso_puntos; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo number_format($progreso_puntos, 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo number_format($puntos_mes['puntos']); ?> / <?php echo number_format($meta_puntos); ?> puntos
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Gráfica de ventas por hora
        const ventasHoraCtx = document.getElementById('ventasHoraChart').getContext('2d');
        
        // Preparar datos para la gráfica
        const horas = <?php echo json_encode(array_column($ventas_hora, 'hora')); ?>;
        const ventasPorHora = <?php echo json_encode(array_column($ventas_hora, 'total')); ?>;
        
        // Crear array completo de 24 horas
        const todasHoras = Array.from({length: 24}, (_, i) => i);
        const ventasCompletas = todasHoras.map(hora => {
            const index = horas.indexOf(hora.toString());
            return index !== -1 ? parseFloat(ventasPorHora[index]) : 0;
        });
        
        const ventasHoraChart = new Chart(ventasHoraCtx, {
            type: 'bar',
            data: {
                labels: todasHoras.map(h => h + ':00'),
                datasets: [{
                    label: 'Ventas por Hora (Q)',
                    data: ventasCompletas,
                    backgroundColor: 'rgba(26, 188, 156, 0.7)',
                    borderColor: '#1abc9c',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribución de Ventas por Hora - <?php echo date("d/m/Y"); ?>'
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Monto en Quetzales (Q)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Hora del Día'
                        }
                    }
                }
            }
        });
        
        // Actualizar dashboard automáticamente
        function actualizarDashboard() {
            fetch('api/dashboard_data.php')
                .then(response => response.json())
                .then(data => {
                    // Actualizar métricas
                    document.querySelectorAll('.metric-value').forEach((element, index) => {
                        if (data.metrics[index]) {
                            element.textContent = data.metrics[index];
                        }
                    });
                    
                    // Actualizar hora de última actualización
                    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
                    
                    // Mostrar notificación
                    showNotification('Dashboard actualizado correctamente', 'success');
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error al actualizar el dashboard', 'error');
                });
        }
        
        function showNotification(message, type) {
            // Crear notificación
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remover después de 3 segundos
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // Actualizar automáticamente cada 60 segundos
        setInterval(actualizarDashboard, 60000);
        
        // Actualizar hora cada segundo
        setInterval(() => {
            document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
        }, 1000);
        
        // Hacer que las tarjetas sean clickeables
        document.querySelectorAll('.stats-card').forEach(card => {
            card.style.cursor = 'pointer';
            card.addEventListener('click', function() {
                const title = this.querySelector('.card-title').textContent.trim();
                
                switch(title) {
                    case 'VENTAS HOY':
                        window.location.href = '../mod3/historial_ventas.php?fecha_desde=<?php echo date("Y-m-d"); ?>&fecha_fin=<?php echo date("Y-m-d"); ?>';
                        break;
                    case 'CLIENTES NUEVOS':
                        window.location.href = '../mod1/personas.php';
                        break;
                    case 'BAJO STOCK':
                        window.location.href = '../mod2/inventario.php';
                        break;
                    case 'UTILIDAD MES':
                        window.location.href = '../mod4/contabilidad.php';
                        break;
                }
            });
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>