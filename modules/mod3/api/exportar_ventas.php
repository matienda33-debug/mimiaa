<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Validar autenticación
$auth = new Auth($db);
if (!$auth->isLoggedIn()) {
    http_response_code(403);
    exit('No autorizado');
}

// Solo permitir roles supervisores
if (!in_array($_SESSION['rol'], [1, 2])) {
    http_response_code(403);
    exit('Permiso denegado');
}

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$estado = $_GET['estado'] ?? null;
$formato = $_GET['formato'] ?? 'excel';

// Validar fechas
$fecha_inicio = DateTime::createFromFormat('Y-m-d', $fecha_inicio) ? $fecha_inicio : date('Y-m-01');
$fecha_fin = DateTime::createFromFormat('Y-m-d', $fecha_fin) ? $fecha_fin : date('Y-m-t');

// Construir query
$where_clauses = ["DATE(fc.fecha) BETWEEN :fecha_inicio AND :fecha_fin"];

if ($estado && in_array($estado, ['pendiente', 'confirmada', 'entregada', 'cancelada'])) {
    $where_clauses[] = "fc.estado_factura = :estado";
}

$where = implode(' AND ', $where_clauses);

$query = "SELECT 
          fc.id_factura,
          fc.numero_factura,
          fc.fecha,
          fc.total,
          fc.estado_factura,
          fc.id_cliente,
          per.nombre,
          per.apellido,
          COUNT(fd.id_detalle) as cantidad_productos,
          SUM(fd.cantidad) as unidades_vendidas
          FROM factura_cabecera fc
          LEFT JOIN clientes c ON fc.id_cliente = c.id_cliente
          LEFT JOIN personas per ON c.id_persona = per.id_persona
          LEFT JOIN factura_detalle fd ON fc.id_factura = fd.id_factura
          WHERE {$where}
          GROUP BY fc.id_factura
          ORDER BY fc.fecha DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':fecha_inicio', $fecha_inicio);
$stmt->bindParam(':fecha_fin', $fecha_fin);
if ($estado) {
    $stmt->bindParam(':estado', $estado);
}
$stmt->execute();
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exportar según formato
if ($formato == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=exportar_ventas_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['ID Factura', 'Número', 'Fecha', 'Cliente', 'Productos', 'Unidades', 'Total', 'Estado']);
    
    // Datos
    foreach ($ventas as $venta) {
        fputcsv($output, [
            $venta['id_factura'],
            $venta['numero_factura'],
            date('d/m/Y', strtotime($venta['fecha'])),
            $venta['nombre'] . ' ' . $venta['apellido'],
            $venta['cantidad_productos'],
            $venta['unidades_vendidas'],
            'Q' . number_format($venta['total'], 2),
            ucfirst($venta['estado_factura'])
        ]);
    }
    
    fclose($output);
} else {
    // Excel export
    require_once('../../vendor/autoload.php');
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Headers
    $sheet->setCellValue('A1', 'ID Factura');
    $sheet->setCellValue('B1', 'Número');
    $sheet->setCellValue('C1', 'Fecha');
    $sheet->setCellValue('D1', 'Cliente');
    $sheet->setCellValue('E1', 'Productos');
    $sheet->setCellValue('F1', 'Unidades');
    $sheet->setCellValue('G1', 'Total');
    $sheet->setCellValue('H1', 'Estado');
    
    // Datos
    $row = 2;
    foreach ($ventas as $venta) {
        $sheet->setCellValue('A' . $row, $venta['id_factura']);
        $sheet->setCellValue('B' . $row, $venta['numero_factura']);
        $sheet->setCellValue('C' . $row, $venta['fecha']);
        $sheet->setCellValue('D' . $row, $venta['nombre'] . ' ' . $venta['apellido']);
        $sheet->setCellValue('E' . $row, $venta['cantidad_productos']);
        $sheet->setCellValue('F' . $row, $venta['unidades_vendidas']);
        $sheet->setCellValue('G' . $row, $venta['total']);
        $sheet->setCellValue('H' . $row, $venta['estado_factura']);
        $row++;
    }
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename=exportar_ventas_' . date('Y-m-d') . '.xlsx');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
}
