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

// Obtener ID de factura
$factura_id = isset($_GET['id']) ? $_GET['id'] : (isset($_SESSION['factura_generada']['id_factura']) ? $_SESSION['factura_generada']['id_factura'] : null);

if (!$factura_id) {
    header('Location: ventas.php');
    exit();
}

// Obtener información de la factura
$query = "SELECT fc.*, u.nombre as vendedor_nombre, u.apellido as vendedor_apellido,
          c.dpi as cliente_dpi, c.email as cliente_email
          FROM factura_cabecera fc
          LEFT JOIN usuarios u ON fc.id_usuario = u.id_usuario
          LEFT JOIN clientes c ON fc.id_cliente = c.id_cliente
          WHERE fc.id_factura = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $factura_id);
$stmt->execute();
$factura = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$factura) {
    header('Location: ventas.php');
    exit();
}

// Obtener detalles de la factura
$detalle_query = "SELECT fd.*, pv.color, pv.talla, pr.codigo, pr.nombre as producto_nombre,
                  pr.es_ajitos
                  FROM factura_detalle fd
                  INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                  INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                  WHERE fd.id_factura = :id_factura
                  ORDER BY fd.id_detalle";
$detalle_stmt = $db->prepare($detalle_query);
$detalle_stmt->bindParam(':id_factura', $factura_id);
$detalle_stmt->execute();
$detalles = $detalle_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular IVA (asumiendo 12% para Guatemala)
$iva = $factura['subtotal'] * 0.12;
$subtotal_sin_iva = $factura['subtotal'] - $iva;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Venta - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .comprobante-container, .comprobante-container * {
                visibility: visible;
            }
            .comprobante-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
        .comprobante-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 2px solid #333;
            background: white;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: #1abc9c;
        }
        .ajitos-badge {
            background: #ff6b6b;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .factura-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .detalle-table th {
            background: #2c3e50;
            color: white;
            border: none;
        }
        .totales {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 0.9rem;
            color: #666;
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .legal-info {
            font-size: 0.8rem;
            color: #666;
            margin-top: 20px;
        }
        .badge-ajitos {
            background-color: #ff6b6b;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4 no-print">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Comprobante de Venta</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i> Imprimir
                            </button>
                            <a href="ventas.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Nueva Venta
                            </a>
                            <button class="btn btn-sm btn-outline-success" onclick="enviarCorreo()">
                                <i class="fas fa-envelope me-1"></i> Enviar por Correo
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="comprobante-container">
        <!-- Encabezado -->
        <div class="header">
            <div class="logo">
                <?php echo SITE_NAME; ?>
                <span class="ajitos-badge"><?php echo AJITOS_NAME; ?></span>
            </div>
            <p class="mb-0">Dirección: Ciudad de Guatemala</p>
            <p class="mb-0">Teléfono: (502) 1234-5678 | NIT: 1234567-8</p>
            <p class="mb-0">Correo: info@tiendamm.com</p>
        </div>
        
        <!-- Información de factura -->
        <div class="factura-info">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>No. Factura:</strong> <?php echo $factura['numero_factura']; ?></p>
                    <p class="mb-1"><strong>No. Orden:</strong> <?php echo $factura['numero_orden']; ?></p>
                    <p class="mb-1"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($factura['fecha'])); ?></p>
                    <p class="mb-1"><strong>Vendedor:</strong> <?php echo $factura['vendedor_nombre'] . ' ' . $factura['vendedor_apellido']; ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Cliente:</strong> <?php echo $factura['nombre_cliente']; ?></p>
                    <?php if ($factura['cliente_dpi']): ?>
                        <p class="mb-1"><strong>DPI:</strong> <?php echo $factura['cliente_dpi']; ?></p>
                    <?php endif; ?>
                    <?php if ($factura['telefono']): ?>
                        <p class="mb-1"><strong>Teléfono:</strong> <?php echo $factura['telefono']; ?></p>
                    <?php endif; ?>
                    <p class="mb-1"><strong>Tipo:</strong> <?php echo ucfirst($factura['tipo_venta']); ?></p>
                    <p class="mb-1"><strong>Estado:</strong> 
                        <?php if ($factura['id_estado'] == 2): ?>
                            <span class="badge bg-success">Pagada</span>
                        <?php elseif ($factura['id_estado'] == 1): ?>
                            <span class="badge bg-warning">Pendiente</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?php echo $factura['id_estado']; ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Detalle de productos -->
        <div class="table-responsive">
            <table class="table table-bordered detalle-table">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="10%">Código</th>
                        <th>Descripción</th>
                        <th width="10%">Color/Talla</th>
                        <th width="8%">Cant.</th>
                        <th width="12%">Precio Unit.</th>
                        <th width="12%">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $contador = 1; ?>
                    <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?php echo $contador++; ?></td>
                        <td><?php echo $detalle['codigo']; ?></td>
                        <td>
                            <?php echo $detalle['producto_nombre']; ?>
                            <?php if ($detalle['es_ajitos']): ?>
                                <span class="badge badge-ajitos">Ajitos</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $detalle['color']; ?><br>
                            <strong><?php echo $detalle['talla']; ?></strong>
                        </td>
                        <td class="text-center"><?php echo $detalle['cantidad']; ?></td>
                        <td class="text-end"><?php echo formatMoney($detalle['precio_unitario']); ?></td>
                        <td class="text-end"><?php echo formatMoney($detalle['subtotal']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Totales -->
        <div class="totales">
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal sin IVA:</span>
                        <span><?php echo formatMoney($subtotal_sin_iva); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>IVA (12%):</span>
                        <span><?php echo formatMoney($iva); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span><?php echo formatMoney($factura['subtotal']); ?></span>
                    </div>
                    
                    <?php if ($factura['descuento'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-success">
                        <span>Descuento (puntos):</span>
                        <span>-<?php echo formatMoney($factura['descuento']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between">
                        <h5 class="mb-0">Total a Pagar:</h5>
                        <h3 class="mb-0 text-primary"><?php echo formatMoney($factura['total']); ?></h3>
                    </div>
                    
                    <!-- Información de puntos -->
                    <div class="mt-3">
                        <?php if ($factura['puntos_usados'] > 0): ?>
                            <p class="mb-1"><strong>Puntos usados:</strong> <?php echo $factura['puntos_usados']; ?></p>
                        <?php endif; ?>
                        <?php if ($factura['puntos_ganados'] > 0): ?>
                            <p class="mb-1 text-success">
                                <strong>Puntos ganados:</strong> <?php echo $factura['puntos_ganados']; ?>
                                <small>(Q<?php echo number_format(calcularPuntos($factura['total']), 0); ?> = 1 punto)</small>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Información legal -->
        <div class="legal-info">
            <p class="text-center mb-2">
                <strong>ORIGINAL: CLIENTE | COPIA: ARCHIVO</strong>
            </p>
            <p class="text-center mb-1">
                Esta factura es un documento tributario electrónico generado automáticamente.
            </p>
            <p class="text-center mb-1">
                Autorización SAT: 12345678901234567890 | Fecha Autorización: <?php echo date('d/m/Y'); ?>
            </p>
            <p class="text-center mb-0">
                Serie: A | Número de autorización: 123456789012345678901234567890
            </p>
        </div>
        
        <!-- QR Code (simulado) -->
        <div class="qr-code">
            <div style="display: inline-block; padding: 10px; border: 1px solid #ddd; background: #f8f9fa;">
                <div style="width: 150px; height: 150px; background: #eee; display: flex; align-items: center; justify-content: center;">
                    <span style="color: #666;">QR Code</span><br>
                    <small>Factura Electrónica</small>
                </div>
            </div>
            <p class="mt-2 small">Escanea para verificar en SAT</p>
        </div>
        
        <!-- Firmas -->
        <div class="row mt-4">
            <div class="col-md-6 text-center">
                <div style="border-top: 1px solid #333; padding-top: 10px; margin-top: 60px;">
                    <strong>FIRMA DEL CLIENTE</strong>
                </div>
            </div>
            <div class="col-md-6 text-center">
                <div style="border-top: 1px solid #333; padding-top: 10px; margin-top: 60px;">
                    <strong>FIRMA DEL VENDEDOR</strong><br>
                    <small><?php echo $factura['vendedor_nombre'] . ' ' . $factura['vendedor_apellido']; ?></small>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p class="mb-1">¡Gracias por su compra!</p>
            <p class="mb-0">
                <i class="fas fa-phone me-1"></i> (502) 1234-5678 |
                <i class="fas fa-envelope me-1 ms-2"></i> info@tiendamm.com |
                <i class="fas fa-globe me-1 ms-2"></i> www.tiendamm.com
            </p>
            <p class="mb-0">Horario: Lunes a Viernes 8:00 - 18:00, Sábados 9:00 - 13:00</p>
            <p class="mb-0">Política de devoluciones: 7 días con factura original</p>
        </div>
    </div>

    <!-- Modal para solicitar correo -->
    <div class="modal fade" id="modalEnviarCorreo" tabindex="-1" aria-labelledby="modalEnviarCorreoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="modalEnviarCorreoLabel">
                        <i class="fas fa-envelope me-2"></i> Enviar Comprobante por Correo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="correoDestino" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="correoDestino" placeholder="ejemplo@correo.com">
                        <small class="form-text text-muted">Ingrese el correo donde desea recibir el comprobante.</small>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="guardarCorreo">
                        <label class="form-check-label" for="guardarCorreo">
                            Guardar este correo en el cliente
                        </label>
                    </div>
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nota:</strong> Si no recibes el correo en 5 minutos, revisa tu carpeta de spam.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="enviarCorreoConfirmado()">
                        <i class="fas fa-send me-2"></i> Enviar Comprobante
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function enviarCorreo() {
            // Mostrar modal para solicitar correo
            const modal = new bootstrap.Modal(document.getElementById('modalEnviarCorreo'));
            modal.show();
        }

        function enviarCorreoConfirmado() {
            const correo = document.getElementById('correoDestino').value.trim();
            const guardarCorreo = document.getElementById('guardarCorreo').checked;

            if (!correo) {
                alert('Por favor ingrese un correo válido');
                return;
            }

            // Validar formato de correo
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(correo)) {
                alert('Por favor ingrese un correo válido');
                return;
            }

            // Mostrar spinner
            const btnEnviar = document.querySelector('#modalEnviarCorreo .btn-primary');
            const textOriginal = btnEnviar.innerHTML;
            btnEnviar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Enviando...';
            btnEnviar.disabled = true;

            // Obtener la URL base dinámicamente
            const baseUrl = window.location.protocol + '//' + window.location.host + '/tiendaAA';
            
            // Enviar solicitud
            fetch(baseUrl + '/modules/mod3/api/enviar_factura.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'factura_id=<?php echo $factura_id; ?>&correo=' + encodeURIComponent(correo) + '&guardar=' + (guardarCorreo ? '1' : '0')
            })
            .then(response => response.json())
            .then(data => {
                btnEnviar.innerHTML = textOriginal;
                btnEnviar.disabled = false;

                if (data.success) {
                    // Cerrar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEnviarCorreo'));
                    modal.hide();

                    // Limpiar campos
                    document.getElementById('correoDestino').value = '';
                    document.getElementById('guardarCorreo').checked = false;

                    // Mostrar éxito
                    alert('✓ Comprobante enviado exitosamente a ' + correo);
                } else {
                    alert('✗ Error al enviar: ' + (data.message || 'Error desconocido'));
                }
            })
            .catch(error => {
                btnEnviar.innerHTML = textOriginal;
                btnEnviar.disabled = false;
                alert('✗ Error de conexión al enviar el correo');
                console.error('Error:', error);
            });
        }

        // Pre-llenar correo del cliente si existe
        document.addEventListener('DOMContentLoaded', function() {
            const correoCliente = '<?php echo $factura['cliente_email'] ?? ''; ?>';
            if (correoCliente) {
                document.getElementById('correoDestino').value = correoCliente;
            }
        });
        
        // Imprimir automáticamente si se solicita
        if (window.location.search.includes('print=true')) {
            window.print();
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>