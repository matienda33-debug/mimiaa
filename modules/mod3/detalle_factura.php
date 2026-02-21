<?php
// modules/mod3/detalle_factura.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/config.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Detectar si está embebido
$embedded = isset($_GET['embedded']) && $_GET['embedded'] == '1';

if (!$auth->isLoggedIn()) {
    header('Location: /tiendaAA/index.php');
    exit();
}

if (!$auth->hasPermission('ventas')) {
    header('Location: /tiendaAA/index.php?error=no_permission');
    exit();
}

// Verificar que se proporcione ID de factura
if (!isset($_GET['id'])) {
    header('Location: ventas.php');
    exit();
}

$id_factura = $_GET['id'];

// Obtener información de la factura cabecera
$query_factura = "SELECT fc.*, 
                         e.nombre as estado_nombre,
                         CONCAT(u.nombre, ' ', u.apellido) as vendedor_nombre,
                         c.nombre as cliente_nombre,
                         c.apellido as cliente_apellido,
                         c.dpi as cliente_dpi,
                         c.email as cliente_email,
                         c.telefono as cliente_telefono,
                         c.direccion as cliente_direccion,
                         c.puntos as cliente_puntos
                  FROM factura_cabecera fc
                  LEFT JOIN estado_factura e ON fc.id_estado = e.id_estado
                  LEFT JOIN usuarios u ON fc.id_usuario = u.id_usuario
                  LEFT JOIN clientes c ON fc.id_cliente = c.id_cliente
                  WHERE fc.id_factura = :id";

$stmt_factura = $db->prepare($query_factura);
$stmt_factura->bindParam(':id', $id_factura);
$stmt_factura->execute();
$factura = $stmt_factura->fetch(PDO::FETCH_ASSOC);

if (!$factura) {
    header('Location: ventas.php?error=factura_no_encontrada');
    exit();
}

// Obtener detalle de la factura con fotos de productos
$query_detalle = "SELECT fd.*, 
                         pr.nombre as producto_nombre,
                         pr.id_raiz,
                         pv.color,
                         pv.talla,
                         pv.sku
                  FROM factura_detalle fd
                  INNER JOIN productos_variantes pv ON fd.id_producto_variante = pv.id_variante
                  INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                  WHERE fd.id_factura = :id
                  ORDER BY fd.id_detalle";

$stmt_detalle = $db->prepare($query_detalle);
$stmt_detalle->bindParam(':id', $id_factura);
$stmt_detalle->execute();
$detalles = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);

// Obtener fotos de los productos
foreach ($detalles as &$detalle) {
    $stmt_fotos = $db->prepare("SELECT * FROM productos_raiz_fotos WHERE id_producto_raiz = :id AND es_principal = 1 LIMIT 1");
    $stmt_fotos->bindParam(':id', $detalle['id_raiz']);
    $stmt_fotos->execute();
    $foto = $stmt_fotos->fetch(PDO::FETCH_ASSOC);
    $detalle['foto'] = $foto ? ($foto['nombre_archivo'] ?? null) : null;
}
unset($detalle);

// Calcular totales
$subtotal = $factura['subtotal'];
$descuento = $factura['descuento'] ?? 0;
$total = $subtotal - $descuento;
$puntos_ganados = $factura['puntos_ganados'] ?? 0;

// Determinar si la factura puede ser editada (antes de empaque)
$estado_no_editable = in_array(strtolower($factura['estado_nombre'] ?? ''), 
                               ['empacado', 'enviado', 'entregada', 'cancelada']);

// Obtener historial de estados si existe (tabla opcional)
$historial = [];
try {
    $query_historial = "SELECT * FROM factura_historial 
                        WHERE id_factura = :id 
                        ORDER BY fecha_cambio DESC";
    $stmt_historial = $db->prepare($query_historial);
    $stmt_historial->bindParam(':id', $id_factura);
    $stmt_historial->execute();
    $historial = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabla factura_historial no existe, continuar sin historial
    $historial = [];
}

$titulo = "Factura #" . $factura['numero_factura'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo . ' - ' . SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        .status-badge {
            font-size: 0.9em;
            padding: 8px 15px;
        }
        .status-pendiente { background-color: #ffc107; color: #000; }
        .status-pagada { background-color: #198754; color: white; }
        .status-enviada { background-color: #0dcaf0; color: white; }
        .status-entregada { background-color: #6f42c1; color: white; }
        .status-cancelada { background-color: #dc3545; color: white; }
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        .total-section {
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            border-radius: 5px;
        }
        .customer-info {
            background-color: #e9ecef;
            border-radius: 8px;
            padding: 15px;
        }
        .print-only {
            display: none;
        }
        <?php if ($embedded): ?>
        .sidebar,
        nav.sidebar,
        .navbar,
        .navbar-custom,
        .topbar,
        .offcanvas,
        #sidebar,
        #sidebarMenu,
        [data-role="sidebar"] {
            display: none !important;
        }
        main,
        .main-content,
        .content-wrapper,
        .page-content,
        .container-fluid,
        .container {
            margin-left: 0 !important;
            max-width: 100%;
        }
        .row {
            --bs-gutter-x: 1rem;
        }
        <?php endif; ?>
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                font-size: 12px;
            }
            .container {
                width: 100% !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php if (!$embedded): ?>
            <!-- Sidebar -->
            <?php include $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/includes/sidebar.php'; ?>
            <?php endif; ?>
            
            <!-- Main content -->
            <main class="<?php echo $embedded ? 'w-100' : 'col-md-9 ms-sm-auto col-lg-10'; ?> px-md-4">
                <?php if (!$embedded): ?>
                <!-- Header -->
                <?php include $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/includes/header.php'; ?>
                <?php endif; ?>
                
                <div class="container-fluid mt-4">
                    <!-- Botones de acción -->
                    <div class="d-flex justify-content-between align-items-center mb-4 no-print <?php echo $embedded ? 'p-0 border-0' : ''; ?>">
                        <?php if (!$embedded): ?>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="ventas.php">Ventas</a></li>
                                <li class="breadcrumb-item active">Factura #<?php echo $factura['numero_factura']; ?></li>
                            </ol>
                        </nav>
                        <?php else: ?>
                        <h5 class="mb-0">Factura #<?php echo $factura['numero_factura']; ?></h5>
                        <?php endif; ?>
                        <div class="btn-group" role="group">
                            <button onclick="window.print()" class="btn btn-outline-primary btn-sm" title="Imprimir">
                                <i class="fas fa-print me-1"></i><span class="d-none d-md-inline">Imprimir</span>
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="abrirModalEnviarCorreo()" title="Enviar por correo">
                                <i class="fas fa-envelope me-1"></i><span class="d-none d-md-inline">Email</span>
                            </button>
                            <?php if (!$embedded): ?>
                            <?php if (!$estado_no_editable): ?>
                            <a href="editar_factura.php?id=<?php echo $id_factura; ?>" class="btn btn-warning btn-sm" title="Editar">
                                <i class="fas fa-edit me-1"></i><span class="d-none d-md-inline">Editar</span>
                            </a>
                            <?php else: ?>
                            <button class="btn btn-warning btn-sm" disabled title="No se puede editar facturas empacadas o enviadas">
                                <i class="fas fa-edit me-1"></i><span class="d-none d-md-inline">Editar</span>
                            </button>
                            <?php endif; ?>
                            <?php if ($auth->isAdmin() || $factura['id_estado'] == 1): ?>
                            <button class="btn btn-danger btn-sm" onclick="confirmarEliminar(<?php echo $id_factura; ?>)" title="Eliminar">
                                <i class="fas fa-trash me-1"></i><span class="d-none d-md-inline">Eliminar</span>
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                            <button class="btn btn-success btn-sm" onclick="descargarPDF()" title="Descargar PDF">
                                <i class="fas fa-download me-1"></i><span class="d-none d-md-inline">PDF</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Alerta de restricción de edición -->
                    <?php if ($estado_no_editable): ?>
                    <div class="alert alert-info no-print mb-4" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Factura en estado "<?php echo ucfirst($factura['estado_nombre']); ?>"</strong> — 
                        Esta factura no puede ser editada porque ya fue empacada. 
                        Si necesita hacer cambios, por favor cree una nota de ajuste o devolución.
                    </div>
                    <?php endif; ?>
                    
                    <!-- Encabezado de la factura -->
                    <div class="card mb-4">
                        <div class="card-body p-0">
                            <div class="invoice-header">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h1 class="h3 mb-2">
                                            <i class="fas fa-file-invoice-dollar me-2"></i>
                                            <?php echo SITE_NAME; ?>
                                        </h1>
                                        <p class="mb-0">
                                            <i class="fas fa-store me-2"></i>Tienda de Ropa
                                        </p>
                                        <p class="mb-0">
                                            <i class="fas fa-phone me-2"></i>Teléfono: 1234-5678
                                        </p>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <h2 class="h1 mb-2">FACTURA</h2>
                                        <p class="h5 mb-1">#<?php echo $factura['numero_factura']; ?></p>
                                        <p class="mb-0">
                                            <i class="fas fa-calendar me-2"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($factura['fecha'])); ?>
                                        </p>
                                        <?php 
                                        $status_class = '';
                                        switch($factura['estado_nombre']) {
                                            case 'pendiente': $status_class = 'status-pendiente'; break;
                                            case 'pagada': $status_class = 'status-pagada'; break;
                                            case 'enviada': $status_class = 'status-enviada'; break;
                                            case 'entregada': $status_class = 'status-entregada'; break;
                                            case 'cancelada': $status_class = 'status-cancelada'; break;
                                        }
                                        ?>
                                        <span class="badge status-badge <?php echo $status_class; ?> mt-2">
                                            <?php echo strtoupper($factura['estado_nombre']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-4">
                                <!-- Información del cliente y tienda -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="customer-info">
                                            <h5 class="mb-3">
                                                <i class="fas fa-user me-2"></i>Información del Cliente
                                            </h5>
                                            <p class="mb-1"><strong>Nombre:</strong> <?php echo $factura['nombre_cliente']; ?></p>
                                            <p class="mb-1"><strong>DPI:</strong> <?php echo $factura['cliente_dpi'] ?? 'No registrado'; ?></p>
                                            <p class="mb-1"><strong>Teléfono:</strong> <?php echo $factura['telefono'] ?? 'No registrado'; ?></p>
                                            <p class="mb-1"><strong>Email:</strong> <?php echo $factura['correo_envio'] ?? $factura['cliente_email'] ?? 'No registrado'; ?></p>
                                            <p class="mb-0"><strong>Dirección:</strong> <?php echo $factura['direccion'] ?? $factura['cliente_direccion'] ?? 'No registrada'; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="customer-info">
                                            <h5 class="mb-3">
                                                <i class="fas fa-store me-2"></i>Información de la Tienda
                                            </h5>
                                            <p class="mb-1"><strong>Vendedor:</strong> <?php echo $factura['vendedor_nombre'] ?? 'No asignado'; ?></p>
                                            <p class="mb-1"><strong>Orden #:</strong> <?php echo $factura['numero_orden'] ?? 'N/A'; ?></p>
                                            <p class="mb-1"><strong>Tipo Venta:</strong> <?php echo $factura['tipo_venta'] == 1 ? 'Contado' : 'Crédito'; ?></p>
                                            <p class="mb-1"><strong>Referencia:</strong> <?php echo $factura['referencia'] ?? 'N/A'; ?></p>
                                            <p class="mb-0"><strong>Puntos Ganados:</strong> <span class="badge bg-success"><?php echo $puntos_ganados; ?></span></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Detalle de productos -->
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered">
                                        <thead class="table-dark">
                                            <tr>
                                                <th width="5%">#</th>
                                                <th width="8%">Foto</th>
                                                <th width="8%">Código</th>
                                                <th>Producto</th>
                                                <th width="8%">Color</th>
                                                <th width="8%">Talla</th>
                                                <th width="8%">Cantidad</th>
                                                <th width="10%">Precio Unitario</th>
                                                <th width="10%">Descuento</th>
                                                <th width="10%">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $contador = 1;
                                            foreach ($detalles as $detalle): 
                                                $subtotal_item = $detalle['precio_unitario'] * $detalle['cantidad'] - $detalle['descuento_unitario'];
                                            ?>
                                            <tr>
                                                <td><?php echo $contador++; ?></td>
                                                <td>
                                                    <?php if (!empty($detalle['foto'])): ?>
                                                        <img src="<?php echo '../../' . IMG_DIR . 'productos/' . htmlspecialchars($detalle['foto']); ?>" alt="Foto" class="img-fluid" style="max-width: 50px; height: 50px; object-fit: cover; border-radius: 3px;">
                                                    <?php else: ?>
                                                        <span class="text-muted small">Sin foto</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><code><?php echo $detalle['sku']; ?></code></td>
                                                <td><?php echo htmlspecialchars($detalle['producto_nombre']); ?></td>
                                                <td>
                                                    <span class="badge" style="background-color: <?php echo $detalle['color']; ?>; color: white;">
                                                        <?php echo $detalle['color']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $detalle['talla']; ?></td>
                                                <td class="text-center"><?php echo $detalle['cantidad']; ?></td>
                                                <td class="text-end">Q<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                                                <td class="text-end">Q<?php echo number_format($detalle['descuento_unitario'], 2); ?></td>
                                                <td class="text-end fw-bold">Q<?php echo number_format($subtotal_item, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Totales -->
                                <div class="row">
                                    <div class="col-md-6 offset-md-6">
                                        <div class="total-section">
                                            <div class="row mb-2">
                                                <div class="col-6 text-end">Subtotal:</div>
                                                <div class="col-6 text-end fw-bold">Q<?php echo number_format($subtotal, 2); ?></div>
                                            </div>
                                            <div class="row mb-2">
                                                <div class="col-6 text-end">Descuento:</div>
                                                <div class="col-6 text-end text-danger">-Q<?php echo number_format($descuento, 2); ?></div>
                                            </div>
                                            <div class="row mb-2">
                                                <div class="col-6 text-end">Puntos Usados:</div>
                                                <div class="col-6 text-end"><?php echo $factura['puntos_usados'] ?? 0; ?> pts</div>
                                            </div>
                                            <hr>
                                            <div class="row">
                                                <div class="col-6 text-end"><h5 class="mb-0">TOTAL:</h5></div>
                                                <div class="col-6 text-end"><h5 class="mb-0 text-primary">Q<?php echo number_format($total, 2); ?></h5></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Información adicional -->
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <h6><i class="fas fa-info-circle me-2"></i>Información Adicional</h6>
                                            <p class="mb-1"><strong>Llave de confirmación:</strong> <?php echo $factura['llave_confirmacion'] ?? 'N/A'; ?></p>
                                            <p class="mb-1"><strong>Fecha de expiración:</strong> <?php echo $factura['fecha_expiracion'] ? date('d/m/Y H:i', strtotime($factura['fecha_expiracion'])) : 'N/A'; ?></p>
                                            <p class="mb-0"><strong>Notas:</strong> <?php echo $factura['notas'] ?? 'Sin notas adicionales'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Historial de estados -->
                    <?php if (!empty($historial)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-history me-2"></i>Historial de Estados
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php foreach ($historial as $evento): ?>
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-exchange-alt"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">
                                            Estado cambiado a: 
                                            <span class="badge bg-secondary"><?php echo ucfirst($evento['estado_anterior']); ?></span>
                                            <i class="fas fa-arrow-right mx-2"></i>
                                            <span class="badge bg-primary"><?php echo ucfirst($evento['estado_nuevo']); ?></span>
                                        </h6>
                                        <p class="text-muted mb-1">
                                            <i class="fas fa-user me-1"></i>
                                            Usuario: <?php echo $evento['usuario'] ?? 'Sistema'; ?>
                                        </p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($evento['fecha_cambio'])); ?>
                                        </small>
                                        <?php if (!empty($evento['comentario'])): ?>
                                        <p class="mt-2 mb-0">
                                            <i class="fas fa-comment me-1"></i>
                                            <?php echo htmlspecialchars($evento['comentario']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Acciones rápidas -->
                    <?php if (!$embedded && $auth->hasPermission('ventas')): ?>
                    <div class="card no-print">
                        <div class="card-header">
                            <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php if ($factura['id_estado'] == 1): // Pendiente ?>
                                <div class="col-md-3">
                                    <form method="POST" action="api/cambiar_estado.php" class="d-inline">
                                        <input type="hidden" name="id_factura" value="<?php echo $id_factura; ?>">
                                        <input type="hidden" name="nuevo_estado" value="2">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-check-circle me-2"></i>Marcar como Pagada
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($factura['id_estado'] == 2): // Pagada ?>
                                <div class="col-md-3">
                                    <form method="POST" action="api/cambiar_estado.php" class="d-inline">
                                        <input type="hidden" name="id_factura" value="<?php echo $id_factura; ?>">
                                        <input type="hidden" name="nuevo_estado" value="3">
                                        <button type="submit" class="btn btn-info w-100">
                                            <i class="fas fa-shipping-fast me-2"></i>Marcar como Enviada
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($factura['id_estado'] == 3): // Enviada ?>
                                <div class="col-md-3">
                                    <form method="POST" action="api/cambiar_estado.php" class="d-inline">
                                        <input type="hidden" name="id_factura" value="<?php echo $id_factura; ?>">
                                        <input type="hidden" name="nuevo_estado" value="4">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-box me-2"></i>Marcar como Entregada
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($factura['id_estado'] != 5): // No cancelada ?>
                                <div class="col-md-3">
                                    <button class="btn btn-danger w-100" onclick="confirmarCancelacion(<?php echo $id_factura; ?>)">
                                        <i class="fas fa-times-circle me-2"></i>Cancelar Factura
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para solicitar correo -->
    <div class="modal fade" id="modalEnviarCorreoDetalle" tabindex="-1" aria-labelledby="modalEnviarCorreoDetalleLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="modalEnviarCorreoDetalleLabel">
                        <i class="fas fa-envelope me-2"></i> Enviar Factura por Correo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="correoDestinoDetalle" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="correoDestinoDetalle" placeholder="ejemplo@correo.com">
                        <small class="form-text text-muted">Ingrese el correo donde desea recibir la factura.</small>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="guardarCorreoDetalle">
                        <label class="form-check-label" for="guardarCorreoDetalle">
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
                        <i class="fas fa-send me-2"></i> Enviar Factura
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para cancelar -->
    <div class="modal fade" id="modalCancelar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancelar Factura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="api/cambiar_estado.php" id="formCancelar">
                    <div class="modal-body">
                        <input type="hidden" name="id_factura" id="cancel_id_factura">
                        <input type="hidden" name="nuevo_estado" value="5">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>¡Advertencia!</strong> Esta acción no se puede deshacer.
                        </div>
                        
                        <div class="mb-3">
                            <label for="motivo_cancelacion" class="form-label">Motivo de cancelación *</label>
                            <textarea class="form-control" id="motivo_cancelacion" name="comentario" rows="3" required placeholder="Explique el motivo de la cancelación..."></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="devolver_stock" name="devolver_stock" checked>
                            <label class="form-check-label" for="devolver_stock">
                                Devolver productos al inventario
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar Cancelación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script>
    function confirmarEliminar(id) {
        if (confirm('¿Está seguro de eliminar esta factura? Esta acción no se puede deshacer.')) {
            window.location.href = 'api/eliminar_factura.php?id=' + id;
        }
    }
    
    function confirmarCancelacion(id) {
        document.getElementById('cancel_id_factura').value = id;
        var modal = new bootstrap.Modal(document.getElementById('modalCancelar'));
        modal.show();
    }
    
    function descargarPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Agregar contenido HTML a PDF
        html2canvas(document.querySelector('.card-body')).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const imgWidth = 210; // A4 width in mm
            const pageHeight = 295; // A4 height in mm
            const imgHeight = canvas.height * imgWidth / canvas.width;
            let heightLeft = imgHeight;
            let position = 0;
            
            doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
            
            while (heightLeft >= 0) {
                position = heightLeft - imgHeight;
                doc.addPage();
                doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
            }
            
            doc.save('factura_<?php echo $factura['numero_factura']; ?>.pdf');
        });
    }
    
    // Imprimir
    function imprimirFactura() {
        window.print();
    }

    // Modal para enviar correo
    function abrirModalEnviarCorreo() {
        const modal = new bootstrap.Modal(document.getElementById('modalEnviarCorreoDetalle'));
        
        // Pre-llenar correo del cliente si existe
        const correoCliente = '<?php echo $factura['cliente_email'] ?? ''; ?>';
        document.getElementById('correoDestinoDetalle').value = correoCliente || '';
        
        modal.show();
    }

    function enviarCorreoConfirmado() {
        const correo = document.getElementById('correoDestinoDetalle').value.trim();
        const guardarCorreo = document.getElementById('guardarCorreoDetalle').checked;

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
        const btnEnviar = document.querySelector('#modalEnviarCorreoDetalle .btn-primary');
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
            body: 'factura_id=<?php echo $id_factura; ?>&correo=' + encodeURIComponent(correo) + '&guardar=' + (guardarCorreo ? '1' : '0')
        })
        .then(response => response.json())
        .then(data => {
            btnEnviar.innerHTML = textOriginal;
            btnEnviar.disabled = false;

            if (data.success) {
                // Cerrar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalEnviarCorreoDetalle'));
                modal.hide();

                // Limpiar campos
                document.getElementById('correoDestinoDetalle').value = '';
                document.getElementById('guardarCorreoDetalle').checked = false;

                // Mostrar éxito
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Comprobante enviado a ' + correo,
                    timer: 3000
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Error al enviar el correo'
                });
            }
        })
        .catch(error => {
            btnEnviar.innerHTML = textOriginal;
            btnEnviar.disabled = false;
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error de conexión al enviar'
            });
            console.error('Error:', error);
        });
    }
    
    // Mostrar mensajes de éxito/error
    <?php if (isset($_GET['success'])): ?>
    Swal.fire({
        icon: 'success',
        title: '¡Éxito!',
        text: '<?php echo $_GET['success']; ?>',
        timer: 3000
    });
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: '<?php echo $_GET['error']; ?>'
    });
    <?php endif; ?>
    </script>
</body>
</html>