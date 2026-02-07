# Mandato de Arquitectura: Protocolo de Blindaje y Scaffolding para Aura
## Implementación por Fases del "WordPress de la Contabilidad"

---

## 1. Visión Estratégica: El Núcleo de Confianza Inmutable

La soberanía del dato no es una característica opcional en Aura; es el cimiento de nuestra propuesta de **"Confianza como Servicio" (Trust as a Service)**. Nuestra visión es erigir el "WordPress de la contabilidad", una plataforma cuya potencia emane de una modularidad sin precedentes. 

### 1.1 El Paradigma de Desarrollo Bifásico

La arquitectura de Aura se construye en dos fases críticas interdependientes:

- **Fase I - Núcleo Blindado**: Aislamiento multi-tenant y RBAC jerárquico (gatekeeper de seguridad)
- **Fase II - Ecosistema Abierto**: Sistema de plugins y extensibilidad controlada

Sin el blindaje de la Fase I, la apertura a innovaciones de terceros (como integraciones con MailChimp o Quiz And Survey Master) resultaría en una catástrofe sistémica donde un plugin defectuoso podría comprometer el núcleo. La precisión en este andamiaje técnico es lo que transforma la robustez en valor comercial, garantizando que el "código genético" de Aura sea inmune a la degradación por deuda técnica.

---

## 2. Especificaciones Técnicas del Stack de Modernización

Para asegurar una ejecución quirúrgica por parte de herramientas como VS Code Copilot y GitHub Copilot, el siguiente marco técnico es de **cumplimiento obligatorio**. Cualquier desviación se considerará una brecha en la integridad arquitectónica.

### 2.1 Stack Tecnológico Obligatorio

| Componente | Especificación | Por qué Estratégico (Mandato del Arquitecto) |
|------------|----------------|----------------------------------------------|
| **Backend** | PHP 8.2+ | Uso mandatorio de `readonly properties` y `Enums` para definir roles, garantizando un estado inmutable y tipos estrictos. |
| **Base de Datos** | MySQL / MariaDB | Motor relacional con adhesión total a ACID. La durabilidad es innegociable para la persistencia financiera en el POS. |
| **Capa de Acceso** | PHP PDO | Definido como nuestro "Security Firewall". Es la barrera de sanitización que prohíbe el SQL Injection y centraliza la lógica multi-tenant. |
| **Frontend** | Bootstrap 5.3.3+ | Estandarización de la UX/UI inspirada en la eficiencia de Gentelella para un ecosistema visual coherente. |
| **Visualización** | Chart.js 4.0+ | Motor de renderizado para dashboards interactivos y reportes analíticos. |

### 2.2 Convenciones de Código No Negociables

- **Estándar**: PSR-12 (PHP Standards Recommendations)
- **Tipado Estricto**: `declare(strict_types=1);` en todos los archivos PHP
- **Comentarios**: En español, orientados a explicar el "por qué" estratégico, no el "qué" obvio
- **Versionado**: Semantic Versioning (SemVer 2.0.0) para el core y plugins

---

## 3. Fase I: Núcleo Blindado - Arquitectura de Seguridad

### 3.1 Aislamiento Multi-Tenant por Esquemas Independientes

#### 3.1.1 Decisión Arquitectónica: Por qué Esquemas Independientes

Hemos rechazado:
- ❌ **Modelo de `tenant_id`**: Riesgo crítico de fuga de datos por errores en consultas
- ❌ **Bases de datos físicas separadas**: Costo prohibitivo e inmanejable para escalar a miles de inquilinos

**Modelo Seleccionado**: ✅ **Esquemas Independientes (Namespaces en MySQL)**

Este diseño permite tratar a cada inquilino como una **base de datos lógica separada** dentro de una misma instancia, mitigando el fenómeno del "Noisy Neighbor" mediante la segmentación de índices y estructuras.

#### 3.1.2 Ítems de Implementación

**Ítem 3.1.2.1**: Clase `SchemaManager` con Switch Dinámico
```php
declare(strict_types=1);

namespace Aura\Core\Database;

use PDO;
use PDOException;

final class SchemaManager
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    /**
     * Cambia el contexto de ejecución al esquema del inquilino autenticado.
     * 
     * @param string $tenantId Identificador único del inquilino
     * @throws PDOException Si el esquema no existe
     */
    public function switchToTenant(string $tenantId): void
    {
        $schemaName = $this->generateSchemaName($tenantId);
        
        // Validar existencia antes del switch
        if (!$this->schemaExists($schemaName)) {
            throw new PDOException("Esquema no encontrado: {$schemaName}");
        }
        
        // Ejecutar cambio de contexto
        $this->pdo->exec("USE `{$schemaName}`");
    }

    /**
     * Crea un nuevo esquema para un inquilino con tablas base.
     * 
     * @param string $tenantId Identificador del nuevo inquilino
     * @return bool True si se creó exitosamente
     */
    public function createTenantSchema(string $tenantId): bool
    {
        $schemaName = $this->generateSchemaName($tenantId);
        
        try {
            $this->pdo->beginTransaction();
            
            // Crear esquema
            $this->pdo->exec("CREATE DATABASE `{$schemaName}` 
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Cambiar al nuevo esquema
            $this->pdo->exec("USE `{$schemaName}`");
            
            // Ejecutar migraciones base
            $this->runBaseMigrations();
            
            $this->pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function generateSchemaName(string $tenantId): string
    {
        return "tenant_" . preg_replace('/[^a-z0-9_]/', '', strtolower($tenantId));
    }

    private function schemaExists(string $schemaName): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA 
             WHERE SCHEMA_NAME = :name"
        );
        $stmt->execute(['name' => $schemaName]);
        return $stmt->fetch() !== false;
    }

    private function runBaseMigrations(): void
    {
        // Tabla de usuarios
        $this->pdo->exec("
            CREATE TABLE usuarios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                rol_id INT UNSIGNED NOT NULL,
                activo BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_rol (rol_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Tabla de roles
        $this->pdo->exec("
            CREATE TABLE roles (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(50) UNIQUE NOT NULL,
                descripcion TEXT,
                rol_padre_id INT UNSIGNED NULL,
                FOREIGN KEY (rol_padre_id) REFERENCES roles(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insertar roles base
        $this->pdo->exec("
            INSERT INTO roles (id, nombre, descripcion) VALUES 
            (1, 'ADMIN', 'Administrador con control total de la instancia'),
            (2, 'SELLER', 'Vendedor con permisos operativos de POS'),
            (3, 'SPECIAL', 'Rol personalizable vía ExtensionPoints')
        ");
    }
}
```

**Ítem 3.1.2.2**: Middleware de Identificación de Tenant
- Detectar inquilino mediante:
  - Subdominio: `{tenant}.aura.app` → extraer `{tenant}`
  - Header HTTP: `X-Tenant-ID`
  - Token JWT con claim `tenant_id`
- Ejecutar `SchemaManager::switchToTenant()` antes de cada request

**Ítem 3.1.2.3**: Restricciones de Consultas
- ❌ **PROHIBIDO**: `SELECT *` (fuerza selección explícita de columnas)
- ✅ **OBLIGATORIO**: Prepared statements con placeholders
- ✅ **OBLIGATORIO**: Validación de existencia de esquema antes de operaciones

---

### 3.2 Control de Acceso: Las "Tres Reglas de Hierro" del RBAC

#### 3.2.1 Los Tres Axiomas del Control de Acceso

Aura implementa un **RBAC Jerárquico** para anular el fraude interno y minimizar la superficie de ataque:

1. **Asignación a Roles**: Los permisos residen en el rol; **jamás** en el usuario directamente
2. **Mínimo Privilegio**: Acceso restringido estrictamente a la función laboral (Principle of Least Privilege)
3. **Autorización de Rol Activo**: Validación obligatoria del rol en la sesión actual antes de ejecutar acciones

#### 3.2.2 Estructura de Roles Jerárquicos de Aura

| Rol | Descripción | Hereda de | Permisos Clave |
|-----|-------------|-----------|----------------|
| **ADMIN** | Administrador | SELLER | `usuarios.administrar`, `config.modificar`, `backups.ejecutar` |
| **SELLER** | Vendedor | — | `ventas.crear`, `ventas.listar`, `productos.ver`, `pagos.procesar` |
| **SPECIAL** | Personalizado | Configurable | Definido dinámicamente vía ExtensionPoints |

**Jerarquía de Herencia**:
```
ADMIN
  ├─ Hereda todos los permisos de SELLER
  └─ + Permisos exclusivos de administración

SELLER
  └─ Permisos operativos base

SPECIAL
  └─ Matriz de permisos configurable por administrador
```

#### 3.2.3 Ítems de Implementación

**Ítem 3.2.3.1**: Enum de Roles (PHP 8.2)
```php
declare(strict_types=1);

namespace Aura\Core\Auth;

enum Role: string
{
    case ADMIN = 'ADMIN';
    case SELLER = 'SELLER';
    case SPECIAL = 'SPECIAL';

    /**
     * Obtiene el ID numérico del rol para relaciones DB.
     */
    public function getId(): int
    {
        return match($this) {
            self::ADMIN => 1,
            self::SELLER => 2,
            self::SPECIAL => 3,
        };
    }

    /**
     * Verifica si este rol hereda de otro.
     */
    public function inheritsFrom(Role $role): bool
    {
        return match($this) {
            self::ADMIN => in_array($role, [self::SELLER, self::ADMIN]),
            self::SELLER => $role === self::SELLER,
            self::SPECIAL => $role === self::SPECIAL,
        };
    }
}
```

**Ítem 3.2.3.2**: Clase `Auth` con Verificación de Permisos
```php
declare(strict_types=1);

namespace Aura\Core\Auth;

use PDO;

final class Auth
{
    private ?array $permissionsCache = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly int $userId,
        private readonly Role $activeRole
    ) {}

    /**
     * Verifica si el usuario tiene un permiso específico.
     * 
     * Aplica las "Tres Reglas de Hierro":
     * 1. Permisos asignados a roles, no usuarios
     * 2. Valida solo permisos del rol activo en sesión
     * 3. Respeta jerarquía de herencia
     * 
     * @param string $permissionSlug Identificador del permiso (ej: 'ventas.crear')
     * @return bool True si el usuario tiene el permiso
     */
    public function checkPermission(string $permissionSlug): bool
    {
        // Lazy load de permisos con cache de sesión
        if ($this->permissionsCache === null) {
            $this->loadPermissions();
        }

        return in_array($permissionSlug, $this->permissionsCache, true);
    }

    /**
     * Middleware de autorización. Lanza excepción si falta permiso.
     * 
     * @throws UnauthorizedException
     */
    public function requirePermission(string $permissionSlug): void
    {
        if (!$this->checkPermission($permissionSlug)) {
            throw new UnauthorizedException(
                "Permiso requerido: {$permissionSlug}. Rol activo: {$this->activeRole->value}"
            );
        }
    }

    private function loadPermissions(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT p.slug 
            FROM permisos p
            INNER JOIN rol_permisos rp ON p.id = rp.permiso_id
            WHERE rp.rol_id = :rol_id
        ");
        
        $stmt->execute(['rol_id' => $this->activeRole->getId()]);
        $this->permissionsCache = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Aplicar herencia jerárquica
        if ($this->activeRole === Role::ADMIN) {
            $this->inheritPermissionsFrom(Role::SELLER);
        }
    }

    private function inheritPermissionsFrom(Role $parentRole): void
    {
        $stmt = $this->pdo->prepare("
            SELECT p.slug 
            FROM permisos p
            INNER JOIN rol_permisos rp ON p.id = rp.permiso_id
            WHERE rp.rol_id = :rol_id
        ");
        
        $stmt->execute(['rol_id' => $parentRole->getId()]);
        $inheritedPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->permissionsCache = array_unique(
            array_merge($this->permissionsCache, $inheritedPermissions)
        );
    }
}
```

**Ítem 3.2.3.3**: Tabla de Permisos Base
```sql
CREATE TABLE permisos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) UNIQUE NOT NULL,
    descripcion VARCHAR(255),
    modulo VARCHAR(50),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol_permisos (
    rol_id INT UNSIGNED NOT NULL,
    permiso_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (rol_id, permiso_id),
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permisos base para SELLER
INSERT INTO permisos (slug, descripcion, modulo) VALUES
('ventas.crear', 'Registrar nuevas ventas en el POS', 'Ventas'),
('ventas.listar', 'Ver historial de ventas', 'Ventas'),
('productos.ver', 'Consultar catálogo de productos', 'Inventario'),
('pagos.procesar', 'Procesar pagos de clientes', 'Finanzas');

-- Permisos exclusivos de ADMIN
INSERT INTO permisos (slug, descripcion, modulo) VALUES
('usuarios.administrar', 'Crear/editar/eliminar usuarios', 'Sistema'),
('config.modificar', 'Cambiar configuración del tenant', 'Sistema'),
('backups.ejecutar', 'Generar respaldos de datos', 'Sistema');
```

---

### 3.3 Integridad Transaccional y Protocolo de Modelo PDO

#### 3.3.1 Garantía ACID en Operaciones Críticas

La integridad de Aura es delegada en el cumplimiento estricto de las propiedades ACID:

- **A**tomicidad: Todas las operaciones de una transacción se ejecutan o ninguna
- **C**onsistencia: Los datos cumplen todas las reglas de integridad definidas
- **I**solation (Aislamiento): Transacciones concurrentes no interfieren entre sí
- **D**urabilidad: Una vez confirmada, la transacción persiste incluso ante fallos catastróficos

**Caso Crítico en POS**: La atomicidad garantiza que la venta y la actualización de stock ocurran simultáneamente o no ocurran en absoluto.

#### 3.3.2 Mandato Técnico: PDO como Security Firewall

**PROHIBICIONES ESTRICTAS**:
- ❌ SQL directo en controladores
- ❌ Concatenación de strings para construir queries
- ❌ Uso de `mysql_*` functions (deprecadas)

**OBLIGACIONES**:
- ✅ Toda interacción DB vía patrón MVC → Modelo PDO
- ✅ Prepared statements con placeholders nombrados
- ✅ Transacciones explícitas para operaciones multi-tabla

#### 3.3.3 Ítems de Implementación

**Ítem 3.3.3.1**: Controlador POS con Transacción Atómica
```php
declare(strict_types=1);

namespace Aura\Core\Controllers;

use Aura\Core\Auth\Auth;
use Aura\Core\Models\VentaModel;
use Aura\Core\Models\StockModel;
use PDO;
use PDOException;

final class SalesController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Auth $auth,
        private readonly VentaModel $ventaModel,
        private readonly StockModel $stockModel
    ) {}

    /**
     * Procesa una venta con actualización atómica de inventario.
     * 
     * Aplica propiedades ACID:
     * - Atomicidad: Venta + Stock + Pago en una sola transacción
     * - Consistencia: Validaciones de stock antes de commit
     * - Aislamiento: REPEATABLE READ para evitar condiciones de carrera
     * - Durabilidad: Commit garantiza persistencia
     * 
     * @param array $ventaData Datos de la venta
     * @param array $items Productos con cantidades
     * @param array $pagos Métodos de pago aplicados
     * @return int ID de la venta creada
     * @throws UnauthorizedException Si el usuario no tiene permisos
     * @throws VentaException Si falla validación o transacción
     */
    public function procesarVenta(
        array $ventaData,
        array $items,
        array $pagos
    ): int {
        // Verificar permiso (Regla #3 del RBAC)
        $this->auth->requirePermission('ventas.crear');

        try {
            // Nivel de aislamiento para evitar lecturas inconsistentes
            $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            
            $this->pdo->beginTransaction();

            // 1. Validar stock disponible (antes de cualquier modificación)
            foreach ($items as $item) {
                if (!$this->stockModel->hayStockDisponible($item['producto_id'], $item['cantidad'])) {
                    throw new VentaException(
                        "Stock insuficiente para producto ID {$item['producto_id']}"
                    );
                }
            }

            // 2. Registrar venta
            $ventaId = $this->ventaModel->crear($ventaData);

            // 3. Registrar detalle de items
            foreach ($items as $item) {
                $this->ventaModel->agregarItem($ventaId, $item);
            }

            // 4. Actualizar stock (decrementar)
            foreach ($items as $item) {
                $this->stockModel->decrementar(
                    $item['producto_id'],
                    $item['cantidad']
                );
            }

            // 5. Registrar pagos
            foreach ($pagos as $pago) {
                $this->ventaModel->registrarPago($ventaId, $pago);
            }

            // 6. Validar que suma de pagos = total venta
            $totalPagos = array_sum(array_column($pagos, 'monto'));
            $totalVenta = $ventaData['total'];
            
            if (abs($totalPagos - $totalVenta) > 0.01) { // Tolerancia de centavos
                throw new VentaException(
                    "Total de pagos ({$totalPagos}) no coincide con total venta ({$totalVenta})"
                );
            }

            // 7. Confirmar transacción (Durabilidad garantizada)
            $this->pdo->commit();

            // Log de auditoría
            $this->logVenta($ventaId, $this->auth->getUserId());

            return $ventaId;

        } catch (PDOException $e) {
            // Rollback automático en caso de error
            $this->pdo->rollBack();
            
            // Log del error para análisis forense
            error_log("Transacción de venta falló: " . $e->getMessage());
            
            throw new VentaException(
                "Error al procesar venta: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    private function logVenta(int $ventaId, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO auditoria_ventas (venta_id, usuario_id, accion, timestamp)
            VALUES (:venta_id, :usuario_id, 'CREADA', NOW())
        ");
        
        $stmt->execute([
            'venta_id' => $ventaId,
            'usuario_id' => $userId
        ]);
    }
}
```

**Ítem 3.3.3.2**: Modelo de Datos con Prepared Statements
```php
declare(strict_types=1);

namespace Aura\Core\Models;

use PDO;

final class VentaModel
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function crear(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ventas (
                cliente_id,
                total,
                subtotal,
                impuestos,
                descuento,
                estado,
                created_at
            ) VALUES (
                :cliente_id,
                :total,
                :subtotal,
                :impuestos,
                :descuento,
                'COMPLETADA',
                NOW()
            )
        ");

        $stmt->execute([
            'cliente_id' => $data['cliente_id'],
            'total' => $data['total'],
            'subtotal' => $data['subtotal'],
            'impuestos' => $data['impuestos'],
            'descuento' => $data['descuento'] ?? 0
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function agregarItem(int $ventaId, array $item): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO venta_items (
                venta_id,
                producto_id,
                cantidad,
                precio_unitario,
                subtotal
            ) VALUES (
                :venta_id,
                :producto_id,
                :cantidad,
                :precio_unitario,
                :subtotal
            )
        ");

        $stmt->execute([
            'venta_id' => $ventaId,
            'producto_id' => $item['producto_id'],
            'cantidad' => $item['cantidad'],
            'precio_unitario' => $item['precio_unitario'],
            'subtotal' => $item['cantidad'] * $item['precio_unitario']
        ]);
    }

    // Selección explícita de columnas (NO SELECT *)
    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                v.id,
                v.cliente_id,
                v.total,
                v.subtotal,
                v.impuestos,
                v.descuento,
                v.estado,
                v.created_at,
                c.nombre AS cliente_nombre
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            WHERE v.id = :id
        ");

        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}
```

---

## 4. Fase II: Ecosistema de Extensibilidad Controlada

### 4.1 Sistema de Plugins con Carga Prioritaria

Inspirados en **FacturaScripts**, los plugins pueden **sustituir** archivos del núcleo mediante rutas idénticas, permitiendo personalización total sin modificar el core.

**Ítems de Implementación**:
- Clase `PluginLoader` con resolución prioritaria de archivos
- Metadata `plugin.json` con versionado semántico
- Sistema de activación/desactivación de plugins

### 4.2 Sistema de Hooks y Eventos

Los **hooks** permiten inyectar lógica en puntos estratégicos del flujo:

**Hooks Críticos**:
- `before_sale_creation`: Validaciones custom pre-venta
- `after_sale_creation`: Integraciones post-venta (facturación electrónica, email, etc.)
- `before_stock_update`: Alertas de inventario bajo

---

## 5. Super-Prompt Maestro para GitHub Copilot

Este prompt es la **instrucción definitiva de scaffolding** para implementar la arquitectura Aura de forma blindada.

```markdown
# ROL: Arquitecto de Soluciones Senior & Ingeniero de Prompts Experto
# CONTEXTO: Fase I de la Plataforma Aura (El "WordPress de la Contabilidad")
# ARQUITECTURA: Multi-tenant vía Esquemas MySQL, RBAC Jerárquico, Cumplimiento ACID
# INSPIRACIÓN: FacturaScripts, Gentelella

---

## TAREA 1: ESTRUCTURA DE DIRECTORIOS & LÓGICA DE PLUGINS

Generar estructura MVC en PHP 8.2+:

### Estructura de Directorios
```
/aura
├── /core
│   ├── /controllers
│   ├── /models
│   ├── /vistas
│   └── /lib
│       ├── SchemaManager.php
│       ├── Auth.php
│       └── PluginLoader.php
├── /plugins
│   └── /{nombre_plugin}
│       ├── plugin.json
│       ├── /controllers
│       ├── /models
│       └── /vistas
├── /public
│   ├── index.php
│   └── /assets
└── /storage
    ├── /logs
    └── /cache
```

### Lógica de Carga Prioritaria
- **IMPLEMENTAR**: Clase `PluginLoader` que resuelva rutas de archivos
- **REGLA**: Si un plugin activo contiene un archivo en ruta idéntica al core (ej: `/plugins/test/vistas/Venta.php`), cargar el del plugin preferencialmente
- **CACHE**: Almacenar resoluciones de rutas para optimizar rendimiento

---

## TAREA 2: GESTOR DE ESQUEMAS DINÁMICO (SchemaManager)

Implementar clase `SchemaManager` usando PHP PDO:

### Funcionalidades Requeridas

**Método 1: `switchToTenant(string $tenantId): void`**
- Usar `PDO::exec("USE tenant_{$tenantId}")` para cambiar contexto dinámicamente
- Validar existencia del esquema antes del switch
- Lanzar `PDOException` si el esquema no existe

**Método 2: `createTenantSchema(string $tenantId): bool`**
- Generar esquema MySQL con nomenclatura `tenant_{$tenantId}`
- Crear tablas base: `usuarios`, `roles`, `permisos`, `rol_permisos`
- Insertar roles iniciales: ADMIN (id=1), SELLER (id=2), SPECIAL (id=3)
- Usar transacciones para garantizar atomicidad

### Restricciones Técnicas
- ❌ **PROHIBIDO**: Usar `SELECT *` (forzar selección explícita de columnas)
- ✅ **OBLIGATORIO**: Usar prepared statements con placeholders nombrados
- ✅ **OBLIGATORIO**: Charset UTF8MB4 con collation unicode_ci

### Código de Ejemplo Esperado
```php
declare(strict_types=1);

namespace Aura\Core\Database;

use PDO;
use PDOException;

final class SchemaManager
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function switchToTenant(string $tenantId): void
    {
        $schemaName = "tenant_" . preg_replace('/[^a-z0-9_]/', '', $tenantId);
        
        if (!$this->schemaExists($schemaName)) {
            throw new PDOException("Esquema no encontrado: {$schemaName}");
        }
        
        $this->pdo->exec("USE `{$schemaName}`");
    }

    public function createTenantSchema(string $tenantId): bool
    {
        $schemaName = "tenant_" . preg_replace('/[^a-z0-9_]/', '', $tenantId);
        
        try {
            $this->pdo->beginTransaction();
            
            $this->pdo->exec("CREATE DATABASE `{$schemaName}` 
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            $this->pdo->exec("USE `{$schemaName}`");
            
            $this->runBaseMigrations();
            
            $this->pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function schemaExists(string $schemaName): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA 
             WHERE SCHEMA_NAME = :name"
        );
        $stmt->execute(['name' => $schemaName]);
        return $stmt->fetch() !== false;
    }

    private function runBaseMigrations(): void
    {
        // Implementar creación de tablas base
    }
}
```

---

## TAREA 3: RBAC JERÁRQUICO CON ENUMS

Crear clase `Auth` usando PHP 8.2 Enums para roles.

### Enum de Roles
```php
declare(strict_types=1);

namespace Aura\Core\Auth;

enum Role: string
{
    case ADMIN = 'ADMIN';
    case SELLER = 'SELLER';
    case SPECIAL = 'SPECIAL';

    public function getId(): int
    {
        return match($this) {
            self::ADMIN => 1,
            self::SELLER => 2,
            self::SPECIAL => 3,
        };
    }

    public function inheritsFrom(Role $role): bool
    {
        return match($this) {
            self::ADMIN => in_array($role, [self::SELLER, self::ADMIN]),
            self::SELLER => $role === self::SELLER,
            self::SPECIAL => $role === self::SPECIAL,
        };
    }
}
```

### Clase Auth con Verificación de Permisos

**Método Principal: `checkPermission(string $slug): bool`**
- Validar que el rol activo tiene el permiso especificado
- Aplicar **"Tres Reglas de Hierro"**:
  1. **Asignación a Roles**: Permisos vinculados a roles, no usuarios
  2. **Mínimo Privilegio**: Solo permisos necesarios para la función
  3. **Validación de Rol Activo**: Verificar rol en sesión actual

**Jerarquía de Herencia**:
- ADMIN hereda automáticamente todos los permisos de SELLER
- SPECIAL permite configuración dinámica vía ExtensionPoints

### Código Esperado
```php
declare(strict_types=1);

namespace Aura\Core\Auth;

use PDO;

final class Auth
{
    private ?array $permissionsCache = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly int $userId,
        private readonly Role $activeRole
    ) {}

    public function checkPermission(string $permissionSlug): bool
    {
        if ($this->permissionsCache === null) {
            $this->loadPermissions();
        }

        return in_array($permissionSlug, $this->permissionsCache, true);
    }

    public function requirePermission(string $permissionSlug): void
    {
        if (!$this->checkPermission($permissionSlug)) {
            throw new UnauthorizedException(
                "Permiso requerido: {$permissionSlug}"
            );
        }
    }

    private function loadPermissions(): void
    {
        // Cargar permisos del rol activo desde DB
        // Aplicar herencia si es ADMIN
    }
}
```

---

## TAREA 4: CONTROLADOR POS ATÓMICO

Generar `SalesController` para transacciones de punto de venta.

### Requisitos de Transacción ACID

**OBLIGATORIO**: Usar try-catch envolviendo una transacción PDO:
```php
try {
    $this->pdo->beginTransaction();
    
    // 1. Registrar venta
    // 2. Actualizar stock
    // 3. Registrar log de transacción
    
    $this->pdo->commit();
    
} catch (PDOException $e) {
    $this->pdo->rollBack();
    throw $e;
}
```

### Lógica de Negocio
1. **Validar permisos**: Usar `Auth::requirePermission('ventas.crear')`
2. **Validar stock**: Verificar disponibilidad antes de decrementar
3. **Atomicidad**: Todas las operaciones en una sola transacción
4. **Durabilidad**: Commit solo si todas las operaciones son exitosas

### Restricciones de Seguridad
- Solo usuarios con rol SELLER o ADMIN pueden ejecutar
- Validación de permisos antes de iniciar transacción
- Log de auditoría en tabla `auditoria_ventas`

---

## CONSTRAINTS & BLINDAJE TÉCNICO

### Estrictos (Incumplimiento = Violación de Arquitectura)

1. **Sin SQL Directo en Controladores**
   - ❌ PROHIBIDO: SQL strings en controllers
   - ✅ OBLIGATORIO: Toda lógica DB en modelos PDO

2. **Tipado Estricto**
   - ✅ USAR: `readonly properties` donde sea aplicable
   - ✅ USAR: Type hints en parámetros y returns
   - ✅ AGREGAR: `declare(strict_types=1);` en todos los archivos

3. **Estándar de Código**
   - ✅ SEGUIR: PSR-12 (indentación 4 espacios, encoding UTF-8)
   - ✅ COMENTARIOS: En español, explicando el "por qué" estratégico
   - ✅ NOMENCLATURA: PascalCase para clases, camelCase para métodos

4. **Cumplimiento ACID**
   - ✅ Atomicidad: Transacciones explícitas con rollback
   - ✅ Consistencia: Validaciones antes de commit
   - ✅ Aislamiento: Nivel REPEATABLE READ para operaciones críticas
   - ✅ Durabilidad: Commit solo al finalizar con éxito

### Output Esperado
- Código PHP puro funcional (sin explicaciones textuales previas)
- Comentarios técnicos integrados en español
- Respeto absoluto a las convenciones PSR-12
```

---

## 6. Reflexión de Cierre

Este mandato constituye el **código genético de Aura**. No estamos simplemente programando; estamos orquestando un ecosistema donde la seguridad no es una fricción, sino el motor de la innovación. 

Al establecer estas reglas desde la Fase I, aseguramos que Aura no solo sea una aplicación, sino una **infraestructura arquitectada para dominar** el mercado ERP/CRM con:

- **Confianza Inquebrantable**: Aislamiento multi-tenant de grado bancario
- **Extensibilidad Sin Límites**: Ecosistema de plugins seguro y escalable
- **Mantenibilidad Perpetua**: Núcleo inmutable que permite actualizaciones masivas

**La precisión en este andamiaje técnico es lo que transforma la robustez en valor comercial.**
