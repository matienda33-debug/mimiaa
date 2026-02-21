// ========== Utility Functions ==========

/**
 * Agregar producto al carrito
 */
function agregarAlCarrito(idProducto) {
    const formulario = new FormData();
    
    const cantidadInput = document.getElementById('cantidad');
    const cantidad = cantidadInput ? cantidadInput.value : 1;
    
    let varianteId = null;
    
    // Intentar obtener variante del nuevo sistema (variante seleccionada)
    if (typeof varianteSeleccionada !== 'undefined' && varianteSeleccionada) {
        varianteId = varianteSeleccionada.id_variante;
    } else {
        // Fallback al sistema anterior (input radio)
        const varianteInput = document.querySelector('input[name="variante"]:checked');
        if (varianteInput) {
            varianteId = varianteInput.value;
        }
    }
    
    // Validar que se haya seleccionado una variante
    if (!varianteId) {
        mostrarNotificacion('Por favor selecciona una variante (talla y color)', 'warning');
        return;
    }
    
    formulario.append('variante_id', varianteId);
    formulario.append('cantidad', cantidad);
    
    fetch('../cliente/api/add_to_cart.php', {
        method: 'POST',
        body: formulario
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        // Log para debugging
        console.log('Respuesta API:', text);
        
        // Intentar parsear JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Error parseando JSON:', e);
            mostrarNotificacion('Error de comunicación con servidor', 'danger');
            return;
        }
        
        if (data.success) {
            let mensaje = `✓ <strong>${data.producto_nombre}</strong> agregado al carrito`;
            if (data.color && data.talla) {
                mensaje += `<br><small>${data.color} - Talla ${data.talla} | Cantidad: ${data.cantidad}</small>`;
            }
            mostrarNotificacion(mensaje, 'success');
            actualizarContadorCarrito();
        } else {
            mostrarNotificacion('Error: ' + (data.message || 'Desconocido'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error completo:', error);
        mostrarNotificacion('Error al agregar producto: ' + error.message, 'danger');
    });
}

/**
 * Mostrar notificación toast
 */
function mostrarNotificacion(mensaje, tipo = 'info') {
    const alertaId = 'alerta-' + Date.now();
    
    const alerta = document.createElement('div');
    alerta.id = alertaId;
    alerta.className = `alert alert-${tipo} alert-dismissible fade show`;
    alerta.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 500px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: slideInRight 0.3s ease-out;
    `;
    alerta.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(alerta);
    
    // Duración más larga para mensajes de éxito
    const duracion = tipo === 'success' ? 7000 : 5000;
    
    setTimeout(() => {
        const elemento = document.getElementById(alertaId);
        if (elemento) {
            elemento.classList.remove('show');
            setTimeout(() => {
                elemento.remove();
            }, 150);
        }
    }, duracion);
}


/**
 * Actualizar contador del carrito
 */
function actualizarContadorCarrito() {
    fetch('../cliente/api/get_cart_count.php')
    .then(response => response.json())
    .then(data => {
        const badge = document.querySelector('.cart-badge');
        if (badge) {
            badge.textContent = data.count;
            if (data.count > 0) {
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

/**
 * Formatear moneda
 */
function formatearMoneda(valor) {
    return 'Q' + parseFloat(valor).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Validar email
 */
function validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Validar teléfono
 */
function validarTelefono(telefono) {
    const regex = /^\d{8,}$/;
    return regex.test(telefono.replace(/\D/g, ''));
}

/**
 * Obtener variantes de producto (AJAX)
 */
function obtenerVariantes(idProducto) {
    fetch(`../cliente/api/get_variantes.php?id=${idProducto}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            actualizarSelectVariantes(data.variantes);
        }
    })
    .catch(error => console.error('Error:', error));
}

/**
 * Actualizar select de variantes
 */
function actualizarSelectVariantes(variantes) {
    const select = document.getElementById('variante');
    if (!select) return;
    
    select.innerHTML = '<option value="">-- Selecciona una opción --</option>';
    
    variantes.forEach(variante => {
        const option = document.createElement('option');
        option.value = variante.id_variante;
        option.textContent = `${variante.nombre} (Stock: ${variante.stock_tienda + variante.stock_bodega})`;
        select.appendChild(option);
    });
}

/**
 * Filtrar productos por precio (en página de destacados/categoría)
 */
function filtrarPrecio() {
    const precioMin = document.getElementById('precioMin')?.value || '0';
    const precioMax = document.getElementById('precioMax')?.value || '999999';
    
    const url = new URL(window.location);
    url.searchParams.set('precio_min', precioMin);
    url.searchParams.set('precio_max', precioMax);
    url.searchParams.set('pagina', '1');
    
    window.location.href = url.toString();
}

/**
 * Ordenar productos
 */
function ordenarProductos(campo) {
    const url = new URL(window.location);
    url.searchParams.set('orden', campo);
    url.searchParams.set('pagina', '1');
    window.location.href = url.toString();
}

/**
 * Limpiar filtros
 */
function limpiarFiltros() {
    window.location.href = window.location.pathname;
}

/**
 * Confirmar acción
 */
function confirmar(mensaje = '¿Estás seguro?') {
    return confirm(mensaje);
}

/**
 * Cargar contenido dinámico
 */
function cargarContenido(url, elementId) {
    fetch(url)
    .then(response => response.text())
    .then(html => {
        document.getElementById(elementId).innerHTML = html;
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error al cargar contenido', 'danger');
    });
}

/**
 * Copiar a portapapeles
 */
function copiarAlPortapapeles(texto) {
    navigator.clipboard.writeText(texto).then(() => {
        mostrarNotificacion('Copiado al portapapeles', 'success');
    }).catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error al copiar', 'danger');
    });
}

/**
 * Mostrar/ocultar contraseña en formularios
 */
document.addEventListener('DOMContentLoaded', function() {
    // Agregar botón de mostrar/ocultar contraseña
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    
    passwordInputs.forEach(input => {
        const wrapper = document.createElement('div');
        wrapper.className = 'input-group';
        
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        
        const button = document.createElement('button');
        button.className = 'btn btn-outline-secondary';
        button.type = 'button';
        button.innerHTML = '<i class="fas fa-eye"></i>';
        
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (input.type === 'password') {
                input.type = 'text';
                button.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                button.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
        
        wrapper.appendChild(button);
    });
    
    // Inicializar tooltips de Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
});

/**
 * Validar formulario antes de enviar
 */
function validarFormulario(formularioId) {
    const formulario = document.getElementById(formularioId);
    if (!formulario) return false;
    
    const campos = formulario.querySelectorAll('[required]');
    let valido = true;
    
    campos.forEach(campo => {
        if (!campo.value.trim()) {
            campo.classList.add('is-invalid');
            valido = false;
        } else {
            campo.classList.remove('is-invalid');
        }
    });
    
    return valido;
}

/**
 * Debugger - Para desarrollo
 */
function debug(mensaje, datos = null) {
    if (window.DEBUG) {
        console.log(`[DEBUG] ${mensaje}`, datos || '');
    }
}

/**
 * Event listener para cuando el documento está listo
 */
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes Bootstrap
    const navbar = document.querySelector('nav');
    if (navbar) {
        navbar.style.cssText = 'animation: slideDown 0.5s ease;';
    }
    
    // Actualizar contador de carrito al cargar
    actualizarContadorCarrito();
});

/**
 * Animaciones CSS
 */
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
`;
document.head.appendChild(style);
