<?php
// Footer simple sin dependencias
?>
<footer class="bg-dark text-white mt-5 py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <h6 class="mb-3"><i class="fas fa-store me-2"></i>MI&MI Store</h6>
                <p class="text-muted small">Tu tienda de moda favorita. Especialistas en moda infantil.</p>
                <div class="mb-3">
                    <a href="#" class="text-white me-3"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                </div>
            </div>

            <div class="col-md-3">
                <h6 class="mb-3">Categorías</h6>
                <ul class="list-unstyled">
                    <li><a href="/tiendaAA/cliente/index.php" class="text-muted text-decoration-none">Inicio</a></li>
                    <li><a href="/tiendaAA/cliente/destacados.php" class="text-muted text-decoration-none">Destacados</a></li>
                    <li><a href="/tiendaAA/cliente/nuevos.php" class="text-muted text-decoration-none">Nuevos</a></li>
                    <li><a href="/tiendaAA/cliente/ofertas.php" class="text-muted text-decoration-none">Ofertas</a></li>
                    <li><a href="/tiendaAA/cliente/ajitos.php" class="text-muted text-decoration-none">Ajitos Kids</a></li>
                </ul>
            </div>

            <div class="col-md-3">
                <h6 class="mb-3">Información</h6>
                <ul class="list-unstyled">
                    <li><a href="/tiendaAA/cliente/terminos.php" class="text-muted text-decoration-none">Términos</a></li>
                    <li><a href="/tiendaAA/cliente/privacidad.php" class="text-muted text-decoration-none">Privacidad</a></li>
                    <li><a href="/tiendaAA/cliente/login.php" class="text-muted text-decoration-none">Ingresar</a></li>
                    <li><a href="/tiendaAA/cliente/registro.php" class="text-muted text-decoration-none">Registrarse</a></li>
                </ul>
            </div>

            <div class="col-md-3">
                <h6 class="mb-3">Contacto</h6>
                <ul class="list-unstyled small text-muted">
                    <li><i class="fas fa-phone me-2"></i> +502 7777-7777</li>
                    <li><i class="fas fa-envelope me-2"></i> info@mimistore.com</li>
                    <li><i class="fas fa-map-marker-alt me-2"></i> Guatemala</li>
                </ul>
            </div>
        </div>

        <hr class="bg-secondary">
        <div class="text-center text-muted small">
            <p>© 2026 MI&MI Store. Todos los derechos reservados.</p>
        </div>
    </div>
</footer>

<!-- Modal del Carrito - Flotante y mejorado -->
<div class="modal fade" id="carritoModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-shopping-cart me-2"></i> Mi Carrito
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="carritoContenido" style="min-height: 300px;">
                <!-- Contenido del carrito se carga aquí -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="text-muted mt-2">Cargando carrito...</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Cerrar
                </button>
                <button type="button" class="btn btn-primary" onclick="irAlCheckout()">
                    <i class="fas fa-credit-card me-2"></i> Proceder al Pago
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/tiendaAA/assets/js/carrito-modal.js"></script>