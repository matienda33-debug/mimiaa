<?php
header('Location: /tiendaAA/modules/mod1/dashboard.php');
exit();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            z-index: 1000;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 2px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }
        .sidebar .nav-link i {
            width: 25px;
            text-align: center;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        .navbar-custom {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            border: none;
            margin-bottom: 20px;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        .stat-sales { background: linear-gradient(45deg, #667eea, #764ba2); color: white; }
        .stat-users { background: linear-gradient(45deg, #10b981, #34d399); color: white; }
        .stat-inventory { background: linear-gradient(45deg, #f59e0b, #fbbf24); color: white; }
        .stat-profit { background: linear-gradient(45deg, #ef4444, #f87171); color: white; }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #6b7280;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .table-responsive {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .table thead th {
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
        }
        .table tbody tr:hover {
            background-color: #f9fafb;
        }
        .badge-online { background-color: #10b981; }
        .badge-store { background-color: #3b82f6; }
        .badge-pending { background-color: #f59e0b; }
        .badge-completed { background-color: #10b981; }
        .badge-cancelled { background-color: #ef4444; }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header p-4">
            <h3 class="mb-0">MI&MI STORE</h3>
            <small class="text-white-50">Ajitos Kids</small>
            <hr class="bg-white">
            <div class="user-info">
                <p class="mb-1"><?php echo htmlspecialchars($user_info['nombres'] . ' ' . $user_info['apellidos']); ?></p>
                <small class="badge bg-light text-dark"><?php echo htmlspecialchars($user_info['rol']); ?></small>
            </div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            
            <?php if ($user_info['nivel_acceso'] >= 80): ?>
            <li class="nav-item">
                <a class="nav-link" href="productos.php">
                    <i class="fas fa-box"></i> Productos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="banners.php">
                    <i class="fas fa-images"></i> Banners Inicio
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="inventario.php">
                    <i class="fas fa-warehouse"></i> Inventario
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link" href="ventas.php">
                    <i class="fas fa-shopping-cart"></i> Ventas
                </a>
            </li>
            
            <?php if ($user_info['nivel_acceso'] >= 80): ?>
            <li class="nav-item">
                <a class="nav-link" href="clientes.php">
                    <i class="fas fa-users"></i> Clientes
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($user_info['nivel_acceso'] >= 100): ?>
            <li class="nav-item">
                <a class="nav-link" href="contabilidad.php">
                    <i class="fas fa-chart-line"></i> Contabilidad
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reportes.php">
                    <i class="fas fa-chart-bar"></i> Reportes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="usuarios.php">
                    <i class="fas fa-user-cog"></i> Usuarios
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item mt-4">
                <a class="nav-link text-warning" href="../index.php">
                    <i class="fas fa-store"></i> Ir a Tienda
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer p-3">
            <small class="text-white-50">© <?php echo date('Y'); ?> Tienda MI&MI</small>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Navbar -->
        <nav class="navbar-custom">
            <div class="container-fluid">
                <button class="btn btn-primary d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>
                            <?php echo htmlspecialchars($user_info['nombres']); ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Configuración</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-sales">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3 class="stat-value">Q12,450</h3>
                    <p class="stat-label">VENTAS HOY</p>
                    <small class="text-success"><i class="fas fa-arrow-up"></i> 12% vs ayer</small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-users">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="stat-value">48</h3>
                    <p class="stat-label">CLIENTES NUEVOS</p>
                    <small class="text-success"><i class="fas fa-arrow-up"></i> 8% vs ayer</small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-inventory">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <h3 class="stat-value">156</h3>
                    <p class="stat-label">PRODUCTOS BAJOS</p>
                    <small class="text-danger"><i class="fas fa-arrow-down"></i> 5 items más</small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon stat-profit">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="stat-value">Q8,325</h3>
                    <p class="stat-label">UTILIDAD HOY</p>
                    <small class="text-success"><i class="fas fa-arrow-up"></i> 15% vs ayer</small>
                </div>
            </div>
        </div>

        <!-- Charts and Tables -->
        <div class="row">
            <div class="col-lg-8">
                <div class="chart-container">
                    <h5 class="mb-4">Ventas de la Semana</h5>
                    <canvas id="salesChart" height="250"></canvas>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="chart-container">
                    <h5 class="mb-4">Ventas por Categoría</h5>
                    <canvas id="categoryChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="chart-container">
                    <h5 class="mb-4">Órdenes Recientes</h5>
                    <div class="table-responsive">
                        <table class="table table-hover" id="recentOrders">
                            <thead>
                                <tr>
                                    <th># Orden</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th>Método</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>ORD-2024-001</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <img src="https://ui-avatars.com/api/?name=Juan+Perez&background=667eea&color=fff" 
                                                     class="user-avatar" alt="Juan Perez">
                                            </div>
                                            <div>
                                                <strong>Juan Perez</strong><br>
                                                <small>juan@email.com</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>20/03/2024 14:30</td>
                                    <td><span class="badge badge-online">Online</span></td>
                                    <td>Q450.00</td>
                                    <td><span class="badge badge-pending">Pendiente</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>ORD-2024-002</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <img src="https://ui-avatars.com/api/?name=Maria+Garcia&background=10b981&color=fff" 
                                                     class="user-avatar" alt="Maria Garcia">
                                            </div>
                                            <div>
                                                <strong>Maria Garcia</strong><br>
                                                <small>maria@email.com</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>20/03/2024 12:15</td>
                                    <td><span class="badge badge-store">Tienda</span></td>
                                    <td>Q890.00</td>
                                    <td><span class="badge badge-completed">Completada</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>ORD-2024-003</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <img src="https://ui-avatars.com/api/?name=Carlos+Lopez&background=f59e0b&color=fff" 
                                                     class="user-avatar" alt="Carlos Lopez">
                                            </div>
                                            <div>
                                                <strong>Carlos Lopez</strong><br>
                                                <small>carlos@email.com</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>19/03/2024 16:45</td>
                                    <td><span class="badge badge-online">Online</span></td>
                                    <td>Q320.00</td>
                                    <td><span class="badge badge-cancelled">Cancelada</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Initialize DataTable
        $(document).ready(function() {
            $('#recentOrders').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                }
            });
        });
        
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                datasets: [{
                    label: 'Ventas (Q)',
                    data: [4500, 5200, 4800, 6500, 7200, 8500, 9200],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['Mujer', 'Hombre', 'Niños', 'Bebés', 'Accesorios'],
                datasets: [{
                    data: [35, 25, 20, 10, 10],
                    backgroundColor: [
                        '#667eea',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#8b5cf6'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>