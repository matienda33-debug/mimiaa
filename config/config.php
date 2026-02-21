<?php
session_start();

// Configuración del sitio
define('SITE_NAME', 'MI&MI Store');
define('AJITOS_NAME', 'Ajitos Kids');
define('BASE_URL', 'http://localhost:8080/tiendaAA/');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/uploads/');
define('IMG_DIR', 'uploads/');

// Configuración SMTP (para envío de correos)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'matienda33@gmail.com');
define('SMTP_PASS', 'bzxf mgwb iasq kaon');

// Configuración de puntos
define('PUNTOS_POR_COMPRA', 20); // Q20 = 1 punto
define('VALOR_PUNTO', 30); // 30 puntos = Q1

// Incluir clases necesarias
require_once 'database.php';
require_once 'auth.php';
require_once 'functions.php';
?>