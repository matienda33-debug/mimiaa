<?php
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos y Condiciones - <?php echo SITE_NAME; ?></title>
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
                <li class="breadcrumb-item active">Términos y Condiciones</li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-md-8 mx-auto">
                <h1 class="mb-4">Términos y Condiciones</h1>
                
                <div class="card bg-light">
                    <div class="card-body">
                        <h4>1. Aceptación de los Términos</h4>
                        <p>Al acceder y utilizar este sitio web, usted acepta estar
 ligado por estos términos y condiciones. Si no está de acuerdo con alguna parte de estos términos, no podrá utilizar el sitio.</p>
                        
                        <h4>2. Uso del Sitio</h4>
                        <p>Usted se compromete a utilizar este sitio solo para propósitos lícitos y de una manera que no infrinja los derechos de otros o restrinja su uso y disfrute del sitio.</p>
                        
                        <h4>3. Productos y Precios</h4>
                        <p>Todos los productos mostrados están sujetos a disponibilidad. Los precios están sujetos a cambios sin previo aviso. Nos reservamos el derecho de limitar cantidades.</p>
                        
                        <h4>4. Proceso de Compra</h4>
                        <p>Al realizar una compra, usted está haciendo una oferta para comprar un producto. Nos reservamos el derecho de aceptar o rechazar cualquier pedido.</p>
                        
                        <h4>5. Privacidad</h4>
                        <p>Su use de datos personales está regulado por nuestra política de privacidad. Por favor revisa nuestra <a href="privacidad.php">política de privacidad</a>.</p>
                        
                        <h4>6. Limitación de Responsabilidad</h4>
                        <p>En la máxima medida permitida por la ley, <?php echo SITE_NAME; ?> no será responsable de daños indirectos, incidentales, especiales o consecuentes.</p>
                        
                        <h4>7. Cambios en los Términos</h4>
                        <p>Nos reservamos el derecho de modificar estos términos en cualquier momento. El uso continuado del sitio después de tales modificaciones constituye una aceptación de los términos actualizados.</p>
                        
                        <h4>8. Contacto</h4>
                        <p>Si tienes preguntas sobre estos términos, por favor <a href="mailto:<?php echo defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'info@' . SITE_DOMAIN; ?>">contáctanos</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
