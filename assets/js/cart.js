// ========== Shopping Cart Functions ==========

/**
 * Carrito de compras - Funciones avanzadas
 */

class ShoppingCart {
    constructor() {
        this.items = this.loadCart();
        this.init();
    }
    
    /**
     * Cargar carrito del localStorage
     */
    loadCart() {
        const cart = localStorage.getItem('tienda_carrito');
        return cart ? JSON.parse(cart) : [];
    }
    
    /**
     * Guardar carrito en localStorage
     */
    saveCart() {
        localStorage.setItem('tienda_carrito', JSON.stringify(this.items));
        this.updateUI();
    }
    
    /**
     * Agregar item al carrito
     */
    addItem(id, nombre, precio, cantidad = 1, variante = null) {
        const existente = this.items.find(item => 
            item.id === id && item.variante === variante
        );
        
        if (existente) {
            existente.cantidad += cantidad;
        } else {
            this.items.push({
                id,
                nombre,
                precio,
                cantidad,
                variante,
                timestamp: Date.now()
            });
        }
        
        this.saveCart();
        this.showNotification(`${nombre} agregado al carrito`);
    }
    
    /**
     * Eliminar item del carrito
     */
    removeItem(id, variante = null) {
        this.items = this.items.filter(item =>
            !(item.id === id && item.variante === variante)
        );
        this.saveCart();
    }
    
    /**
     * Actualizar cantidad de item
     */
    updateQuantity(id, cantidad, variante = null) {
        const item = this.items.find(item =>
            item.id === id && item.variante === variante
        );
        
        if (item) {
            item.cantidad = Math.max(1, cantidad);
            this.saveCart();
        }
    }
    
    /**
     * Obtener total del carrito
     */
    getTotal() {
        return this.items.reduce((total, item) =>
            total + (item.precio * item.cantidad), 0
        );
    }
    
    /**
     * Obtener número de items
     */
    getItemCount() {
        return this.items.reduce((total, item) => total + item.cantidad, 0);
    }
    
    /**
     * Vaciar carrito
     */
    clearCart() {
        if (confirm('¿Deseas vaciar el carrito completamente?')) {
            this.items = [];
            this.saveCart();
        }
    }
    
    /**
     * Actualizar UI del carrito
     */
    updateUI() {
        this.updateCartBadge();
        this.updateCartTable();
    }
    
    /**
     * Actualizar badge del carrito
     */
    updateCartBadge() {
        const badge = document.querySelector('.cart-badge');
        const count = this.getItemCount();
        
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    }
    
    /**
     * Actualizar tabla del carrito
     */
    updateCartTable() {
        const tableBody = document.getElementById('cart-items');
        if (!tableBody) return;
        
        tableBody.innerHTML = '';
        
        if (this.items.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4">El carrito está vacío</td></tr>';
            document.getElementById('cart-total').textContent = 'Q0.00';
            return;
        }
        
        this.items.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <strong>${item.nombre}</strong>
                    ${item.variante ? '<br><small class="text-muted">' + item.variante + '</small>' : ''}
                </td>
                <td>Q${item.precio.toFixed(2)}</td>
                <td>
                    <input type="number" class="form-control" style="width: 80px;"
                           value="${item.cantidad}" min="1"
                           onchange="cart.updateQuantity(${item.id}, this.value, '${item.variante}')">
                </td>
                <td>Q${(item.precio * item.cantidad).toFixed(2)}</td>
                <td>
                    <button class="btn btn-sm btn-danger"
                            onclick="cart.removeItem(${item.id}, '${item.variante}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });
        
        document.getElementById('cart-total').textContent = 'Q' + this.getTotal().toFixed(2);
    }
    
    /**
     * Mostrar notificación
     */
    showNotification(mensaje) {
        const notif = document.createElement('div');
        notif.className = 'alert alert-success alert-dismissible fade show';
        notif.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        `;
        notif.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notif);
        
        setTimeout(() => notif.remove(), 3000);
    }
    
    /**
     * Inicializar
     */
    init() {
        this.updateUI();
    }
}

// Instancia global del carrito
const cart = new ShoppingCart();

/**
 * Proceder a checkout
 */
function proceedToCheckout() {
    if (cart.items.length === 0) {
        alert('El carrito está vacío');
        return;
    }
    
    // Guardar carrito en sesión
    fetch('../cliente/api/save_session_cart.php', {
        method: 'POST',
        body: JSON.stringify(cart.items)
    })
    .then(() => {
        window.location.href = '../cliente/checkout.php';
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar el carrito');
    });
}

/**
 * Aplicar cupón de descuento
 */
function aplicarCupon() {
    const codigo = document.getElementById('cupon-codigo')?.value;
    
    if (!codigo) {
        alert('Ingresa un código de cupón');
        return;
    }
    
    fetch('../cliente/api/validar_cupon.php', {
        method: 'POST',
        body: JSON.stringify({ codigo: codigo })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const descuento = data.descuento;
            const nuevoTotal = cart.getTotal() - descuento;
            
            document.getElementById('cart-descuento').textContent = '-Q' + descuento.toFixed(2);
            document.getElementById('cart-total').textContent = 'Q' + nuevoTotal.toFixed(2);
            
            alert('Cupón aplicado: -Q' + descuento.toFixed(2));
        } else {
            alert('Cupón inválido');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al validar cupón');
    });
}

/**
 * Persistir carrito entre páginas
 */
window.addEventListener('beforeunload', () => {
    cart.saveCart();
});
