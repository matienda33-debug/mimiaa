<?php
// modules/mod3/api/graficas_ventas.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/config.php';

header('Content-Type: application/json');

session_start();

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

// Obtener parámetros
$filtro = $_GET['filtro'] ?? 'mes'; // día, semana, mes, año
$fecha_inicio = $_GET['fecha_inicio'] ?? null;
$fecha_fin = $_GET['fecha_fin'] ?? null;

try {
    $data = [];
    
    // Ventas por día (últimos 7 días)
    if ($filtro == 'dia' || $filtro == 'semana') {
        $query = "SELECT 
                    DATE(fecha) as fecha,
                    COUNT(*) as cantidad_ventas,
                    SUM(subtotal - descuento) as total_ventas,
                    AVG(subtotal - descuento) as promedio_venta
                  FROM factura_cabecera 
                  WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    AND id_estado IN (2,3,4) -- Solo ventas pagadas, enviadas o entregadas
                  GROUP BY DATE(fecha)
                  ORDER BY fecha";
        
        $stmt = $db->query($query);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $labels = [];
        $ventas = [];
        $cantidades = [];
        
        foreach ($resultados as $row) {
            $labels[] = date('d/m', strtotime($row['fecha']));
            $ventas[] = floatval($row['total_ventas']);
            $cantidades[] = intval($row['cantidad_ventas']);
        }
        
        $data = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Ventas (Q)',
                    'data' => $ventas,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'yAxisID' => 'y'
                ],
                [
                    'label' => 'Cantidad de Ventas',
                    'data' => $cantidades,
                    'borderColor' => 'rgb(255, 99, 132)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'yAxisID' => 'y1'
                ]
            ]
        ];
    }
    
    // Ventas por mes (últimos 12 meses)
    elseif ($filtro == 'mes') {
        $query = "SELECT 
                    DATE_FORMAT(fecha, '%Y-%m') as mes,
                    COUNT(*) as cantidad_ventas,
                    SUM(subtotal - descuento) as total_ventas,
                    AVG(subtotal - descuento) as promedio_venta
                  FROM factura_cabecera 
                  WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    AND id_estado IN (2,3,4)
                  GROUP BY DATE_FORMAT(fecha, '%Y-%m')
                  ORDER BY mes";
        
        $stmt = $db->query($query);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $labels = [];
        $ventas = [];
        $cantidades = [];
        
        foreach ($resultados as $row) {
            $labels[] = date('M Y', strtotime($row['mes'] . '-01'));
            $ventas[] = floatval($row['total_ventas']);
            $cantidades[] = intval($row['cantidad_ventas']);
        }
        
        $data = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Ventas (Q)',
                    'data' => $ventas,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'yAxisID' => 'y'
                ],
                [
                    'label' => 'Cantidad de Ventas',
                    'data' => $cantidades,
                    'borderColor' => 'rgb(255, 99, 132)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'yAxisID' => 'y1'
                ]
            ]
        ];
    }
    
    // Ventas por año
    elseif ($filtro == 'ano') {
        $query = "SELECT 
                    YEAR(fecha) as ano,
                    COUNT(*) as cantidad_ventas,
                    SUM(subtotal - descuento) as total_ventas,
                    AVG(subtotal - descuento) as promedio_venta
                  FROM factura_cabecera 
                  WHERE id_estado IN (2,3,4)
                  GROUP BY YEAR(fecha)
                  ORDER BY ano
                  LIMIT 5";
        
        $stmt = $db->query($query);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $labels = [];
        $ventas = [];
        $cantidades = [];
        
        foreach ($resultados as $row) {
            $labels[] = $row['ano'];
            $ventas[] = floatval($row['total_ventas']);
            $cantidades[] = intval($row['cantidad_ventas']);
        }
        
        $data = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Ventas (Q)',
                    'data' => $ventas,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'yAxisID' => 'y'
                ],
                [
                    'label' => 'Cantidad de Ventas',
                    'data' => $cantidades,
                    'borderColor' => 'rgb(255, 99, 132)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'yAxisID' => 'y1'
                ]
            ]
        ];
    }
    
    // Ventas por producto (top 10)
    $query_productos = "SELECT 
                          pr.nombre as producto,
                          SUM(fd.cantidad) as cantidad_vendida,
                          SUM(fd.subtotal) as total_ventas,
                          COUNT(DISTINCT fd.id_factura) as veces_vendido
                        FROM factura_detalle fd
                        INNER JOIN productos_variantes pv ON fd.id_producto = pv.id_variante
                        INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                        INNER JOIN factura_cabecera fc ON fd.id_factura = fc.id_factura
                        WHERE fc.id_estado IN (2,3,4)
                        GROUP BY pr.id_raiz
                        ORDER BY cantidad_vendida DESC
                        LIMIT 10";
    
    $stmt_productos = $db->query($query_productos);
    $productos_data = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
    
    // Ventas por vendedor
    $query_vendedores = "SELECT 
                           CONCAT(u.nombre, ' ', u.apellido) as vendedor,
                           COUNT(*) as cantidad_ventas,
                           SUM(fc.subtotal - fc.descuento) as total_ventas,
                           AVG(fc.subtotal - fc.descuento) as promedio_venta
                         FROM factura_cabecera fc
                         INNER JOIN usuarios u ON fc.id_usuario = u.id_usuario
                         WHERE fc.id_estado IN (2,3,4)
                         GROUP BY fc.id_usuario
                         ORDER BY total_ventas DESC";
    
    $stmt_vendedores = $db->query($query_vendedores);
    $vendedores_data = $stmt_vendedores->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas generales
    $query_estadisticas = "SELECT 
                             COUNT(*) as total_ventas,
                             SUM(subtotal - descuento) as ventas_totales,
                             AVG(subtotal - descuento) as promedio_venta,
                             MIN(subtotal - descuento) as venta_minima,
                             MAX(subtotal - descuento) as venta_maxima,
                             COUNT(DISTINCT id_cliente) as clientes_atendidos
                           FROM factura_cabecera 
                           WHERE id_estado IN (2,3,4)
                           AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    
    $stmt_estadisticas = $db->query($query_estadisticas);
    $estadisticas = $stmt_estadisticas->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'grafico_temporal' => $data,
        'productos_top' => $productos_data,
        'vendedores_top' => $vendedores_data,
        'estadisticas' => $estadisticas,
        'filtro' => $filtro
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en la consulta: ' . $e->getMessage()
    ]);
}