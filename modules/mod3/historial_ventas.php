<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Verificar autenticación
if (!$auth->isLoggedIn()) {
    header('Location: ../../index.php');
    exit();
}

// Filtros de búsqueda
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-30 days'));
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$filtro_vendedor = isset($_GET['vendedor']) ? $_GET['vendedor'] : '';

// Obtener vendedores para filtro
$vendedores_query = "SELECT id_usuario, nombre, apellido FROM usuarios WHERE id_rol IN (1,2,3) AND activo = 1 ORDER BY nombre";
$vendedores_stmt = $db->prepare($vendedores_query);
$vendedores_stmt->execute();
$vendedores = $vendedores_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estados
$estados_query = "SELECT * FROM estado_factura ORDER BY id_estado";
$estados_stmt = $db->prepare($estados_query);
$estados_stmt->execute();
$estados = $estados_stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir consulta con filtros
$query = "SELECT fc.*, u.nombre as vendedor_nombre, u.apellido as vendedor_apellido,
          COUNT(fd.id_detalle) as total_productos,
          SUM(fd.cantidad) as total_unidades
          FROM factura_cabecera fc
          LEFT JOIN usuarios u ON fc.id_usuario = u.id_usuario
          LEFT JOIN factura_detalle fd ON fc.id_factura = fd.id_factura
          WHERE fc.fecha BETWEEN :fecha_desde AND DATE_ADD(:fecha_hasta, INTERVAL 1 DAY)";
          
$params = [
    ':fecha_desde' => $filtro_fecha_desde,
    ':fecha_hasta' => $filtro_fecha_hasta
];

if ($filtro_estado) {
    $query .= " AND fc.id_estado = :estado";
    $params[':estado'] = $filtro_estado;
}

if ($filtro_tipo) {
    $query .= " AND fc.tipo_venta = :tipo";
    $params[':tipo'] = $filtro_tipo;
}

if ($filtro_vendedor) {
    $query .= " AND fc.id_usuario = :vendedor";
    $params[':vendedor'] = $filtro_vendedor;
}

if ($filtro_cliente) {
    $query .= " AND (fc.nombre_cliente LIKE :cliente OR fc.id_cliente = :cliente_id)";
    $cliente_term = "%$filtro_cliente%";
    $params[':cliente'] = $cliente_term;
    $params[':cliente_id'] = is_numeric($filtro_cliente) ? $filtro_cliente : 0;
}

$query .= " GROUP BY fc.id_factura ORDER BY fc.fecha DESC, fc.id_factura DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindParam($key, $value);
}
$stmt->execute();
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats_query = "SELECT 
                COUNT(*) as total_ventas,
                SUM(fc.total) as total_monto,
                AVG(fc.total) as promedio_venta,
                SUM(fc.puntos_ganados) as total_puntos,
                COUNT(DISTINCT fc.id_cliente) as clientes_unicos
                FROM factura_cabecera fc
                WHERE fc.fecha BETWEEN :fecha_desde AND DATE_ADD(:fecha_hasta, INTERVAL 1 DAY)";
                
if ($filtro_estado) {
    $stats_query .= " AND fc.id_estado = :estado";
}
if ($filtro_tipo) {
    $stats_query .= " AND fc.tipo_venta = :tipo";
}

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':fecha_desde', $filtro_fecha_desde);
$stats_stmt->bindParam(':fecha_hasta', $filtro_fecha_hasta);
if ($filtro_estado) {
    $stats_stmt->bindParam(':estado', $filtro_estado);
}
if ($filtro_tipo) {
    $stats_stmt->bindParam(':tipo', $filtro_tipo);
}
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .stats-card {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .badge-estado {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        .estado-pendiente { background: #ffc107; color: #000; }
        .estado-pagada { background: #28a745; color: #fff; }
        .estado-enviada { background: #17a2b8; color: #fff; }
        .estado-entregada { background: #20c997; color: #fff; }
        .estado-cancelada { background: #dc3545; color: #fff; }
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .export-buttons {
            margin-top: 20px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(26, 188, 156, 0.1);
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
                        <i class="fas fa-history me-2"></i>
                        Historial de Ventas
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="ventas.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i> Nueva Venta
                            </a>
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportarExcel()">
                                <i class="fas fa-file-excel me-1"></i> Excel
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="exportarPDF()">
                                <i class="fas fa-file-pdf me-1"></i> PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card text-white bg-primary">
                            <div class="card-body">
                                <h6 class="card-title">Total Ventas</h6>
                                <h2 class="card-text"><?php echo number_format($stats['total_ventas']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card text-white bg-success">
                            <div class="card-body">
                                <h6 class="card-title">Monto Total</h6>
                                <h2 class="card-text"><?php echo formatMoney($stats['total_monto']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card text-white bg-warning">
                            <div class="card-body">
                                <h6 class="card-title">Promedio Venta</h6>
                                <h2 class="card-text"><?php echo formatMoney($stats['promedio_venta']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card text-white bg-info">
                            <div class="card-body">
                                <h6 class="card-title">Clientes Únicos</h6>
                                <h2 class="card-text"><?php echo number_format($stats['clientes_unicos']); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filter-card">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label for="fecha_desde" class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" id="fecha_desde" 
                                   name="fecha_desde" value="<?php echo $filtro_fecha_desde; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" id="fecha_hasta" 
                                   name="fecha_hasta" value="<?php echo $filtro_fecha_hasta; ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="">Todos</option>
                                <?php foreach ($estados as $estado): ?>
                                    <option value="<?php echo $estado['id_estado']; ?>" 
                                        <?php echo $filtro_estado == $estado['id_estado'] ? 'selected' : ''; ?>>
                                        <?php echo $estado['nombre']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="tipo" class="form-label">Tipo Venta</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="">Todos</option>
                                <option value="tienda" <?php echo $filtro_tipo == 'tienda' ? 'selected' : ''; ?>>Tienda</option>
                                <option value="online" <?php echo $filtro_tipo == 'online' ? 'selected' : ''; ?>>Online</option>
                                <option value="recoger" <?php echo $filtro_tipo == 'recoger' ? 'selected' : ''; ?>>Recoger</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="vendedor" class="form-label">Vendedor</label>
                            <select class="form-select" id="vendedor" name="vendedor">
                                <option value="">Todos</option>
                                <?php foreach ($vendedores as $vendedor): ?>
                                    <option value="<?php echo $vendedor['id_usuario']; ?>" 
                                        <?php echo $filtro_vendedor == $vendedor['id_usuario'] ? 'selected' : ''; ?>>
                                        <?php echo $vendedor['nombre'] . ' ' . $vendedor['apellido']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="cliente" class="form-label">Cliente</label>
                            <input type="text" class="form-control" id="cliente" name="cliente" 
                                   value="<?php echo $filtro_cliente; ?>" placeholder="Nombre o ID">
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <div class="btn-group w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Filtrar
                                </button>
                                <a href="historial_ventas.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Limpiar
                                </a>
                                <button type="button" class="btn btn-success" onclick="mostrarGraficas()">
                                    <i class="fas fa-chart-bar me-1"></i> Gráficas
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tabla de ventas -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th># Factura</th>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th>Vendedor</th>
                                        <th>Productos</th>
                                        <th>Total</th>
                                        <th>Puntos</th>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ventas as $venta): 
                                        $estado_clases = [
                                            1 => 'estado-pendiente',
                                            2 => 'estado-pagada',
                                            3 => 'estado-enviada',
                                            4 => 'estado-entregada',
                                            5 => 'estado-cancelada'
                                        ];
                                        $estado_nombres = [
                                            1 => 'Pendiente',
                                            2 => 'Pagada',
                                            3 => 'Enviada',
                                            4 => 'Entregada',
                                            5 => 'Cancelada'
                                        ];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $venta['numero_factura']; ?></strong><br>
                                            <small class="text-muted"><?php echo $venta['numero_orden']; ?></small>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                        <td>
                                            <?php echo $venta['nombre_cliente']; ?><br>
                                            <?php if ($venta['id_cliente']): ?>
                                                <small class="text-muted">ID: <?php echo $venta['id_cliente']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($venta['vendedor_nombre']): ?>
                                                <?php echo $venta['vendedor_nombre'] . ' ' . $venta['vendedor_apellido']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Online</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $venta['total_productos']; ?> prod.</span><br>
                                            <span class="badge bg-secondary"><?php echo $venta['total_unidades']; ?> unid.</span>
                                        </td>
                                        <td>
                                            <strong><?php echo formatMoney($venta['total']); ?></strong><br>
                                            <?php if ($venta['descuento'] > 0): ?>
                                                <small class="text-success">-<?php echo formatMoney($venta['descuento']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($venta['puntos_usados'] > 0): ?>
                                                <span class="badge bg-warning text-dark">-<?php echo $venta['puntos_usados']; ?></span><br>
                                            <?php endif; ?>
                                            <?php if ($venta['puntos_ganados'] > 0): ?>
                                                <span class="badge bg-success">+<?php echo $venta['puntos_ganados']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $venta['tipo_venta'] == 'online' ? 'info' : ($venta['tipo_venta'] == 'recoger' ? 'warning text-dark' : 'secondary'); ?>">
                                                <?php echo ucfirst($venta['tipo_venta']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-estado <?php echo $estado_clases[$venta['id_estado']]; ?>">
                                                <?php echo $estado_nombres[$venta['id_estado']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="comprobante.php?id=<?php echo $venta['id_factura']; ?>" 
                                                   class="btn btn-outline-primary" title="Ver Comprobante" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="detalle_factura.php?id=<?php echo $venta['id_factura']; ?>" 
                                                   class="btn btn-outline-info" title="Detalles">
                                                    <i class="fas fa-info-circle"></i>
                                                </a>
                                                <button class="btn btn-outline-success" 
                                                        onclick="cambiarEstado(<?php echo $venta['id_factura']; ?>)" 
                                                        title="Cambiar Estado">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <?php if ($auth->isAdmin()): ?>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="eliminarFactura(<?php echo $venta['id_factura']; ?>)" 
                                                        title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <small class="text-muted">
                                    Mostrando <?php echo count($ventas); ?> ventas
                                </small>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#">Anterior</a>
                                    </li>
                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                    <li class="page-item">
                                        <a class="page-link" href="#">Siguiente</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- Exportar -->
                <div class="export-buttons text-center mt-4">
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" onclick="exportarVentas('csv')">
                            <i class="fas fa-file-csv me-2"></i> Exportar CSV
                        </button>
                        <button class="btn btn-outline-success" onclick="exportarVentas('excel')">
                            <i class="fas fa-file-excel me-2"></i> Exportar Excel
                        </button>
                        <button class="btn btn-outline-danger" onclick="exportarVentas('pdf')">
                            <i class="fas fa-file-pdf me-2"></i> Exportar PDF
                        </button>
                        <button class="btn btn-outline-info" onclick="imprimirReporte()">
                            <i class="fas fa-print me-2"></i> Imprimir Reporte
                        </button>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para cambiar estado -->
    <div class="modal fade" id="estadoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar Estado de Factura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="estadoForm">
                        <input type="hidden" id="factura_id">
                        <div class="mb-3">
                            <label for="nuevo_estado" class="form-label">Nuevo Estado</label>
                            <select class="form-select" id="nuevo_estado" required>
                                <?php foreach ($estados as $estado): ?>
                                    <option value="<?php echo $estado['id_estado']; ?>">
                                        <?php echo $estado['nombre']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="comentario" class="form-label">Comentario (opcional)</label>
                            <textarea class="form-control" id="comentario" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarEstado()">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para gráficas -->
    <div class="modal fade" id="graficasModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gráficas de Ventas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="ventasChart" height="250"></canvas>
                        </div>
                        <div class="col-md-6">
                            <canvas id="tipoVentasChart" height="250"></canvas>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <canvas id="vendedoresChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Inicializar datepickers
        flatpickr("#fecha_desde", {
            dateFormat: "Y-m-d",
            locale: "es"
        });
        flatpickr("#fecha_hasta", {
            dateFormat: "Y-m-d",
            locale: "es"
        });
        
        let estadoModal = null;
        let graficasModal = null;
        
        function cambiarEstado(facturaId) {
            document.getElementById('factura_id').value = facturaId;
            if (!estadoModal) {
                estadoModal = new bootstrap.Modal(document.getElementById('estadoModal'));
            }
            estadoModal.show();
        }
        
        function guardarEstado() {
            const facturaId = document.getElementById('factura_id').value;
            const nuevoEstado = document.getElementById('nuevo_estado').value;
            const comentario = document.getElementById('comentario').value;
            
            fetch('api/cambiar_estado.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `factura_id=${facturaId}&nuevo_estado=${nuevoEstado}&comentario=${encodeURIComponent(comentario)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Estado actualizado correctamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error al actualizar el estado');
            });
        }
        
        function eliminarFactura(facturaId) {
            if (confirm('¿Está seguro de eliminar esta factura? Esta acción no se puede deshacer.')) {
                fetch('api/eliminar_factura.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `factura_id=${facturaId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Factura eliminada correctamente');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
        
        function mostrarGraficas() {
            if (!graficasModal) {
                graficasModal = new bootstrap.Modal(document.getElementById('graficasModal'));
            }
            graficasModal.show();
            cargarGraficas();
        }
        
        function cargarGraficas() {
            // Obtener datos para gráficas
            fetch(`api/graficas_ventas.php?fecha_desde=<?php echo $filtro_fecha_desde; ?>&fecha_hasta=<?php echo $filtro_fecha_hasta; ?>`)
            .then(response => response.json())
            .then(data => {
                // Gráfica de ventas por día
                const ctx1 = document.getElementById('ventasChart').getContext('2d');
                new Chart(ctx1, {
                    type: 'line',
                    data: {
                        labels: data.dias,
                        datasets: [{
                            label: 'Ventas por Día',
                            data: data.ventas_dia,
                            borderColor: '#1abc9c',
                            backgroundColor: 'rgba(26, 188, 156, 0.1)',
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Ventas por Día'
                            }
                        }
                    }
                });
                
                // Gráfica de tipo de ventas
                const ctx2 = document.getElementById('tipoVentasChart').getContext('2d');
                new Chart(ctx2, {
                    type: 'pie',
                    data: {
                        labels: ['Tienda', 'Online', 'Recoger'],
                        datasets: [{
                            data: data.tipo_ventas,
                            backgroundColor: ['#2c3e50', '#1abc9c', '#f39c12']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Distribución por Tipo de Venta'
                            }
                        }
                    }
                });
                
                // Gráfica de vendedores
                const ctx3 = document.getElementById('vendedoresChart').getContext('2d');
                new Chart(ctx3, {
                    type: 'bar',
                    data: {
                        labels: data.vendedores_nombres,
                        datasets: [{
                            label: 'Ventas por Vendedor',
                            data: data.vendedores_ventas,
                            backgroundColor: '#3498db'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Ventas por Vendedor'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });
        }
        
        function exportarVentas(formato) {
            const params = new URLSearchParams(window.location.search);
            params.set('exportar', formato);
            window.location.href = 'api/exportar_ventas.php?' + params.toString();
        }
        
        function exportarExcel() {
            exportarVentas('excel');
        }
        
        function exportarPDF() {
            exportarVentas('pdf');
        }
        
        function imprimirReporte() {
            window.print();
        }
        
        // Cargar gráficas automáticamente si hay pocos resultados
        <?php if (count($ventas) <= 50): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar gráficas iniciales
            cargarGraficas();
        });
        <?php endif; ?>
    </script>
</body>
</html>