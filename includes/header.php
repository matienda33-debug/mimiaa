<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/config.php';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-store me-2"></i>
            <?php echo SITE_NAME; ?>
            <span class="badge bg-danger ms-2"><?php echo AJITOS_NAME; ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link text-light">
                        <i class="fas fa-user me-1"></i>
                        <?php echo $_SESSION['nombre']; ?>
                    </span>
                </li>
                <li class="nav-item">
                    <span class="nav-link text-light">
                        <i class="fas fa-user-tag me-1"></i>
                        <?php echo $_SESSION['rol_nombre']; ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../config/logout.php">
                        <i class="fas fa-sign-out-alt me-1"></i> Salir
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>