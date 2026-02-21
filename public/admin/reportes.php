<?php
// public/admin/reportes.php
require_once '../../app/config/database.php';
require_once '../../app/models/Auth.php';
require_once '../../app/models/Reporte.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$reporteModel = new Reporte($db);

// Verificar acceso (solo admin)
$auth->requireAccess(100, '../../login.php');

$user_info = $auth->getUserInfo();

// Fechas por defecto (este mes)
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$tipo_reporte = $_GET['tipo_reporte'] ?? 'ventas';

// Obtener datos según tipo de reporte
$datos_reporte = [];
$titulo_reporte = '';

switch ($tipo_reporte) {
    case 'ventas':
        $titulo_reporte = 'Reporte de Ventas';
        $datos_reporte = $reporteModel->obtenerVentasPorPeriodo($fecha_inicio, $fecha_fin, 'dia');
        break;
        
    case 'productos':
        $titulo_reporte = 'Ventas por Producto';
        $datos_reporte = $reporteModel->obtenerVentasPorProducto($fecha_inicio, $fecha_fin);
        break;
        
    case 'vendedores':
        $titulo_reporte = 'Ventas por Vendedor';
        $datos_reporte = $reporteModel->obtenerVentasPorVendedor($fecha_inicio, $fecha_fin);
        break;
        
    case 'inventario':
        $titulo_reporte = 'Estado de Inventario';
        $datos_reporte = $reporteModel->obtenerReporteInventario();
        break;
        
    case 'contabilidad':
        $titulo_reporte = 'Estado Contable';
        $datos_reporte = $reporteModel->obtenerEstadoContable($fecha_inicio, $fecha_fin);
        break;
        
    case 'clientes':
        $titulo_reporte = 'Reporte de Clientes';
        $datos_reporte = $reporteModel->obtenerReporteClientes();
        break;
}

// Obtener métricas para dashboard
$metricas = $reporteModel->obtenerMetricasDashboard($fecha_inicio, $fecha_fin);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Tienda MI&MI Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            height: 100%;
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .metric-card:hover {
            transform: translateY(-5px);
        }
        .metric-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .metric-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .chart-container {
            height: 300px;
            position: relative;
        }
        .table-report {
            font-size: 14px;
        }
        .table-report th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .nav-reports {
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
        }
        .nav-reports .nav-link {
            border: none;
            color: #666;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 10px 10px 0 0;
            margin-right: 5px;
        }
        .nav-reports .nav-link.active {
            background: #667eea;
            color: white;
            border-bottom: 3px solid #4a5fc1;
        }
        .export-buttons {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Navbar -->
        <nav class="navbar-custom">
            <div class="container-fluid">
                <button class="btn btn-primary d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h4 class="mb-0">Sistema de Reportes</h4>
                <div class="export-buttons">
                    <button class="btn btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-2"></i>Excel
                    </button>
                    <button class="btn btn-danger" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-2"></i>PDF
                    </button>
                    <button class="btn btn-info" onclick="printReport()">
                        <i class="fas fa-print me-2"></i>Imprimir
                    </button>
                </div>
            </div>
        </nav>
        
        <!-- Filtros -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tipo de Reporte</label>
                    <select name="tipo_reporte" class="form-select" onchange="this.form.submit()">
                        <option value="ventas" <?php echo $tipo_reporte == 'ventas' ? 'selected' : ''; ?>>Ventas por Período</option>
                        <option value="productos" <?php echo $tipo_reporte == 'productos' ? 'selected' : ''; ?>>Ventas por Producto</option>
                        <option value="vendedores" <?php echo $tipo_reporte == 'vendedores' ? 'selected' : ''; ?>>Ventas por Vendedor</option>
                        <option value="inventario" <?php echo $tipo_reporte == 'inventario' ? 'selected' : ''; ?>>Estado de Inventario</option>
                        <option value="contabilidad" <?php echo $tipo_reporte == 'contabilidad' ? 'selected' : ''; ?>>Estado Contable</option>
                        <option value="clientes" <?php echo $tipo_reporte == 'clientes' ? 'selected' : ''; ?>>Reporte de Clientes</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" 
                           value="<?php echo $fecha_inicio; ?>" required>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?php echo $fecha_fin; ?>" required>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Generar Reporte
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Métricas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="metric-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="metric-value">Q<?php echo number_format($metricas['total_ingresos'] ?? 0, 2); ?></div>
                    <div class="metric-label">INGRESOS TOTALES</div>
                    <small><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="metric-card" style="background: linear-gradient(135deg, #10b981 0%, #34d399 100%);">
                    <div class="metric-value"><?php echo $metricas['total_ventas'] ?? 0; ?></div>
                    <div class="metric-label">VENTAS REALIZADAS</div>
                    <small>Promedio: Q<?php echo number_format($metricas['promedio_venta'] ?? 0, 2); ?></small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="metric-card" style="background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);">
                    <div class="metric-value"><?php echo $metricas['clientes_nuevos'] ?? 0; ?></div>
                    <div class="metric-label">CLIENTES NUEVOS</div>
                    <small><?php echo $metricas['puntos_generados'] ?? 0; ?> puntos generados</small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="metric-card" style="background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);">
                    <div class="metric-value"><?php echo $metricas['productos_bajo_stock'] ?? 0; ?></div>
                    <div class="metric-label">PRODUCTOS BAJO STOCK</div>
                    <small><?php echo $metricas['productos_activos'] ?? 0; ?> productos activos</small>
                </div>
            </div>
        </div>
        
        <!-- Navegación de reportes -->
        <ul class="nav nav-reports">
            <li class="nav-item">
                <a class="nav-link <?php echo $tipo_reporte == 'ventas' ? 'active' : ''; ?>" 
                   href="?tipo_reporte=ventas&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>">
                   <i class="fas fa-chart-line me-2"></i>Ventas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tipo_reporte == 'productos' ? 'active' : ''; ?>" 
                   href="?tipo_reporte=productos&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>">
                   <i class="fas fa-box me-2"></i>Productos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tipo_reporte == 'vendedores' ? 'active' : ''; ?>" 
                   href="?tipo_reporte=vendedores&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>">
                   <i class="fas fa-users me-2"></i>Vendedores
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tipo_reporte == 'inventario' ? 'active' : ''; ?>" 
                   href="?tipo_reporte=inventario">
                   <i class="fas fa-warehouse me-2"></i>Inventario
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tipo_reporte == 'contabilidad' ? 'active' : ''; ?>" 
                   href="?tipo_reporte=contabilidad&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>">
                   <i class="fas fa-calculator me-2"></i>Contabilidad
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $tipo_reporte == 'clientes' ? 'active' : ''; ?>" 
                   href="?tipo_reporte=clientes">
                   <i class="fas fa-user-friends me-2"></i>Clientes
                </a>
            </li>
        </ul>
        
        <!-- Contenido del Reporte -->
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4><?php echo $titulo_reporte; ?></h4>
                <span class="text-muted">
                    <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
                </span>
            </div>
            
            <?php if ($tipo_reporte == 'ventas'): ?>
            <!-- Gráfico de ventas -->
            <div class="chart-container mb-4">
                <canvas id="salesChart"></canvas>
            </div>
            <?php endif; ?>
            
            <!-- Tabla de datos -->
            <div class="table-responsive">
                <table class="table table-hover table-report" id="reportTable">
                    <thead>
                        <?php if ($tipo_reporte == 'ventas'): ?>
                        <tr>
                            <th>Período</th>
                            <th>Facturas</th>
                            <th>Total Ventas</th>
                            <th>Promedio</th>
                            <th>Clientes</th>
                            <th>Puntos</th>
                        </tr>
                        <?php elseif ($tipo_reporte == 'productos'): ?>
                        <tr>
                            <th>Producto</th>
                            <th>Departamento</th>
                            <th>Veces Vendido</th>
                            <th>Unidades</th>
                            <th>Total Ventas</th>
                            <th>Precio Promedio</th>
                        </tr>
                        <?php elseif ($tipo_reporte == 'vendedores'): ?>
                        <tr>
                            <th>Vendedor</th>
                            <th>Facturas</th>
                            <th>Total Ventas</th>
                            <th>Promedio</th>
                            <th>Clientes</th>
                            <th>Puntos</th>
                        </tr>
                        <?php elseif ($tipo_reporte == 'inventario'): ?>
                        <tr>
                            <th>Producto</th>
                            <th>Departamento</th>
                            <th>Variantes</th>
                            <th>Stock Tienda</th>
                            <th>Stock Bodega</th>
                            <th>Stock Total</th>
                            <th>Estado</th>
                        </tr>
                        <?php elseif ($tipo_reporte == 'contabilidad'): ?>
                        <tr>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th>Total</th>
                            <th>Movimientos</th>
                            <th>Promedio</th>
                        </tr>
                        <?php elseif ($tipo_reporte == 'clientes'): ?>
                        <tr>
                            <th>Cliente</th>
                            <th>DPI</th>
                            <th>Compras</th>
                            <th>Total Gastado</th>
                            <th>Puntos</th>
                            <th>Última Compra</th>
                        </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php foreach ($datos_reporte as $dato): ?>
                        <tr>
                            <?php if ($tipo_reporte == 'ventas'): ?>
                            <td><?php echo $dato['periodo']; ?></td>
                            <td class="text-center"><?php echo $dato['cantidad_facturas']; ?></td>
                            <td class="text-end">Q<?php echo number_format($dato['total_ventas'], 2); ?></td>
                            <td class="text-end">Q<?php echo number_format($dato['promedio_venta'], 2); ?></td>
                            <td class="text-center"><?php echo $dato['clientes_unicos']; ?></td>
                            <td class="text-center"><?php echo $dato['puntos_generados']; ?></td>
                            
                            <?php elseif ($tipo_reporte == 'productos'): ?>
                            <td>
                                <strong><?php echo htmlspecialchars($dato['producto']); ?></strong>
                                <br><small class="text-muted"><?php echo $dato['codigo_producto']; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($dato['departamento']); ?></td>
                            <td class="text-center"><?php echo $dato['veces_vendido']; ?></td>
                            <td class="text-center"><?php echo $dato['unidades_vendidas']; ?></td>
                            <td class="text-end">Q<?php echo number_format($dato['total_ventas'], 2); ?></td>
                            <td class="text-end">Q<?php echo number_format($dato['precio_promedio'], 2); ?></td>
                            
                            <?php elseif ($tipo_reporte == 'vendedores'): ?>
                            <td><?php echo htmlspecialchars($dato['vendedor']); ?></td>
                            <td class="text-center"><?php echo $dato['cantidad_facturas']; ?></td>
                            <td class="text-end">Q<?php echo number_format($dato['total_ventas'], 2); ?></td>
                            <td class="text-end">Q<?php echo number_format($dato['promedio_venta'], 2); ?></td>
                            <td class="text-center"><?php echo $dato['clientes_atendidos']; ?></td>
                            <td class="text-center"><?php echo $dato['puntos_generados']; ?></td>
                            
                            <?php elseif ($tipo_reporte == 'inventario'): ?>
                            <td>
                                <strong><?php echo htmlspecialchars($dato['producto']); ?></strong>
                                <br><small class="text-muted"><?php echo $dato['codigo_producto']; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($dato['departamento']); ?></td>
                            <td class="text-center"><?php echo $dato['variantes']; ?></td>
                            <td class="text-center"><?php echo $dato['stock_tienda']; ?></td>
                            <td class="text-center"><?php echo $dato['stock_bodega']; ?></td>
                            <td class="text-center">
                                <strong><?php echo $dato['stock_total']; ?></strong>
                                <br><small>Mín: <?php echo $dato['stock_minimo_total']; ?></small>
                            </td>
                            <td>
                                <?php if ($dato['estado_stock'] == 'agotado'): ?>
                                <span class="badge bg-danger">Agotado</span>
                                <?php elseif ($dato['estado_stock'] == 'bajo'): ?>
                                <span class="badge bg-warning">Bajo</span>
                                <?php else: ?>
                                <span class="badge bg-success">Normal</span>
                                <?php endif; ?>
                            </td>
                            
                            <?php elseif ($tipo_reporte == 'contabilidad'): ?>
                            <td>
                                <?php if ($dato['tipo'] == 'ingreso'): ?>
                                <span class="badge bg-success">Ingreso</span>
                                <?php elseif ($dato['tipo'] == 'egreso'): ?>
                                <span class="badge bg-danger">Egreso</span>
                                <?php else: ?>
                                <span class="badge bg-warning">Ajuste</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($dato['categoria']); ?></td>
                            <td class="text-end">Q<?php echo number_format($dato['total'], 2); ?></td>
                            <td class="text-center"><?php echo $dato['cantidad_movimientos']; ?></td>
                            <td class="text-end">Q<?php echo number_format($dato['total'] / max($dato['cantidad_movimientos'], 1), 2); ?></td>
                            
                            <?php elseif ($tipo_reporte == 'clientes'): ?>
                            <td>
                                <strong><?php echo htmlspecialchars($dato['cliente']); ?></strong>
                                <br><small class="text-muted"><?php echo $dato['email']; ?></small>
                                <br><small class="text-muted"><?php echo $dato['telefono']; ?></small>
                            </td>
                            <td><?php echo $dato['dpi'] ?: 'N/A'; ?></td>
                            <td class="text-center"><?php echo $dato['compras_realizadas']; ?></td>
                            <td class="text-end">Q<?php echo number_format($dato['total_gastado'] ?? 0, 2); ?></td>
                            <td class="text-center">
                                <span class="badge bg-info">
                                    <?php echo $dato['puntos_disponibles'] ?? 0; ?> / <?php echo $dato['puntos_acumulados'] ?? 0; ?>
                                </span>
                            </td>
                            <td><?php echo $dato['ultima_compra'] ? date('d/m/Y', strtotime($dato['ultima_compra'])) : 'Sin compras'; ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    
    <script>
        // Inicializar DataTable
        $(document).ready(function() {
            $('#reportTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ],
                order: [],
                pageLength: 25
            });
        });
        
        // Gráfico de ventas
        <?php if ($tipo_reporte == 'ventas' && !empty($datos_reporte)): ?>
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($datos_reporte, 'periodo')); ?>,
                datasets: [{
                    label: 'Ventas Totales (Q)',
                    data: <?php echo json_encode(array_column($datos_reporte, 'total_ventas')); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Q' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Q' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Funciones de exportación
        function exportToExcel() {
            // En una implementación real, esto generaría un archivo Excel
            alert('Exportando a Excel...');
            // window.location.href = 'export.php?type=excel&report=<?php echo $tipo_reporte; ?>&start=<?php echo $fecha_inicio; ?>&end=<?php echo $fecha_fin; ?>';
        }
        
        function exportToPDF() {
            alert('Exportando a PDF...');
            // window.location.href = 'export.php?type=pdf&report=<?php echo $tipo_reporte; ?>&start=<?php echo $fecha_inicio; ?>&end=<?php echo $fecha_fin; ?>';
        }
        
        function printReport() {
            window.print();
        }
        
        // Toggle sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>