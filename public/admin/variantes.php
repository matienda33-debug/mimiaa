<?php
// public/admin/variantes.php
require_once '../../app/config/database.php';
require_once '../../app/models/Auth.php';
require_once '../../app/models/Producto.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$productoModel = new Producto($db);

// Verificar acceso
$auth->requireAccess(80, '../../login.php');

$id_producto = $_GET['id'] ?? 0;
if (!$id_producto) {
    header('Location: productos.php');
    exit();
}

$producto = $productoModel->obtenerProductoPorId($id_producto);
if (!$producto) {
    header('Location: productos.php');
    exit();
}

$variantes = $productoModel->obtenerVariantes($id_producto);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $csrf_token) {
        die('Token CSRF inválido');
    }
    
    switch ($_POST['action'] ?? '') {
        case 'crear_variante':
            $data = [
                'id_producto_raiz' => $id_producto,
                'talla' => $_POST['talla'],
                'color' => $_POST['color'],
                'color_hex' => $_POST['color_hex'],
                'stock_tienda' => $_POST['stock_tienda'],
                'stock_bodega' => $_POST['stock_bodega'],
                'stock_minimo' => $_POST['stock_minimo'],
                'ubicacion_tienda' => $_POST['ubicacion_tienda'],
                'ubicacion_bodega' => $_POST['ubicacion_bodega'],
                'estado' => $_POST['estado']
            ];
            
            $result = $productoModel->crearVariante($data);
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'danger';
            break;
    }
}

// Definir tallas y colores disponibles
$tallas = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'Única'];
$colores = [
    ['nombre' => 'Blanco', 'hex' => '#FFFFFF'],
    ['nombre' => 'Negro', 'hex' => '#000000'],
    ['nombre' => 'Rojo', 'hex' => '#FF0000'],
    ['nombre' => 'Azul', 'hex' => '#0000FF'],
    ['nombre' => 'Verde', 'hex' => '#00FF00'],
    ['nombre' => 'Amarillo', 'hex' => '#FFFF00'],
    ['nombre' => 'Rosa', 'hex' => '#FFC0CB'],
    ['nombre' => 'Morado', 'hex' => '#800080'],
    ['nombre' => 'Gris', 'hex' => '#808080'],
    ['nombre' => 'Beige', 'hex' => '#F5F5DC']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Variantes - <?php echo htmlspecialchars($producto['nombre']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .color-box {
            width: 20px;
            height: 20px;
            display: inline-block;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            margin-right: 5px;
            vertical-align: middle;
        }
        .badge-tienda {
            background-color: #10b981;
            color: white;
        }
        .badge-bodega {
            background-color: #3b82f6;
            color: white;
        }
        .stock-cell {
            min-width: 120px;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <nav class="navbar-custom">
            <div class="container-fluid">
                <button class="btn btn-primary d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h4 class="mb-0">Variantes de Producto</h4>
                    <small><?php echo htmlspecialchars($producto['nombre']); ?> - <?php echo htmlspecialchars($producto['codigo_producto']); ?></small>
                </div>
                <div>
                    <a href="productos.php" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#crearVarianteModal">
                        <i class="fas fa-plus me-2"></i>Nueva Variante
                    </button>
                </div>
            </div>
        </nav>
        
        <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Talla</th>
                                        <th>Color</th>
                                        <th class="stock-cell">Stock Tienda</th>
                                        <th class="stock-cell">Stock Bodega</th>
                                        <th>Stock Total</th>
                                        <th>Ubicaciones</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($variantes as $variante): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-dark"><?php echo htmlspecialchars($variante['talla']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($variante['color_hex']): ?>
                                            <div class="color-box" style="background-color: <?php echo $variante['color_hex']; ?>;"></div>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($variante['color']); ?>
                                        </td>
                                        <td class="stock-cell">
                                            <span class="badge badge-tienda"><?php echo $variante['stock_tienda']; ?> unidades</span>
                                            <br>
                                            <small><?php echo htmlspecialchars($variante['ubicacion_tienda']); ?></small>
                                        </td>
                                        <td class="stock-cell">
                                            <span class="badge badge-bodega"><?php echo $variante['stock_bodega']; ?> unidades</span>
                                            <br>
                                            <small><?php echo htmlspecialchars($variante['ubicacion_bodega']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo $variante['stock_tienda'] + $variante['stock_bodega']; ?></strong>
                                            <?php if ($variante['nivel_stock'] == 'bajo'): ?>
                                            <br><small class="text-danger">Stock bajo (mín: <?php echo $variante['stock_minimo']; ?>)</small>
                                            <?php elseif ($variante['nivel_stock'] == 'agotado'): ?>
                                            <br><small class="text-danger">AGOTADO</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>T: <?php echo htmlspecialchars($variante['ubicacion_tienda'] ?: 'N/A'); ?></small>
                                            <br>
                                            <small>B: <?php echo htmlspecialchars($variante['ubicacion_bodega'] ?: 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $variante['estado'] == 'disponible' ? 'success' : ($variante['estado'] == 'agotado' ? 'warning' : 'secondary'); ?>">
                                                <?php echo ucfirst($variante['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-warning" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="Ajustar Stock">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Crear Variante -->
    <div class="modal fade" id="crearVarianteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nueva Variante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="crear_variante">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Talla *</label>
                                    <select class="form-select" name="talla" required>
                                        <option value="">Seleccionar...</option>
                                        <?php foreach ($tallas as $talla): ?>
                                        <option value="<?php echo $talla; ?>"><?php echo $talla; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Color *</label>
                                    <select class="form-select" name="color" id="colorSelect" required>
                                        <option value="">Seleccionar...</option>
                                        <?php foreach ($colores as $color): ?>
                                        <option value="<?php echo $color['nombre']; ?>" data-hex="<?php echo $color['hex']; ?>">
                                            <?php echo $color['nombre']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                        <option value="otro">Otro color...</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3" id="colorCustomDiv" style="display: none;">
                                    <label class="form-label">Nombre del Color</label>
                                    <input type="text" class="form-control" name="color_custom" id="colorCustom">
                                    
                                    <label class="form-label mt-2">Código Hex</label>
                                    <div class="input-group">
                                        <span class="input-group-text">#</span>
                                        <input type="text" class="form-control" name="color_hex_custom" id="colorHexCustom" maxlength="6">
                                    </div>
                                    <small class="text-muted">Ejemplo: FF5733</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Estado</label>
                                    <select class="form-select" name="estado">
                                        <option value="disponible">Disponible</option>
                                        <option value="agotado">Agotado</option>
                                        <option value="inactivo">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Stock Tienda *</label>
                                            <input type="number" class="form-control" name="stock_tienda" value="0" min="0" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Stock Bodega *</label>
                                            <input type="number" class="form-control" name="stock_bodega" value="0" min="0" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Stock Mínimo</label>
                                    <input type="number" class="form-control" name="stock_minimo" value="5" min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Ubicación en Tienda</label>
                                    <input type="text" class="form-control" name="ubicacion_tienda" placeholder="Ej: Estante A-3">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Ubicación en Bodega</label>
                                    <input type="text" class="form-control" name="ubicacion_bodega" placeholder="Ej: Rack B-2">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Crear Variante</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Manejar selección de color
        document.getElementById('colorSelect').addEventListener('change', function() {
            const colorCustomDiv = document.getElementById('colorCustomDiv');
            const colorCustom = document.getElementById('colorCustom');
            const colorHexCustom = document.getElementById('colorHexCustom');
            
            if (this.value === 'otro') {
                colorCustomDiv.style.display = 'block';
                colorCustom.required = true;
                colorHexCustom.required = true;
            } else {
                colorCustomDiv.style.display = 'none';
                colorCustom.required = false;
                colorHexCustom.required = false;
                
                // Establecer hex del color seleccionado
                const selectedOption = this.options[this.selectedIndex];
                const hex = selectedOption.getAttribute('data-hex');
                colorHexCustom.value = hex ? hex.replace('#', '') : '';
            }
        });
        
        // Toggle sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Previsualización del color
        document.getElementById('colorHexCustom').addEventListener('input', function() {
            const preview = document.getElementById('colorPreview');
            if (preview) {
                preview.style.backgroundColor = '#' + this.value;
            }
        });
    </script>
</body>
</html>