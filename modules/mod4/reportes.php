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
$auth->requirePermission('reportes');

$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';

// Tipos de reportes disponibles
$reportes_disponibles = [
    'ventas_diarias' => 'Ventas Diarias',
    'ventas_mensuales' => 'Ventas Mensuales',
    'productos_vendidos' => 'Productos Vendidos',
    'clientes_frecuentes' => 'Clientes Frecuentes',
    'inventario_valorizado' => 'Inventario Valorizado',
    'puntos_clientes' => 'Puntos de Clientes',
    'ventas_vendedor' => 'Ventas por Vendedor',
    'comparativo_mensual' => 'Comparativo Mensual'
];

// Parámetros por defecto
$tipo_reporte = isset($_GET['tipo_reporte']) ? $_GET['tipo_reporte'] : 'ventas_diarias';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');
$agrupacion = isset($_GET['agrupacion']) ? $_GET['agrupacion'] : 'dia';
$formato = isset($_GET['formato']) ? $_GET['formato'] : 'pantalla';

// Procesar reporte según tipo
$reporte_data = [];
$reporte_titulo = '';
$reporte_columnas = [];

switch ($tipo_reporte) {
    case 'ventas_diarias':
        $reporte_titulo = 'Reporte de Ventas Diarias';
        $reporte_columnas = ['Fecha', 'Ventas', 'Cantidad', 'Promedio', 'Descuentos', 'Puntos Generados'];
        
        $query = "SELECT 
                  DATE(fc.fecha) as fecha,
                  COUNT(*) as cantidad_ventas,
                  SUM(fc.total) as total_ventas,
                  AVG(fc.total) as promedio_venta,
                  SUM(fc.descuento) as total_descuentos,
                  SUM(fc.puntos_ganados) as puntos_generados
                  FROM factura_cabecera fc
                  WHERE fc.id_estado = 2 
                  AND fc.fecha BETWEEN :fecha_inicio AND DATE_ADD(:fecha_fin, INTERVAL 1 DAY)
                  GROUP BY DATE(fc.fecha)
                  ORDER BY fecha DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt->bindParam(':fecha_fin', $fecha_fin);
        $stmt->execute();
        $reporte_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'ventas_mensuales':
        $reporte_titulo = 'Reporte de Ventas Mensuales';
        $reporte_columnas = ['Mes', 'Ventas', 'Cantidad', 'Promedio', 'Crecimiento %', 'Clientes Únicos'];
        
        $query = "SELECT 
                  DATE_FORMAT(fc.fecha, '%Y-%m') as mes,
                  COUNT(*) as cantidad_ventas,
                  SUM(fc.total) as total_ventas,
                  AVG(fc.total) as promedio_venta,
                  COUNT(DISTINCT fc.id_cliente) as clientes_unicos
                  FROM factura_cabecera fc
                  WHERE fc.id_estado = 2 
                  AND fc.fecha BETWEEN DATE_SUB(:fecha_inicio, INTERVAL 12 MONTH) AND DATE_ADD(:fecha_fin, INTERVAL 1 DAY)
                  GROUP BY DATE_FORMAT(fc.fecha, '%Y-%m')
                  ORDER BY mes DESC
                  LIMIT 12";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt->bindParam(':fecha_fin', $fecha_fin);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular crecimiento
        $reporte_data = [];
        for ($i = 0; $i < count($data); $i++) {
            $row = $data[$i];
            $crecimiento = 0;
            
            if ($i < count($data) - 1) {
                $mes_anterior = $data[$i + 1]['total_ventas'];
                if ($mes_anterior > 0) {
                    $crecimiento = (($row['total_ventas'] - $mes_anterior) / $mes_anterior) * 100;
                }
            }
            
            $row['crecimiento'] = $crecimiento;
            $reporte_data[] = $row;
        }
        break;
        
    case 'productos_vendidos':
        $reporte_titulo = 'Reporte de Productos Vendidos';
        $reporte_columnas = ['Producto', 'Código', 'Color/Talla', 'Departamento', 'Vendidos', 'Ventas', 'Costo', 'Utilidad'];
        
        $query = "SELECT 
                  pr.nombre as producto,
                  pr.codigo,
                  pv.color,
                  pv.talla,
                  d.nombre as departamento,
                  SUM(fd.cantidad) as total_vendido,
                  SUM(fd.subtotal) as total_ventas,
                  SUM(fd.cantidad * COALESCE(pr.precio_compra, pr.precio_venta * 0.6)) as costo_total,
                  SUM(fd.subtotal) - SUM(fd.cantidad * COALESCE(pr.precio_compra, pr.precio_venta * 0.6)) as utilidad
                  FROM factura_detalle fd
                  INNER JOIN factura_cabecera fc ON fd.id_factura = fc.id_factura
                  INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                  INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                  INNER JOIN departamentos d ON pr.id_departamento = d.id_departamento
                  WHERE fc.id_estado = 2 
                  AND fc.fecha BETWEEN :fecha_inicio AND DATE_ADD(:fecha_fin, INTERVAL 1 DAY)
                  GROUP BY pv.id_variante
                  ORDER BY total_vendido DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt->bindParam(':fecha_fin', $fecha_fin);
        $stmt->execute();
        $reporte_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'clientes_frecuentes':
        $reporte_titulo = 'Reporte de Clientes Frecuentes';
        $reporte_columnas = ['Cliente', 'DPI', 'Compras', 'Total Gastado', 'Promedio', 'Última Compra', 'Puntos', 'Valor Puntos'];
        
        $query = "SELECT 
                  CONCAT(c.nombre, ' ', c.apellido) as cliente,
                  c.dpi,
                  COUNT(fc.id_factura) as total_compras,
                  SUM(fc.total) as total_gastado,
                  AVG(fc.total) as promedio_compra,
                  MAX(fc.fecha) as ultima_compra,
                  c.puntos
                  FROM clientes c
                  LEFT JOIN factura_cabecera fc ON c.id_cliente = fc.id_cliente
                  WHERE fc.id_estado = 2 
                  AND fc.fecha BETWEEN :fecha_inicio AND DATE_ADD(:fecha_fin, INTERVAL 1 DAY)
                  GROUP BY c.id_cliente
                  HAVING total_compras > 0
                  ORDER BY total_gastado DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt->bindParam(':fecha_fin', $fecha_fin);
        $stmt->execute();
        $reporte_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'inventario_valorizado':
        $reporte_titulo = 'Reporte de Inventario Valorizado';
        $reporte_columnas = ['Departamento', 'Productos', 'Variantes', 'Stock', 'Costo Total', 'Venta Total', 'Margen %'];
        
        $query = "SELECT 
                  d.nombre as departamento,
                  COUNT(DISTINCT pr.id_raiz) as productos,
                  COUNT(DISTINCT pv.id_variante) as variantes,
                  SUM(pv.stock_tienda + pv.stock_bodega) as stock_total,
                  SUM((pv.stock_tienda + pv.stock_bodega) * COALESCE(pr.precio_compra, pr.precio_venta * 0.6)) as costo_total,
                  SUM((pv.stock_tienda + pv.stock_bodega) * pr.precio_venta) as venta_total
                  FROM departamentos d
                  INNER JOIN productos_raiz pr ON d.id_departamento = pr.id_departamento
                  INNER JOIN productos_variantes pv ON pr.id_raiz = pv.id_producto_raiz
                  WHERE d.activo = 1 AND pr.activo = 1 AND pv.activo = 1
                  GROUP BY d.id_departamento
                  ORDER BY venta_total DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $reporte_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'puntos_clientes':
        $reporte_titulo = 'Reporte de Puntos de Clientes';
        $reporte_columnas = ['Cliente', 'DPI', 'Puntos Acumulados', 'Valor en Q', 'Puntos Canjeados', 'Puntos Vencidos', 'Estado'];
        
        $query = "SELECT 
                  CONCAT(c.nombre, ' ', c.apellido) as cliente,
                  c.dpi,
                  c.puntos as puntos_acumulados,
                  (c.puntos / 30) as valor_quetzales,
                  COALESCE(SUM(fc.puntos_usados), 0) as puntos_canjeados,
                  CASE 
                    WHEN c.puntos > 0 AND MAX(fc.fecha) < DATE_SUB(NOW(), INTERVAL 6 MONTH) 
                    THEN c.puntos ELSE 0 
                  END as puntos_vencidos
                  FROM clientes c
                  LEFT JOIN factura_cabecera fc ON c.id_cliente = fc.id_cliente
                  WHERE c.puntos > 0
                  GROUP BY c.id_cliente
                  ORDER BY c.puntos DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $reporte_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'ventas_vendedor':
        $reporte_titulo = 'Reporte de Ventas por Vendedor';
        $reporte_columnas = ['Vendedor', 'Ventas', 'Total', 'Promedio', 'Clientes Atendidos', 'Productos Vendidos', 'Puntos Otorgados'];
        
        $query = "SELECT 
                  CONCAT(u.nombre, ' ', u.apellido) as vendedor,
                  COUNT(fc.id_factura) as cantidad_ventas,
                  SUM(fc.total) as total_ventas,
                  AVG(fc.total) as promedio_venta,
                  COUNT(DISTINCT fc.id_cliente) as clientes_atendidos,
                  SUM(fd.cantidad) as productos_vendidos,
                  SUM(fc.puntos_ganados) as puntos_otorgados
                  FROM factura_cabecera fc
                  INNER JOIN usuarios u ON fc.id_usuario = u.id_usuario
                  INNER JOIN factura_detalle fd ON fc.id_factura = fd.id_factura
                  WHERE fc.id_estado = 2 
                  AND fc.fecha BETWEEN :fecha_inicio AND DATE_ADD(:fecha_fin, INTERVAL 1 DAY)
                  GROUP BY u.id_usuario
                  ORDER BY total_ventas DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt->bindParam(':fecha_fin', $fecha_fin);
        $stmt->execute();
        $reporte_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'comparativo_mensual':
        $reporte_titulo = 'Reporte Comparativo Mensual';
        $reporte_columnas = ['Indicador', date('M Y', strtotime('-2 month')), date('M Y', strtotime('-1 month')), date('M Y'), 'Crecimiento %'];
        
        // Obtener datos de los últimos 3 meses
        $meses = [
            date('Y-m', strtotime('-2 month')),
            date('Y-m', strtotime('-1 month')),
            date('Y-m')
        ];
        
        $indicadores = [];
        
        // Ventas totales
        foreach ($meses as $mes) {
            $query = "SELECT SUM(total) as ventas FROM factura_cabecera 
                     WHERE id_estado = 2 AND DATE_FORMAT(fecha, '%Y-%m') = :mes";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':mes', $mes);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $indicadores['Ventas Totales'][$mes] = $result['ventas'] ?: 0;
        }
        
        // Cantidad de ventas
        foreach ($meses as $mes) {
            $query = "SELECT COUNT(*) as cantidad FROM factura_cabecera 
                     WHERE id_estado = 2 AND DATE_FORMAT(fecha, '%Y-%m') = :mes";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':mes', $mes);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $indicadores['Cantidad de Ventas'][$mes] = $result['cantidad'] ?: 0;
        }
        
        // Clientes nuevos
        foreach ($meses as $mes) {
            $query = "SELECT COUNT(*) as cantidad FROM clientes 
                     WHERE DATE_FORMAT(fecha_registro, '%Y-%m') = :mes";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':mes', $mes);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $indicadores['Clientes Nuevos'][$mes] = $result['cantidad'] ?: 0;
        }
        
        // Productos vendidos
        foreach ($meses as $mes) {
            $query = "SELECT SUM(fd.cantidad) as cantidad 
                     FROM factura_detalle fd
                     INNER JOIN factura_cabecera fc ON fd.id_factura = fc.id_factura
                     WHERE fc.id_estado = 2 AND DATE_FORMAT(fc.fecha, '%Y-%m') = :mes";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':mes', $mes);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $indicadores['Productos Vendidos'][$mes] = $result['cantidad'] ?: 0;
        }
        
        // Puntos generados
        foreach ($meses as $mes) {
            $query = "SELECT SUM(puntos_ganados) as puntos FROM factura_cabecera 
                     WHERE id_estado = 2 AND DATE_FORMAT(fecha, '%Y-%m') = :mes";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':mes', $mes);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $indicadores['Puntos Generados'][$mes] = $result['puntos'] ?: 0;
        }
        
        // Formatear datos para la tabla
        $reporte_data = [];
        foreach ($indicadores as $indicador => $valores) {
            $mes1 = $valores[$meses[0]] ?? 0;
            $mes2 = $valores[$meses[1]] ?? 0;
            $mes3 = $valores[$meses[2]] ?? 0;
            
            $crecimiento = $mes2 > 0 ? (($mes3 - $mes2) / $mes2) * 100 : ($mes3 > 0 ? 100 : 0);
            
            $reporte_data[] = [
                'indicador' => $indicador,
                'mes1' => $mes1,
                'mes2' => $mes2,
                'mes3' => $mes3,
                'crecimiento' => $crecimiento
            ];
        }
        break;
}

// Si se solicita exportar
if ($formato != 'pantalla' && !empty($reporte_data)) {
    if ($formato == 'excel') {
        exportarExcel($reporte_titulo, $reporte_columnas, $reporte_data);
    } elseif ($formato == 'pdf') {
        exportarPDF($reporte_titulo, $reporte_columnas, $reporte_data);
    } elseif ($formato == 'csv') {
        exportarCSV($reporte_titulo, $reporte_columnas, $reporte_data);
    }
    exit();
}

// Funciones de exportación
function exportarExcel($titulo, $columnas, $datos) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $titulo . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<table border="1">';
    echo '<tr><th colspan="' . count($columnas) . '" style="background:#1abc9c;color:white;font-size:16px;">' . $titulo . '</th></tr>';
    echo '<tr>';
    foreach ($columnas as $columna) {
        echo '<th style="background:#2c3e50;color:white;">' . $columna . '</th>';
    }
    echo '</tr>';
    
    foreach ($datos as $fila) {
        echo '<tr>';
        foreach ($fila as $valor) {
            if (is_numeric($valor)) {
                echo '<td>' . number_format($valor, 2) . '</td>';
            } else {
                echo '<td>' . $valor . '</td>';
            }
        }
        echo '</tr>';
    }
    
    echo '</table>';
    exit();
}

function exportarPDF($titulo, $columnas, $datos) {
    // En un sistema real usarías TCPDF o Dompdf
    // Esta es una implementación básica para demostración
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="' . $titulo . '_' . date('Y-m-d') . '.pdf"');
    
    $html = '<h1>' . $titulo . '</h1>';
    $html .= '<p>Generado: ' . date('d/m/Y H:i:s') . '</p>';
    $html .= '<table border="1" cellpadding="5">';
    $html .= '<tr>';
    foreach ($columnas as $columna) {
        $html .= '<th>' . $columna . '</th>';
    }
    $html .= '</tr>';
    
    foreach ($datos as $fila) {
        $html .= '<tr>';
        foreach ($fila as $valor) {
            if (is_numeric($valor)) {
                $html .= '<td>' . number_format($valor, 2) . '</td>';
            } else {
                $html .= '<td>' . $valor . '</td>';
            }
        }
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    // En producción usarías: $pdf->writeHTML($html);
    echo $html;
    exit();
}

function exportarCSV($titulo, $columnas, $datos) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="' . $titulo . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $columnas);
    
    foreach ($datos as $fila) {
        $row_data = [];
        foreach ($fila as $valor) {
            if (is_numeric($valor)) {
                $row_data[] = number_format($valor, 2);
            } else {
                $row_data[] = $valor;
            }
        }
        fputcsv($output, $row_data);
    }
    
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Avanzados - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .report-card {
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .table-report {
            font-size: 0.9rem;
        }
        .table-report th {
            background: #2c3e50;
            color: white;
            position: sticky;
            top: 0;
        }
        .positive-growth {
            color: #28a745;
            font-weight: bold;
        }
        .negative-growth {
            color: #dc3545;
            font-weight: bold;
        }
        .export-buttons {
            position: sticky;
            bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .data-container {
            max-height: 500px;
            overflow-y: auto;
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
                        <i class="fas fa-chart-bar me-2"></i>
                        Reportes Avanzados
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="guardarConfiguracion()">
                                <i class="fas fa-save me-1"></i> Guardar Configuración
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="cargarConfiguracion()">
                                <i class="fas fa-folder-open me-1"></i> Cargar Configuración
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Configuración de reportes -->
                <div class="filter-section">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label for="tipo_reporte" class="form-label">Tipo de Reporte *</label>
                            <select class="form-select" id="tipo_reporte" name="tipo_reporte" required>
                                <?php foreach ($reportes_disponibles as $key => $nombre): ?>
                                    <option value="<?php echo $key; ?>" 
                                        <?php echo $tipo_reporte == $key ? 'selected' : ''; ?>>
                                        <?php echo $nombre; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
                        <div class="col-md-2">
                            <label for="agrupacion" class="form-label">Agrupación</label>
                            <select class="form-select" id="agrupacion" name="agrupacion">
                                <option value="dia" <?php echo $agrupacion == 'dia' ? 'selected' : ''; ?>>Día</option>
                                <option value="semana" <?php echo $agrupacion == 'semana' ? 'selected' : ''; ?>>Semana</option>
                                <option value="mes" <?php echo $agrupacion == 'mes' ? 'selected' : ''; ?>>Mes</option>
                                <option value="anio" <?php echo $agrupacion == 'anio' ? 'selected' : ''; ?>>Año</option>
                            </select>
                        </div>
                        <div class="col-md-6">
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
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="setPeriodo('ultimos30')">Últimos 30 días</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Formato de Salida</label>
                            <div class="btn-group w-100">
                                <button type="submit" name="formato" value="pantalla" 
                                        class="btn btn-primary">
                                    <i class="fas fa-desktop me-1"></i> Pantalla
                                </button>
                                <button type="submit" name="formato" value="excel" 
                                        class="btn btn-success">
                                    <i class="fas fa-file-excel me-1"></i> Excel
                                </button>
                                <button type="submit" name="formato" value="pdf" 
                                        class="btn btn-danger">
                                    <i class="fas fa-file-pdf me-1"></i> PDF
                                </button>
                                <button type="submit" name="formato" value="csv" 
                                        class="btn btn-info">
                                    <i class="fas fa-file-csv me-1"></i> CSV
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Resumen del reporte -->
                <div class="report-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <?php echo $reporte_titulo; ?>
                            <small class="float-end">
                                <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - 
                                <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
                            </small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($reporte_data)): ?>
                            <div class="alert alert-info">
                                No hay datos para el reporte seleccionado en el período especificado.
                            </div>
                        <?php else: ?>
                            <div class="data-container">
                                <div class="table-responsive">
                                    <table class="table table-hover table-report">
                                        <thead>
                                            <tr>
                                                <?php foreach ($reporte_columnas as $columna): ?>
                                                    <th><?php echo $columna; ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reporte_data as $fila): ?>
                                                <tr>
                                                    <?php foreach ($fila as $key => $valor): 
                                                        if ($key == 'crecimiento' || $key == 'crecimiento %'): ?>
                                                            <td class="<?php echo $valor >= 0 ? 'positive-growth' : 'negative-growth'; ?>">
                                                                <?php echo number_format($valor, 2); ?>%
                                                            </td>
                                                        <?php elseif (is_numeric($valor) && (strpos($key, 'total') !== false || 
                                                                 strpos($key, 'ventas') !== false || 
                                                                 strpos($key, 'gastado') !== false || 
                                                                 strpos($key, 'costo') !== false || 
                                                                 strpos($key, 'utilidad') !== false || 
                                                                 strpos($key, 'promedio') !== false)): ?>
                                                            <td><?php echo formatMoney($valor); ?></td>
                                                        <?php elseif (is_numeric($valor)): ?>
                                                            <td><?php echo number_format($valor, 0); ?></td>
                                                        <?php else: ?>
                                                            <td><?php echo $valor; ?></td>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Estadísticas del reporte -->
                            <div class="row mt-4">
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h6 class="card-title">Total Registros</h6>
                                            <h3 class="text-primary"><?php echo count($reporte_data); ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h6 class="card-title">Fecha Generación</h6>
                                            <h5><?php echo date('d/m/Y H:i:s'); ?></h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h6 class="card-title">Generado por</h6>
                                            <h5><?php echo $_SESSION['nombre']; ?></h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h6 class="card-title">Periodo</h6>
                                            <h5><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - 
                                                <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Botones de exportación flotantes -->
                <?php if (!empty($reporte_data)): ?>
                <div class="export-buttons text-center">
                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="exportarReporte('excel')">
                            <i class="fas fa-file-excel me-2"></i> Exportar a Excel
                        </button>
                        <button class="btn btn-danger" onclick="exportarReporte('pdf')">
                            <i class="fas fa-file-pdf me-2"></i> Exportar a PDF
                        </button>
                        <button class="btn btn-info" onclick="exportarReporte('csv')">
                            <i class="fas fa-file-csv me-2"></i> Exportar a CSV
                        </button>
                        <button class="btn btn-success" onclick="imprimirReporte()">
                            <i class="fas fa-print me-2"></i> Imprimir Reporte
                        </button>
                        <button class="btn btn-warning" onclick="generarGrafica()">
                            <i class="fas fa-chart-bar me-2"></i> Generar Gráfica
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal para gráficas -->
    <div class="modal fade" id="graficaModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gráfica del Reporte</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <canvas id="reportChart" height="400"></canvas>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="chartType">Tipo de Gráfica</label>
                                <select class="form-select" id="chartType" onchange="cambiarTipoGrafica()">
                                    <option value="bar">Barras</option>
                                    <option value="line">Líneas</option>
                                    <option value="pie">Pastel</option>
                                    <option value="doughnut">Dona</option>
                                    <option value="radar">Radar</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="descargarGrafica()">
                        <i class="fas fa-download me-2"></i> Descargar Imagen
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                case 'ultimos30':
                    fin = hoy.toISOString().split('T')[0];
                    const inicio30 = new Date(hoy);
                    inicio30.setDate(hoy.getDate() - 30);
                    inicio = inicio30.toISOString().split('T')[0];
                    break;
            }
            
            document.getElementById('fecha_inicio').value = inicio;
            document.getElementById('fecha_fin').value = fin;
        }
        
        // Funciones de exportación
        function exportarReporte(formato) {
            const params = new URLSearchParams(window.location.search);
            params.set('formato', formato);
            window.location.href = 'reportes.php?' + params.toString();
        }
        
        function imprimirReporte() {
            window.print();
        }
        
        // Guardar configuración en localStorage
        function guardarConfiguracion() {
            const config = {
                tipo_reporte: document.getElementById('tipo_reporte').value,
                fecha_inicio: document.getElementById('fecha_inicio').value,
                fecha_fin: document.getElementById('fecha_fin').value,
                agrupacion: document.getElementById('agrupacion').value
            };
            
            localStorage.setItem('reporte_config', JSON.stringify(config));
            alert('Configuración guardada exitosamente');
        }
        
        // Cargar configuración desde localStorage
        function cargarConfiguracion() {
            const config = JSON.parse(localStorage.getItem('reporte_config'));
            
            if (config) {
                document.getElementById('tipo_reporte').value = config.tipo_reporte;
                document.getElementById('fecha_inicio').value = config.fecha_inicio;
                document.getElementById('fecha_fin').value = config.fecha_fin;
                document.getElementById('agrupacion').value = config.agrupacion;
                alert('Configuración cargada exitosamente');
            } else {
                alert('No hay configuración guardada');
            }
        }
        
        let reportChart = null;
        
        // Generar gráfica del reporte
        function generarGrafica() {
            const tipoReporte = document.getElementById('tipo_reporte').value;
            const modal = new bootstrap.Modal(document.getElementById('graficaModal'));
            modal.show();
            
            // Preparar datos según el tipo de reporte
            setTimeout(() => {
                prepararDatosGrafica(tipoReporte);
            }, 500);
        }
        
        function prepararDatosGrafica(tipoReporte) {
            const ctx = document.getElementById('reportChart').getContext('2d');
            
            if (reportChart) {
                reportChart.destroy();
            }
            
            // Datos de ejemplo basados en el tipo de reporte
            let labels = [];
            let datasets = [];
            
            switch(tipoReporte) {
                case 'ventas_diarias':
                    labels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
                    datasets = [{
                        label: 'Ventas Diarias',
                        data: [1500, 2300, 1800, 2500, 3000, 2800, 2000],
                        backgroundColor: 'rgba(26, 188, 156, 0.5)',
                        borderColor: '#1abc9c',
                        borderWidth: 2
                    }];
                    break;
                    
                case 'ventas_mensuales':
                    labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                    datasets = [{
                        label: 'Ventas Mensuales',
                        data: [45000, 52000, 48000, 61000, 55000, 58000, 62000, 65000, 60000, 68000, 72000, 75000],
                        backgroundColor: 'rgba(52, 152, 219, 0.5)',
                        borderColor: '#3498db',
                        borderWidth: 2
                    }];
                    break;
                    
                case 'productos_vendidos':
                    labels = ['Producto A', 'Producto B', 'Producto C', 'Producto D', 'Producto E'];
                    datasets = [{
                        label: 'Unidades Vendidas',
                        data: [150, 230, 180, 250, 300],
                        backgroundColor: [
                            'rgba(231, 76, 60, 0.5)',
                            'rgba(52, 152, 219, 0.5)',
                            'rgba(46, 204, 113, 0.5)',
                            'rgba(155, 89, 182, 0.5)',
                            'rgba(241, 196, 15, 0.5)'
                        ],
                        borderWidth: 2
                    }];
                    break;
                    
                default:
                    labels = ['Dato 1', 'Dato 2', 'Dato 3', 'Dato 4', 'Dato 5'];
                    datasets = [{
                        label: 'Datos del Reporte',
                        data: [100, 200, 150, 250, 180],
                        backgroundColor: 'rgba(26, 188, 156, 0.5)',
                        borderColor: '#1abc9c',
                        borderWidth: 2
                    }];
            }
            
            reportChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Gráfica del Reporte: ' + document.querySelector('#tipo_reporte option:checked').textContent
                        },
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        function cambiarTipoGrafica() {
            if (reportChart) {
                reportChart.config.type = document.getElementById('chartType').value;
                reportChart.update();
            }
        }
        
        function descargarGrafica() {
            if (reportChart) {
                const link = document.createElement('a');
                link.download = 'grafica_reporte.png';
                link.href = reportChart.toBase64Image();
                link.click();
            }
        }
        
        // Cargar automáticamente configuración guardada
        document.addEventListener('DOMContentLoaded', function() {
            const config = JSON.parse(localStorage.getItem('reporte_config'));
            if (config && window.location.search === '') {
                document.getElementById('tipo_reporte').value = config.tipo_reporte;
                document.getElementById('fecha_inicio').value = config.fecha_inicio;
                document.getElementById('fecha_fin').value = config.fecha_fin;
                document.getElementById('agrupacion').value = config.agrupacion;
            }
        });
    </script>
</body>
</html>