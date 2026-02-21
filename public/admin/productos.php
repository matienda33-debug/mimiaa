<?php
// public/admin/productos.php
require_once '../../app/config/config.php';
require_once '../../app/models/Producto.php';
require_once '../../app/models/Auth.php';

// Verificar acceso (solo admin y trabajador1)
requireRole(80, '../../login.php');

$productoModel = new Producto();
$auth = new Auth();
$userInfo = $auth->getUserInfo();

$page_title = 'Gestión de Productos';

// Obtener datos para filtros
$departamentos = $productoModel->getDepartamentos();
$tipos = $productoModel->getTiposRopa();
$marcas = $productoModel->getMarcas();

// Parámetros de búsqueda
$filters = [
    'id_departamento' => $_GET['departamento'] ?? '',
    'id_tipo' => $_GET['tipo'] ?? '',
    'id_marca' => $_GET['marca'] ?? '',
    'etiqueta' => $_GET['etiqueta'] ?? '',
    'estado' => $_GET['estado'] ?? 'activo',
    'es_kids' => $_GET['kids'] ?? '',
    'busqueda' => $_GET['busqueda'] ?? '',
    'precio_min' => $_GET['precio_min'] ?? '',
    'precio_max' => $_GET['precio_max'] ?? ''
];

// Paginación
$page = $_GET['page'] ?? 1;
$perPage = $_GET['per_page'] ?? 20;

// Obtener productos
$result = $productoModel->getAll($filters, $page, $perPage);
$products = $result['products'];
$pagination = [
    'total' => $result['total'],
    'page' => $result['page'],
    'per_page' => $result['per_page'],
    'total_pages' => $result['total_pages']
];

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        die('Token CSRF inválido');
    }
    
    $action = $_POST['action'] ?? '';
    $productId = $_POST['id_producto'] ?? 0;
    
    switch ($action) {
        case 'delete':
            if ($productId) {
                $result = $productoModel->delete($productId);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
            }
            redirect('/admin/productos.php');
            break;
            
        case 'bulk_delete':
            if (!empty($_POST['selected_products'])) {
                $deleted = 0;
                foreach ($_POST['selected_products'] as $id) {
                    $result = $productoModel->delete($id);
                    if ($result['success']) {
                        $deleted++;
                    }
                }
                $_SESSION['success'] = "Se desactivaron $deleted productos";
            }
            redirect('/admin/productos.php');
            break;
            
        case 'bulk_update':
            if (!empty($_POST['selected_products']) && !empty($_POST['bulk_action'])) {
                $updated = 0;
                foreach ($_POST['selected_products'] as $id) {
                    $data = [];
                    switch ($_POST['bulk_action']) {
                        case 'activate':
                            $data['estado'] = 'activo';
                            break;
                        case 'deactivate':
                            $data['estado'] = 'inactivo';
                            break;
                        case 'mark_sale':
                            $data['etiqueta'] = 'oferta';
                            break;
                        case 'mark_new':
                            $data['etiqueta'] = 'nuevo';
                            break;
                    }
                    
                    if (!empty($data)) {
                        $result = $productoModel->update($id, $data);
                        if ($result['success']) {
                            $updated++;
                        }
                    }
                }
                $_SESSION['success'] = "Se actualizaron $updated productos";
            }
            redirect('/admin/productos.php');
            break;
    }
}
?>
<?php include '../includes/admin_header.php'; ?>

<div class="container-fluid">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Gestión de Productos</h1>
        <div>
            <a href="/admin/producto_editar.php?action=new" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>Nuevo Producto
            </a>
            <a href="/admin/inventario.php" class="btn btn-outline-primary ms-2">
                <i class="fas fa-warehouse me-2"></i>Inventario
            </a>
        </div>
    </div>
    
    <!-- Alertas -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Departamento</label>
                    <select name="departamento" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($departamentos as $dep): ?>
                        <option value="<?php echo $dep['id_departamento']; ?>" 
                            <?php echo $filters['id_departamento'] == $dep['id_departamento'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dep['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($tipos as $tipo): ?>
                        <option value="<?php echo $tipo['id_tipo']; ?>"
                            <?php echo $filters['id_tipo'] == $tipo['id_tipo'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tipo['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Marca</label>
                    <select name="marca" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($marcas as $marca): ?>
                        <option value="<?php echo $marca['id_marca']; ?>"
                            <?php echo $filters['id_marca'] == $marca['id_marca'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($marca['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Etiqueta</label>
                    <select name="etiqueta" class="form-select">
                        <option value="">Todas</option>
                        <option value="oferta" <?php echo $filters['etiqueta'] == 'oferta' ? 'selected' : ''; ?>>Oferta</option>
                        <option value="nuevo" <?php echo $filters['etiqueta'] == 'nuevo' ? 'selected' : ''; ?>>Nuevo</option>
                        <option value="reingreso" <?php echo $filters['etiqueta'] == 'reingreso' ? 'selected' : ''; ?>>Reingreso</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="activo" <?php echo $filters['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $filters['estado'] == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        <option value="agotado" <?php echo $filters['estado'] == 'agotado' ? 'selected' : ''; ?>>Agotado</option>
                        <option value="">Todos</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Línea</label>
                    <select name="kids" class="form-select">
                        <option value="">Todas</option>
                        <option value="0" <?php echo $filters['es_kids'] === '0' ? 'selected' : ''; ?>>MI&MI</option>
                        <option value="1" <?php echo $filters['es_kids'] === '1' ? 'selected' : ''; ?>>Ajitos Kids</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Precio Mínimo</label>
                    <input type="number" class="form-control" name="precio_min" 
                           placeholder="0.00" step="0.01"
                           value="<?php echo htmlspecialchars($filters['precio_min']); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Precio Máximo</label>
                    <input type="number" class="form-control" name="precio_max" 
                           placeholder="9999.99" step="0.01"
                           value="<?php echo htmlspecialchars($filters['precio_max']); ?>">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Búsqueda</label>
                    <input type="text" class="form-control" name="busqueda" 
                           placeholder="Nombre, código, descripción..."
                           value="<?php echo htmlspecialchars($filters['busqueda']); ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid w-100">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Filtrar
                        </button>
                    </div>
                </div>
                
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="/admin/productos.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Limpiar filtros
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Aplicar filtros
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Acciones masivas -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" id="bulkActionsForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label" for="selectAll">
                                Seleccionar todos
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <select name="bulk_action" class="form-select" required>
                            <option value="">Acción masiva...</option>
                            <option value="activate">Activar seleccionados</option>
                            <option value="deactivate">Desactivar seleccionados</option>
                            <option value="mark_sale">Marcar como oferta</option>
                            <option value="mark_new">Marcar como nuevo</option>
                            <option value="delete">Eliminar seleccionados</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <button type="submit" name="action" value="bulk_update" class="btn btn-warning">
                            <i class="fas fa-play me-2"></i>Ejecutar acción
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tabla de productos -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="50"></th>
                            <th width="80">Imagen</th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Departamento</th>
                            <th>Precios</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <i class="fas fa-box-open fa-2x text-muted mb-3"></i>
                                <p class="text-muted">No se encontraron productos</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($products as $product): 
                            $stockTotal = $product['stock_total'] ?? 0;
                            $precioFinal = $product['precio_oferta'] ?? $product['precio_venta'];
                        ?>
                        <tr>
                            <td>
                                <input class="form-check-input product-checkbox" 
                                       type="checkbox" 
                                       name="selected_products[]" 
                                       value="<?php echo $product['id_producto_raiz']; ?>">
                            </td>
                            <td>
                                <img src="<?php echo getProductImageUrl($product['foto_principal'] ?? '', true); ?>" 
                                     alt="<?php echo htmlspecialchars($product['nombre']); ?>"
                                     class="product-thumb">
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($product['codigo_producto']); ?></strong>
                                <?php if ($product['es_kids']): ?>
                                <br><span class="badge bg-warning">Ajitos Kids</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($product['nombre']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($product['tipo_nombre'] ?? ''); ?></small>
                                <?php if ($product['etiqueta']): ?>
                                <br>
                                <span class="badge bg-<?php echo $product['etiqueta'] == 'oferta' ? 'danger' : 'success'; ?>">
                                    <?php echo ucfirst($product['etiqueta']); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['departamento_nombre'] ?? ''); ?></td>
                            <td>
                                <?php if ($userInfo['nivel_acceso'] >= 100): // Solo admin ve precio compra ?>
                                <div class="text-danger">
                                    <small>Compra: Q<?php echo number_format($product['precio_compra'], 2); ?></small>
                                </div>
                                <?php endif; ?>
                                <div class="text-success">
                                    <strong>Venta: Q<?php echo number_format($precioFinal, 2); ?></strong>
                                </div>
                                <?php if ($product['precio_oferta']): ?>
                                <div class="text-warning">
                                    <small>Original: Q<?php echo number_format($product['precio_venta'], 2); ?></small>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="stock-indicator <?php echo $stockTotal > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                    <?php echo $stockTotal; ?> unidades
                                </span>
                                <?php if ($stockTotal <= 10 && $stockTotal > 0): ?>
                                <br><small class="text-warning">Stock bajo</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $product['estado'] == 'activo' ? 'success' : 
                                          ($product['estado'] == 'agotado' ? 'warning' : 'secondary'); 
                                ?>">
                                    <?php echo ucfirst($product['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="/producto.php?id=<?php echo $product['id_producto_raiz']; ?>" 
                                       class="btn btn-sm btn-outline-primary" target="_blank" title="Ver en tienda">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="/admin/producto_editar.php?id=<?php echo $product['id_producto_raiz']; ?>" 
                                       class="btn btn-sm btn-outline-warning" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="/admin/producto_variantes.php?id=<?php echo $product['id_producto_raiz']; ?>" 
                                       class="btn btn-sm btn-outline-info" title="Variantes">
                                        <i class="fas fa-list"></i>
                                    </a>
                                    <a href="/admin/producto_fotos.php?id=<?php echo $product['id_producto_raiz']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Fotos">
                                        <i class="fas fa-images"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-product-btn" 
                                            data-id="<?php echo $product['id_producto_raiz']; ?>"
                                            data-name="<?php echo htmlspecialchars($product['nombre']); ?>"
                                            title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($pagination['total_pages'] > 1): ?>
            <nav aria-label="Paginación de productos">
                <ul class="pagination justify-content-center">
                    <!-- Anterior -->
                    <li class="page-item <?php echo $pagination['page'] == 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" 
                           href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['page'] - 1])); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <!-- Números de página -->
                    <?php 
                    $startPage = max(1, $pagination['page'] - 2);
                    $endPage = min($pagination['total_pages'], $pagination['page'] + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                    <li class="page-item <?php echo $i == $pagination['page'] ? 'active' : ''; ?>">
                        <a class="page-link" 
                           href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <!-- Siguiente -->
                    <li class="page-item <?php echo $pagination['page'] == $pagination['total_pages'] ? 'disabled' : ''; ?>">
                        <a class="page-link" 
                           href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['page'] + 1])); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="text-center text-muted">
                Mostrando <?php echo (($pagination['page'] - 1) * $pagination['per_page']) + 1; ?> - 
                <?php echo min($pagination['page'] * $pagination['per_page'], $pagination['total']); ?> 
                de <?php echo $pagination['total']; ?> productos
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Formulario para eliminación -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id_producto" id="deleteProductId">
</form>

<?php include '../includes/admin_footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Seleccionar todos
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.product-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
    
    // Acciones masivas
    document.getElementById('bulkActionsForm').addEventListener('submit', function(e) {
        const action = this.querySelector('select[name="bulk_action"]').value;
        const checkboxes = document.querySelectorAll('.product-checkbox:checked');
        
        if (checkboxes.length === 0) {
            e.preventDefault();
            alert('Por favor selecciona al menos un producto');
            return;
        }
        
        if (!action) {
            e.preventDefault();
            alert('Por favor selecciona una acción');
            return;
        }
        
        if (action === 'delete') {
            e.preventDefault();
            if (confirm(`¿Estás seguro de eliminar ${checkboxes.length} producto(s)?`)) {
                // Cambiar a formulario de eliminación masiva
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = 'csrf_token';
                csrf.value = '<?php echo generateCSRFToken(); ?>';
                form.appendChild(csrf);
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'bulk_delete';
                form.appendChild(actionInput);
                
                checkboxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_products[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    });
    
    // Eliminación individual
    document.querySelectorAll('.delete-product-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.id;
            const productName = this.dataset.name;
            
            if (confirm(`¿Estás seguro de desactivar el producto "${productName}"?`)) {
                document.getElementById('deleteProductId').value = productId;
                document.getElementById('deleteForm').submit();
            }
        });
    });
});
</script>