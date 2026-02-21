<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/tiendaAA/config/database.php';

// Verificar sesión
if (!isset($_SESSION)) {
    session_start();
}

// Obtener departamentos
$database = new Database();
$db = $database->getConnection();
$departamentos_query = "SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre";
$departamentos_stmt = $db->prepare($departamentos_query);
$departamentos_stmt->execute();
$departamentos = $departamentos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar items en carrito
$carrito_count = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-store me-2"></i>
            <?php echo SITE_NAME; ?>
            <span class="badge badge-ajitos ms-2" style="font-size: 0.7rem;">
                <?php echo AJITOS_NAME; ?>
            </span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-home me-1"></i> Inicio
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="departamentosDropdown" 
                       data-bs-toggle="dropdown">
                        <i class="fas fa-th-large me-1"></i> Categorías
                    </a>
                    <ul class="dropdown-menu">
                        <?php foreach ($departamentos as $depto): ?>
                        <li>
                            <a class="dropdown-item" href="categoria.php?id=<?php echo $depto['id_departamento']; ?>">
                                <?php echo htmlspecialchars($depto['nombre']); ?>
                                <?php if ($depto['es_ajitos']): ?>
                                    <span class="badge badge-ajitos ms-2">Ajitos</span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="ofertas.php">
                        <i class="fas fa-tag me-1"></i> Ofertas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="nuevos.php">
                        <i class="fas fa-star me-1"></i> Nuevos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="destacados.php">
                        <i class="fas fa-fire me-1"></i> Destacados
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="ajitos.php">
                        <i class="fas fa-flash me-1"></i>
                        <span class="badge badge-ajitos">Ajitos</span>
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="busqueda.php">
                        <i class="fas fa-search me-1"></i> Buscar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link position-relative cursor-pointer" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#carritoModal" onclick="cargarCarrito(); return false;">
                        <i class="fas fa-shopping-cart me-1"></i> Carrito
                        <?php if ($carrito_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-badge">
                                <?php echo $carrito_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php if (isset($_SESSION['id_usuario'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" 
                       data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i> 
                        <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="perfil.php">
                                <i class="fas fa-user-circle me-2"></i> Mi Perfil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="historial.php">
                                <i class="fas fa-history me-2"></i> Mis Compras
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="../config/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="login.php">
                        <i class="fas fa-sign-in-alt me-1"></i> Iniciar Sesión
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="registro.php">
                        <i class="fas fa-user-plus me-1"></i> Registrarse
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<script src="<?php echo isset($GLOBALS['BASE_URL']) ? $GLOBALS['BASE_URL'] : '/tiendaAA/'; ?>assets/js/carrito-modal.js"></script>