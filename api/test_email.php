<?php
// Asegurar que se devuelve JSON
header('Content-Type: application/json; charset=utf-8');

// Manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Evitar buffering de output
ob_start();

try {
    // Cargar configuración
    $config_file = __DIR__ . '/../config/config.php';
    if (!file_exists($config_file)) {
        throw new Exception('Archivo de configuración no encontrado: ' . $config_file);
    }
    
    require_once $config_file;
    
    // Validar método y parámetro
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['email'])) {
        throw new Exception('Solicitud inválida');
    }
    
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        throw new Exception('Correo electrónico inválido');
    }
    
    // Preparar contenido del correo
    $site_name = defined('SITE_NAME') ? SITE_NAME : 'Sistema';
    $subject = "Prueba de Correo - " . $site_name;
    
    // Construir HTML
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; line-height: 1.6;">';
    $html .= '<div style="max-width: 600px; margin: 0 auto;">';
    $html .= '<h2>Prueba de Sistema de Correos</h2>';
    $html .= '<p>Hola,</p>';
    $html .= '<p>Este es un correo de prueba para verificar que el sistema funciona correctamente.</p>';
    $html .= '<div style="background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 20px 0;">';
    $html .= '<p><strong>Información del Sistema:</strong></p>';
    $html .= '<ul>';
    $html .= '<li>Sitio: ' . $site_name . '</li>';
    $html .= '<li>Fecha: ' . date('d/m/Y H:i:s') . '</li>';
    $html .= '</ul>';
    $html .= '</div>';
    $html .= '<p>Si recibiste este correo, el sistema está funcionando correctamente.</p>';
    $html .= '<p style="color: #666; font-size: 12px;">Este correo fue enviado automáticamente.</p>';
    $html .= '</div></body></html>';
    
    // Configurar headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    
    if (defined('SMTP_USER') && SMTP_USER) {
        $headers .= "From: " . SMTP_USER . " <" . SMTP_USER . ">\r\n";
    }
    
    // Codificar asunto
    $subject_encoded = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    
    // Enviar correo
    $result = @mail($email, $subject_encoded, $html, $headers);
    
    // Registrar intento
    @mkdir('logs', 0777, true);
    $log_file = 'logs/correos_' . date('Y-m-d') . '.log';
    $log_status = $result ? "ENVIADO (PRUEBA)" : "ERROR AL ENVIAR (PRUEBA)";
    $log_entry = date('Y-m-d H:i:s') . " | Para: $email | Asunto: $subject | Estado: $log_status\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Limpiar cualquier output previo
    ob_clean();
    
    // Responder JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Prueba enviada. Revisa tu correo (incluyendo spam) y los logs.'
    ]);
    
} catch (Exception $e) {
    // Limpiar cualquier output previo
    ob_clean();
    
    // Responder con error
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit();
?>

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Correo inválido']);
        exit();
    }
    
    try {
        $subject = "Prueba de Correo - " . SITE_NAME;
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto;'>
                <h2>Prueba de Sistema de Correos</h2>
                <p>Hola,</p>
                <p>Este es un correo de prueba para verificar que el sistema de envío de correos funciona correctamente.</p>
                
                <div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Información del Sistema:</strong></p>
                    <ul>
                        <li>Sitio: " . SITE_NAME . "</li>
                        <li>Fecha: " . date('d/m/Y H:i:s') . "</li>
                        <li>Servidor: " . php_uname() . "</li>
                    </ul>
                </div>
                
                <p>Si recibiste este correo, significa que el sistema está funcionando correctamente.</p>
                
                <hr style='margin-top: 30px;'>
                <p style='color: #666; font-size: 12px;'>
                    Este correo fue enviado automáticamente. Por favor no responda.
                </p>
            </div>
        </body>
        </html>";
        
        // Enviar correo
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SMTP_USER . " <" . SMTP_USER . ">\r\n";
        $headers .= "Reply-To: " . SMTP_USER . "\r\n";
        
        $subject_encoded = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        
        $result = @mail($email, $subject_encoded, $html, $headers);
        
        // Log del intento
        $log_dir = 'logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        
        $log_file = $log_dir . 'correos_' . date('Y-m-d') . '.log';
        $log_result = $result ? "ENVIADO (PRUEBA)" : "ERROR AL ENVIAR (PRUEBA)";
        $log_content = date('Y-m-d H:i:s') . " | Para: $email | Asunto: $subject | Estado: $log_result\n";
        @file_put_contents($log_file, $log_content, FILE_APPEND | LOCK_EX);
        
        echo json_encode([
            'success' => true,
            'message' => 'Prueba enviada. Revisa tu correo y los logs.',
            'log_file' => 'logs/correos_' . date('Y-m-d') . '.log'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
}
?>
