<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/config.php';
?>
<style>
  .navbar-header {
    background: linear-gradient(135deg, #E35AD9 0%, #C94CC0 100%);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  .navbar-header .navbar-brand {
    color: white !important;
    font-weight: bold;
    font-size: 1.3rem;
  }
  .navbar-header .badge {
    background: #C2EDE8 !important;
    color: #1f2a29 !important;
    font-weight: 600;
  }
  .navbar-header .nav-link {
    color: rgba(255, 255, 255, 0.85) !important;
    transition: color 0.2s ease;
  }
  .navbar-header .nav-link:hover {
    color: white !important;
  }
</style>
<nav class="navbar navbar-expand-lg navbar-header">
    <div class="container-fluid">
        <a class="navbar-brand" href="../../modules/mod1/dashboard.php">
            <i class="fas fa-store me-2"></i>
            <?php echo SITE_NAME; ?>
            <span class="badge ms-2"><?php echo AJITOS_NAME; ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link">
                        <i class="fas fa-user me-1"></i>
                        <?php echo $_SESSION['nombre'] ?? 'Usuario'; ?>
                    </span>
                </li>
                <li class="nav-item">
                    <span class="nav-link">
                        <i class="fas fa-user-tag me-1"></i>
                        <?php echo $_SESSION['rol_nombre'] ?? 'Sin rol'; ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../../config/logout.php" title="Cerrar sesión">
                        <i class="fas fa-sign-out-alt me-1"></i>Salir
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>