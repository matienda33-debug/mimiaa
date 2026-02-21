<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function formatMoney($amount) {
    return 'Q' . number_format($amount, 2);
}

function getCurrentDate() {
    return date('Y-m-d H:i:s');
}

function calcularPuntos($monto) {
    return floor($monto / PUNTOS_POR_COMPRA);
}

function valorEnPuntos($puntos) {
    return floor($puntos / VALOR_PUNTO);
}

function uploadImage($file, $directory) {
    $target_dir = UPLOAD_DIR . $directory . '/';
    
    // Crear directorio si no existe
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $fileExtension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $target_file = $target_dir . $newFileName;
    
    // Verificar si es una imagen
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return ['success' => false, 'message' => 'El archivo no es una imagen.'];
    }
    
    // Verificar tamaño (máximo 5MB)
    if ($file["size"] > 5000000) {
        return ['success' => false, 'message' => 'La imagen es demasiado grande.'];
    }
    
    // Permitir ciertos formatos
    $allowedExtensions = ["jpg", "jpeg", "png", "gif"];
    if (!in_array($fileExtension, $allowedExtensions)) {
        return ['success' => false, 'message' => 'Solo se permiten archivos JPG, JPEG, PNG & GIF.'];
    }
    
    // Subir archivo
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => true, 'filename' => $newFileName];
    } else {
        return ['success' => false, 'message' => 'Error al subir la imagen.'];
    }
}
?>