<?php
// public/includes/funciones.php

/**
 * Obtener banners activos
 */
function getBanners($tipo = 'principal', $limit = 5) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT * FROM banners 
               WHERE tipo = ? AND estado = 'activo' 
               AND (fecha_inicio IS NULL OR fecha_inicio <= CURDATE())
               AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
               ORDER BY orden ASC
               LIMIT ?";
        return $db->getAll($sql, [$tipo, $limit]);
    } catch (Exception $e) {
        error_log("Error obteniendo banners: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener productos destacados
 */
function getFeaturedProducts($limit = 8) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT p.*, 
                       (SELECT nombre_archivo FROM fotos_productos fp 
                        WHERE fp.id_producto_raiz = p.id_producto_raiz 
                        AND fp.es_principal = 1 LIMIT 1) as foto_principal,
                       d.nombre as departamento,
                       t.nombre as tipo_ropa
               FROM productos_raiz p
               INNER JOIN departamentos d ON p.id_departamento = d.id_departamento
               INNER JOIN tipos_ropa t ON p.id_tipo = t.id_tipo
               WHERE p.estado = 'activo'
               AND (p.etiqueta = 'nuevo' OR p.etiqueta = 'oferta')
               ORDER BY p.creado_en DESC
               LIMIT ?";
        return $db->getAll($sql, [$limit]);
    } catch (Exception $e) {
        error_log("Error obteniendo productos destacados: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener productos en oferta
 */
function getProductsOnSale($limit = 8) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT p.*, 
                       (SELECT nombre_archivo FROM fotos_productos fp 
                        WHERE fp.id_producto_raiz = p.id_producto_raiz 
                        AND fp.es_principal = 1 LIMIT 1) as foto_principal,
                       d.nombre as departamento,
                       t.nombre as tipo_ropa
               FROM productos_raiz p
               INNER JOIN departamentos d ON p.id_departamento = d.id_departamento
               INNER JOIN tipos_ropa t ON p.id_tipo = t.id_tipo
               WHERE p.estado = 'activo'
               AND p.precio_oferta IS NOT NULL
               AND (p.fecha_inicio_oferta IS NULL OR p.fecha_inicio_oferta <= CURDATE())
               AND (p.fecha_fin_oferta IS NULL OR p.fecha_fin_oferta >= CURDATE())
               ORDER BY p.precio_oferta / p.precio_venta ASC
               LIMIT ?";
        return $db->getAll($sql, [$limit]);
    } catch (Exception $e) {
        error_log("Error obteniendo productos en oferta: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener productos nuevos
 */
function getNewProducts($limit = 8) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT p.*, 
                       (SELECT nombre_archivo FROM fotos_productos fp 
                        WHERE fp.id_producto_raiz = p.id_producto_raiz 
                        AND fp.es_principal = 1 LIMIT 1) as foto_principal,
                       d.nombre as departamento,
                       t.nombre as tipo_ropa
               FROM productos_raiz p
               INNER JOIN departamentos d ON p.id_departamento = d.id_departamento
               INNER JOIN tipos_ropa t ON p.id_tipo = t.id_tipo
               WHERE p.estado = 'activo'
               AND p.etiqueta = 'nuevo'
               ORDER BY p.creado_en DESC
               LIMIT ?";
        return $db->getAll($sql, [$limit]);
    } catch (Exception $e) {
        error_log("Error obteniendo productos nuevos: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener productos Ajitos Kids
 */
function getKidsProducts($limit = 8) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT p.*, 
                       (SELECT nombre_archivo FROM fotos_productos fp 
                        WHERE fp.id_producto_raiz = p.id_producto_raiz 
                        AND fp.es_principal = 1 LIMIT 1) as foto_principal,
                       d.nombre as departamento,
                       t.nombre as tipo_ropa
               FROM productos_raiz p
               INNER JOIN departamentos d ON p.id_departamento = d.id_departamento
               INNER JOIN tipos_ropa t ON p.id_tipo = t.id_tipo
               WHERE p.estado = 'activo'
               AND p.es_kids = 1
               ORDER BY p.creado_en DESC
               LIMIT ?";
        return $db->getAll($sql, [$limit]);
    } catch (Exception $e) {
        error_log("Error obteniendo productos kids: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtener estadísticas rápidas
 */
function getQuickStats() {
    try {
        $db = Database::getInstance();
        
        // Ventas hoy
        $sql = "SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total 
               FROM maestro_facturas 
               WHERE DATE(fecha_factura) = CURDATE() 
               AND estado IN ('confirmada', 'enviada', 'entregada')";
        $todaySales = $db->getSingle($sql);
        
        // Productos bajos en stock
        $sql = "SELECT COUNT(*) as count 
               FROM variantes_productos 
               WHERE (stock_tienda + stock_bodega) <= stock_minimo";
        $lowStock = $db->getSingle($sql);
        
        // Clientes nuevos hoy
        $sql = "SELECT COUNT(*) as count 
               FROM personas 
               WHERE DATE(creado_en) = CURDATE()";
        $newCustomers = $db->getSingle($sql);
        
        // Órdenes pendientes
        $sql = "SELECT COUNT(*) as count 
               FROM maestro_facturas 
               WHERE estado = 'pendiente'";
        $pendingOrders = $db->getSingle($sql);
        
        return [
            'today_sales' => [
                'count' => $todaySales['count'] ?? 0,
                'total' => $todaySales['total'] ?? 0
            ],
            'low_stock' => $lowStock['count'] ?? 0,
            'new_customers' => $newCustomers['count'] ?? 0,
            'pending_orders' => $pendingOrders['count'] ?? 0
        ];
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas: " . $e->getMessage());
        return [];
    }
}

/**
 * Generar número de factura único
 */
function generateInvoiceNumber() {
    $year = date('Y');
    $month = date('m');
    
    try {
        $db = Database::getInstance();
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(numero_factura, '-', -1) AS UNSIGNED)) as ultimo_numero
               FROM maestro_facturas 
               WHERE numero_factura LIKE CONCAT('FACT-', ?, '-', ?, '-%')";
        $result = $db->getSingle($sql, [$year, $month]);
        
        $nextNumber = ($result['ultimo_numero'] ?? 0) + 1;
        
        return sprintf('FACT-%s-%s-%06d', $year, $month, $nextNumber);
        
    } catch (Exception $e) {
        error_log("Error generando número de factura: " . $e->getMessage());
        return 'FACT-' . date('Ymd-His') . '-' . rand(1000, 9999);
    }
}

/**
 * Generar número de orden único
 */
function generateOrderNumber() {
    return 'ORD-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Calcular puntos según monto
 */
function calculatePoints($amount) {
    $pointsPerQuetzal = getConfigValue('puntos_por_quetzal') ?? PUNTOS_POR_QUETZAL;
    return floor($amount / $pointsPerQuetzal);
}

/**
 * Calcular descuento por puntos
 */
function calculateDiscountFromPoints($points) {
    $quetzalesPerPoint = getConfigValue('quetzales_por_punto') ?? QUETZALES_POR_PUNTO;
    return floor($points / $quetzalesPerPoint);
}

/**
 * Enviar email de confirmación
 */
function sendConfirmationEmail($to, $subject, $template, $data) {
    // Implementación básica - en producción usar PHPMailer o similar
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">" . "\r\n";
    
    $message = renderEmailTemplate($template, $data);
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Renderizar plantilla de email
 */
function renderEmailTemplate($template, $data) {
    $templatePath = __DIR__ . "/../emails/{$template}.html";
    
    if (!file_exists($templatePath)) {
        return "Plantilla de email no encontrada: {$template}";
    }
    
    $content = file_get_contents($templatePath);
    
    foreach ($data as $key => $value) {
        $content = str_replace("{{{$key}}}", $value, $content);
    }
    
    return $content;
}

/**
 * Subir imagen de producto
 */
function uploadProductImage($file, $productId) {
    $uploadDir = UPLOAD_PATH . 'productos/';
    
    // Crear directorio si no existe
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Validar tipo de archivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Tipo de archivo no permitido. Solo se permiten imágenes JPEG, PNG, GIF y WebP.');
    }
    
    // Validar tamaño (máximo 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('La imagen es demasiado grande. El tamaño máximo es 5MB.');
    }
    
    // Generar nombre único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'prod_' . $productId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filePath = $uploadDir . $fileName;
    
    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Error al subir la imagen.');
    }
    
    // Crear miniatura si es necesario
    createThumbnail($filePath, $uploadDir . 'thumbs/' . $fileName, 300, 300);
    
    return $fileName;
}

/**
 * Crear miniatura de imagen
 */
function createThumbnail($source, $destination, $width, $height) {
    $info = getimagesize($source);
    
    if (!$info) {
        return false;
    }
    
    $sourceImage = null;
    
    switch ($info[2]) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            $sourceImage = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    $srcWidth = imagesx($sourceImage);
    $srcHeight = imagesy($sourceImage);
    
    // Calcular nuevo tamaño manteniendo proporción
    $ratio = min($width / $srcWidth, $height / $srcHeight);
    $newWidth = $srcWidth * $ratio;
    $newHeight = $srcHeight * $ratio;
    
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preservar transparencia para PNG y GIF
    if ($info[2] == IMAGETYPE_PNG || $info[2] == IMAGETYPE_GIF) {
        imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }
    
    imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);
    
    // Crear directorio para miniaturas si no existe
    $thumbDir = dirname($destination);
    if (!file_exists($thumbDir)) {
        mkdir($thumbDir, 0777, true);
    }
    
    // Guardar miniatura
    switch ($info[2]) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumbnail, $destination, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumbnail, $destination, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumbnail, $destination);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($thumbnail, $destination, 90);
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
    
    return true;
}

/**
 * Eliminar imagen de producto
 */
function deleteProductImage($fileName) {
    $uploadDir = UPLOAD_PATH . 'productos/';
    $filePath = $uploadDir . $fileName;
    $thumbPath = $uploadDir . 'thumbs/' . $fileName;
    
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    if (file_exists($thumbPath)) {
        unlink($thumbPath);
    }
    
    return true;
}

/**
 * Obtener URL de imagen de producto
 */
function getProductImageUrl($fileName, $thumbnail = false) {
    if (empty($fileName)) {
        return ASSETS_URL . '/img/products/default.jpg';
    }
    
    $path = $thumbnail ? 'productos/thumbs/' : 'productos/';
    return BASE_URL . '/uploads/' . $path . $fileName;
}

/**
 * Formatear precio con descuento
 */
function formatPrice($price, $discountPrice = null) {
    if ($discountPrice !== null && $discountPrice < $price) {
        return '<span class="price-old">' . formatCurrency($price) . '</span> ' .
               '<span class="price-new">' . formatCurrency($discountPrice) . '</span>';
    }
    
    return '<span class="price">' . formatCurrency($price) . '</span>';
}

/**
 * Obtener stock total de producto
 */
function getProductTotalStock($productId) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT SUM(stock_tienda + stock_bodega) as total_stock 
               FROM variantes_productos 
               WHERE id_producto_raiz = ?";
        $result = $db->getSingle($sql, [$productId]);
        
        return $result['total_stock'] ?? 0;
    } catch (Exception $e) {
        error_log("Error obteniendo stock: " . $e->getMessage());
        return 0;
    }
}

/**
 * Verificar si producto está en oferta
 */
function isProductOnSale($product) {
    if (empty($product['precio_oferta'])) {
        return false;
    }
    
    $now = date('Y-m-d');
    $start = $product['fecha_inicio_oferta'] ?? $now;
    $end = $product['fecha_fin_oferta'] ?? $now;
    
    return $now >= $start && $now <= $end;
}

/**
 * Obtener porcentaje de descuento
 */
function getDiscountPercentage($price, $discountPrice) {
    if ($discountPrice >= $price) {
        return 0;
    }
    
    $percentage = (($price - $discountPrice) / $price) * 100;
    return round($percentage);
}

/**
 * Generar breadcrumbs
 */
function generateBreadcrumbs($items) {
    $html = '<nav aria-label="breadcrumb">';
    $html .= '<ol class="breadcrumb">';
    
    $html .= '<li class="breadcrumb-item"><a href="/index.php"><i class="fas fa-home"></i></a></li>';
    
    foreach ($items as $key => $item) {
        if ($key == count($items) - 1) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($item['name']) . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . $item['url'] . '">' . htmlspecialchars($item['name']) . '</a></li>';
        }
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Validar DPI
 */
function validateDPI($dpi) {
    if (empty($dpi)) {
        return true; // DPI es opcional
    }
    
    return preg_match('/^\d{13}$/', $dpi);
}

/**
 * Obtener puntos de cliente
 */
function getCustomerPoints($dpi) {
    if (empty($dpi)) {
        return 0;
    }
    
    try {
        $db = Database::getInstance();
        $sql = "SELECT pc.puntos_disponibles 
               FROM puntos_clientes pc
               INNER JOIN personas p ON pc.id_persona = p.id_persona
               WHERE p.dpi = ?";
        $result = $db->getSingle($sql, [$dpi]);
        
        return $result['puntos_disponibles'] ?? 0;
    } catch (Exception $e) {
        error_log("Error obteniendo puntos: " . $e->getMessage());
        return 0;
    }
}
?>