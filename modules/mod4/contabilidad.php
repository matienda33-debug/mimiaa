<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Verificar autenticación y permiso
if (!$auth->isLoggedIn()) {
    header('Location: ../../index.php');
    exit();
}
$auth->requirePermission('contabilidad');

$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';

// Fechas por defecto (mes actual)
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');

// Obtener estadísticas financieras
$stats_query = "SELECT 
                -- Ventas
                COALESCE(SUM(CASE WHEN fc.id_estado = 2 THEN fc.total ELSE 0 END), 0) as ventas_totales,
                COALESCE(COUNT(CASE WHEN fc.id_estado = 2 THEN 1 END), 0) as ventas_cantidad,
                COALESCE(AVG(CASE WHEN fc.id_estado = 2 THEN fc.total END), 0) as ventas_promedio,
                
                -- Costos (estimados basados en precio de compra)
                COALESCE(SUM(
                    CASE WHEN fc.id_estado = 2 THEN (
                        SELECT SUM(fd.cantidad * COALESCE(pr.precio_compra, pr.precio_venta * 0.6))
                        FROM factura_detalle fd
                        INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                        INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                        WHERE fd.id_factura = fc.id_factura
                    ) ELSE 0 END
                ), 0) as costos_totales,
                
                -- Utilidad
                COALESCE(SUM(CASE WHEN fc.id_estado = 2 THEN fc.total ELSE 0 END), 0) -
                COALESCE(SUM(
                    CASE WHEN fc.id_estado = 2 THEN (
                        SELECT SUM(fd.cantidad * COALESCE(pr.precio_compra, pr.precio_venta * 0.6))
                        FROM factura_detalle fd
                        INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                        INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                        WHERE fd.id_factura = fc.id_factura
                    ) ELSE 0 END
                ), 0) as utilidad_bruta,
                
                -- Descuentos por puntos
                COALESCE(SUM(CASE WHEN fc.id_estado = 2 THEN fc.descuento ELSE 0 END), 0) as descuentos_puntos,
                
                -- Puntos generados
                COALESCE(SUM(CASE WHEN fc.id_estado = 2 THEN fc.puntos_ganados ELSE 0 END), 0) as puntos_generados,
                
                -- Puntos canjeados
                COALESCE(SUM(CASE WHEN fc.id_estado = 2 THEN fc.puntos_usados ELSE 0 END), 0) as puntos_canjeados
                
                FROM factura_cabecera fc
                WHERE fc.fecha BETWEEN :fecha_inicio AND DATE_ADD(:fecha_fin, INTERVAL 1 DAY)";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':fecha_inicio', $fecha_inicio);
$stats_stmt->bindParam(':fecha_fin', $fecha_fin);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calcular porcentajes
$margen_utilidad = $stats['ventas_totales'] > 0 ? 
                  ($stats['utilidad_bruta'] / $stats['ventas_totales']) * 100 : 0;

// Ventas por día para gráfica
$ventas_dia_query = "SELECT 
                     DATE(fc.fecha) as fecha_dia,
                     COUNT(*) as cantidad_ventas,
                     SUM(fc.total) as total_ventas,
                     SUM(fc.descuento) as total_descuentos
                     FROM factura_cabecera fc
                     WHERE fc.id_estado = 2 
                     AND fc.fecha BETWEEN :fecha_inicio AND DATE_ADD(:fecha_fin, INTERVAL 1 DAY)
                     GROUP BY DATE(fc.fecha)
                     ORDER BY fecha_dia";

$ventas_dia_stmt = $db->prepare($ventas_dia_query);
$ventas_dia_stmt->bindParam(':fecha_inicio', $fecha_inicio);
$ventas_dia_stmt->bindParam(':fecha_fin', $fecha_fin);
$ventas_dia_stmt->execute();
$ventas_dia = $ventas_dia_stmt->fetchAll(PDO::FETCH_ASSOC);

// Ventas por departamento
$ventas_depto_query = "SELECT 
                       d.nombre as departamento,
                       COUNT(DISTINCT fc.id_factura) as cantidad_ventas,
                       SUM(fd.cantidad) as total_unidades,
                       SUM(fd.subtotal) as total_ventas,
                       SUM(fd.cantidad * COALESCE(pr.precio_compra, pr.precio_venta * 0.6)) as costo_total
                       FROM factura_cabecera fc
                       INNER JOIN factura_detalle fd ON fc.id_factura = fd.id_factura
                       INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                       INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                       INNER JOIN departamentos d ON pr.id_departamento = d.id_departamento
                       WHERE fc.id_estado = 2 
                       AND fc.fecha BETWEEN :fecha_inicio AND DATE_ADD(:fecha_fin, INTERVAL 1 DAY)
                       GROUP BY d.id_departamento
                       ORDER BY total_ventas DESC";

$ventas_depto_stmt = $db->prepare($ventas_depto_query);
$ventas_depto_stmt->bindParam(':fecha_inicio', $fecha_inicio);
$ventas_depto_stmt->bindParam(':fecha_fin', $fecha_fin);
$ventas_depto_stmt->execute();
$ventas_depto = $ventas_depto_stmt->fetchAll(PDO::FETCH_ASSOC);

// Productos más vendidos
$productos_top_query = "SELECT 
                        pr.codigo,
                        pr.nombre as producto,
                        pv.color,
                        pv.talla,
                        d.nombre as departamento,
                        SUM(fd.cantidad) as total_vendido,
                        SUM(fd.subtotal) as total_ventas,
                        COUNT(DISTINCT fc.id_factura) as veces_vendido
                        FROM factura_detalle fd
                        INNER JOIN factura_cabecera fc ON fd.id_factura = fc.id_factura
                        INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                        INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                        INNER JOIN departamentos d ON pr.id_departamento = d.id_departamento
                        WHERE fc.id_estado = 2 
                        AND fc.fecha BETWEEN :fecha_inicio AND DATE_ADD(:fecha_fin, INTERVAL 1 DAY)
                        GROUP BY pv.id_variante
                        ORDER BY total_vendido DESC
                        LIMIT 10";

$productos_top_stmt = $db->prepare($productos_top_query);
$productos_top_stmt->bindParam(':fecha_inicio', $fecha_inicio);
$productos_top_stmt->bindParam(':fecha_fin', $fecha_fin);
$productos_top_stmt->execute();
$productos_top = $productos_top_stmt->fetchAll(PDO::FETCH_ASSOC);

// Clientes top
$clientes_top_query = "SELECT 
                       c.id_cliente,
                       c.nombre,
                       c.apellido,
                       c.dpi,
                       COUNT(fc.id_factura) as total_compras,
                       SUM(fc.total) as total_gastado,
                       MAX(fc.fecha) as ultima_compra,
                       c.puntos
                       FROM clientes c
                       LEFT JOIN factura_cabecera fc ON c.id_cliente = fc.id_cliente
                       WHERE fc.id_estado = 2 
                       AND fc.fecha BETWEEN :fecha_inicio AND DATE_ADD(:fecha_fin, INTERVAL 1 DAY)
                       GROUP BY c.id_cliente
                       HAVING total_compras > 0
                       ORDER BY total_gastado DESC
                       LIMIT 10";

$clientes_top_stmt = $db->prepare($clientes_top_query);
$clientes_top_stmt->bindParam(':fecha_inicio', $fecha_inicio);
$clientes_top_stmt->bindParam(':fecha_fin', $fecha_fin);
$clientes_top_stmt->execute();
$clientes_top = $clientes_top_stmt->fetchAll(PDO::FETCH_ASSOC);

// Estado actual del inventario (valorizado)
$inventario_query = "SELECT 
                     SUM((pv.stock_tienda + pv.stock_bodega) * COALESCE(pr.precio_compra, pr.precio_venta * 0.6)) as valor_costo,
                     SUM((pv.stock_tienda + pv.stock_bodega) * pr.precio_venta) as valor_venta,
                     COUNT(DISTINCT pr.id_raiz) as productos_activos,
                     COUNT(DISTINCT pv.id_variante) as variantes_activas
                     FROM productos_variantes pv
                     INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                     WHERE pv.activo = 1 AND pr.activo = 1";

$inventario_stmt = $db->prepare($inventario_query);
$inventario_stmt->execute();
$inventario = $inventario_stmt->fetch(PDO::FETCH_ASSOC);

// Movimientos de inventario (últimos 30 días)
$movimientos_query = "SELECT 
                      DATE(im.fecha_movimiento) as fecha,
                      im.tipo_movimiento,
                      im.ubicacion,
                      COUNT(*) as cantidad_movimientos,
                      SUM(im.cantidad) as total_unidades
                      FROM inventario_movimientos im
                      WHERE im.fecha_movimiento >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      GROUP BY DATE(im.fecha_movimiento), im.tipo_movimiento, im.ubicacion
                      ORDER BY fecha DESC, im.tipo_movimiento";

$movimientos_stmt = $db->prepare($movimientos_query);
$movimientos_stmt->execute();
$movimientos = $movimientos_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contabilidad - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-card {
            border-radius: 10px;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        .profit-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        .loss-badge {
            background: linear-gradient(45deg, #dc3545, #e83e8c);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(26, 188, 156, 0.1);
        }
        .badge-ajitos {
            background-color: #ff6b6b;
            color: white;
        }
        .tab-content {
            padding: 20px 0;
        }
        .nav-tabs .nav-link.active {
            background: #1abc9c;
            color: white;
            border-color: #1abc9c;
        }
    </style>
</head>
<body>
    <?php if (!$embedded): ?>
    <?php include '../../includes/header.php'; ?>
    <?php endif; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <main class="col-12 px-2">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chart-line me-2"></i>
                        Contabilidad y Finanzas
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportarReporte('excel')">
                                <i class="fas fa-file-excel me-1"></i> Excel
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="exportarReporte('pdf')">
                                <i class="fas fa-file-pdf me-1"></i> PDF
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="imprimirReporte()">
                                <i class="fas fa-print me-1"></i> Imprimir
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filter-card">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" id="fecha_inicio" 
                                   name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="fecha_fin" class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" id="fecha_fin" 
                                   name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Periodo Rápido</label>
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="setPeriodo('hoy')">Hoy</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="setPeriodo('ayer')">Ayer</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="setPeriodo('semana')">Esta Semana</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="setPeriodo('mes')">Este Mes</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="setPeriodo('anio')">Este Año</button>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-1"></i> Aplicar Filtro
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Estadísticas principales -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card text-white bg-primary">
                            <div class="card-body">
                                <h6 class="card-title">Ventas Totales</h6>
                                <h2 class="card-text"><?php echo formatMoney($stats['ventas_totales']); ?></h2>
                                <p class="card-text mb-0">
                                    <small><?php echo number_format($stats['ventas_cantidad']); ?> ventas</small><br>
                                    <small>Promedio: <?php echo formatMoney($stats['ventas_promedio']); ?></small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card text-white bg-warning">
                            <div class="card-body">
                                <h6 class="card-title">Costos Totales</h6>
                                <h2 class="card-text"><?php echo formatMoney($stats['costos_totales']); ?></h2>
                                <p class="card-text mb-0">
                                    <small>Margen estimado: <?php echo number_format($margen_utilidad, 2); ?>%</small><br>
                                    <small>Basado en precio de compra</small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card text-white <?php echo $stats['utilidad_bruta'] >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                            <div class="card-body">
                                <h6 class="card-title">Utilidad Bruta</h6>
                                <h2 class="card-text"><?php echo formatMoney($stats['utilidad_bruta']); ?></h2>
                                <p class="card-text mb-0">
                                    <small>Descuentos: <?php echo formatMoney($stats['descuentos_puntos']); ?></small><br>
                                    <small>Valor en puntos</small>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card text-white bg-info">
                            <div class="card-body">
                                <h6 class="card-title">Puntos del Sistema</h6>
                                <h2 class="card-text"><?php echo number_format($stats['puntos_generados']); ?></h2>
                                <p class="card-text mb-0">
                                    <small>Generados: <?php echo number_format($stats['puntos_generados']); ?></small><br>
                                    <small>Canjeados: <?php echo number_format($stats['puntos_canjeados']); ?></small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficas principales -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Ventas por Día</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="ventasDiaChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Ventas por Departamento</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="ventasDeptoChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pestañas de información detallada -->
                <ul class="nav nav-tabs" id="contabilidadTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="inventario-tab" data-bs-toggle="tab" 
                                data-bs-target="#inventario" type="button">
                            <i class="fas fa-boxes me-2"></i> Valor del Inventario
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="productos-tab" data-bs-toggle="tab" 
                                data-bs-target="#productos" type="button">
                            <i class="fas fa-tshirt me-2"></i> Productos Más Vendidos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="clientes-tab" data-bs-toggle="tab" 
                                data-bs-target="#clientes" type="button">
                            <i class="fas fa-users me-2"></i> Mejores Clientes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="movimientos-tab" data-bs-toggle="tab" 
                                data-bs-target="#movimientos" type="button">
                            <i class="fas fa-exchange-alt me-2"></i> Movimientos de Inventario
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="contabilidadTabsContent">
                    <!-- Tab: Valor del Inventario -->
                    <div class="tab-pane fade show active" id="inventario" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary"><?php echo formatMoney($inventario['valor_costo']); ?></h3>
                                        <p class="text-muted">Valor al Costo</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-success"><?php echo formatMoney($inventario['valor_venta']); ?></h3>
                                        <p class="text-muted">Valor de Venta</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3><?php echo number_format($inventario['productos_activos']); ?></h3>
                                        <p class="text-muted">Productos Activos</p>
                                        <small><?php echo number_format($inventario['variantes_activas']); ?> variantes</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Distribución por departamento -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Distribución del Inventario por Departamento</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Departamento</th>
                                                <th>Productos</th>
                                                <th>Variantes</th>
                                                <th>Stock Total</th>
                                                <th>Valor al Costo</th>
                                                <th>Valor de Venta</th>
                                                <th>Margen Potencial</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $depto_inventario_query = "SELECT 
                                                d.nombre as departamento,
                                                COUNT(DISTINCT pr.id_raiz) as productos,
                                                COUNT(DISTINCT pv.id_variante) as variantes,
                                                SUM(pv.stock_tienda + pv.stock_bodega) as stock_total,
                                                SUM((pv.stock_tienda + pv.stock_bodega) * COALESCE(pr.precio_compra, pr.precio_venta * 0.6)) as valor_costo,
                                                SUM((pv.stock_tienda + pv.stock_bodega) * pr.precio_venta) as valor_venta
                                                FROM departamentos d
                                                INNER JOIN productos_raiz pr ON d.id_departamento = pr.id_departamento
                                                INNER JOIN productos_variantes pv ON pr.id_raiz = pv.id_producto_raiz
                                                WHERE d.activo = 1 AND pr.activo = 1 AND pv.activo = 1
                                                GROUP BY d.id_departamento
                                                ORDER BY valor_venta DESC";
                                            
                                            $depto_inventario_stmt = $db->prepare($depto_inventario_query);
                                            $depto_inventario_stmt->execute();
                                            $depto_inventario = $depto_inventario_stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            foreach ($depto_inventario as $depto):
                                                $margen_potencial = $depto['valor_costo'] > 0 ? 
                                                    (($depto['valor_venta'] - $depto['valor_costo']) / $depto['valor_costo']) * 100 : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo $depto['departamento']; ?></td>
                                                <td><?php echo number_format($depto['productos']); ?></td>
                                                <td><?php echo number_format($depto['variantes']); ?></td>
                                                <td><?php echo number_format($depto['stock_total']); ?></td>
                                                <td><?php echo formatMoney($depto['valor_costo']); ?></td>
                                                <td><?php echo formatMoney($depto['valor_venta']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $margen_potencial >= 50 ? 'bg-success' : ($margen_potencial >= 30 ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                                        <?php echo number_format($margen_potencial, 1); ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Productos más vendidos -->
                    <div class="tab-pane fade" id="productos" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Top 10 Productos Más Vendidos</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Código</th>
                                                <th>Producto</th>
                                                <th>Color/Talla</th>
                                                <th>Departamento</th>
                                                <th>Vendidos</th>
                                                <th>Total Ventas</th>
                                                <th>Veces Vendido</th>
                                                <th>Promedio por Venta</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $contador = 1; ?>
                                            <?php foreach ($productos_top as $producto): 
                                                $promedio_venta = $producto['veces_vendido'] > 0 ? 
                                                    $producto['total_ventas'] / $producto['veces_vendido'] : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo $contador++; ?></td>
                                                <td><code><?php echo $producto['codigo']; ?></code></td>
                                                <td>
                                                    <?php echo $producto['producto']; ?>
                                                    <?php if (strpos(strtolower($producto['departamento']), 'bebé') !== false): ?>
                                                        <span class="badge badge-ajitos">Ajitos</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo $producto['color']; ?><br>
                                                    <strong><?php echo $producto['talla']; ?></strong>
                                                </td>
                                                <td><?php echo $producto['departamento']; ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo number_format($producto['total_vendido']); ?></span>
                                                </td>
                                                <td><?php echo formatMoney($producto['total_ventas']); ?></td>
                                                <td><?php echo number_format($producto['veces_vendido']); ?></td>
                                                <td><?php echo formatMoney($promedio_venta); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Mejores clientes -->
                    <div class="tab-pane fade" id="clientes" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Top 10 Clientes por Compras</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Cliente</th>
                                                <th>DPI</th>
                                                <th>Compras</th>
                                                <th>Total Gastado</th>
                                                <th>Promedio por Compra</th>
                                                <th>Última Compra</th>
                                                <th>Puntos Acumulados</th>
                                                <th>Valor en Q</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $contador = 1; ?>
                                            <?php foreach ($clientes_top as $cliente): 
                                                $promedio_compra = $cliente['total_compras'] > 0 ? 
                                                    $cliente['total_gastado'] / $cliente['total_compras'] : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo $contador++; ?></td>
                                                <td>
                                                    <?php echo $cliente['nombre'] . ' ' . $cliente['apellido']; ?><br>
                                                    <small class="text-muted">ID: <?php echo $cliente['id_cliente']; ?></small>
                                                </td>
                                                <td><?php echo $cliente['dpi']; ?></td>
                                                <td><?php echo number_format($cliente['total_compras']); ?></td>
                                                <td><?php echo formatMoney($cliente['total_gastado']); ?></td>
                                                <td><?php echo formatMoney($promedio_compra); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($cliente['ultima_compra'])); ?></td>
                                                <td>
                                                    <span class="badge bg-warning text-dark">
                                                        <?php echo number_format($cliente['puntos']); ?> pts
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?php echo formatMoney(valorEnPuntos($cliente['puntos'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Resumen de clientes -->
                                <div class="row mt-4">
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h3><?php echo count($clientes_top); ?></h3>
                                                <p class="text-muted">Clientes Top</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h3 class="text-primary"><?php echo formatMoney(array_sum(array_column($clientes_top, 'total_gastado'))); ?></h3>
                                                <p class="text-muted">Total de Clientes Top</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h3 class="text-success"><?php echo formatMoney(valorEnPuntos(array_sum(array_column($clientes_top, 'puntos')))); ?></h3>
                                                <p class="text-muted">Puntos por Canjear</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Movimientos de inventario -->
                    <div class="tab-pane fade" id="movimientos" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Movimientos de Inventario (Últimos 30 días)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Tipo Movimiento</th>
                                                <th>Ubicación</th>
                                                <th>Cantidad Movimientos</th>
                                                <th>Total Unidades</th>
                                                <th>Promedio por Movimiento</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($movimientos as $mov): 
                                                $promedio = $mov['cantidad_movimientos'] > 0 ? 
                                                    $mov['total_unidades'] / $mov['cantidad_movimientos'] : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($mov['fecha'])); ?></td>
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
                                                    <span class="badge bg-<?php echo $mov['ubicacion'] == 'tienda' ? 'info' : 'warning text-dark'; ?>">
                                                        <?php echo ucfirst($mov['ubicacion']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($mov['cantidad_movimientos']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo number_format($mov['total_unidades']); ?></span>
                                                </td>
                                                <td><?php echo number_format($promedio, 1); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Gráfica de movimientos -->
                                <div class="mt-4">
                                    <div class="chart-container" style="height: 250px;">
                                        <canvas id="movimientosChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Inicializar datepickers
        flatpickr("#fecha_inicio", {
            dateFormat: "Y-m-d",
            locale: "es"
        });
        flatpickr("#fecha_fin", {
            dateFormat: "Y-m-d",
            locale: "es"
        });
        
        // Función para establecer periodos rápidos
        function setPeriodo(periodo) {
            const hoy = new Date();
            let inicio, fin;
            
            switch(periodo) {
                case 'hoy':
                    inicio = fin = hoy.toISOString().split('T')[0];
                    break;
                case 'ayer':
                    const ayer = new Date(hoy);
                    ayer.setDate(ayer.getDate() - 1);
                    inicio = fin = ayer.toISOString().split('T')[0];
                    break;
                case 'semana':
                    const inicioSemana = new Date(hoy);
                    inicioSemana.setDate(hoy.getDate() - hoy.getDay());
                    const finSemana = new Date(hoy);
                    finSemana.setDate(hoy.getDate() + (6 - hoy.getDay()));
                    inicio = inicioSemana.toISOString().split('T')[0];
                    fin = finSemana.toISOString().split('T')[0];
                    break;
                case 'mes':
                    inicio = hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0') + '-01';
                    fin = hoy.toISOString().split('T')[0];
                    break;
                case 'anio':
                    inicio = hoy.getFullYear() + '-01-01';
                    fin = hoy.toISOString().split('T')[0];
                    break;
            }
            
            document.getElementById('fecha_inicio').value = inicio;
            document.getElementById('fecha_fin').value = fin;
        }
        
        // Gráfica de ventas por día
        const ventasDiaCtx = document.getElementById('ventasDiaChart').getContext('2d');
        const ventasDiaData = {
            labels: <?php echo json_encode(array_column($ventas_dia, 'fecha_dia')); ?>,
            datasets: [
                {
                    label: 'Ventas Totales',
                    data: <?php echo json_encode(array_column($ventas_dia, 'total_ventas')); ?>,
                    borderColor: '#1abc9c',
                    backgroundColor: 'rgba(26, 188, 156, 0.1)',
                    fill: true,
                    yAxisID: 'y'
                },
                {
                    label: 'Cantidad de Ventas',
                    data: <?php echo json_encode(array_column($ventas_dia, 'cantidad_ventas')); ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    fill: true,
                    type: 'bar',
                    yAxisID: 'y1'
                }
            ]
        };
        
        new Chart(ventasDiaCtx, {
            type: 'line',
            data: ventasDiaData,
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Ventas (Q)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Cantidad'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        
        // Gráfica de ventas por departamento
        const ventasDeptoCtx = document.getElementById('ventasDeptoChart').getContext('2d');
        const ventasDeptoData = {
            labels: <?php echo json_encode(array_column($ventas_depto, 'departamento')); ?>,
            datasets: [
                {
                    label: 'Ventas Totales',
                    data: <?php echo json_encode(array_column($ventas_depto, 'total_ventas')); ?>,
                    backgroundColor: [
                        '#1abc9c', '#3498db', '#9b59b6', '#e74c3c', '#f39c12',
                        '#2ecc71', '#34495e', '#7f8c8d', '#d35400', '#c0392b'
                    ]
                }
            ]
        };
        
        new Chart(ventasDeptoCtx, {
            type: 'doughnut',
            data: ventasDeptoData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${formatMoney(value)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Gráfica de movimientos de inventario
        const movimientosCtx = document.getElementById('movimientosChart').getContext('2d');
        const movimientosData = {
            labels: <?php echo json_encode(array_column($movimientos, 'fecha')); ?>,
            datasets: [
                {
                    label: 'Entradas',
                    data: <?php echo json_encode(array_map(function($m) {
                        return $m['tipo_movimiento'] == 'entrada' ? $m['total_unidades'] : 0;
                    }, $movimientos)); ?>,
                    borderColor: '#2ecc71',
                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                    fill: true
                },
                {
                    label: 'Salidas',
                    data: <?php echo json_encode(array_map(function($m) {
                        return $m['tipo_movimiento'] == 'salida' ? $m['total_unidades'] : 0;
                    }, $movimientos)); ?>,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    fill: true
                },
                {
                    label: 'Ajustes',
                    data: <?php echo json_encode(array_map(function($m) {
                        return $m['tipo_movimiento'] == 'ajuste' ? $m['total_unidades'] : 0;
                    }, $movimientos)); ?>,
                    borderColor: '#f39c12',
                    backgroundColor: 'rgba(243, 156, 18, 0.1)',
                    fill: true
                }
            ]
        };
        
        new Chart(movimientosCtx, {
            type: 'line',
            data: movimientosData,
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Movimientos de Inventario por Día'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Cantidad de Unidades'
                        }
                    }
                }
            }
        });
        
        // Función para formatear dinero
        function formatMoney(amount) {
            return 'Q' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        
        // Funciones de exportación
        function exportarReporte(formato) {
            const params = new URLSearchParams(window.location.search);
            params.set('exportar', formato);
            window.location.href = 'api/exportar_contabilidad.php?' + params.toString();
        }
        
        function imprimirReporte() {
            window.print();
        }
    </script>
</body>
</html>