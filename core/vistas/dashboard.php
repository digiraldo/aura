<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Aura Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-primary navbar-expand-lg shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="/dashboard">
                <i class="bi bi-lightning-charge-fill"></i> Aura Platform
            </a>
            
            <div class="d-flex align-items-center text-white">
                <span class="me-3">
                    <i class="bi bi-person-circle"></i>
                    <?= htmlspecialchars($auth->getActiveRole()->getDisplayName()) ?>
                </span>
                <a href="/logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block bg-white sidebar border-end vh-100 position-sticky top-0">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="/dashboard">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        
                        <?php if ($auth->checkPermission('ventas.crear')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/ventas/nueva">
                                <i class="bi bi-cart-plus"></i> Nueva Venta
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($auth->checkPermission('ventas.listar')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/ventas/listar">
                                <i class="bi bi-list-ul"></i> Ventas
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($auth->checkPermission('usuarios.administrar')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/usuarios">
                                <i class="bi bi-people"></i> Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/plugins">
                                <i class="bi bi-puzzle"></i> Plugins
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">Dashboard</h1>
                    <div class="text-muted">
                        <i class="bi bi-calendar3"></i>
                        <?= date('d/m/Y H:i') ?>
                    </div>
                </div>

                <!-- Cards de Resumen -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Ventas Hoy</h6>
                                        <h2 class="card-title mb-0">0</h2>
                                    </div>
                                    <i class="bi bi-cart-check fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Total Hoy</h6>
                                        <h2 class="card-title mb-0">$0.00</h2>
                                    </div>
                                    <i class="bi bi-cash-stack fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Productos</h6>
                                        <h2 class="card-title mb-0">0</h2>
                                    </div>
                                    <i class="bi bi-box-seam fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Clientes</h6>
                                        <h2 class="card-title mb-0">0</h2>
                                    </div>
                                    <i class="bi bi-people fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráfica de Ventas -->
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-graph-up"></i> Ventas de los Últimos 7 Días
                            </div>
                            <div class="card-body">
                                <canvas id="ventasChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-exclamation-triangle"></i> Productos con Stock Bajo
                            </div>
                            <div class="card-body">
                                <p class="text-muted text-center py-4">No hay productos con stock bajo</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información del Sistema -->
                <div class="card mt-4 border-0 bg-light">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <i class="bi bi-shield-check text-success fs-3"></i>
                                <p class="mt-2 mb-0"><small><strong>Multi-Tenant Seguro</strong></small></p>
                                <p class="text-muted"><small>Aislamiento por esquemas</small></p>
                            </div>
                            <div class="col-md-4">
                                <i class="bi bi-lock-fill text-primary fs-3"></i>
                                <p class="mt-2 mb-0"><small><strong>RBAC Jerárquico</strong></small></p>
                                <p class="text-muted"><small>Control de acceso granular</small></p>
                            </div>
                            <div class="col-md-4">
                                <i class="bi bi-puzzle text-info fs-3"></i>
                                <p class="mt-2 mb-0"><small><strong>Sistema de Plugins</strong></small></p>
                                <p class="text-muted"><small>Extensibilidad sin límites</small></p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gráfica de ventas (Chart.js)
        const ctx = document.getElementById('ventasChart');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                datasets: [{
                    label: 'Ventas ($)',
                    data: [0, 0, 0, 0, 0, 0, 0],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
