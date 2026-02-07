<?php
declare(strict_types=1);

/**
 * Configuración General de la Aplicación
 */

// Versión del núcleo de Aura (para validación de plugins)
define('AURA_VERSION', '1.0.0');

return [
    // Información de la aplicación
    'app_name' => 'Aura Platform',
    'app_url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => 'America/Mexico_City',
    
    // Modo de depuración (NUNCA en producción)
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    
    // Configuración de seguridad
    'security' => [
        'bcrypt_cost' => 12, // Cost factor para hashing de contraseñas
        'session_lifetime' => 7200, // 2 horas en segundos
        'csrf_token_expiry' => 3600, // 1 hora
    ],
    
    // Configuración de multi-tenancy
    'tenancy' => [
        'mode' => 'subdomain', // 'subdomain' o 'header'
        'schema_prefix' => 'tenant_',
        'master_schema' => 'aura_master',
    ],
    
    // Configuración de plugins
    'plugins' => [
        'enabled' => true,
        'auto_discover' => true,
        'cache_enabled' => !($_ENV['APP_DEBUG'] ?? false),
    ],
    
    // Rutas del sistema
    'paths' => [
        'core' => __DIR__ . '/../',
        'plugins' => __DIR__ . '/../../plugins/',
        'storage' => __DIR__ . '/../../storage/',
        'public' => __DIR__ . '/../../public/',
    ],
];
