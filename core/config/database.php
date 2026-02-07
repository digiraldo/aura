<?php
declare(strict_types=1);

/**
 * Configuración de Base de Datos
 * 
 * Define los parámetros de conexión para la instancia MySQL/MariaDB
 * que alojará los esquemas multi-tenant de Aura.
 */

return [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    
    // Credenciales de superusuario para gestión de esquemas
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    
    // Base de datos principal para metadata de tenants
    'database' => $_ENV['DB_DATABASE'] ?? 'aura_master',
    
    // Opciones PDO para seguridad y rendimiento
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Prepared statements nativos
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];
