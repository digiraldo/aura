<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Venta - Aura Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .product-card {
            cursor: pointer;
            transition: all 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .cart-item {
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
        }
        .total-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
        }
        .payment-method {
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        .payment-method:hover {
            border-color: #667eea;
        }
        .payment-method.active {
            border-color: #667eea;
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
        <div class="row">
            
            <!-- Columna izquierda: Selección de productos -->
            <div class="col-md-7">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-search"></i> Buscar Productos
                        </h5>
                    </div>
                    <div class="card-body">
                        
                        <!-- Buscador -->
                        <div class="mb-4">
                            <input type="text" 
                                   id="searchProduct" 
                                   class="form-control form-control-lg" 
                                   placeholder="Buscar por nombre, código o SKU..."
                                   autofocus>
                        </div>

                        <!-- Grid de productos -->
                        <div class="row" id="productsGrid">
                            <!-- Los productos se cargarán dinámicamente aquí -->
                            <div class="col-12 text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-3 text-muted">Cargando productos...</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Columna derecha: Carrito y pago -->
            <div class="col-md-5">
                
                <!-- Carrito de compras -->
                <div class="card shadow mb-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-cart3"></i> Carrito de Venta
                            <span class="badge bg-light text-dark float-end" id="cartCount">0</span>
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <div id="cartItems">
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-cart-x" style="font-size: 3rem;"></i>
                                <p class="mt-3">Carrito vacío</p>
                                <small>Selecciona productos para comenzar</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Totales -->
                <div class="total-section mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span class="fs-5" id="subtotal">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>IVA (16%):</span>
                        <span class="fs-5" id="iva">$0.00</span>
                    </div>
                    <hr class="bg-white">
                    <div class="d-flex justify-content-between">
                        <span class="fs-4 fw-bold">TOTAL:</span>
                        <span class="fs-3 fw-bold" id="total">$0.00</span>
                    </div>
                </div>

                <!-- Métodos de pago -->
                <div class="card shadow mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-credit-card"></i> Método de Pago</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2" id="paymentMethods">
                            <div class="col-6">
                                <div class="payment-method card text-center p-3 active" data-method="efectivo">
                                    <i class="bi bi-cash-coin fs-2"></i>
                                    <div class="mt-2">Efectivo</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="payment-method card text-center p-3" data-method="tarjeta">
                                    <i class="bi bi-credit-card-2-front fs-2"></i>
                                    <div class="mt-2">Tarjeta</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="payment-method card text-center p-3" data-method="transferencia">
                                    <i class="bi bi-bank fs-2"></i>
                                    <div class="mt-2">Transferencia</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="payment-method card text-center p-3" data-method="mixto">
                                    <i class="bi bi-piggy-bank fs-2"></i>
                                    <div class="mt-2">Mixto</div>
                                </div>
                            </div>
                        </div>

                        <!-- Campos adicionales para pago mixto -->
                        <div id="mixedPaymentFields" class="mt-3" style="display: none;">
                            <div class="mb-2">
                                <label class="form-label">Efectivo:</label>
                                <input type="number" class="form-control" id="pagoEfectivo" step="0.01" min="0">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Tarjeta:</label>
                                <input type="number" class="form-control" id="pagoTarjeta" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="d-grid gap-2">
                    <button class="btn btn-success btn-lg" id="btnCompletarVenta" disabled>
                        <i class="bi bi-check-circle"></i> Completar Venta
                    </button>
                    <button class="btn btn-outline-danger" id="btnCancelar">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                </div>

            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Estado global de la venta
        const ventaState = {
            items: [],
            metodoPago: 'efectivo',
            pagos: []
        };

        // Cargar productos al iniciar
        document.addEventListener('DOMContentLoaded', async () => {
            await cargarProductos();
            configurarEventos();
        });

        // Cargar productos desde el servidor
        async function cargarProductos() {
            try {
                const response = await fetch('/api/productos/listar');
                const productos = await response.json();
                
                renderizarProductos(productos);
            } catch (error) {
                console.error('Error al cargar productos:', error);
                document.getElementById('productsGrid').innerHTML = `
                    <div class="col-12 text-center py-5 text-danger">
                        <i class="bi bi-exclamation-triangle fs-1"></i>
                        <p class="mt-3">Error al cargar productos</p>
                    </div>
                `;
            }
        }

        // Renderizar grid de productos
        function renderizarProductos(productos) {
            const grid = document.getElementById('productsGrid');
            
            if (productos.length === 0) {
                grid.innerHTML = `
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-3">No hay productos disponibles</p>
                    </div>
                `;
                return;
            }

            grid.innerHTML = productos.map(producto => `
                <div class="col-md-4 mb-3">
                    <div class="product-card card h-100" onclick="agregarAlCarrito(${producto.id})">
                        <div class="card-body">
                            <h6 class="card-title">${producto.nombre}</h6>
                            <p class="card-text text-muted small">${producto.codigo || 'Sin código'}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-5 text-success fw-bold">$${parseFloat(producto.precio).toFixed(2)}</span>
                                <span class="badge bg-info">Stock: ${producto.stock}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Agregar producto al carrito
        function agregarAlCarrito(productoId) {
            // Buscar producto en el catálogo (simulado por ahora)
            const producto = {
                id: productoId,
                nombre: `Producto ${productoId}`,
                precio: 100.00,
                cantidad: 1
            };

            // Verificar si ya existe en el carrito
            const existente = ventaState.items.find(item => item.id === productoId);
            
            if (existente) {
                existente.cantidad++;
            } else {
                ventaState.items.push(producto);
            }

            actualizarCarrito();
        }

        // Actualizar visualización del carrito
        function actualizarCarrito() {
            const cartItems = document.getElementById('cartItems');
            const cartCount = document.getElementById('cartCount');
            
            if (ventaState.items.length === 0) {
                cartItems.innerHTML = `
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-cart-x" style="font-size: 3rem;"></i>
                        <p class="mt-3">Carrito vacío</p>
                    </div>
                `;
                cartCount.textContent = '0';
                document.getElementById('btnCompletarVenta').disabled = true;
                return;
            }

            cartCount.textContent = ventaState.items.length;
            
            cartItems.innerHTML = ventaState.items.map((item, index) => `
                <div class="cart-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${item.nombre}</h6>
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <button class="btn btn-outline-secondary" onclick="cambiarCantidad(${index}, -1)">-</button>
                                <input type="number" class="form-control text-center" value="${item.cantidad}" readonly>
                                <button class="btn btn-outline-secondary" onclick="cambiarCantidad(${index}, 1)">+</button>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">$${(item.precio * item.cantidad).toFixed(2)}</div>
                            <small class="text-muted">$${item.precio.toFixed(2)} c/u</small>
                        </div>
                        <button class="btn btn-sm btn-outline-danger ms-2" onclick="eliminarItem(${index})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');

            calcularTotales();
            document.getElementById('btnCompletarVenta').disabled = false;
        }

        // Cambiar cantidad de un item
        function cambiarCantidad(index, delta) {
            ventaState.items[index].cantidad += delta;
            
            if (ventaState.items[index].cantidad <= 0) {
                eliminarItem(index);
            } else {
                actualizarCarrito();
            }
        }

        // Eliminar item del carrito
        function eliminarItem(index) {
            ventaState.items.splice(index, 1);
            actualizarCarrito();
        }

        // Calcular totales
        function calcularTotales() {
            const subtotal = ventaState.items.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
            const iva = subtotal * 0.16;
            const total = subtotal + iva;

            document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
            document.getElementById('iva').textContent = `$${iva.toFixed(2)}`;
            document.getElementById('total').textContent = `$${total.toFixed(2)}`;
        }

        // Configurar eventos
        function configurarEventos() {
            // Métodos de pago
            document.querySelectorAll('.payment-method').forEach(method => {
                method.addEventListener('click', function() {
                    document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
                    this.classList.add('active');
                    ventaState.metodoPago = this.dataset.method;

                    // Mostrar campos mixtos si es necesario
                    document.getElementById('mixedPaymentFields').style.display = 
                        this.dataset.method === 'mixto' ? 'block' : 'none';
                });
            });

            // Completar venta
            document.getElementById('btnCompletarVenta').addEventListener('click', async () => {
                await procesarVenta();
            });

            // Cancelar
            document.getElementById('btnCancelar').addEventListener('click', () => {
                if (confirm('¿Desea cancelar la venta actual?')) {
                    ventaState.items = [];
                    actualizarCarrito();
                }
            });
        }

        // Procesar venta
        async function procesarVenta() {
            try {
                const total = parseFloat(document.getElementById('total').textContent.replace('$', ''));
                
                // Preparar datos de la venta
                const ventaData = {
                    items: ventaState.items,
                    metodo_pago: ventaState.metodoPago,
                    pagos: ventaState.metodoPago === 'mixto' ? [
                        { metodo: 'efectivo', monto: parseFloat(document.getElementById('pagoEfectivo').value) || 0 },
                        { metodo: 'tarjeta', monto: parseFloat(document.getElementById('pagoTarjeta').value) || 0 }
                    ] : [
                        { metodo: ventaState.metodoPago, monto: total }
                    ]
                };

                const response = await fetch('/ventas/crear', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(ventaData)
                });

                const result = await response.json();

                if (result.success) {
                    alert(`✅ Venta completada exitosamente!\nFolio: ${result.folio}`);
                    ventaState.items = [];
                    actualizarCarrito();
                } else {
                    alert(`❌ Error: ${result.message}`);
                }

            } catch (error) {
                console.error('Error al procesar venta:', error);
                alert('Error al procesar la venta. Intente nuevamente.');
            }
        }
    </script>

</body>
</html>
