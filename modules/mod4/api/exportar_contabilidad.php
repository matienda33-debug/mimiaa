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

// Solo contador y admin
if (!in_array($_SESSION['rol'], [1, 3])) {
    http_response_code(403);
    exit('Permiso denegado');
}

// Parámetros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$formato = $_GET['formato'] ?? 'excel';

// Validar fechas
$fecha_inicio = DateTime::createFromFormat('Y-m-d', $fecha_inicio) ? $fecha_inicio : date('Y-m-01');
$fecha_fin = DateTime::createFromFormat('Y-m-d', $fecha_fin) ? $fecha_fin : date('Y-m-t');

// Obtener datos contables
$query = "SELECT 
          fc.id_factura,
          fc.numero_factura,
          fc.fecha,
          fc.subtotal,
          fc.impuesto,
          fc.total,
          fc.metodo_pago,
          COUNT(fd.id_detalle) as cantidad_lineas,
          SUM(fd.cantidad * fd.precio_unitario) as monto_productos
          FROM factura_cabecera fc
          LEFT JOIN factura_detalle fd ON fc.id_factura = fd.id_factura
          WHERE DATE(fc.fecha) BETWEEN :inicio AND :fin
          AND fc.estado_factura IN ('confirmada', 'entregada')
          GROUP BY fc.id_factura
          ORDER BY fc.fecha DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':inicio', $fecha_inicio);
$stmt->bindParam(':fin', $fecha_fin);
$stmt->execute();
$facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_ingresos = array_sum(array_column($facturas, 'total'));
$total_impuestos = array_sum(array_column($facturas, 'impuesto'));
$total_productos = count($facturas);

if ($formato == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=exportar_contabilidad_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['REPORTE CONTABLE', SITE_NAME, date('d/m/Y')]);
    fputcsv($output, []);
    fputcsv($output, ['Período', 'De: ' . $fecha_inicio . ' Hasta: ' . $fecha_fin]);
    fputcsv($output, []);
    
    // Resumen
    fputcsv($output, ['RESUMEN FINANCIERO']);
    fputcsv($output, ['Total de Facturas', $total_productos]);
    fputcsv($output, ['Total de Ingresos', 'Q' . number_format($total_ingresos, 2)]);
    fputcsv($output, ['Total de Impuestos', 'Q' . number_format($total_impuestos, 2)]);
    fputcsv($output, []);
    
    // Detalle
    fputcsv($output, ['ID Factura', 'Número', 'Fecha', 'Subtotal', 'Impuesto', 'Total', 'Método Pago']);
    
    foreach ($facturas as $factura) {
        fputcsv($output, [
            $factura['id_factura'],
            $factura['numero_factura'],
            date('d/m/Y', strtotime($factura['fecha'])),
            'Q' . number_format($factura['subtotal'], 2),
            'Q' . number_format($factura['impuesto'], 2),
            'Q' . number_format($factura['total'], 2),
            ucfirst($factura['metodo_pago'])
        ]);
    }
    
    fclose($output);
} else {
    // Excel
    require_once('../../vendor/autoload.php');
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Contabilidad');
    
    // Encabezado
    $sheet->setCellValue('A1', 'REPORTE CONTABLE');
    $sheet->setCellValue('A2', SITE_NAME);
    $sheet->setCellValue('A3', 'Período: ' . $fecha_inicio . ' a ' . $fecha_fin);
    
    // Resumen
    $sheet->setCellValue('A5', 'RESUMEN FINANCIERO');
    $sheet->setCellValue('A6', 'Total de Facturas:');
    $sheet->setCellValue('B6', $total_productos);
    $sheet->setCellValue('A7', 'Total Ingresos:');
    $sheet->setCellValue('B7', $total_ingresos);
    $sheet->setCellValue('A8', 'Total Impuestos:');
    $sheet->setCellValue('B8', $total_impuestos);
    
    // Headers para detalle
    $sheet->setCellValue('A10', 'ID Factura');
    $sheet->setCellValue('B10', 'Número');
    $sheet->setCellValue('C10', 'Fecha');
    $sheet->setCellValue('D10', 'Subtotal');
    $sheet->setCellValue('E10', 'Impuesto');
    $sheet->setCellValue('F10', 'Total');
    $sheet->setCellValue('G10', 'Método Pago');
    
    // Datos
    $row = 11;
    foreach ($facturas as $factura) {
        $sheet->setCellValue('A' . $row, $factura['id_factura']);
        $sheet->setCellValue('B' . $row, $factura['numero_factura']);
        $sheet->setCellValue('C' . $row, $factura['fecha']);
        $sheet->setCellValue('D' . $row, $factura['subtotal']);
        $sheet->setCellValue('E' . $row, $factura['impuesto']);
        $sheet->setCellValue('F' . $row, $factura['total']);
        $sheet->setCellValue('G' . $row, $factura['metodo_pago']);
        $row++;
    }
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename=exportar_contabilidad_' . date('Y-m-d') . '.xlsx');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
}
