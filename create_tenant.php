<?php
declare(strict_types=1);

/**
 * Script de Creación de Tenant
 * 
 * Facilita la creación de nuevos inquilinos (tenants) en Aura Platform
 * Ejecutar desde CLI para generar un tenant con su esquema y usuario admin
 * 
 * Uso: php create_tenant.php <nombre_tenant> [admin_username] [admin_password]
 * 
 * Ejemplo:
 *   php create_tenant.php empresa_demo
 *   php create_tenant.php mi_empresa admin mipassword123
 */

// Validar que se ejecuta desde CLI
if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos.\n");
}

// Obtener argumentos
$nombreTenant = $argv[1] ?? null;
$adminUsername = $argv[2] ?? 'admin';
$adminPassword = $argv[3] ?? 'admin123';

if (!$nombreTenant) {
    echo "❌ ERROR: Nombre del tenant requerido.\n\n";
    echo "Uso: php create_tenant.php <nombre_tenant> [admin_username] [admin_password]\n\n";
    echo "Ejemplos:\n";
    echo "  php create_tenant.php empresa_demo\n";
    echo "  php create_tenant.php mi_empresa admin mipassword123\n\n";
    exit(1);
}

// Validar formato del nombre
if (!preg_match('/^[a-z0-9_]+$/', $nombreTenant)) {
    die("❌ ERROR: El nombre del tenant solo puede contener letras minúsculas, números y guiones bajos.\n");
}

echo "╔═══════════════════════════════════════════════╗\n";
echo "║      AURA PLATFORM - CREACIÓN DE TENANT      ║\n";
echo "╚═══════════════════════════════════════════════╝\n\n";

echo "📋 Información del Tenant:\n";
echo "   Nombre: {$nombreTenant}\n";
echo "   Usuario Admin: {$adminUsername}\n";
echo "   Contraseña: " . str_repeat('*', strlen($adminPassword)) . "\n\n";

// Confirmar creación
echo "¿Desea continuar? (s/n): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 's' && strtolower($line) !== 'si') {
    echo "\n⛔ Operación cancelada.\n";
    exit(0);
}

echo "\n";

// Cargar variables de entorno
if (!file_exists(__DIR__ . '/.env')) {
    die("❌ ERROR: Archivo .env no encontrado.\n");
}

$envFile = file_get_contents(__DIR__ . '/.env');
$envLines = explode("\n", $envFile);
$env = [];

foreach ($envLines as $line) {
    $line = trim($line);
    if (empty($line) || str_starts_with($line, '#')) {
        continue;
    }
    
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value);
}

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbDatabase = $env['DB_DATABASE'] ?? 'aura_master';
$dbUsername = $env['DB_USERNAME'] ?? 'root';
$dbPassword = $env['DB_PASSWORD'] ?? '';

// Incluir SchemaManager (estructura PSR-4)
require_once __DIR__ . '/core/lib/Database/SchemaManager.php';

// Verificar que la clase existe con el namespace correcto
if (!class_exists('Aura\\Core\\Database\\SchemaManager')) {
    die("❌ ERROR: Clase SchemaManager no encontrada.\n   Verifica que el archivo core/lib/Database/SchemaManager.php existe.\n");
}

try {
    // Conectar a base de datos master
    echo "🔌 Conectando a base de datos master...\n";
    
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbDatabase};charset=utf8mb4";
    $pdoMaster = new PDO($dsn, $dbUsername, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Conectado a {$dbDatabase}\n\n";
    
    // Verificar si el tenant ya existe
    $stmt = $pdoMaster->prepare("SELECT id FROM tenants WHERE nombre = ?");
    $stmt->execute([$nombreTenant]);
    $existente = $stmt->fetch();
    
    if ($existente) {
        die("❌ ERROR: El tenant '{$nombreTenant}' ya existe con ID {$existente['id']}.\n");
    }
    
    // Conectar sin base de datos específica para crear esquemas
    $dsnRoot = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdoRoot = new PDO($dsnRoot, $dbUsername, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Instanciar SchemaManager con namespace completo
    $schemaManager = new Aura\Core\Database\SchemaManager($pdoRoot, $pdoMaster);
    
    echo "🏗️  Creando tenant...\n";
    echo "   (esto puede tardar unos segundos)\n\n";
    
    // Crear tenant con usuario admin
    $tenantId = $schemaManager->createTenantSchema($nombreTenant, [
        'username' => $adminUsername,
        'password' => $adminPassword,
        'email' => "{$adminUsername}@{$nombreTenant}.local",
        'nombre_completo' => 'Administrador Principal'
    ]);
    
    echo "✅ Tenant creado exitosamente!\n\n";
    
    // Mostrar resumen
    echo "╔═══════════════════════════════════════════════╗\n";
    echo "║          ✅ TENANT CREADO                     ║\n";
    echo "╚═══════════════════════════════════════════════╝\n\n";
    
    echo "🎉 Información del Tenant:\n";
    echo "   ID: {$tenantId}\n";
    echo "   Nombre: {$nombreTenant}\n";
    echo "   Esquema: tenant_{$nombreTenant}\n";
    echo "   Estado: activo\n\n";
    
    echo "👤 Usuario Administrador:\n";
    echo "   Usuario: {$adminUsername}\n";
    echo "   Contraseña: {$adminPassword}\n";
    echo "   Rol: ADMIN\n\n";
    
    echo "🌐 Acceso:\n";
    echo "   URL: http://{$nombreTenant}.localhost\n";
    echo "   (Configurar DNS/hosts si es necesario)\n\n";
    
    echo "📊 Estructura Creada:\n";
    echo "   ✅ 15 tablas del sistema\n";
    echo "   ✅ Usuario administrador\n";
    echo "   ✅ Roles y permisos base\n";
    echo "   ✅ Índices optimizados\n";
    echo "   ✅ Claves foráneas\n\n";
    
    echo "📝 Próximos pasos:\n";
    echo "   1. Acceder a http://{$nombreTenant}.localhost\n";
    echo "   2. Iniciar sesión con las credenciales admin\n";
    echo "   3. Configurar información de la empresa\n";
    echo "   4. Crear usuarios adicionales\n";
    echo "   5. Cargar catálogo de productos\n\n";
    
    echo "⚠️  Importante:\n";
    echo "   - Cambiar la contraseña por defecto\n";
    echo "   - Configurar permisos de usuarios según roles\n";
    echo "   - Realizar backup periódico del esquema tenant_{$nombreTenant}\n\n";
    
    echo "¡Listo para usar! 🚀\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERROR DE BASE DE DATOS:\n";
    echo "   " . $e->getMessage() . "\n";
    exit(1);
    
} catch (Exception $e) {
    echo "\n❌ ERROR:\n";
    echo "   " . $e->getMessage() . "\n";
    exit(1);
}
