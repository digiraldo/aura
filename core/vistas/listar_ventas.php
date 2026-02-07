<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas - Aura Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .badge-estado {
            padding: 5px 10px;
            border-radius: 5px;
        }
        .badge-completada {
            background-color: #28a745;
            color: white;
        }
        .badge-cancelada {
            background-color: #dc3545;
            color: white;
        }
        .table-hover tbody tr:hover {
            cursor: pointer;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body class="bg-light">
    
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard">
                <i class="bi bi-shop"></i> Aura Platform
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($auth->getUser()['nombre_completo']) ?>
                </span>
                <a href="/logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        
        <!-- Encabezado y filtros -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="bi bi-receipt"></i> Historial de Ventas</h2>
                <p class="text-muted">Consulta y gestiona todas las transacciones realizadas</p>
            </div>
            <div class="col-md-4 text-end">
                <?php if ($auth->checkPermission('ventas.crear')): ?>
                <a href="/ventas/nueva" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nueva Venta
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Fecha Desde:</label>
                        <input type="date" class="form-control" id="fechaDesde">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha Hasta:</label>
                        <input type="date" class="form-control" id="fechaHasta">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado:</label>
                        <select class="form-select" id="filtroEstado">
                            <option value="">Todos</option>
                            <option value="completada">Completada</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Buscar Folio:</label>
                        <input type="text" class="form-control" id="buscarFolio" placeholder="VTA-20240202-0001">
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary" id="btnFiltrar">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <button class="btn btn-outline-secondary" id="btnLimpiar">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen estadístico -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total Ventas</h6>
                                <h3 class="mb-0" id="totalVentas">0</h3>
                            </div>
                            <i class="bi bi-receipt-cutoff fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Monto Total</h6>
                                <h3 class="mb-0" id="montoTotal">$0.00</h3>
                            </div>
                            <i class="bi bi-cash-stack fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Promedio</h6>
                                <h3 class="mb-0" id="promedioVenta">$0.00</h3>
                            </div>
                            <i class="bi bi-graph-up-arrow fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Hoy</h6>
                                <h3 class="mb-0" id="ventasHoy">0</h3>
                            </div>
                            <i class="bi bi-calendar-check fs-1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de ventas -->
        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-table"></i> Listado de Ventas</h5>
            </div>
            <div class="card-body">
                
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Método Pago</th>
                                <th>Estado</th>
                                <th>Vendedor</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="ventasTableBody">
                            <!-- Las ventas se cargarán dinámicamente -->
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <p class="mt-3 text-muted">Cargando ventas...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <nav aria-label="Paginación">
                    <ul class="pagination justify-content-center" id="pagination">
                        <!-- Se generará dinámicamente -->
                    </ul>
                </nav>

            </div>
        </div>

    </div>

    <!-- Modal de Detalle de Venta -->
    <div class="modal fade" id="modalDetalleVenta" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-file-text"></i> Detalle de Venta
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detalleVentaContent">
                    <!-- Se cargará dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="imprimirTicket()">
                        <i class="bi bi-printer"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPage = 1;
        let totalPages = 1;
        let currentFilters = {};

        // Cargar ventas al iniciar
        document.addEventListener('DOMContentLoaded', () => {
            cargarVentas();
            configurarEventos();
        });

        // Configurar eventos
        function configurarEventos() {
            document.getElementById('btnFiltrar').addEventListener('click', () => {
                currentFilters = {
                    fechaDesde: document.getElementById('fechaDesde').value,
                    fechaHasta: document.getElementById('fechaHasta').value,
                    estado: document.getElementById('filtroEstado').value,
                    folio: document.getElementById('buscarFolio').value
                };
                currentPage = 1;
                cargarVentas();
            });

            document.getElementById('btnLimpiar').addEventListener('click', () => {
                document.getElementById('fechaDesde').value = '';
                document.getElementById('fechaHasta').value = '';
                document.getElementById('filtroEstado').value = '';
                document.getElementById('buscarFolio').value = '';
                currentFilters = {};
                currentPage = 1;
                cargarVentas();
            });
        }

        // Cargar ventas desde el servidor
        async function cargarVentas() {
            try {
                const params = new URLSearchParams({
                    page: currentPage,
                    limit: 20,
                    ...currentFilters
                });

                const response = await fetch(`/ventas/listar?${params}`);
                const data = await response.json();

                renderizarVentas(data.ventas);
                actualizarEstadisticas(data.estadisticas);
                renderizarPaginacion(data.pagination);

            } catch (error) {
                console.error('Error al cargar ventas:', error);
                mostrarError();
            }
        }

        // Renderizar tabla de ventas
        function renderizarVentas(ventas) {
            const tbody = document.getElementById('ventasTableBody');

            if (ventas.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1"></i>
                            <p class="mt-3">No se encontraron ventas</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = ventas.map(venta => `
                <tr onclick="verDetalle(${venta.id})">
                    <td><strong>${venta.folio}</strong></td>
                    <td>${formatearFecha(venta.fecha_venta)}</td>
                    <td>${venta.cliente_nombre || 'Público General'}</td>
                    <td class="fw-bold text-success">$${parseFloat(venta.total).toFixed(2)}</td>
                    <td>
                        <span class="badge bg-secondary">
                            ${venta.metodo_pago}
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-estado badge-${venta.estado}">
                            ${venta.estado}
                        </span>
                    </td>
                    <td>${venta.vendedor_nombre}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); verDetalle(${venta.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if ($auth->checkPermission('ventas.cancelar')): ?>
                        ${venta.estado === 'completada' ? `
                        <button class="btn btn-sm btn-danger" onclick="event.stopPropagation(); cancelarVenta(${venta.id})">
                            <i class="bi bi-x-circle"></i>
                        </button>
                        ` : ''}
                        <?php endif; ?>
                    </td>
                </tr>
            `).join('');
        }

        // Actualizar estadísticas
        function actualizarEstadisticas(stats) {
            document.getElementById('totalVentas').textContent = stats.total_ventas || 0;
            document.getElementById('montoTotal').textContent = `$${parseFloat(stats.monto_total || 0).toFixed(2)}`;
            document.getElementById('promedioVenta').textContent = `$${parseFloat(stats.promedio || 0).toFixed(2)}`;
            document.getElementById('ventasHoy').textContent = stats.ventas_hoy || 0;
        }

        // Renderizar paginación
        function renderizarPaginacion(pagination) {
            totalPages = pagination.total_pages;
            currentPage = pagination.current_page;

            const paginationEl = document.getElementById('pagination');
            
            if (totalPages <= 1) {
                paginationEl.innerHTML = '';
                return;
            }

            let html = `
                <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="cambiarPagina(${currentPage - 1}); return false;">Anterior</a>
                </li>
            `;

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += `
                        <li class="page-item ${i === currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="cambiarPagina(${i}); return false;">${i}</a>
                        </li>
                    `;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            html += `
                <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="cambiarPagina(${currentPage + 1}); return false;">Siguiente</a>
                </li>
            `;

            paginationEl.innerHTML = html;
        }

        // Cambiar página
        function cambiarPagina(page) {
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            cargarVentas();
        }

        // Ver detalle de venta
        async function verDetalle(ventaId) {
            try {
                const response = await fetch(`/ventas/detalle/${ventaId}`);
                const venta = await response.json();

                const modal = new bootstrap.Modal(document.getElementById('modalDetalleVenta'));
                
                document.getElementById('detalleVentaContent').innerHTML = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Folio:</strong> ${venta.folio}<br>
                            <strong>Fecha:</strong> ${formatearFecha(venta.fecha_venta)}<br>
                            <strong>Estado:</strong> <span class="badge badge-${venta.estado}">${venta.estado}</span>
                        </div>
                        <div class="col-md-6">
                            <strong>Vendedor:</strong> ${venta.vendedor_nombre}<br>
                            <strong>Cliente:</strong> ${venta.cliente_nombre || 'Público General'}<br>
                            <strong>Método de Pago:</strong> ${venta.metodo_pago}
                        </div>
                    </div>

                    <h6>Productos:</h6>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unit.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${venta.items.map(item => `
                                <tr>
                                    <td>${item.producto_nombre}</td>
                                    <td>${item.cantidad}</td>
                                    <td>$${parseFloat(item.precio_unitario).toFixed(2)}</td>
                                    <td>$${parseFloat(item.subtotal).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>

                    <div class="text-end">
                        <h5>Total: <span class="text-success">$${parseFloat(venta.total).toFixed(2)}</span></h5>
                    </div>
                `;

                modal.show();

            } catch (error) {
                console.error('Error al cargar detalle:', error);
                alert('Error al cargar el detalle de la venta');
            }
        }

        // Cancelar venta
        async function cancelarVenta(ventaId) {
            if (!confirm('¿Está seguro de cancelar esta venta?')) return;

            try {
                const response = await fetch(`/ventas/cancelar/${ventaId}`, {
                    method: 'POST'
                });

                const result = await response.json();

                if (result.success) {
                    alert('Venta cancelada exitosamente');
                    cargarVentas();
                } else {
                    alert(`Error: ${result.message}`);
                }

            } catch (error) {
                console.error('Error al cancelar venta:', error);
                alert('Error al cancelar la venta');
            }
        }

        // Formatear fecha
        function formatearFecha(fecha) {
            const d = new Date(fecha);
            return d.toLocaleDateString('es-MX', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Mostrar error
        function mostrarError() {
            document.getElementById('ventasTableBody').innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5 text-danger">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                        <p class="mt-3">Error al cargar las ventas</p>
                    </td>
                </tr>
            `;
        }

        // Imprimir ticket
        function imprimirTicket() {
            window.print();
        }
    </script>

</body>
</html>
