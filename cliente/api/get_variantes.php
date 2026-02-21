<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['producto_id'])) {
    $producto_id = $_POST['producto_id'];
    
    $query = "SELECT pv.*, (pv.stock_tienda + pv.stock_bodega) as stock_total,
              pr.precio_venta as precio_base
              FROM productos_variantes pv
              INNER JOIN productos_raiz pr ON pv.id_producto_raiz = pr.id_raiz
              WHERE pv.id_producto_raiz = :id AND pv.activo = 1 AND pr.activo = 1
              ORDER BY pv.color, pv.talla";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $producto_id);
    $stmt->execute();
    
    $variantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($variantes) > 0) {
        echo '<div class="row">';
        foreach ($variantes as $variante) {
            $precio_variante = isset($variante['precio_venta']) ? (float)$variante['precio_venta'] : 0;
            $precio_base = isset($variante['precio_base']) ? (float)$variante['precio_base'] : 0;
            $precio = $precio_variante > 0 ? $precio_variante : $precio_base;
            $stock_class = $variante['stock_total'] > 0 ? ($variante['stock_total'] < 5 ? 'text-warning' : 'text-success') : 'text-danger';
            
            echo '<div class="col-md-6 mb-3">';
            echo '<div class="card">';
            echo '<div class="card-body">';
            echo '<h6>' . $variante['color'] . ' - ' . $variante['talla'] . '</h6>';
            echo '<p class="mb-1"><strong>Precio:</strong> ' . formatMoney($precio) . '</p>';
            echo '<p class="mb-2 ' . $stock_class . '">';
            echo '<strong>Stock disponible:</strong> ' . $variante['stock_total'];
            echo '</p>';
            
            if ($variante['stock_total'] > 0) {
                echo '<div class="input-group">';
                echo '<input type="number" class="form-control" id="cantidad_' . $variante['id_variante'] . '" 
                       value="1" min="1" max="' . $variante['stock_total'] . '">';
                echo '<button class="btn btn-primary" type="button" 
                       onclick="addVarianteToCart(' . $variante['id_variante'] . ', 
                       document.getElementById(\'cantidad_' . $variante['id_variante'] . '\').value)">';
                echo '<i class="fas fa-cart-plus"></i> Agregar';
                echo '</button>';
                echo '</div>';
            } else {
                echo '<button class="btn btn-secondary w-100" disabled>Sin stock</button>';
            }
            
            echo '</div></div></div>';
        }
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning">No hay variantes disponibles para este producto.</div>';
    }
} else {
    echo '<div class="alert alert-danger">Error al cargar las variantes.</div>';
}
?>