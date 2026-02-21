<?php

function generarFacturaPdf($db, $factura_id) {
    $dompdfAutoload = __DIR__ . '/../lib/dompdf/autoload.inc.php';
    if (!file_exists($dompdfAutoload)) {
        return [
            'ok' => false,
            'error' => 'Dompdf no encontrado en lib/dompdf'
        ];
    }

    require_once $dompdfAutoload;

    $factura_stmt = $db->prepare("SELECT id_factura, numero_factura, fecha, nombre_cliente, direccion, telefono, subtotal, descuento, total FROM factura_cabecera WHERE id_factura = :id");
    $factura_stmt->bindValue(':id', (int)$factura_id, PDO::PARAM_INT);
    $factura_stmt->execute();
    $factura = $factura_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$factura) {
        return [
            'ok' => false,
            'error' => 'Factura no encontrada'
        ];
    }

    $detalle_stmt = $db->prepare("SELECT fd.cantidad, fd.precio_unitario, fd.subtotal, pr.nombre FROM factura_detalle fd INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz WHERE fd.id_factura = :id");
    $detalle_stmt->bindValue(':id', (int)$factura_id, PDO::PARAM_INT);
    $detalle_stmt->execute();
    $detalles = $detalle_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_items = 0;
    foreach ($detalles as $detalle) {
        $total_items += (int)$detalle['cantidad'];
    }

    $safeNumero = preg_replace('/[^A-Za-z0-9_-]+/', '_', $factura['numero_factura']);
    $pdf_dir = __DIR__ . '/../uploads/facturas/';
    if (!file_exists($pdf_dir)) {
        mkdir($pdf_dir, 0777, true);
    }
    $pdf_path = $pdf_dir . 'factura_' . $safeNumero . '.pdf';

    $subtotal = number_format((float)$factura['subtotal'], 2);
    $descuento = number_format((float)$factura['descuento'], 2);
    $total = number_format((float)$factura['total'], 2);

    $rows_html = '';
    foreach ($detalles as $detalle) {
        $nombre = htmlspecialchars($detalle['nombre']);
        $cantidad = (int)$detalle['cantidad'];
        $precio_unitario = number_format((float)$detalle['precio_unitario'], 2);
        $linea_subtotal = number_format((float)$detalle['subtotal'], 2);
        $rows_html .= "<tr>\n";
        $rows_html .= "<td>{$nombre}</td>\n";
        $rows_html .= "<td style='text-align:center;'>{$cantidad}</td>\n";
        $rows_html .= "<td style='text-align:right;'>Q{$precio_unitario}</td>\n";
        $rows_html .= "<td style='text-align:right;'>Q{$linea_subtotal}</td>\n";
        $rows_html .= "</tr>\n";
    }

    $fecha = date('d/m/Y H:i', strtotime($factura['fecha']));

    $html = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; }
            .header { text-align: center; margin-bottom: 20px; }
            .meta { width: 100%; margin-bottom: 10px; }
            .meta td { vertical-align: top; padding: 4px 0; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border-bottom: 1px solid #ddd; padding: 8px; }
            th { background: #f1f1f1; text-align: left; }
            .totales { margin-top: 15px; width: 100%; }
            .totales td { padding: 6px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>" . SITE_NAME . "</h2>
            <div>Factura: {$factura['numero_factura']}</div>
        </div>

        <table class='meta'>
            <tr>
                <td><strong>Cliente:</strong> {$factura['nombre_cliente']}</td>
                <td style='text-align:right;'><strong>Fecha:</strong> {$fecha}</td>
            </tr>
            <tr>
                <td><strong>Direccion:</strong> {$factura['direccion']}</td>
                <td style='text-align:right;'><strong>Telefono:</strong> {$factura['telefono']}</td>
            </tr>
            <tr>
                <td><strong>Total de items:</strong> {$total_items}</td>
                <td></td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style='text-align:center;'>Cantidad</th>
                    <th style='text-align:right;'>Precio Unitario</th>
                    <th style='text-align:right;'>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                {$rows_html}
            </tbody>
        </table>

        <table class='totales'>
            <tr>
                <td style='text-align:right;'><strong>Subtotal:</strong></td>
                <td style='text-align:right; width:120px;'>Q{$subtotal}</td>
            </tr>
            <tr>
                <td style='text-align:right;'><strong>Descuento:</strong></td>
                <td style='text-align:right;'>Q{$descuento}</td>
            </tr>
            <tr>
                <td style='text-align:right;'><strong>Total:</strong></td>
                <td style='text-align:right;'><strong>Q{$total}</strong></td>
            </tr>
        </table>
    </body>
    </html>
    ";

    $dompdf = new Dompdf\Dompdf();
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();

    file_put_contents($pdf_path, $dompdf->output());

    return [
        'ok' => true,
        'path' => $pdf_path,
        'items' => $total_items,
        'numero' => $factura['numero_factura'],
        'total' => $factura['total']
    ];
}
