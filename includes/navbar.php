<?php
// public/includes/navbar.php
require_once '../app/models/Auth.php';
require_once '../app/models/Producto.php';

$auth = new Auth();
$productoModel = new Producto();

// Obtener departamentos para menú
$departamentos = $productoModel->getDepartamentos();

// Obtener carrito info
$carritoCount = 0;
$carritoTotal = 0;

if (isset($_SESSION['cart_session_id'])) {
    require_once '../app/models/Carrito.php';
    $carritoModel = new Carrito();
    $carritoInfo = $carritoModel->getCartSummary($_SESSION['cart_session_id']);
    $carritoCount = $carritoInfo['items'] ?? 0;
    $carritoTotal = $carritoInfo['total'] ?? 0;
}

// Configuración de la tienda
$nombreTienda = getConfigValue('nombre_tienda') ?? 'Tienda MI&MI Store';
?>
<nav class="navbar navbar-expand-lg navbar-main sticky-top">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand" href="/index.php">
            <div class="brand-logo"><?php echo htmlspecialchars($nombreTienda); ?></div>
            <div class="brand-subtitle">Ajitos Kids</div>
        </a>
        
        <!-- Botón menú móvil -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Contenido del menú -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- Menú categorías -->
            <ul class="navbar-nav me-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bars me-2"></i>Categorías
                    </a>
                    <ul class="dropdown-menu">
                        <?php foreach ($departamentos as $dep): ?>
                        <li>
                            <a class="dropdown-item" href="/categoria.php?id=<?php echo $dep['id_departamento']; ?>">
                                <?php echo htmlspecialchars($dep['nombre']); ?>
                                <?php if ($dep['tipo'] == 'AJITOS'): ?>
                                <span class="badge bg-warning ms-2">Kids</span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/nuevos.php"><i class="fas fa-star me-2"></i>Nuevos</a></li>
                        <li><a class="dropdown-item" href="/ofertas.php"><i class="fas fa-tag me-2"></i>Ofertas</a></li>
                        <li><a class="dropdown-item" href="/kids.php"><i class="fas fa-baby me-2"></i>Ajitos Kids</a></li>
                    </ul>
                </li>
            </ul>
            
            <!-- Barra de búsqueda -->
            <form class="d-flex mx-3" role="search" action="/busqueda.php" method="GET">
                <div class="input-group">
                    <input class="form-control" type="search" name="q" placeholder="Buscar productos..." 
                           value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            
            <!-- Carrito y usuario -->
            <div class="d-flex align-items-center">
                <?php if (isLoggedIn()): ?>
                <?php 
                $userInfo = $auth->getUserInfo();
                $initials = substr($userInfo['nombres'] ?? '', 0, 1) . substr($userInfo['apellidos'] ?? '', 0, 1);
                ?>
                <div class="dropdown me-3">
                    <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                        <div class="user-avatar me-2">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <span><?php echo htmlspecialchars($userInfo['nombres'] ?? ''); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/mi-cuenta.php"><i class="fas fa-user me-2"></i>Mi Cuenta</a></li>
                        <li><a class="dropdown-item" href="/mis-pedidos.php"><i class="fas fa-box me-2"></i>Mis Pedidos</a></li>
                        <li><a class="dropdown-item" href="/mis-puntos.php"><i class="fas fa-gift me-2"></i>Mis Puntos</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (getCurrentUserAccessLevel() >= 60): ?>
                        <li><a class="dropdown-item" href="/admin/dashboard.php"><i class="fas fa-cog me-2"></i>Panel Admin</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item text-danger" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
                <?php else: ?>
                <a href="/login.php" class="btn btn-outline-primary me-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Ingresar
                </a>
                <?php endif; ?>
                
                <!-- Carrito -->
                <button class="btn btn-primary position-relative" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($carritoCount > 0): ?>
                    <span class="cart-badge"><?php echo $carritoCount; ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>
    </div>
</nav>

<!-- Carrito Offcanvas -->
<div class="offcanvas offcanvas-end offcanvas-cart" tabindex="-1" id="cartOffcanvas">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Mi Carrito</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <?php if ($carritoCount == 0): ?>
        <div class="text-center py-5">
            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
            <p class="text-muted">Tu carrito está vacío</p>
            <a href="/productos.php" class="btn btn-primary">Ver Productos</a>
        </div>
        <?php else: ?>
        <div class="cart-items" id="cartItemsContainer">
            <!-- Los items del carrito se cargan via AJAX -->
            <div class="text-center py-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2">Cargando carrito...</p>
            </div>
        </div>
        
        <div class="cart-summary mt-4">
            <div class="d-flex justify-content-between mb-2">
                <span>Subtotal:</span>
                <strong id="cartSubtotal">Q<?php echo number_format($carritoTotal, 2); ?></strong>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Envío:</span>
                <span id="cartShipping">Calculado al finalizar</span>
            </div>
            <hr>
            <div class="d-flex justify-content-between mb-4">
                <span class="h5">Total:</span>
                <span class="h5 text-primary" id="cartTotal">Q<?php echo number_format($carritoTotal, 2); ?></span>
            </div>
            
            <div class="d-grid gap-2">
                <a href="/carrito.php" class="btn btn-outline-primary">Ver Carrito Detallado</a>
                <a href="<?php echo BASE_URL; ?>cliente/checkout.php" class="btn btn-primary">Proceder al Pago</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Cargar carrito via AJAX
document.addEventListener('DOMContentLoaded', function() {
    if (window.userLoggedIn || <?php echo $carritoCount > 0 ? 'true' : 'false'; ?>) {
        loadCartItems();
    }
    
    // Actualizar carrito cuando se cierra el offcanvas
    document.getElementById('cartOffcanvas').addEventListener('hidden.bs.offcanvas', function() {
        updateCartBadge();
    });
});

function loadCartItems() {
    fetch('/api/carrito.php?action=get')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('cartItemsContainer');
                if (container) {
                    container.innerHTML = data.html || 'No hay productos en el carrito';
                }
                
                // Actualizar totales
                if (document.getElementById('cartSubtotal')) {
                    document.getElementById('cartSubtotal').textContent = 'Q' + data.subtotal.toFixed(2);
                    document.getElementById('cartTotal').textContent = 'Q' + data.total.toFixed(2);
                }
                
                // Actualizar badge
                updateCartBadge(data.items_count || 0);
            }
        })
        .catch(error => {
            console.error('Error cargando carrito:', error);
        });
}

function updateCartBadge(count) {
    if (count === undefined) {
        fetch('/api/carrito.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateBadgeUI(data.count);
                }
            });
    } else {
        updateBadgeUI(count);
    }
}

function updateBadgeUI(count) {
    const badge = document.querySelector('.cart-badge');
    if (count > 0) {
        if (!badge) {
            const cartBtn = document.querySelector('button[data-bs-target="#cartOffcanvas"]');
            if (cartBtn) {
                const newBadge = document.createElement('span');
                newBadge.className = 'cart-badge';
                newBadge.textContent = count;
                cartBtn.appendChild(newBadge);
            }
        } else {
            badge.textContent = count;
        }
    } else if (badge) {
        badge.remove();
    }
}
</script>