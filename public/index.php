<?php
declare(strict_types=1);

/**
 * Punto de Entrada Principal de Aura
 * 
 * Este archivo es el único punto de entrada público para todas las peticiones.
 * Implementa el patrón Front Controller.
 */

// Definir constantes de rutas
define('ROOT_PATH', dirname(__DIR__));
define('CORE_PATH', ROOT_PATH . '/core');
define('PUBLIC_PATH', __DIR__);

// Cargar autoloader de Composer (si existe)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}

// Autoloader simple para las clases de Aura
spl_autoload_register(function ($class) {
    // Solo cargar clases del namespace Aura
    if (strpos($class, 'Aura\\') !== 0) {
        return;
    }

    // Convertir namespace a ruta de archivo
    $classPath = str_replace('Aura\\Core\\', '', $class);
    $classPath = str_replace('\\', '/', $classPath);
    
    // Mapear directorios
    $directories = [
        CORE_PATH . '/lib/',
        CORE_PATH . '/controllers/',
        CORE_PATH . '/models/',
    ];

    foreach ($directories as $dir) {
        $file = $dir . $classPath . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Cargar variables de entorno desde .env si existe
if (file_exists(ROOT_PATH . '/.env')) {
    $lines = file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Ignorar comentarios
        }
        
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Inicializar aplicación
use Aura\Core\Bootstrap;
use Aura\Core\Controllers\SalesController;
use Aura\Core\Models\VentaModel;
use Aura\Core\Models\StockModel;

try {
    $app = new Bootstrap();
    $app->boot();
    
    // Ruteo simple (mejorar en futuras versiones)
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Limpiar query string de la URI
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    // Router básico
    match(true) {
        $path === '/' || $path === '/index.php' => handleHome($app),
        $path === '/login' && $requestMethod === 'GET' => handleLoginPage($app),
        $path === '/login' && $requestMethod === 'POST' => handleLoginSubmit($app),
        $path === '/logout' => handleLogout($app),
        $path === '/dashboard' => handleDashboard($app),
        $path === '/ventas/nueva' && $requestMethod === 'GET' => handleNuevaVentaPage($app),
        $path === '/ventas/crear' && $requestMethod === 'POST' => handleCrearVenta($app),
        $path === '/ventas/listar' => handleListarVentas($app),
        default => handle404()
    };

} catch (\Exception $e) {
    // Manejo global de errores
    error_log("Error no capturado: " . $e->getMessage());
    error_log($e->getTraceAsString());
    
    http_response_code(500);
    
    if ($_ENV['APP_DEBUG'] ?? false) {
        echo "<h1>Error</h1>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        echo "Ha ocurrido un error. Por favor, contacte al administrador.";
    }
}

/**
 * Handlers de Rutas
 */

function handleHome(Bootstrap $app): void
{
    if ($app->isAuthenticated()) {
        header('Location: /dashboard');
        exit;
    }
    
    header('Location: /login');
    exit;
}

function handleLoginPage(Bootstrap $app): void
{
    if ($app->isAuthenticated()) {
        header('Location: /dashboard');
        exit;
    }
    
    // Cargar vista de login
    require CORE_PATH . '/vistas/login.php';
}

function handleLoginSubmit(Bootstrap $app): void
{
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Usuario y contraseña son requeridos";
        header('Location: /login');
        exit;
    }
    
    try {
        $auth = \Aura\Core\Auth\Auth::authenticate($app->getPdo(), $username, $password);
        
        if ($auth) {
            $app->setAuth($auth);
            header('Location: /dashboard');
            exit;
        } else {
            $_SESSION['error'] = "Credenciales inválidas";
            header('Location: /login');
            exit;
        }
        
    } catch (\Exception $e) {
        error_log("Error en login: " . $e->getMessage());
        $_SESSION['error'] = "Error al iniciar sesión";
        header('Location: /login');
        exit;
    }
}

function handleLogout(Bootstrap $app): void
{
    session_destroy();
    header('Location: /login');
    exit;
}

function handleDashboard(Bootstrap $app): void
{
    if (!$app->isAuthenticated()) {
        header('Location: /login');
        exit;
    }
    
    // Cargar vista de dashboard
    $auth = $app->getAuth();
    require CORE_PATH . '/vistas/dashboard.php';
}

function handleNuevaVentaPage(Bootstrap $app): void
{
    if (!$app->isAuthenticated()) {
        header('Location: /login');
        exit;
    }
    
    // Verificar permiso
    try {
        $app->getAuth()->requirePermission('ventas.crear');
    } catch (\Aura\Core\Auth\UnauthorizedException $e) {
        http_response_code(403);
        echo "No tienes permiso para crear ventas";
        exit;
    }
    
    // Cargar vista de nueva venta
    require CORE_PATH . '/vistas/nueva_venta.php';
}

function handleCrearVenta(Bootstrap $app): void
{
    if (!$app->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    
    // Decodificar JSON del body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos inválidos']);
        exit;
    }
    
    try {
        $ventaModel = new VentaModel($app->getPdo());
        $stockModel = new StockModel($app->getPdo());
        
        $controller = new SalesController(
            $app->getPdo(),
            $app->getAuth(),
            $ventaModel,
            $stockModel
        );
        
        $result = $controller->procesarVenta(
            $input['venta'],
            $input['items'],
            $input['pagos']
        );
        
        header('Content-Type: application/json');
        echo json_encode($result);
        
    } catch (\Exception $e) {
        error_log("Error procesando venta: " . $e->getMessage());
        
        http_response_code($e->getCode() ?: 500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function handleListarVentas(Bootstrap $app): void
{
    if (!$app->isAuthenticated()) {
        header('Location: /login');
        exit;
    }
    
    // Verificar permiso
    try {
        $app->getAuth()->requirePermission('ventas.listar');
    } catch (\Aura\Core\Auth\UnauthorizedException $e) {
        http_response_code(403);
        echo "No tienes permiso para listar ventas";
        exit;
    }
    
    // Cargar vista de listado de ventas
    require CORE_PATH . '/vistas/listar_ventas.php';
}

function handle404(): never
{
    http_response_code(404);
    echo "<!DOCTYPE html>
<html>
<head>
    <title>404 - Página No Encontrada</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #6c757d; }
    </style>
</head>
<body>
    <h1>404 - Página No Encontrada</h1>
    <p>La página que buscas no existe.</p>
    <a href='/'>Volver al inicio</a>
</body>
</html>";
    exit;
}
