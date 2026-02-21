<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    header('Location: ../../index.php');
    exit();
}

if ($_SESSION['rol'] == 4) {
    header('Location: ../cliente/index.php');
    exit();
}

$views = [
    'usuarios' => ['path' => '/tiendaAA/modules/mod1/usuarios.php', 'permission' => 'usuarios', 'label' => 'Usuarios'],
    'personas' => ['path' => '/tiendaAA/modules/mod1/personas.php', 'permission' => 'usuarios', 'label' => 'Personas'],
    'productos' => ['path' => '/tiendaAA/modules/mod2/productos.php', 'permission' => 'productos', 'label' => 'Productos'],
    'banners' => ['path' => '/tiendaAA/public/admin/banners.php', 'permission' => 'productos', 'label' => 'Banners Inicio'],
    'ventas' => ['path' => '/tiendaAA/modules/mod3/ventas.php', 'permission' => 'ventas', 'label' => 'Ventas'],
    'inventario' => ['path' => '/tiendaAA/modules/mod2/inventario.php', 'permission' => 'inventario', 'label' => 'Inventario'],
    'contabilidad' => ['path' => '/tiendaAA/modules/mod4/contabilidad.php', 'permission' => 'contabilidad', 'label' => 'Contabilidad'],
    'reportes' => ['path' => '/tiendaAA/modules/mod4/reportes.php', 'permission' => 'reportes', 'label' => 'Reportes']
];

$view = $_GET['view'] ?? 'ventas';
$iframeSrc = '';
$tituloVista = 'Panel';
$errorVista = '';

if (!isset($views[$view])) {
    $errorVista = 'La sección solicitada no existe.';
} else {
    $vistaSeleccionada = $views[$view];
    if (!$auth->hasPermission($vistaSeleccionada['permission'])) {
        $errorVista = 'No tienes permisos para acceder a esta sección.';
    } else {
        $separador = (strpos($vistaSeleccionada['path'], '?') !== false) ? '&' : '?';
        $iframeSrc = $vistaSeleccionada['path'] . $separador . 'embedded=1';
        $tituloVista = $vistaSeleccionada['label'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo htmlspecialchars($tituloVista); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 10px 15px;
            margin: 5px 0;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
            color: #1abc9c;
        }
        .sidebar .nav-link.active {
            background: #1abc9c;
            color: white;
        }
        .brand-title {
            color: #1abc9c;
            font-weight: bold;
        }
        .ajitos-badge {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .iframe-wrapper {
            width: 100%;
            height: calc(100vh - 120px);
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }
        .iframe-wrapper iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4 p-3">
                        <h4 class="brand-title"><?php echo SITE_NAME; ?></h4>
                        <span class="ajitos-badge"><?php echo AJITOS_NAME; ?></span>
                        <p class="text-white-50 small mt-2">Bienvenido, <?php echo $_SESSION['nombre']; ?></p>
                        <p class="text-white-50 small"><?php echo $_SESSION['rol_nombre']; ?></p>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home me-2"></i> Dashboard
                            </a>
                        </li>

                        <?php if ($auth->hasPermission('usuarios')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'usuarios' ? 'active' : ''; ?>" href="panel.php?view=usuarios">
                                <i class="fas fa-users me-2"></i> Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'personas' ? 'active' : ''; ?>" href="panel.php?view=personas">
                                <i class="fas fa-address-card me-2"></i> Personas
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('productos')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'productos' ? 'active' : ''; ?>" href="panel.php?view=productos">
                                <i class="fas fa-tshirt me-2"></i> Productos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'banners' ? 'active' : ''; ?>" href="panel.php?view=banners">
                                <i class="fas fa-images me-2"></i> Banners Inicio
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('ventas')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'ventas' ? 'active' : ''; ?>" href="panel.php?view=ventas">
                                <i class="fas fa-shopping-cart me-2"></i> Ventas
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('inventario')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'inventario' ? 'active' : ''; ?>" href="panel.php?view=inventario">
                                <i class="fas fa-boxes me-2"></i> Inventario
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('contabilidad')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'contabilidad' ? 'active' : ''; ?>" href="panel.php?view=contabilidad">
                                <i class="fas fa-chart-line me-2"></i> Contabilidad
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('reportes')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'reportes' ? 'active' : ''; ?>" href="panel.php?view=reportes">
                                <i class="fas fa-chart-bar me-2"></i> Reportes
                            </a>
                        </li>
                        <?php endif; ?>

                        <li class="nav-item">
                            <a class="nav-link" href="../../config/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-3">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h1 class="h4 mb-0"><?php echo htmlspecialchars($tituloVista); ?></h1>
                    <a class="btn btn-outline-secondary btn-sm" href="dashboard.php">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
                    </a>
                </div>

                <?php if (!empty($errorVista)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorVista); ?></div>
                <?php else: ?>
                    <div class="iframe-wrapper">
                        <iframe id="panelIframe" src="<?php echo htmlspecialchars($iframeSrc); ?>" title="<?php echo htmlspecialchars($tituloVista); ?>"></iframe>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const iframe = document.getElementById('panelIframe');
            if (!iframe) return;

            function aplicarModoEmbebido() {
                try {
                    const doc = iframe.contentDocument || iframe.contentWindow.document;
                    if (!doc) return;

                    const style = doc.createElement('style');
                    style.textContent = `
                        .sidebar,
                        nav.sidebar,
                        .navbar,
                        .navbar-custom,
                        .topbar,
                        .offcanvas,
                        #sidebar,
                        #sidebarMenu,
                        [data-role="sidebar"] {
                            display: none !important;
                        }

                        .main-content,
                        main,
                        .content-wrapper,
                        .page-content,
                        .container-fluid,
                        .container {
                            margin-left: 0 !important;
                        }
                    `;
                    doc.head.appendChild(style);
                } catch (e) {
                    console.warn('No se pudo aplicar modo embebido en iframe:', e);
                }
            }

            iframe.addEventListener('load', aplicarModoEmbebido);
        })();
    </script>
</body>
</html>
