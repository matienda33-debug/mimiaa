<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Verificar autenticación y permiso
if (!$auth->isLoggedIn()) {
    header('Location: /tiendaAA/index.php');
    exit();
}
$auth->requirePermission('ventas');

$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';

// Inicializar venta en sesión si no existe
if (!isset($_SESSION['venta_actual'])) {
    $_SESSION['venta_actual'] = [
        'items' => [],
        'subtotal' => 0,
        'descuento' => 0,
        'total' => 0,
        'puntos_usados' => 0,
        'cliente' => null,
        'tipo_venta' => 'tienda',
        'metodo_pago' => 'efectivo'
    ];
}

// Procesar acciones de venta
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_item':
                // Agregar item a la venta
                $variante_id = $_POST['variante_id'];
                $cantidad = (int)$_POST['cantidad'];
                
                // Verificar stock y obtener información
                $query = "SELECT pv.*, pr.nombre as producto_nombre, pr.codigo as producto_codigo,
                         pr.precio_venta as precio_base, pr.es_ajitos, pf.nombre_archivo as foto_principal
                         FROM productos_variantes pv
                         INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
                         LEFT JOIN productos_raiz_fotos pf ON pf.id_producto_raiz = pr.id_raiz AND pf.es_principal = 1
                         WHERE pv.id_variante = :id AND pv.activo = 1 AND pr.activo = 1";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $variante_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stock_disponible = $producto['stock_tienda'] + $producto['stock_bodega'];
                    
                    if ($cantidad <= $stock_disponible) {
                        $precio = $producto['precio_venta'] ?: $producto['precio_base'];
                        
                        // Verificar si ya existe en la venta
                        $item_existente = false;
                        foreach ($_SESSION['venta_actual']['items'] as &$item) {
                            if ($item['variante_id'] == $variante_id) {
                                $item['cantidad'] += $cantidad;
                                $item['subtotal'] = $item['cantidad'] * $precio;
                                $item_existente = true;
                                break;
                            }
                        }
                        
                        // Si no existe, agregar nuevo item
                        if (!$item_existente) {
                            $_SESSION['venta_actual']['items'][] = [
                                'variante_id' => $variante_id,
                                'producto_codigo' => $producto['producto_codigo'],
                                'producto_nombre' => $producto['producto_nombre'],
                                'foto' => $producto['foto_principal'] ?? null,
                                'color' => $producto['color'],
                                'talla' => $producto['talla'],
                                'precio' => $precio,
                                'descuento_pct' => 0,
                                'descuento_unitario' => 0,
                                'cantidad' => $cantidad,
                                'subtotal' => $cantidad * $precio,
                                'es_ajitos' => $producto['es_ajitos']
                            ];
                        }
                        
                        // Recalcular totales
                        recalcularVenta();
                        $success = "Producto agregado a la venta.";
                    } else {
                        $error = "Stock insuficiente. Disponible: $stock_disponible";
                    }
                } else {
                    $error = "Producto no encontrado.";
                }
                break;
                
            case 'update_item':
                // Actualizar cantidad de item
                $variante_id = $_POST['variante_id'];
                $cantidad = (int)$_POST['cantidad'];
                
                foreach ($_SESSION['venta_actual']['items'] as &$item) {
                    if ($item['variante_id'] == $variante_id) {
                        // Verificar stock
                        $query = "SELECT (stock_tienda + stock_bodega) as stock_total 
                                 FROM productos_variantes WHERE id_variante = :id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':id', $variante_id);
                        $stmt->execute();
                        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($cantidad <= $stock['stock_total']) {
                            $item['cantidad'] = $cantidad;
                            $precio_final = max(0, $item['precio'] - ($item['descuento_unitario'] ?? 0));
                            $item['subtotal'] = $item['cantidad'] * $precio_final;
                        } else {
                            $item['cantidad'] = $stock['stock_total'];
                            $precio_final = max(0, $item['precio'] - ($item['descuento_unitario'] ?? 0));
                            $item['subtotal'] = $item['cantidad'] * $precio_final;
                            $error = "Stock máximo: " . $stock['stock_total'];
                        }
                        break;
                    }
                }
                
                recalcularVenta();
                break;
                
            case 'remove_item':
                // Remover item de la venta
                $variante_id = $_POST['variante_id'];
                
                foreach ($_SESSION['venta_actual']['items'] as $key => $item) {
                    if ($item['variante_id'] == $variante_id) {
                        unset($_SESSION['venta_actual']['items'][$key]);
                        break;
                    }
                }
                
                // Reindexar array
                $_SESSION['venta_actual']['items'] = array_values($_SESSION['venta_actual']['items']);
                recalcularVenta();
                $success = "Producto removido de la venta.";
                break;

            case 'update_discount':
                // Actualizar descuento por producto (monto unitario)
                $variante_id = $_POST['variante_id'];
                $descuento_monto = (float)$_POST['descuento_monto'];
                $descuento_monto = max(0, $descuento_monto);

                foreach ($_SESSION['venta_actual']['items'] as &$item) {
                    if ($item['variante_id'] == $variante_id) {
                        $max_descuento = $item['precio'];
                        $descuento_unitario = min($descuento_monto, $max_descuento);
                        $pct = $item['precio'] > 0 ? round(($descuento_unitario / $item['precio']) * 100, 2) : 0;

                        $item['descuento_unitario'] = $descuento_unitario;
                        $item['descuento_pct'] = $pct;
                        $precio_final = max(0, $item['precio'] - $descuento_unitario);
                        $item['subtotal'] = $item['cantidad'] * $precio_final;
                        break;
                    }
                }

                if (!empty($pct) && $pct > 10) {
                    $warning = "Alerta: el descuento supera el 10% para este producto.";
                } else {
                    $success = "Descuento aplicado al producto.";
                }

                recalcularVenta();
                break;
                
            case 'clear_venta':
                // Limpiar venta actual
                $_SESSION['venta_actual'] = [
                    'items' => [],
                    'subtotal' => 0,
                    'descuento' => 0,
                    'total' => 0,
                    'puntos_usados' => 0,
                    'cliente' => null,
                    'tipo_venta' => 'tienda',
                    'metodo_pago' => 'efectivo'
                ];
                $success = "Venta limpiada.";
                break;
                
            case 'set_cliente':
                // Asignar cliente a la venta
                $dpi = sanitize($_POST['dpi']);
                
                if (strlen($dpi) == 13) {
                    $query = "SELECT * FROM clientes WHERE dpi = :dpi";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':dpi', $dpi);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                        $_SESSION['venta_actual']['cliente'] = $cliente;
                        $success = "Cliente asignado: " . $cliente['nombre'] . " " . $cliente['apellido'];
                    } else {
                        $error = "Cliente no encontrado. ¿Desea registrarlo?";
                    }
                } else {
                    $error = "DPI debe tener 13 dígitos.";
                }
                break;
                
            case 'remove_cliente':
                // Remover cliente de la venta
                $_SESSION['venta_actual']['cliente'] = null;
                $_SESSION['venta_actual']['puntos_usados'] = 0;
                recalcularVenta();
                $success = "Cliente removido.";
                break;
                
            case 'use_points':
                // Usar puntos del cliente
                if ($_SESSION['venta_actual']['cliente']) {
                    $puntos_usar = (int)$_POST['puntos'];
                    
                    if ($puntos_usar > 0 && $puntos_usar <= $_SESSION['venta_actual']['cliente']['puntos']) {
                        // Los puntos deben ser múltiplos de 30
                        if ($puntos_usar % 30 == 0) {
                            $descuento = valorEnPuntos($puntos_usar);
                            $_SESSION['venta_actual']['puntos_usados'] = $puntos_usar;
                            $_SESSION['venta_actual']['descuento'] = $descuento;
                            recalcularVenta();
                            $success = "Puntos aplicados: $puntos_usar puntos = " . formatMoney($descuento);
                        } else {
                            $error = "Los puntos deben ser múltiplos de 30.";
                        }
                    } else {
                        $error = "Puntos insuficientes o inválidos.";
                    }
                } else {
                    $error = "Primero debe asignar un cliente.";
                }
                break;
                
            case 'process_venta':
                // Procesar venta completa
                if (count($_SESSION['venta_actual']['items']) == 0) {
                    $error = "No hay productos en la venta.";
                    break;
                }
                
                try {
                    $db->beginTransaction();
                    
                    // Generar número de factura
                    $numero_factura = generarNumeroFactura($db);
                    
                    // Insertar cabecera de factura
                    $cliente_id = $_SESSION['venta_actual']['cliente'] ? $_SESSION['venta_actual']['cliente']['id_cliente'] : null;
                    $cliente_nombre = $_SESSION['venta_actual']['cliente'] ? 
                                     $_SESSION['venta_actual']['cliente']['nombre'] . ' ' . 
                                     $_SESSION['venta_actual']['cliente']['apellido'] : 
                                     'Cliente Ocasional';
                    
                    $query = "INSERT INTO factura_cabecera (
                        numero_factura, numero_orden, fecha, id_cliente, nombre_cliente,
                        direccion, telefono, tipo_venta, id_usuario, subtotal, descuento,
                        total, puntos_usados, puntos_ganados, id_estado
                    ) VALUES (
                        :numero_factura, :numero_orden, NOW(), :id_cliente, :nombre_cliente,
                        :direccion, :telefono, :tipo_venta, :id_usuario, :subtotal, :descuento,
                        :total, :puntos_usados, :puntos_ganados, 2
                    )";
                    
                    $stmt = $db->prepare($query);
                    
                    // Generar número de orden
                    $numero_orden = 'ORD' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->bindParam(':numero_factura', $numero_factura);
                    $stmt->bindParam(':numero_orden', $numero_orden);
                    $stmt->bindParam(':id_cliente', $cliente_id);
                    $stmt->bindParam(':nombre_cliente', $cliente_nombre);
                    
                    // Obtener datos del cliente si existe
                    $direccion = '';
                    $telefono = '';
                    if ($cliente_id) {
                        $direccion = $_SESSION['venta_actual']['cliente']['direccion'] ?? '';
                        $telefono = $_SESSION['venta_actual']['cliente']['telefono'] ?? '';
                    }
                    
                    $stmt->bindParam(':direccion', $direccion);
                    $stmt->bindParam(':telefono', $telefono);
                    $stmt->bindParam(':tipo_venta', $_SESSION['venta_actual']['tipo_venta']);
                    $stmt->bindParam(':id_usuario', $_SESSION['user_id']);
                    $stmt->bindParam(':subtotal', $_SESSION['venta_actual']['subtotal']);
                    $stmt->bindParam(':descuento', $_SESSION['venta_actual']['descuento']);
                    $stmt->bindParam(':total', $_SESSION['venta_actual']['total']);
                    $stmt->bindParam(':puntos_usados', $_SESSION['venta_actual']['puntos_usados']);
                    
                    // Calcular puntos ganados
                    $puntos_ganados = calcularPuntos($_SESSION['venta_actual']['total']);
                    $stmt->bindParam(':puntos_ganados', $puntos_ganados);
                    
                    $stmt->execute();
                    $factura_id = $db->lastInsertId();
                    
                    // Insertar detalles de factura y actualizar inventario
                    foreach ($_SESSION['venta_actual']['items'] as $item) {
                        // Insertar detalle
                        $detalle_query = "INSERT INTO factura_detalle (
                            id_factura, id_producto_variante, cantidad,
                            precio_unitario, descuento_unitario, subtotal
                        ) VALUES (
                            :id_factura, :id_producto_variante, :cantidad,
                            :precio_unitario, :descuento_unitario, :subtotal
                        )";
                        
                        $detalle_stmt = $db->prepare($detalle_query);
                        $detalle_stmt->bindParam(':id_factura', $factura_id);
                        $detalle_stmt->bindParam(':id_producto_variante', $item['variante_id']);
                        $detalle_stmt->bindParam(':cantidad', $item['cantidad']);
                        $detalle_stmt->bindParam(':precio_unitario', $item['precio']);
                        $detalle_descuento = $item['descuento_unitario'] ?? 0;
                        $detalle_stmt->bindValue(':descuento_unitario', $detalle_descuento);
                        $detalle_stmt->bindParam(':subtotal', $item['subtotal']);
                        $detalle_stmt->execute();
                        
                        // Actualizar inventario (primero de tienda, luego de bodega)
                        $stock_query = "SELECT stock_tienda, stock_bodega FROM productos_variantes 
                                       WHERE id_variante = :id";
                        $stock_stmt = $db->prepare($stock_query);
                        $stock_stmt->bindParam(':id', $item['variante_id']);
                        $stock_stmt->execute();
                        $stock = $stock_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $cantidad_restante = $item['cantidad'];
                        
                        // Restar de tienda
                        if ($stock['stock_tienda'] > 0) {
                            $restar_tienda = min($stock['stock_tienda'], $cantidad_restante);
                            
                            $update_query = "UPDATE productos_variantes 
                                           SET stock_tienda = stock_tienda - :cantidad 
                                           WHERE id_variante = :id";
                            $update_stmt = $db->prepare($update_query);
                            $update_stmt->bindParam(':cantidad', $restar_tienda);
                            $update_stmt->bindParam(':id', $item['variante_id']);
                            $update_stmt->execute();
                            
                            // Registrar movimiento
                            $movimiento_query = "INSERT INTO inventario_movimientos (
                                id_producto_variante, tipo_movimiento, cantidad,
                                ubicacion, motivo, id_usuario
                            ) VALUES (
                                :id_producto_variante, 'salida', :cantidad,
                                'tienda', 'Venta #$numero_factura', :id_usuario
                            )";
                            $movimiento_stmt = $db->prepare($movimiento_query);
                            $movimiento_stmt->bindParam(':id_producto_variante', $item['variante_id']);
                            $movimiento_stmt->bindParam(':cantidad', $restar_tienda);
                            $movimiento_stmt->bindParam(':id_usuario', $_SESSION['user_id']);
                            $movimiento_stmt->execute();
                            
                            $cantidad_restante -= $restar_tienda;
                        }
                        
                        // Restar de bodega si aún hay cantidad
                        if ($cantidad_restante > 0 && $stock['stock_bodega'] > 0) {
                            $restar_bodega = min($stock['stock_bodega'], $cantidad_restante);
                            
                            $update_query = "UPDATE productos_variantes 
                                           SET stock_bodega = stock_bodega - :cantidad 
                                           WHERE id_variante = :id";
                            $update_stmt = $db->prepare($update_query);
                            $update_stmt->bindParam(':cantidad', $restar_bodega);
                            $update_stmt->bindParam(':id', $item['variante_id']);
                            $update_stmt->execute();
                            
                            // Registrar movimiento
                            $movimiento_query = "INSERT INTO inventario_movimientos (
                                id_producto_variante, tipo_movimiento, cantidad,
                                ubicacion, motivo, id_usuario
                            ) VALUES (
                                :id_producto_variante, 'salida', :cantidad,
                                'bodega', 'Venta #$numero_factura', :id_usuario
                            )";
                            $movimiento_stmt = $db->prepare($movimiento_query);
                            $movimiento_stmt->bindParam(':id_producto_variante', $item['variante_id']);
                            $movimiento_stmt->bindParam(':cantidad', $restar_bodega);
                            $movimiento_stmt->bindParam(':id_usuario', $_SESSION['user_id']);
                            $movimiento_stmt->execute();
                        }
                    }
                    
                    // Actualizar puntos del cliente si aplica
                    if ($cliente_id) {
                        // Restar puntos usados
                        if ($_SESSION['venta_actual']['puntos_usados'] > 0) {
                            $update_puntos = "UPDATE clientes SET puntos = puntos - :puntos_usados 
                                            WHERE id_cliente = :id_cliente";
                            $update_stmt = $db->prepare($update_puntos);
                            $update_stmt->bindParam(':puntos_usados', $_SESSION['venta_actual']['puntos_usados']);
                            $update_stmt->bindParam(':id_cliente', $cliente_id);
                            $update_stmt->execute();
                        }
                        
                        // Sumar puntos ganados
                        if ($puntos_ganados > 0) {
                            $update_puntos = "UPDATE clientes SET puntos = puntos + :puntos_ganados 
                                            WHERE id_cliente = :id_cliente";
                            $update_stmt = $db->prepare($update_puntos);
                            $update_stmt->bindParam(':puntos_ganados', $puntos_ganados);
                            $update_stmt->bindParam(':id_cliente', $cliente_id);
                            $update_stmt->execute();
                        }
                    }
                    
                    $db->commit();
                    
                    // Guardar factura generada para impresión
                    $_SESSION['factura_generada'] = [
                        'id_factura' => $factura_id,
                        'numero_factura' => $numero_factura,
                        'total' => $_SESSION['venta_actual']['total']
                    ];
                    
                    // Limpiar venta actual
                    $_SESSION['venta_actual'] = [
                        'items' => [],
                        'subtotal' => 0,
                        'descuento' => 0,
                        'total' => 0,
                        'puntos_usados' => 0,
                        'cliente' => null,
                        'tipo_venta' => 'tienda',
                        'metodo_pago' => 'efectivo'
                    ];
                    
                    // Redirigir a comprobante
                    header('Location: comprobante.php?id=' . $factura_id);
                    exit();
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Error al procesar la venta: " . $e->getMessage();
                }
                break;
        }
    }
}

// Función para recalcular totales de venta
function recalcularVenta() {
    $subtotal = 0;
    
    foreach ($_SESSION['venta_actual']['items'] as $item) {
        $subtotal += $item['subtotal'];
    }
    
    $_SESSION['venta_actual']['subtotal'] = $subtotal;
    $_SESSION['venta_actual']['total'] = $subtotal - $_SESSION['venta_actual']['descuento'];
}

// Evitar duplicados de variantes en la venta
function normalizarItemsVenta() {
    if (empty($_SESSION['venta_actual']['items'])) {
        return;
    }

    $items = [];
    foreach ($_SESSION['venta_actual']['items'] as $item) {
        $id = $item['variante_id'];
        if (!isset($items[$id])) {
            $items[$id] = $item;
            continue;
        }

        $items[$id]['cantidad'] += $item['cantidad'];
        $descuento_unitario = $items[$id]['descuento_unitario'] ?? 0;
        $precio_final = max(0, $items[$id]['precio'] - $descuento_unitario);
        $items[$id]['subtotal'] = $items[$id]['cantidad'] * $precio_final;
    }

    $_SESSION['venta_actual']['items'] = array_values($items);
}

// Función para generar número de factura
function generarNumeroFactura($db) {
    $query = "SELECT MAX(CAST(SUBSTRING(numero_factura, 3) AS UNSIGNED)) as last_num 
              FROM factura_cabecera 
              WHERE numero_factura LIKE 'F-%'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $last_num = $result['last_num'] ?: 0;
    $new_num = $last_num + 1;
    
    return 'F-' . str_pad($new_num, 8, '0', STR_PAD_LEFT);
}

// Buscar productos para agregar a la venta
$productos_busqueda = [];
$variantes_por_producto = [];
if (isset($_GET['buscar_producto'])) {
    $busqueda = sanitize($_GET['buscar_producto']);
    
    $query = "SELECT pr.id_raiz, pr.codigo, pr.nombre as producto_nombre,
              pr.precio_venta as precio_base, pr.es_ajitos,
              COALESCE(SUM(pv.stock_tienda + pv.stock_bodega), 0) as stock_total,
              pf.nombre_archivo as foto_principal
              FROM productos_raiz pr
              INNER JOIN productos_variantes pv ON pv.id_producto_raiz = pr.id_raiz AND pv.activo = 1
              LEFT JOIN productos_raiz_fotos pf ON pf.id_producto_raiz = pr.id_raiz AND pf.es_principal = 1
              WHERE (pr.nombre LIKE :busqueda OR pr.codigo LIKE :busqueda OR 
                    pv.color LIKE :busqueda OR pv.sku LIKE :busqueda)
              AND pr.activo = 1
              AND (pv.stock_tienda + pv.stock_bodega) > 0
              GROUP BY pr.id_raiz, pr.codigo, pr.nombre, pr.precio_venta, pr.es_ajitos, pf.nombre_archivo
              LIMIT 20";
    
    $stmt = $db->prepare($query);
    $busqueda_term = "%$busqueda%";
    $stmt->bindParam(':busqueda', $busqueda_term);
    $stmt->execute();
    $productos_busqueda = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($productos_busqueda)) {
        $ids = array_column($productos_busqueda, 'id_raiz');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $variantes_query = "SELECT pv.id_variante, pv.id_producto_raiz, pv.color, pv.talla,
                            pv.precio_venta, (pv.stock_tienda + pv.stock_bodega) as stock_total
                            FROM productos_variantes pv
                            WHERE pv.id_producto_raiz IN ($placeholders)
                            AND pv.activo = 1
                            AND (pv.stock_tienda + pv.stock_bodega) > 0
                            ORDER BY pv.color, pv.talla";
        $variantes_stmt = $db->prepare($variantes_query);
        $variantes_stmt->execute($ids);
        $variantes = $variantes_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($variantes as $variante) {
            $producto_id = $variante['id_producto_raiz'];
            if (!isset($variantes_por_producto[$producto_id])) {
                $variantes_por_producto[$producto_id] = [];
            }
            $variantes_por_producto[$producto_id][] = $variante;
        }
    }
}

// Consolidar items por variante para evitar duplicados en la vista
normalizarItemsVenta();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .venta-container {
            min-height: calc(100vh - 200px);
        }
        .productos-container {
            max-height: 500px;
            overflow-y: auto;
        }
        .venta-items {
            max-height: 400px;
            overflow-y: auto;
        }
        .venta-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .cliente-info {
            background: #e8f4fd;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
        }
        .badge-ajitos {
            background-color: #ff6b6b;
            color: white;
        }
        .btn-venta {
            font-size: 1.1rem;
            padding: 10px 20px;
        }
        .search-results {
            position: absolute;
            z-index: 1000;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        .search-item {
            padding: 8px 12px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
        }
        .search-item:hover {
            background: #f8f9fa;
        }
        .producto-thumb {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .item-thumb {
            width: 38px;
            height: 38px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            margin-right: 8px;
        }
        .modal-producto-img {
            width: 100%;
            max-height: 220px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .venta-compact .card-header {
            padding: 0.5rem 0.75rem;
        }
        .venta-compact .card-body {
            padding: 0.75rem;
        }
        .venta-compact .table > :not(caption) > * > * {
            padding: 0.35rem 0.5rem;
        }
        .venta-compact .venta-summary {
            padding: 12px;
        }
        .venta-compact .venta-items {
            max-height: 320px;
        }
        .venta-compact .productos-container {
            max-height: 360px;
        }
        .venta-compact .btn-venta {
            font-size: 1rem;
            padding: 8px 12px;
        }
        .venta-compact .item-thumb {
            width: 32px;
            height: 32px;
        }
        .venta-compact .producto-thumb {
            width: 40px;
            height: 40px;
        }
        .venta-compact .alert {
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.75rem;
        }
        .venta-compact .h2 {
            font-size: 1.4rem;
        }
    </style>
</head>
<body>
    <?php if (!$embedded): ?>
    <?php include '../../includes/header.php'; ?>
    <?php endif; ?>
    
    <div class="container-fluid mt-4 venta-compact">
        <div class="row">
            <main class="col-12 px-2">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-cash-register me-2"></i>
                        Punto de Venta
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                    onclick="window.print()">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    data-bs-toggle="modal" data-bs-target="#clearModal">
                                <i class="fas fa-trash"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($warning)): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <?php echo $warning; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row venta-container">
                    <!-- Columna izquierda: Búsqueda y productos -->
                    <div class="col-md-8">
                        <!-- Búsqueda de productos -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-search me-2"></i>
                                    Buscar Productos
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="" class="mb-3">
                                    <div class="input-group">
                                        <input type="text" class="form-control" 
                                               name="buscar_producto" 
                                               placeholder="Buscar por nombre, código, color o SKU..." 
                                               value="<?php echo isset($_GET['buscar_producto']) ? $_GET['buscar_producto'] : ''; ?>"
                                               id="searchInput"
                                               autocomplete="off">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                                
                                <!-- Resultados de búsqueda -->
                                <?php if (!empty($productos_busqueda)): ?>
                                <div class="productos-container">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Imagen</th>
                                                    <th>Código</th>
                                                    <th>Producto</th>
                                                    <th>Precio</th>
                                                    <th>Stock</th>
                                                    <th>Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($productos_busqueda as $producto): 
                                                    $imagen = $producto['foto_principal']
                                                        ? BASE_URL . 'uploads/productos/' . $producto['foto_principal']
                                                        : BASE_URL . 'assets/img/no-image.png';
                                                ?>
                                                <tr>
                                                    <td>
                                                        <img src="<?php echo $imagen; ?>" class="producto-thumb" alt="<?php echo htmlspecialchars($producto['producto_nombre']); ?>">
                                                    </td>
                                                    <td><small><?php echo $producto['codigo']; ?></small></td>
                                                    <td>
                                                        <?php echo $producto['producto_nombre']; ?>
                                                        <?php if ($producto['es_ajitos']): ?>
                                                            <span class="badge badge-ajitos">Ajitos</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo formatMoney($producto['precio_base']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $producto['stock_total'] > 5 ? 'success' : ($producto['stock_total'] > 0 ? 'warning' : 'danger'); ?>">
                                                            <?php echo $producto['stock_total']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary btn-seleccionar-variantes"
                                                                data-bs-toggle="modal" data-bs-target="#seleccionarVarianteModal"
                                                                data-product-id="<?php echo $producto['id_raiz']; ?>"
                                                                data-product-name="<?php echo htmlspecialchars($producto['producto_nombre']); ?>"
                                                                data-product-code="<?php echo htmlspecialchars($producto['codigo']); ?>"
                                                                data-product-image="<?php echo $imagen; ?>"
                                                                data-product-price="<?php echo $producto['precio_base']; ?>">
                                                            <i class="fas fa-ruler-combined me-1"></i>
                                                            Elegir talla y color
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php elseif (isset($_GET['buscar_producto'])): ?>
                                    <div class="alert alert-info">
                                        No se encontraron productos con esa búsqueda.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Gestión de cliente -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user me-2"></i>
                                    Información del Cliente
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($_SESSION['venta_actual']['cliente']): 
                                    $cliente = $_SESSION['venta_actual']['cliente'];
                                ?>
                                <div class="cliente-info">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6><?php echo $cliente['nombre'] . ' ' . $cliente['apellido']; ?></h6>
                                            <p class="mb-1">
                                                <strong>DPI:</strong> <?php echo $cliente['dpi']; ?><br>
                                                <strong>Puntos:</strong> 
                                                <span class="badge bg-warning text-dark">
                                                    <?php echo $cliente['puntos']; ?> pts
                                                </span>
                                                (<?php echo formatMoney(valorEnPuntos($cliente['puntos'])); ?>)
                                            </p>
                                            <?php if ($cliente['telefono']): ?>
                                                <p class="mb-1"><strong>Teléfono:</strong> <?php echo $cliente['telefono']; ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="action" value="remove_cliente">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-times"></i> Remover
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <!-- Uso de puntos -->
                                    <?php if ($cliente['puntos'] >= 30): ?>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-8">
                                            <label class="form-label">Usar puntos</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control" id="puntosInput" 
                                                       value="0" min="0" max="<?php echo $cliente['puntos']; ?>" 
                                                       step="30">
                                                <button class="btn btn-outline-warning" type="button" 
                                                        onclick="usarPuntos()">
                                                    <i class="fas fa-coins"></i> Aplicar
                                                </button>
                                            </div>
                                            <small class="text-muted">Múltiplos de 30 (30 pts = Q1)</small>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Descuento</label>
                                            <div class="text-center">
                                                <h5 class="text-success"><?php echo formatMoney($_SESSION['venta_actual']['descuento']); ?></h5>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <form method="POST" action="" class="row g-3">
                                    <input type="hidden" name="action" value="set_cliente">
                                    <div class="col-md-8">
                                        <label for="dpi" class="form-label">Buscar cliente por DPI</label>
                                        <input type="text" class="form-control" id="dpi" name="dpi" 
                                               placeholder="Ingrese 13 dígitos del DPI" maxlength="13">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-search me-1"></i> Buscar
                                        </button>
                                    </div>
                                </form>
                                <div class="mt-3 text-center">
                                    <small class="text-muted">O</small><br>
                                    <a href="../mod1/personas.php?action=create" class="btn btn-sm btn-outline-secondary" target="_blank">
                                        <i class="fas fa-user-plus me-1"></i> Registrar Nuevo Cliente
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Columna derecha: Resumen de venta -->
                    <div class="col-md-4">
                        <div class="venta-summary">
                            <!-- Configuración de venta -->
                            <div class="mb-4">
                                <h5 class="mb-3">Configuración de Venta</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Tipo de Venta</label>
                                        <select class="form-select" id="tipoVenta">
                                            <option value="tienda" <?php echo $_SESSION['venta_actual']['tipo_venta'] == 'tienda' ? 'selected' : ''; ?>>En Tienda</option>
                                            <option value="online" <?php echo $_SESSION['venta_actual']['tipo_venta'] == 'online' ? 'selected' : ''; ?>>Online</option>
                                            <option value="recoger" <?php echo $_SESSION['venta_actual']['tipo_venta'] == 'recoger' ? 'selected' : ''; ?>>Recoger</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Método de Pago</label>
                                        <select class="form-select" id="metodoPago">
                                            <option value="efectivo" <?php echo $_SESSION['venta_actual']['metodo_pago'] == 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                                            <option value="tarjeta" <?php echo $_SESSION['venta_actual']['metodo_pago'] == 'tarjeta' ? 'selected' : ''; ?>>Tarjeta</option>
                                            <option value="transferencia" <?php echo $_SESSION['venta_actual']['metodo_pago'] == 'transferencia' ? 'selected' : ''; ?>>Transferencia</option>
                                            <option value="mixto" <?php echo $_SESSION['venta_actual']['metodo_pago'] == 'mixto' ? 'selected' : ''; ?>>Mixto</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Resumen de productos -->
                            <div class="mb-4">
                                <h5 class="mb-3">Productos en Venta</h5>
                                <div class="venta-items">
                                    <?php if (count($_SESSION['venta_actual']['items']) == 0): ?>
                                        <div class="alert alert-info">
                                            No hay productos en la venta.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($_SESSION['venta_actual']['items'] as $item): ?>
                                        <div class="card mb-2">
                                            <div class="card-body py-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center">
                                                        <?php
                                                            $item_imagen = !empty($item['foto'])
                                                                ? BASE_URL . 'uploads/productos/' . $item['foto']
                                                                : BASE_URL . 'assets/img/no-image.png';
                                                        ?>
                                                        <img src="<?php echo $item_imagen; ?>" class="item-thumb" alt="<?php echo htmlspecialchars($item['producto_nombre']); ?>">
                                                        <div>
                                                            <h6 class="mb-0" style="font-size: 0.9rem;">
                                                                <?php echo $item['producto_nombre']; ?>
                                                            </h6>
                                                            <small class="text-muted">
                                                                <?php echo $item['color']; ?> / <?php echo $item['talla']; ?>
                                                                <?php if ($item['es_ajitos']): ?>
                                                                    <span class="badge badge-ajitos">Ajitos</span>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="input-group input-group-sm" style="width: 100px;">
                                                            <input type="number" class="form-control" 
                                                                   value="<?php echo $item['cantidad']; ?>" 
                                                                   min="1" id="cantidad_<?php echo $item['variante_id']; ?>">
                                                            <button type="button" class="btn btn-outline-primary btn-sm" 
                                                                    onclick="actualizarCantidad(<?php echo $item['variante_id']; ?>)">
                                                                <i class="fas fa-sync-alt"></i>
                                                            </button>
                                                        </div>
                                                            <div class="input-group input-group-sm mt-2" style="width: 160px;">
                                                                <span class="input-group-text">Q</span>
                                                                <input type="number" class="form-control" 
                                                                       value="<?php echo $item['descuento_unitario'] ?? 0; ?>" 
                                                                       min="0" step="0.01" 
                                                                       id="descuento_<?php echo $item['variante_id']; ?>"
                                                                       title="Descuento unitario">
                                                                <button type="button" class="btn btn-outline-warning btn-sm" 
                                                                        onclick="actualizarDescuento(<?php echo $item['variante_id']; ?>)">
                                                                    <i class="fas fa-tag"></i>
                                                                </button>
                                                            </div>
                                                        <div class="mt-1">
                                                            <small><?php echo formatMoney($item['precio']); ?> c/u</small><br>
                                                                <?php if (!empty($item['descuento_unitario'])): ?>
                                                                    <small class="text-success">-<?php echo formatMoney($item['descuento_unitario']); ?> (<?php echo $item['descuento_pct']; ?>%)</small><br>
                                                                <?php endif; ?>
                                                            <strong><?php echo formatMoney($item['subtotal']); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-end mt-1">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="removerItem(<?php echo $item['variante_id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Totales -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <strong><?php echo formatMoney($_SESSION['venta_actual']['subtotal']); ?></strong>
                                </div>
                                
                                <?php if ($_SESSION['venta_actual']['descuento'] > 0): ?>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span>Descuento (puntos):</span>
                                    <strong>-<?php echo formatMoney($_SESSION['venta_actual']['descuento']); ?></strong>
                                </div>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between mb-3">
                                    <h5>Total:</h5>
                                    <h3 class="text-primary"><?php echo formatMoney($_SESSION['venta_actual']['total']); ?></h3>
                                </div>
                                
                                <!-- Puntos que se ganarán -->
                                <?php 
                                    $puntos_ganar = calcularPuntos($_SESSION['venta_actual']['total']);
                                    if ($puntos_ganar > 0):
                                ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-coins me-2"></i>
                                    El cliente ganará <strong><?php echo $puntos_ganar; ?> puntos</strong> con esta compra
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Botones de acción -->
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-lg btn-venta" 
                                        data-bs-toggle="modal" data-bs-target="#confirmVentaModal"
                                        <?php echo count($_SESSION['venta_actual']['items']) == 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-check-circle me-2"></i>
                                    Confirmar Venta
                                </button>
                                
                                <button class="btn btn-primary btn-venta" 
                                        onclick="generarFactura()" 
                                        <?php echo count($_SESSION['venta_actual']['items']) == 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-file-invoice me-2"></i>
                                    Generar Factura
                                </button>
                                
                                <a href="historial_ventas.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-history me-2"></i>
                                    Historial de Ventas
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para seleccionar talla y color -->
    <div class="modal fade" id="seleccionarVarianteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="" onsubmit="return validarSeleccionVariante()">
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="variante_id" id="modalVarianteId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTituloProducto">Seleccionar talla y color</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <img id="modalImagenProducto" src="" alt="Producto" class="modal-producto-img">
                                <div class="mt-3">
                                    <div class="text-muted" id="modalCodigoProducto"></div>
                                    <div class="h5 text-primary" id="modalPrecioProducto"></div>
                                    <div class="small text-muted">Stock disponible: <span id="modalStock">0</span></div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="mb-3">
                                    <label class="form-label">Color</label>
                                    <select class="form-select" id="modalColor"></select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Talla</label>
                                    <select class="form-select" id="modalTalla"></select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Cantidad</label>
                                    <input type="number" class="form-control" name="cantidad" id="modalCantidad" value="1" min="1">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Agregar a la venta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de confirmacion de venta -->
    <div class="modal fade" id="confirmVentaModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Venta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (count($_SESSION['venta_actual']['items']) == 0): ?>
                        <div class="alert alert-info">No hay productos en la venta.</div>
                    <?php else: ?>
                        <?php foreach ($_SESSION['venta_actual']['items'] as $item): ?>
                            <?php
                                $item_imagen = !empty($item['foto'])
                                    ? BASE_URL . 'uploads/productos/' . $item['foto']
                                    : BASE_URL . 'assets/img/no-image.png';
                            ?>
                            <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo $item_imagen; ?>" class="item-thumb" alt="<?php echo htmlspecialchars($item['producto_nombre']); ?>">
                                    <div>
                                        <div class="fw-semibold"><?php echo $item['producto_nombre']; ?></div>
                                        <small class="text-muted"><?php echo $item['color']; ?> / <?php echo $item['talla']; ?></small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div><?php echo $item['cantidad']; ?> x <?php echo formatMoney($item['precio']); ?></div>
                                    <?php if (!empty($item['descuento_unitario'])): ?>
                                        <div class="text-success">-<?php echo formatMoney($item['descuento_unitario']); ?> (<?php echo $item['descuento_pct']; ?>%)</div>
                                    <?php endif; ?>
                                    <div class="fw-bold"><?php echo formatMoney($item['subtotal']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between">
                                <span>Subtotal</span>
                                <strong><?php echo formatMoney($_SESSION['venta_actual']['subtotal']); ?></strong>
                            </div>
                            <?php if ($_SESSION['venta_actual']['descuento'] > 0): ?>
                                <div class="d-flex justify-content-between text-success">
                                    <span>Descuento</span>
                                    <strong>-<?php echo formatMoney($_SESSION['venta_actual']['descuento']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span class="h5 mb-0">Total</span>
                                <span class="h5 mb-0 text-primary"><?php echo formatMoney($_SESSION['venta_actual']['total']); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="confirmarVenta()" <?php echo count($_SESSION['venta_actual']['items']) == 0 ? 'disabled' : ''; ?>>
                        Confirmar venta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para limpiar venta -->
    <div class="modal fade" id="clearModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Limpiar Venta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ¿Está seguro de limpiar toda la venta actual? Esta acción no se puede deshacer.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="clear_venta">
                        <button type="submit" class="btn btn-danger">Limpiar Venta</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Formularios ocultos -->
    <form id="updateItemForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_item">
        <input type="hidden" name="variante_id" id="update_variante_id">
        <input type="hidden" name="cantidad" id="update_cantidad">
    </form>
    
    <form id="removeItemForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="remove_item">
        <input type="hidden" name="variante_id" id="remove_variante_id">
    </form>

    <form id="updateDiscountForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_discount">
        <input type="hidden" name="variante_id" id="discount_variante_id">
        <input type="hidden" name="descuento_monto" id="discount_monto">
    </form>
    
    <form id="usePointsForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="use_points">
        <input type="hidden" name="puntos" id="use_points">
    </form>
    
    <form id="processVentaForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="process_venta">
        <input type="hidden" name="tipo_venta" id="form_tipo_venta">
        <input type="hidden" name="metodo_pago" id="form_metodo_pago">
    </form>

    <script>
        // Actualizar configuración de venta en tiempo real
        document.getElementById('tipoVenta').addEventListener('change', function() {
            <?php $_SESSION['venta_actual']['tipo_venta'] = "' + this.value + '"; ?>
        });
        
        document.getElementById('metodoPago').addEventListener('change', function() {
            <?php $_SESSION['venta_actual']['metodo_pago'] = "' + this.value + '"; ?>
        });

        const variantesPorProducto = <?php echo json_encode($variantes_por_producto); ?>;
        let variantesActivas = [];
        let precioBaseActivo = 0;

        const modalColor = document.getElementById('modalColor');
        const modalTalla = document.getElementById('modalTalla');
        const modalVarianteId = document.getElementById('modalVarianteId');
        const modalCantidad = document.getElementById('modalCantidad');
        const modalStock = document.getElementById('modalStock');
        const modalPrecio = document.getElementById('modalPrecioProducto');

        function formatQ(value) {
            const numero = Number(value || 0);
            return 'Q' + numero.toFixed(2);
        }

        function cargarColores() {
            const colores = [...new Set(variantesActivas.map(v => v.color))];
            modalColor.innerHTML = '';
            colores.forEach(color => {
                const option = document.createElement('option');
                option.value = color;
                option.textContent = color;
                modalColor.appendChild(option);
            });
            modalColor.disabled = colores.length === 0;
            cargarTallas();
        }

        function cargarTallas() {
            const colorSeleccionado = modalColor.value;
            const tallas = variantesActivas
                .filter(v => v.color === colorSeleccionado)
                .map(v => v.talla);
            const tallasUnicas = [...new Set(tallas)];
            modalTalla.innerHTML = '';
            tallasUnicas.forEach(talla => {
                const option = document.createElement('option');
                option.value = talla;
                option.textContent = talla;
                modalTalla.appendChild(option);
            });
            modalTalla.disabled = tallasUnicas.length === 0;
            actualizarSeleccionVariante();
        }

        function actualizarSeleccionVariante() {
            const colorSeleccionado = modalColor.value;
            const tallaSeleccionada = modalTalla.value;
            const variante = variantesActivas.find(v => v.color === colorSeleccionado && v.talla === tallaSeleccionada);

            if (variante) {
                modalVarianteId.value = variante.id_variante;
                modalStock.textContent = variante.stock_total;
                modalCantidad.max = variante.stock_total;
                modalCantidad.value = 1;

                const precioVariante = parseFloat(variante.precio_venta || 0);
                const precioFinal = precioVariante > 0 ? precioVariante : precioBaseActivo;
                modalPrecio.textContent = formatQ(precioFinal);
            } else {
                modalVarianteId.value = '';
                modalStock.textContent = '0';
                modalCantidad.max = 0;
                modalPrecio.textContent = formatQ(precioBaseActivo);
            }
        }

        function validarSeleccionVariante() {
            if (!modalVarianteId.value) {
                alert('Seleccione color y talla antes de agregar.');
                return false;
            }
            if (parseInt(modalCantidad.value, 10) <= 0) {
                alert('Cantidad invalida.');
                return false;
            }
            return true;
        }

        document.querySelectorAll('.btn-seleccionar-variantes').forEach(button => {
            button.addEventListener('click', () => {
                const productoId = button.getAttribute('data-product-id');
                const productoNombre = button.getAttribute('data-product-name');
                const productoCodigo = button.getAttribute('data-product-code');
                const productoImagen = button.getAttribute('data-product-image');
                const productoPrecio = parseFloat(button.getAttribute('data-product-price') || '0');

                precioBaseActivo = productoPrecio;
                variantesActivas = variantesPorProducto[productoId] || [];

                document.getElementById('modalTituloProducto').textContent = productoNombre;
                document.getElementById('modalImagenProducto').src = productoImagen;
                document.getElementById('modalCodigoProducto').textContent = productoCodigo;
                modalPrecio.textContent = formatQ(productoPrecio);
                modalCantidad.value = 1;
                modalVarianteId.value = '';

                cargarColores();
            });
        });

        modalColor.addEventListener('change', cargarTallas);
        modalTalla.addEventListener('change', actualizarSeleccionVariante);
        
        function actualizarCantidad(varianteId) {
            const cantidad = document.getElementById('cantidad_' + varianteId).value;
            document.getElementById('update_variante_id').value = varianteId;
            document.getElementById('update_cantidad').value = cantidad;
            document.getElementById('updateItemForm').submit();
        }
        
        function removerItem(varianteId) {
            if (confirm('¿Remover este producto de la venta?')) {
                document.getElementById('remove_variante_id').value = varianteId;
                document.getElementById('removeItemForm').submit();
            }
        }

        function actualizarDescuento(varianteId) {
            const descuento = document.getElementById('descuento_' + varianteId).value;
            document.getElementById('discount_variante_id').value = varianteId;
            document.getElementById('discount_monto').value = descuento;
            document.getElementById('updateDiscountForm').submit();
        }
        
        function usarPuntos() {
            const puntos = document.getElementById('puntosInput').value;
            if (puntos > 0 && puntos % 30 === 0) {
                document.getElementById('use_points').value = puntos;
                document.getElementById('usePointsForm').submit();
            } else {
                alert('Los puntos deben ser múltiplos de 30');
            }
        }
        
        function confirmarVenta() {
            document.getElementById('form_tipo_venta').value = document.getElementById('tipoVenta').value;
            document.getElementById('form_metodo_pago').value = document.getElementById('metodoPago').value;
            document.getElementById('processVentaForm').submit();
        }
        
        function generarFactura() {
            alert('Esta función generará una factura electrónica. Próximamente...');
        }
        
        // Búsqueda en tiempo real
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = this.value;
                if (query.length >= 2) {
                    // Implementar búsqueda AJAX aquí
                    console.log('Buscando:', query);
                }
            }, 500);
        });
        
        // Actualizar automáticamente cada 30 segundos para mantener la sesión activa
        setInterval(() => {
            // Actualizar totales si hay cambios
            fetch('api/update_venta.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=ping'
            });
        }, 30000);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>