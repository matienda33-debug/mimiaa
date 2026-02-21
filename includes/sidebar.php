<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/config.php';
?>
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse bg-dark">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4 p-3">
            <h4 class="text-white"><?php echo SITE_NAME; ?></h4>
            <span class="badge bg-danger"><?php echo AJITOS_NAME; ?></span>
            <p class="text-white-50 small mt-2">Bienvenido, <?php echo $_SESSION['nombre']; ?></p>
            <p class="text-white-50 small"><?php echo $_SESSION['rol_nombre']; ?></p>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="../mod1/dashboard.php">
                    <i class="fas fa-home me-2"></i> Dashboard
                </a>
            </li>
            
            <?php if ($auth->hasPermission('usuarios')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>" 
                   href="../mod1/usuarios.php">
                    <i class="fas fa-users me-2"></i> Usuarios
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'personas.php' ? 'active' : ''; ?>" 
                   href="../mod1/personas.php">
                    <i class="fas fa-address-card me-2"></i> Personas
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($auth->hasPermission('productos')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'productos.php' ? 'active' : ''; ?>" 
                   href="../mod2/productos.php">
                    <i class="fas fa-tshirt me-2"></i> Productos
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($auth->hasPermission('ventas')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ventas.php' ? 'active' : ''; ?>" 
                   href="../mod3/ventas.php">
                    <i class="fas fa-shopping-cart me-2"></i> Ventas
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($auth->hasPermission('inventario')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventario.php' ? 'active' : ''; ?>" 
                   href="../mod2/inventario.php">
                    <i class="fas fa-boxes me-2"></i> Inventario
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($auth->hasPermission('contabilidad')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contabilidad.php' ? 'active' : ''; ?>" 
                   href="../mod4/contabilidad.php">
                    <i class="fas fa-chart-line me-2"></i> Contabilidad
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($auth->hasPermission('reportes')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>" 
                   href="../mod4/reportes.php">
                    <i class="fas fa-chart-bar me-2"></i> Reportes
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>