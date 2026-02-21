<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/email_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$to = isset($_GET['to']) ? trim($_GET['to']) : '';
if ($to === '') {
    echo "Falta parametro ?to=esauprimej2@gmail.com ";
    exit;
}

$email = new EmailConfig();
$subject = 'Prueba SMTP - ' . SITE_NAME;
$html = '<p>Hola,</p><p>Esta es una prueba de envio SMTP desde ' . SITE_NAME . '.</p>';

$ok = $email->sendEmail($to, 'Cliente', $subject, $html);

if ($ok) {
    echo 'Envio correcto';
} else {
    $detalle = $email->getLastError();
    echo 'Error al enviar';
    if ($detalle) {
        echo ': ' . htmlspecialchars($detalle);
    }
}
