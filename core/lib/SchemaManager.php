<?php
declare(strict_types=1);

namespace Aura\Core\Database;

use PDO;
use PDOException;

/**
 * SchemaManager - Gestor de Esquemas Multi-Tenant
 * 
 * Implementa el patrón de aislamiento por esquemas independientes (namespaces)
 * en MySQL/MariaDB. Cada tenant tiene su propio esquema lógico separado.
 * 
 * Cumple con RF-001 y RF-002 del PRD:
 * - RF-001: Creación de inquilino con esquema aislado
 * - RF-002: Switch dinámico de esquema por request
 * 
 * @package Aura\Core\Database
 */
final class SchemaManager
{
    private const SCHEMA_PREFIX = 'tenant_';
    
    public function __construct(
        private readonly PDO $pdo
    ) {}

    /**
     * Cambia el contexto de ejecución al esquema del inquilino autenticado.
     * 
     * Aplica el switch de contexto mediante USE statement de MySQL,
     * permitiendo que todas las queries subsecuentes operen sobre
     * el esquema del tenant específico.
     * 
     * @param string $tenantId Identificador único del inquilino
     * @throws PDOException Si el esquema no existe o falla el switch
     */
    public function switchToTenant(string $tenantId): void
    {
        $schemaName = $this->generateSchemaName($tenantId);
        
        // Validar existencia antes del switch (seguridad)
        if (!$this->schemaExists($schemaName)) {
            throw new PDOException(
                "Error RF-002: Esquema no encontrado para tenant '{$tenantId}'. " .
                "Schema esperado: {$schemaName}"
            );
        }
        
        // Ejecutar cambio de contexto (overhead < 5ms según NFR-PERF-001)
        $this->pdo->exec("USE `{$schemaName}`");
    }

    /**
     * Crea un nuevo esquema para un inquilino con estructura base.
     * 
     * Implementa RF-001: Creación de tenant con:
     * - Esquema MySQL independiente
     * - Tablas base (usuarios, roles, permisos, etc.)
     * - Usuario administrador inicial
     * - Tiempo de aprovisionamiento < 30 segundos
     * 
     * @param string $tenantId Identificador del nuevo inquilino
     * @param array $adminData Datos del usuario administrador inicial
     * @return bool True si se creó exitosamente
     * @throws PDOException Si falla la creación o migración
     */
    public function createTenantSchema(string $tenantId, array $adminData = []): bool
    {
        $schemaName = $this->generateSchemaName($tenantId);
        
        // Validar que no exista previamente
        if ($this->schemaExists($schemaName)) {
            throw new PDOException(
                "Error RF-001: El esquema para tenant '{$tenantId}' ya existe"
            );
        }
        
        try {
            // Inicio de transacción atómica para creación
            $this->pdo->beginTransaction();
            
            // 1. Crear esquema con charset UTF8MB4 (soporte emoji y caracteres especiales)
            $this->pdo->exec(
                "CREATE DATABASE `{$schemaName}` 
                CHARACTER SET utf8mb4 
                COLLATE utf8mb4_unicode_ci"
            );
            
            // 2. Cambiar al nuevo esquema
            $this->pdo->exec("USE `{$schemaName}`");
            
            // 3. Ejecutar migraciones base
            $this->runBaseMigrations();
            
            // 4. Crear usuario administrador inicial
            if (!empty($adminData)) {
                $this->createAdminUser($adminData);
            }
            
            // Confirmar transacción
            $this->pdo->commit();
            
            return true;
            
        } catch (PDOException $e) {
            // Rollback en caso de error
            $this->pdo->rollBack();
            
            // Intentar limpiar esquema parcialmente creado
            try {
                $this->pdo->exec("DROP DATABASE IF EXISTS `{$schemaName}`");
            } catch (PDOException $cleanupError) {
                // Log del error de limpieza pero lanzar el original
                error_log("Error limpiando esquema fallido: " . $cleanupError->getMessage());
            }
            
            throw new PDOException(
                "Error RF-001: Fallo en creación de tenant '{$tenantId}': " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Ejecuta las migraciones base para un nuevo esquema de tenant.
     * 
     * Crea la estructura de tablas fundamental:
     * - usuarios: Cuentas de usuario del tenant
     * - roles: Roles RBAC (ADMIN, SELLER, SPECIAL)
     * - permisos: Permisos granulares del sistema
     * - rol_permisos: Relación many-to-many
     * - configuracion: Parámetros del tenant
     * - auditoria_ventas: Log de transacciones
     */
    private function runBaseMigrations(): void
    {
        // Tabla de roles (RF-003: RBAC Jerárquico)
        $this->pdo->exec("
            CREATE TABLE roles (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(50) UNIQUE NOT NULL,
                descripcion TEXT,
                rol_padre_id INT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (rol_padre_id) REFERENCES roles(id) ON DELETE SET NULL,
                INDEX idx_nombre (nombre)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insertar roles base según estructura definida en PRD
        $this->pdo->exec("
            INSERT INTO roles (id, nombre, descripcion, rol_padre_id) VALUES 
            (1, 'ADMIN', 'Administrador con control total de la instancia', NULL),
            (2, 'SELLER', 'Vendedor con permisos operativos de POS', NULL),
            (3, 'SPECIAL', 'Rol personalizable vía ExtensionPoints', NULL)
        ");

        // Tabla de usuarios
        $this->pdo->exec("
            CREATE TABLE usuarios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                nombre_completo VARCHAR(150),
                rol_id INT UNSIGNED NOT NULL,
                activo BOOLEAN DEFAULT TRUE,
                ultimo_acceso TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT,
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_rol (rol_id),
                INDEX idx_activo (activo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de permisos (RF-004: Verificación de permisos)
        $this->pdo->exec("
            CREATE TABLE permisos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(100) UNIQUE NOT NULL,
                descripcion VARCHAR(255),
                modulo VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_slug (slug),
                INDEX idx_modulo (modulo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de relación rol-permisos
        $this->pdo->exec("
            CREATE TABLE rol_permisos (
                rol_id INT UNSIGNED NOT NULL,
                permiso_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (rol_id, permiso_id),
                FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
                FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insertar permisos base para SELLER (según PRD sección 3.2)
        $this->pdo->exec("
            INSERT INTO permisos (slug, descripcion, modulo) VALUES
            ('ventas.crear', 'Registrar nuevas ventas en el POS', 'Ventas'),
            ('ventas.listar', 'Ver historial de ventas', 'Ventas'),
            ('ventas.editar', 'Modificar ventas existentes', 'Ventas'),
            ('productos.ver', 'Consultar catálogo de productos', 'Inventario'),
            ('pagos.procesar', 'Procesar pagos de clientes', 'Finanzas')
        ");

        // Insertar permisos exclusivos de ADMIN
        $this->pdo->exec("
            INSERT INTO permisos (slug, descripcion, modulo) VALUES
            ('usuarios.administrar', 'Crear/editar/eliminar usuarios', 'Sistema'),
            ('config.modificar', 'Cambiar configuración del tenant', 'Sistema'),
            ('backups.ejecutar', 'Generar respaldos de datos', 'Sistema'),
            ('reportes.financieros.ver', 'Acceder a reportes financieros', 'Reportes')
        ");

        // Asignar permisos al rol SELLER
        $this->pdo->exec("
            INSERT INTO rol_permisos (rol_id, permiso_id)
            SELECT 2, id FROM permisos WHERE slug IN (
                'ventas.crear', 'ventas.listar', 'productos.ver', 'pagos.procesar'
            )
        ");

        // Asignar permisos al rol ADMIN (heredará de SELLER vía código)
        $this->pdo->exec("
            INSERT INTO rol_permisos (rol_id, permiso_id)
            SELECT 1, id FROM permisos WHERE slug IN (
                'usuarios.administrar', 'config.modificar', 'backups.ejecutar', 
                'reportes.financieros.ver'
            )
        ");

        // Tabla de configuración del tenant
        $this->pdo->exec("
            CREATE TABLE configuracion (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                clave VARCHAR(100) UNIQUE NOT NULL,
                valor TEXT,
                tipo ENUM('string', 'int', 'boolean', 'json') DEFAULT 'string',
                descripcion VARCHAR(255),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_clave (clave)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de auditoría de ventas (RF-005: Trazabilidad)
        $this->pdo->exec("
            CREATE TABLE auditoria_ventas (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                venta_id INT UNSIGNED NOT NULL,
                usuario_id INT UNSIGNED NOT NULL,
                accion ENUM('CREADA', 'MODIFICADA', 'CANCELADA', 'REEMBOLSADA') NOT NULL,
                datos_anteriores JSON NULL,
                datos_nuevos JSON NULL,
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
                INDEX idx_venta (venta_id),
                INDEX idx_usuario (usuario_id),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tablas para módulo POS (RF-005: Procesamiento atómico de venta)
        $this->pdo->exec("
            CREATE TABLE clientes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(150) NOT NULL,
                email VARCHAR(100),
                telefono VARCHAR(20),
                rfc VARCHAR(13),
                direccion TEXT,
                activo BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_nombre (nombre),
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE productos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(50) UNIQUE NOT NULL,
                nombre VARCHAR(200) NOT NULL,
                descripcion TEXT,
                precio DECIMAL(10, 2) NOT NULL,
                costo DECIMAL(10, 2),
                stock INT NOT NULL DEFAULT 0,
                stock_minimo INT DEFAULT 0,
                activo BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_codigo (codigo),
                INDEX idx_nombre (nombre),
                INDEX idx_activo (activo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE ventas (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                folio VARCHAR(50) UNIQUE NOT NULL,
                cliente_id INT UNSIGNED,
                usuario_id INT UNSIGNED NOT NULL,
                subtotal DECIMAL(10, 2) NOT NULL,
                impuestos DECIMAL(10, 2) NOT NULL DEFAULT 0,
                descuento DECIMAL(10, 2) NOT NULL DEFAULT 0,
                total DECIMAL(10, 2) NOT NULL,
                estado ENUM('COMPLETADA', 'CANCELADA', 'PENDIENTE') DEFAULT 'COMPLETADA',
                notas TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
                INDEX idx_folio (folio),
                INDEX idx_cliente (cliente_id),
                INDEX idx_usuario (usuario_id),
                INDEX idx_estado (estado),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE venta_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                venta_id INT UNSIGNED NOT NULL,
                producto_id INT UNSIGNED NOT NULL,
                cantidad INT NOT NULL,
                precio_unitario DECIMAL(10, 2) NOT NULL,
                subtotal DECIMAL(10, 2) NOT NULL,
                FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
                FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT,
                INDEX idx_venta (venta_id),
                INDEX idx_producto (producto_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE venta_pagos (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                venta_id INT UNSIGNED NOT NULL,
                metodo ENUM('efectivo', 'tarjeta', 'transferencia', 'cheque') NOT NULL,
                monto DECIMAL(10, 2) NOT NULL,
                referencia VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
                INDEX idx_venta (venta_id),
                INDEX idx_metodo (metodo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de movimientos de stock
        $this->pdo->exec("
            CREATE TABLE stock_movimientos (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                producto_id INT UNSIGNED NOT NULL,
                tipo ENUM('ENTRADA', 'SALIDA', 'AJUSTE') NOT NULL,
                cantidad INT NOT NULL,
                stock_anterior INT NOT NULL,
                stock_nuevo INT NOT NULL,
                referencia VARCHAR(100),
                usuario_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
                INDEX idx_producto (producto_id),
                INDEX idx_tipo (tipo),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Crea el usuario administrador inicial del tenant.
     * 
     * @param array $adminData Debe contener: username, password, email, nombre_completo
     */
    private function createAdminUser(array $adminData): void
    {
        // Validar datos requeridos
        $required = ['username', 'password', 'email', 'nombre_completo'];
        foreach ($required as $field) {
            if (empty($adminData[$field])) {
                throw new PDOException("Campo requerido faltante para admin: {$field}");
            }
        }

        // Hash de contraseña con bcrypt (cost 12 según NFR-SEC-003)
        $passwordHash = password_hash($adminData['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->pdo->prepare("
            INSERT INTO usuarios (username, password_hash, email, nombre_completo, rol_id, activo)
            VALUES (:username, :password_hash, :email, :nombre_completo, 1, TRUE)
        ");

        $stmt->execute([
            'username' => $adminData['username'],
            'password_hash' => $passwordHash,
            'email' => $adminData['email'],
            'nombre_completo' => $adminData['nombre_completo']
        ]);
    }

    /**
     * Genera el nombre del esquema a partir del ID del tenant.
     * 
     * Aplica sanitización para prevenir SQL injection y caracteres inválidos.
     * 
     * @param string $tenantId Identificador del tenant
     * @return string Nombre del esquema (ej: 'tenant_empresa123')
     */
    private function generateSchemaName(string $tenantId): string
    {
        // Sanitizar: solo letras minúsculas, números y guiones bajos
        $sanitized = preg_replace('/[^a-z0-9_]/', '', strtolower($tenantId));
        
        if (empty($sanitized)) {
            throw new PDOException("ID de tenant inválido: '{$tenantId}'");
        }
        
        return self::SCHEMA_PREFIX . $sanitized;
    }

    /**
     * Verifica si un esquema existe en la base de datos.
     * 
     * @param string $schemaName Nombre del esquema a verificar
     * @return bool True si el esquema existe
     */
    private function schemaExists(string $schemaName): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT SCHEMA_NAME 
            FROM information_schema.SCHEMATA 
            WHERE SCHEMA_NAME = :name
        ");
        
        $stmt->execute(['name' => $schemaName]);
        
        return $stmt->fetch() !== false;
    }

    /**
     * Obtiene el nombre del esquema actual en uso.
     * 
     * @return string|null Nombre del esquema o null si no hay contexto
     */
    public function getCurrentSchema(): ?string
    {
        $result = $this->pdo->query("SELECT DATABASE()")->fetchColumn();
        return $result !== false ? $result : null;
    }

    /**
     * Lista todos los esquemas de tenants existentes.
     * 
     * @return array Array de nombres de esquemas
     */
    public function listTenantSchemas(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT SCHEMA_NAME 
            FROM information_schema.SCHEMATA 
            WHERE SCHEMA_NAME LIKE :prefix
            ORDER BY SCHEMA_NAME
        ");
        
        $stmt->execute(['prefix' => self::SCHEMA_PREFIX . '%']);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Elimina un esquema de tenant (usar con extrema precaución).
     * 
     * @param string $tenantId Identificador del tenant a eliminar
     * @return bool True si se eliminó exitosamente
     */
    public function dropTenantSchema(string $tenantId): bool
    {
        $schemaName = $this->generateSchemaName($tenantId);
        
        if (!$this->schemaExists($schemaName)) {
            return false;
        }
        
        // Registrar en log antes de eliminar
        error_log("ALERTA: Eliminando esquema de tenant: {$schemaName}");
        
        $this->pdo->exec("DROP DATABASE `{$schemaName}`");
        
        return true;
    }
}
