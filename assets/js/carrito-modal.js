/**
 * Obtener URL base de la API
 */
function getAPIBaseUrl() {
    const pathParts = window.location.pathname.split('/');
    const tiendaIndex = pathParts.indexOf('tiendaAA');
    
    if (tiendaIndex !== -1) {
        return '/tiendaAA/cliente/api/';
    }
    
    return '/tiendaAA/cliente/api/';
}

/**
 * Cargar carrito y mostrar en modal
 */
function cargarCarrito() {
    const apiUrl = getAPIBaseUrl() + 'get_carrito.php';
    
    const carritoContenido = document.getElementById('carritoContenido');
    
    // Mostrar spinner
    carritoContenido.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="text-muted mt-2">Cargando carrito...</p>
        </div>
    `;
    
    fetch(apiUrl)
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta del servidor');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                mostrarModalCarrito(data.items, data.total);
            } else {
                carritoContenido.innerHTML = `<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i> Error al cargar el carrito</div>`;
            }
        })
        .catch(error => {
            console.error('Error al cargar carrito:', error);
            carritoContenido.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i> Error: ${error.message}</div>`;
        });
}

/**
 * Mostrar modal con contenido del carrito
 */
function mostrarModalCarrito(items, total) {
    let carritoHTML = '';
    
    if (items.length === 0) {
        carritoHTML = `
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart text-muted" style="font-size: 48px;"></i>
                <p class="text-muted mt-3">Tu carrito está vacío</p>
                <button class="btn btn-primary" data-bs-dismiss="modal">Continuar Comprando</button>
            </div>
        `;
    } else {
        carritoHTML = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th><i class="fas fa-box me-2"></i>Producto</th>
                            <th><i class="fas fa-palette me-2"></i>Variante</th>
                            <th class="text-end">Precio</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        items.forEach(item => {
            const subtotal = item.precio * item.cantidad;
            carritoHTML += `
                <tr>
                    <td>
                        <strong>${item.nombre}</strong>
                    </td>
                    <td>
                        <small class="badge bg-info text-dark">${item.color}</small>
                        <small class="badge bg-secondary text-white">Talla ${item.talla}</small>
                    </td>
                    <td class="text-end">Q${parseFloat(item.precio).toFixed(2)}</td>
                    <td class="text-center">
                        <div class="input-group input-group-sm" style="width: 100px; margin: 0 auto;">
                            <button class="btn btn-outline-secondary" type="button" onclick="cambiarCantidad(${item.variante_id}, -1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="form-control text-center" id="cantidad-${item.variante_id}" value="${item.cantidad}" min="1" onchange="actualizarCantidad(${item.variante_id}, this.value)">
                            <button class="btn btn-outline-secondary" type="button" onclick="cambiarCantidad(${item.variante_id}, 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </td>
                    <td class="text-end fw-bold">Q${subtotal.toFixed(2)}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarDelCarrito(${item.variante_id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        carritoHTML += `
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-light border-top pt-3 mt-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p class="text-muted mb-0">Total a pagar:</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <h4 class="text-primary mb-0">Q${total.toFixed(2)}</h4>
                    </div>
                </div>
            </div>
        `;
    }
    
    document.getElementById('carritoContenido').innerHTML = carritoHTML;
}

/**
 * Eliminar producto del carrito
 */
function eliminarDelCarrito(varianteId) {
    if (!confirm('¿Deseas eliminar este producto del carrito?')) {
        return;
    }
    
    const apiUrl = getAPIBaseUrl() + 'remove_from_cart.php';
    
    const formulario = new FormData();
    formulario.append('variante_id', varianteId);
    
    fetch(apiUrl, {
        method: 'POST',
        body: formulario
    })
    .then(response => {
        if (!response.ok) throw new Error('Error en la respuesta del servidor');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Recargar carrito
            cargarCarrito();
            // Actualizar badge del carrito en navbar
            actualizarBadgeCarrito(data.count);
            // Mostrar notificación
            if (typeof mostrarNotificacion === 'function') {
                mostrarNotificacion('Producto eliminado del carrito', 'success');
            }
        } else {
            alert('Error: ' + (data.message || 'No se pudo eliminar el producto'));
        }
    })
    .catch(error => {
        console.error('Error al eliminar:', error);
        alert('Error al eliminar producto: ' + error.message);
    });
}

/**
 * Actualizar badge del carrito
 */
function actualizarBadgeCarrito(count) {
    const badge = document.querySelector('.navbar .position-absolute.badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
}

/**
 * Proceder al checkout
 */
function irAlCheckout() {
    const basePath = window.location.pathname.includes('/tiendaAA')
        ? window.location.pathname.split('/tiendaAA')[0]
        : '';

    window.location.href = basePath + '/tiendaAA/cliente/checkout.php';
}

/**
 * Cambiar cantidad con botones + y -
 */
function cambiarCantidad(varianteId, incremento) {
    const inputElement = document.getElementById(`cantidad-${varianteId}`);
    let nuevaCantidad = parseInt(inputElement.value) + incremento;
    
    if (nuevaCantidad < 1) {
        nuevaCantidad = 1;
    }
    
    inputElement.value = nuevaCantidad;
    actualizarCantidad(varianteId, nuevaCantidad);
}

/**
 * Actualizar cantidad en el carrito
 */
function actualizarCantidad(varianteId, nuevaCantidad) {
    nuevaCantidad = parseInt(nuevaCantidad);
    
    if (nuevaCantidad < 1) {
        alert('La cantidad debe ser mayor a 0');
        cargarCarrito();
        return;
    }
    
    const apiUrl = getAPIBaseUrl() + 'update_cart_quantity.php';
    
    const formulario = new FormData();
    formulario.append('variante_id', varianteId);
    formulario.append('cantidad', nuevaCantidad);
    
    fetch(apiUrl, {
        method: 'POST',
        body: formulario
    })
    .then(response => {
        if (!response.ok) throw new Error('Error en la respuesta del servidor');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Recargar carrito para actualizar totales
            cargarCarrito();
            // Actualizar badge del carrito
            actualizarBadgeCarrito(data.count);
        } else {
            alert('Error: ' + (data.message || 'No se pudo actualizar la cantidad'));
            cargarCarrito();
        }
    })
    .catch(error => {
        console.error('Error al actualizar cantidad:', error);
        alert('Error al actualizar cantidad: ' + error.message);
        cargarCarrito();
    });
}
