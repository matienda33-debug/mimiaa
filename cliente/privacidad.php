<?php
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidad - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active">Política de Privacidad</li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-md-8 mx-auto">
                <h1 class="mb-4">Política de Privacidad</h1>
                
                <div class="card bg-light">
                    <div class="card-body">
                        <h4>1. Información que Recopilamos</h4>
                        <p>Recopilamos información que usted nos proporciona directamente, como su nombre, correo electrónico y dirección de envío cuando realiza una compra.</p>
                        
                        <h4>2. Cómo Usamos Tu Información</h4>
                        <p>Utilizamos tu información para:</p>
                        <ul>
                            <li>Procesar pedidos y entregas</li>
                            <li>Mejorar nuestros servicios</li>
                            <li>Enviar comunicaciones de marketing (con tu consentimiento)</li>
                            <li>Cumplir con obligaciones legales</li>
                        </ul>
                        
                        <h4>3. Protección de Datos</h4>
                        <p>Implementamos medidas de seguridad técnicas y organizativas para proteger tu información personal contra acceso no autorizado o divulgación.</p>
                        
                        <h4>4. Cookies</h4>
                        <p>Este sitio utiliza cookies para mejorar tu experiencia. Puedes configurar tu navegador para rechazar cookies, aunque esto puede afectar la funcionalidad del sitio.</p>
                        
                        <h4>5. Terceros</h4>
                        <p>No compartimos tu información personal con terceros sin tu consentimiento, excepto cuando sea necesario para procesar compras o cumplir con la ley.</p>
                        
                        <h4>6. Derechos del Usuario</h4>
                        <p>Tienes derecho a acceder, rectificar o eliminar tus datos personales en cualquier momento. Contáctanos para ejercer estos derechos.</p>
                        
                        <h4>7. Cambios en la Política</h4>
                        <p>Nos reservamos el derecho de actualizar esta política. Los cambios entrarán en vigencia inmediatamente después de su publicación.</p>
                        
                        <h4>8. Contacto</h4>
                        <p>Para preguntas sobre privacidad, por favor <a href="mailto:<?php echo defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'info@' . SITE_DOMAIN; ?>">contáctanos</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
