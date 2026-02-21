<?php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 12;
$offset = ($pagina - 1) * $por_pagina;

// Obtener productos en oferta
$query = "SELECT pr.*, d.nombre as departamento_nombre,
                 (SELECT nombre_archivo FROM productos_raiz_fotos 
                  WHERE id_producto_raiz = pr.id_raiz AND es_principal = 1 LIMIT 1) as imagen_principal
          FROM productos_raiz pr
          INNER JOIN departamentos d ON pr.id_departamento = d.id_departamento
          WHERE pr.activo = 1 AND d.activo = 1 AND pr.etiqueta = 'oferta'
          ORDER BY pr.fecha_creacion DESC
          LIMIT :offset, :por_pagina";

$stmt = $db->prepare($query);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':por_pagina', $por_pagina, PDO::PARAM_INT);
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar total
$count_query = "SELECT COUNT(*) as total FROM productos_raiz pr
                INNER JOIN departamentos d ON pr.id_departamento = d.id_departamento
                WHERE pr.activo = 1 AND d.activo = 1 AND pr.etiqueta = 'oferta'";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total / $por_pagina);

if (!isset($_SESSION)) {
    session_start();
}
$carrito_count = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ofertas - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-custom.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .product-card {
            transition: transform 0.3s;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            overflow: hidden;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
            background: #f0f0f0;
        }
        .price {
            color: #1abc9c;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .badge-oferta {
            background: #ff6b6b;
            color: white;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                <li class="breadcrumb-item active">Ofertas</li>
            </ol>
        </nav>
        
        <div class="row mb-4">
            <div class="col-md-9">
                <h1><i class="fas fa-tag me-2" style="color: #ff6b6b;"></i>Ofertas Especiales</h1>
                <p class="text-muted">Se encontraron <?php echo $total; ?> productos con descuento</p>
            </div>
        </div>
        
        <!-- Productos -->
        <div class="row g-3">
            <?php if (!empty($productos)): ?>
                <?php foreach ($productos as $producto): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="product-card">
                        <div class="position-relative">
                            <img src="../uploads/productos/<?php echo htmlspecialchars($producto['imagen_principal'] ?? 'no-image.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                 class="product-image">
                            <span class="badge badge-oferta position-absolute top-0 end-0 m-2">¡OFERTA!</span>
                            <?php if ($producto['es_ajitos']): ?>
                                <span class="badge badge-ajitos position-absolute top-0 start-0 m-2">Ajitos</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                            <p class="card-text text-muted small">
                                <?php echo htmlspecialchars(substr($producto['descripcion'], 0, 50)) . '...'; ?>
                            </p>
                            <p class="price">Q<?php echo number_format($producto['precio_venta'], 2); ?></p>
                            <button class="btn btn-sm btn-danger w-100" 
                                    onclick="agregarAlCarrito(<?php echo $producto['id_raiz']; ?>)">
                                <i class="fas fa-shopping-cart me-1"></i> Comprar Ahora
                            </button>
                            <a href="producto.php?id=<?php echo $producto['id_raiz']; ?>" 
                               class="btn btn-sm btn-outline-secondary w-100 mt-2">
                                <i class="fas fa-eye me-1"></i> Ver Detalles
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i> No hay ofertas disponibles en este momento.
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <nav aria-label="Page navigation" class="mt-5">
            <ul class="pagination justify-content-center">
                <?php if ($pagina > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=1">Primera</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>">Anterior</a>
                    </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($pagina < $total_paginas): ?>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>">Siguiente</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=<?php echo $total_paginas; ?>">Última</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
