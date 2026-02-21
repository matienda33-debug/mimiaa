<?php
// Configuración de correo electrónico
class EmailConfig {
    private $smtp_host = SMTP_HOST;
    private $smtp_port = SMTP_PORT;
    private $smtp_user = SMTP_USER;
    private $smtp_pass = SMTP_PASS;
    private $from_email = SMTP_USER;
    private $from_name = SITE_NAME;
    private $last_error = '';

    public function getLastError() {
        return $this->last_error;
    }
    
    public function sendEmail($to, $to_name, $subject, $html_content, $attachments = []) {
        // Usar PHPMailer local (carpeta lib/PHPMailer)
        $phpMailerBase = __DIR__ . '/../lib/PHPMailer-7.0.2/src/';
        $phpMailerFile = $phpMailerBase . 'PHPMailer.php';
        $phpMailerSMTP = $phpMailerBase . 'SMTP.php';
        $phpMailerException = $phpMailerBase . 'Exception.php';

        if (!file_exists($phpMailerFile) || !file_exists($phpMailerSMTP) || !file_exists($phpMailerException)) {
            $this->last_error = 'PHPMailer no encontrado. Instala la libreria en lib/PHPMailer/src';
            error_log($this->last_error);
            return false;
        }

        require_once $phpMailerException;
        require_once $phpMailerFile;
        require_once $phpMailerSMTP;

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_user;
            $mail->Password = $this->smtp_pass;
            $mail->SMTPSecure = 'tls';
            $mail->Port = $this->smtp_port;
            
                // XAMPP local: evita fallo por verificacion de certificado
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            
            // Remitente
            $mail->setFrom($this->from_email ?: $this->smtp_user, $this->from_name);
            $mail->addAddress($to, $to_name);
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_content;
            $mail->AltBody = strip_tags($html_content);
            
            // Adjuntos
            foreach ($attachments as $attachment) {
                $mail->addAttachment($attachment['path'], $attachment['name']);
            }
            
            // Enviar
            $sent = $mail->send();
            if (!$sent) {
                $this->last_error = $mail->ErrorInfo;
            }
            return $sent;
            
        } catch (Exception $e) {
            $this->last_error = $mail->ErrorInfo ?: $e->getMessage();
            error_log("Error al enviar correo: " . $this->last_error);
            return false;
        }
    }
    
    public function sendInvoiceEmail($cliente_email, $cliente_nombre, $factura_id, $pdf_path = null) {
        $subject = "Factura #{$factura_id} - " . SITE_NAME;
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$subject}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .header { background: #1abc9c; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . SITE_NAME . "</h1>
                    <p>¡Gracias por su compra!</p>
                </div>
                <div class='content'>
                    <h2>Hola {$cliente_nombre},</h2>
                    <p>Adjunto encontrará su factura #{$factura_id}.</p>
                    <p>Para cualquier consulta, no dude en contactarnos.</p>
                </div>
                <div class='footer'>
                    <p>" . SITE_NAME . " - " . AJITOS_NAME . "</p>
                    <p>© " . date('Y') . " Todos los derechos reservados</p>
                </div>
            </div>
        </body>
        </html>";
        
        $attachments = [];
        if ($pdf_path && file_exists($pdf_path)) {
            $attachments[] = [
                'path' => $pdf_path,
                'name' => "factura_{$factura_id}.pdf"
            ];
        }
        
        return $this->sendEmail($cliente_email, $cliente_nombre, $subject, $html, $attachments);
    }
}
?>