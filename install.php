<?php
declare(strict_types=1);

/**
 * Script de Instalación de Aura Platform
 * 
 * Configura la base de datos master y estructura inicial del sistema
 * Ejecutar una sola vez después de configurar .env
 * 
 * Uso: php install.php
 */

// Validar que se ejecuta desde CLI
if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos.\n");
}

echo "╔═══════════════════════════════════════════════╗\n";
echo "║   AURA PLATFORM - INSTALACIÓN AUTOMÁTICA    ║\n";
echo "║      El WordPress de la Contabilidad         ║\n";
echo "╚═══════════════════════════════════════════════╝\n\n";

// Cargar variables de entorno
if (!file_exists(__DIR__ . '/.env')) {
    die("❌ ERROR: Archivo .env no encontrado.\n   Copiar .env.example a .env y configurar credenciales.\n");
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

// Extraer configuración
$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbDatabase = $env['DB_DATABASE'] ?? 'aura_master';
$dbUsername = $env['DB_USERNAME'] ?? 'root';
$dbPassword = $env['DB_PASSWORD'] ?? '';

echo "📋 Configuración detectada:\n";
echo "   Host: {$dbHost}:{$dbPort}\n";
echo "   Base de datos: {$dbDatabase}\n";
echo "   Usuario: {$dbUsername}\n\n";

// Conectar a MySQL sin especificar base de datos
try {
    echo "🔌 Conectando a MySQL...\n";
    
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUsername, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Conexión exitosa.\n\n";
    
} catch (PDOException $e) {
    die("❌ ERROR: No se pudo conectar a MySQL.\n   " . $e->getMessage() . "\n");
}

// Crear base de datos master si no existe
try {
    echo "🗄️  Verificando base de datos master...\n";
    
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$dbDatabase}'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "   Base de datos '{$dbDatabase}' no existe. Creando...\n";
        
        $pdo->exec("
            CREATE DATABASE {$dbDatabase} 
            CHARACTER SET utf8mb4 
            COLLATE utf8mb4_unicode_ci
        ");
        
        echo "✅ Base de datos '{$dbDatabase}' creada.\n\n";
    } else {
        echo "✅ Base de datos '{$dbDatabase}' ya existe.\n\n";
    }
    
} catch (PDOException $e) {
    die("❌ ERROR: No se pudo crear la base de datos.\n   " . $e->getMessage() . "\n");
}

// Conectar a la base de datos master
try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbDatabase};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUsername, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
} catch (PDOException $e) {
    die("❌ ERROR: No se pudo conectar a '{$dbDatabase}'.\n   " . $e->getMessage() . "\n");
}

// Crear tabla de tenants
try {
    echo "📊 Creando tabla de tenants...\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tenants (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nombre único del tenant (usado en subdominios)',
            schema_name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nombre del esquema MySQL (tenant_N)',
            estado ENUM('activo', 'suspendido', 'cancelado') DEFAULT 'activo',
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            metadata JSON COMMENT 'Información adicional del tenant (razón social, logo, etc.)',
            INDEX idx_estado (estado),
            INDEX idx_fecha_creacion (fecha_creacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Registro centralizado de inquilinos del sistema'
    ");
    
    echo "✅ Tabla 'tenants' creada.\n\n";
    
} catch (PDOException $e) {
    die("❌ ERROR: No se pudo crear tabla de tenants.\n   " . $e->getMessage() . "\n");
}

// Crear tabla de plugins
try {
    echo "🔌 Creando tabla de plugins...\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS plugins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL UNIQUE COMMENT 'Identificador único del plugin',
            version VARCHAR(20) NOT NULL COMMENT 'Versión semántica (1.0.0)',
            descripcion TEXT COMMENT 'Descripción del plugin',
            autor VARCHAR(100) COMMENT 'Autor del plugin',
            estado ENUM('instalado', 'activo', 'desactivado') DEFAULT 'instalado',
            prioridad TINYINT UNSIGNED DEFAULT 10 COMMENT 'Prioridad de carga (1-99)',
            ruta_carpeta VARCHAR(255) NOT NULL COMMENT 'Ruta relativa en /plugins/',
            fecha_instalacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            metadata JSON COMMENT 'Metadatos del plugin.json',
            INDEX idx_estado (estado),
            INDEX idx_prioridad (prioridad)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Registro de plugins instalados en el sistema'
    ");
    
    echo "✅ Tabla 'plugins' creada.\n\n";
    
} catch (PDOException $e) {
    die("❌ ERROR: No se pudo crear tabla de plugins.\n   " . $e->getMessage() . "\n");
}

// Crear tabla de configuración global
try {
    echo "⚙️  Creando tabla de configuración...\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS configuracion_global (
            clave VARCHAR(100) PRIMARY KEY COMMENT 'Identificador de la configuración',
            valor TEXT COMMENT 'Valor de la configuración (puede ser JSON)',
            tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
            descripcion TEXT COMMENT 'Descripción del propósito de esta configuración',
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Configuraciones globales del sistema (aplican a todos los tenants)'
    ");
    
    echo "✅ Tabla 'configuracion_global' creada.\n\n";
    
} catch (PDOException $e) {
    die("❌ ERROR: No se pudo crear tabla de configuración.\n   " . $e->getMessage() . "\n");
}

// Insertar configuraciones por defecto
try {
    echo "🔧 Configurando valores por defecto...\n";
    
    $configs = [
        ['sistema.version', '1.0.0', 'string', 'Versión actual de Aura Platform'],
        ['sistema.nombre', 'Aura Platform', 'string', 'Nombre del sistema'],
        ['sistema.timezone', 'America/Mexico_City', 'string', 'Zona horaria predeterminada'],
        ['auth.bcrypt_cost', '12', 'number', 'Factor de costo para bcrypt (10-12)'],
        ['auth.session_lifetime', '7200', 'number', 'Duración de sesión en segundos (2 horas)'],
        ['plugins.auto_load', 'true', 'boolean', 'Cargar plugins automáticamente'],
        ['seguridad.max_login_attempts', '5', 'number', 'Intentos de login antes de bloqueo'],
        ['seguridad.lockout_duration', '900', 'number', 'Duración del bloqueo en segundos (15 min)']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO configuracion_global (clave, valor, tipo, descripcion)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)
    ");
    
    foreach ($configs as $config) {
        $stmt->execute($config);
    }
    
    echo "✅ Configuraciones insertadas.\n\n";
    
} catch (PDOException $e) {
    die("❌ ERROR: No se pudo insertar configuraciones.\n   " . $e->getMessage() . "\n");
}

// Crear directorios necesarios
echo "📁 Creando directorios del sistema...\n";

$directories = [
    '/storage/logs',
    '/storage/cache',
    '/storage/uploads',
    '/storage/sessions',
    '/plugins',
    '/tests'
];

foreach ($directories as $dir) {
    $fullPath = __DIR__ . $dir;
    
    if (!is_dir($fullPath)) {
        if (mkdir($fullPath, 0755, true)) {
            echo "   ✅ Creado: {$dir}\n";
        } else {
            echo "   ⚠️  No se pudo crear: {$dir}\n";
        }
    } else {
        echo "   ✓ Ya existe: {$dir}\n";
    }
}

echo "\n";

// Crear archivo .gitignore en storage
file_put_contents(__DIR__ . '/storage/.gitignore', "*\n!.gitignore\n");

// Resumen final
echo "╔═══════════════════════════════════════════════╗\n";
echo "║        ✅ INSTALACIÓN COMPLETADA             ║\n";
echo "╚═══════════════════════════════════════════════╝\n\n";

echo "🎉 Aura Platform ha sido instalado correctamente.\n\n";

echo "📝 Próximos pasos:\n";
echo "   1. Configurar servidor web apuntando a /public\n";
echo "   2. Crear un tenant de prueba:\n";
echo "      php create_tenant.php empresa_demo\n";
echo "   3. Acceder a: http://empresa_demo.localhost\n";
echo "   4. Usuario inicial: admin / admin123\n\n";

echo "📚 Documentación:\n";
echo "   - README.md para guía de inicio\n";
echo "   - PRD.md para requisitos del producto\n";
echo "   - Mandato de Arquitectura.md para decisiones técnicas\n\n";

echo "🔐 Seguridad:\n";
echo "   - Cambiar contraseñas por defecto\n";
echo "   - Configurar APP_DEBUG=false en producción\n";
echo "   - Revisar permisos de directorios (storage debe ser writable)\n\n";

echo "¡Gracias por usar Aura Platform! 🚀\n";
