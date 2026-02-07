<?php
declare(strict_types=1);

namespace Aura\Core\Auth;

use PDO;

/**
 * Sistema de Autenticación y Autorización RBAC
 * 
 * Implementa el Control de Acceso Basado en Roles (RBAC) Jerárquico.
 * Cumple con RF-003 y RF-004 del PRD.
 * 
 * Las "Tres Reglas de Hierro" del RBAC:
 * 1. Asignación a Roles: Los permisos residen en el rol, jamás en el usuario
 * 2. Mínimo Privilegio: Acceso restringido a lo estrictamente necesario
 * 3. Autorización de Rol Activo: Validación obligatoria del rol en sesión
 * 
 * @package Aura\Core\Auth
 */
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
     * Implementa RF-004: Verificación de Permisos en Tiempo de Ejecución.
     * 
     * Aplica las "Tres Reglas de Hierro":
     * 1. Permisos asignados a roles, no usuarios
     * 2. Valida solo permisos del rol activo en sesión
     * 3. Respeta jerarquía de herencia (ADMIN hereda de SELLER)
     * 
     * @param string $permissionSlug Identificador del permiso (ej: 'ventas.crear')
     * @return bool True si el usuario tiene el permiso
     */
    public function checkPermission(string $permissionSlug): bool
    {
        // Lazy load de permisos con cache de sesión (NFR-PERF-001)
        if ($this->permissionsCache === null) {
            $this->loadPermissions();
        }

        return in_array($permissionSlug, $this->permissionsCache, true);
    }

    /**
     * Middleware de autorización. Lanza excepción si falta permiso.
     * 
     * Uso típico en controladores:
     * ```php
     * $auth->requirePermission('ventas.crear');
     * // Código que requiere el permiso...
     * ```
     * 
     * @param string $permissionSlug Permiso requerido
     * @throws UnauthorizedException Si el usuario no tiene el permiso
     */
    public function requirePermission(string $permissionSlug): void
    {
        if (!$this->checkPermission($permissionSlug)) {
            throw new UnauthorizedException(
                "Permiso requerido: '{$permissionSlug}'. " .
                "Rol activo: {$this->activeRole->value} (Usuario ID: {$this->userId})"
            );
        }
    }

    /**
     * Verifica si el usuario tiene ALGUNO de los permisos especificados.
     * 
     * @param array $permissionSlugs Array de slugs de permisos
     * @return bool True si tiene al menos uno
     */
    public function hasAnyPermission(array $permissionSlugs): bool
    {
        foreach ($permissionSlugs as $slug) {
            if ($this->checkPermission($slug)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica si el usuario tiene TODOS los permisos especificados.
     * 
     * @param array $permissionSlugs Array de slugs de permisos
     * @return bool True si tiene todos
     */
    public function hasAllPermissions(array $permissionSlugs): bool
    {
        foreach ($permissionSlugs as $slug) {
            if (!$this->checkPermission($slug)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Carga los permisos del rol activo desde la base de datos.
     * 
     * Implementa cache en memoria para evitar consultas repetidas
     * en el mismo request (NFR-PERF-001: P95 < 300ms).
     */
    private function loadPermissions(): void
    {
        // Cargar permisos directos del rol activo
        $stmt = $this->pdo->prepare("
            SELECT p.slug 
            FROM permisos p
            INNER JOIN rol_permisos rp ON p.id = rp.permiso_id
            WHERE rp.rol_id = :rol_id
        ");
        
        $stmt->execute(['rol_id' => $this->activeRole->getId()]);
        $this->permissionsCache = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Aplicar herencia jerárquica si el rol hereda de otros
        if ($this->activeRole === Role::ADMIN) {
            // ADMIN hereda automáticamente todos los permisos de SELLER
            $this->inheritPermissionsFrom(Role::SELLER);
        }
    }

    /**
     * Hereda permisos de un rol padre.
     * 
     * Implementa la jerarquía de roles donde roles superiores
     * heredan los permisos de roles inferiores.
     * 
     * @param Role $parentRole Rol del cual heredar permisos
     */
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

        // Combinar permisos únicos (evitar duplicados)
        $this->permissionsCache = array_unique(
            array_merge($this->permissionsCache, $inheritedPermissions)
        );
    }

    /**
     * Obtiene el ID del usuario autenticado.
     * 
     * @return int User ID
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Obtiene el rol activo en la sesión actual.
     * 
     * @return Role Rol activo
     */
    public function getActiveRole(): Role
    {
        return $this->activeRole;
    }

    /**
     * Invalida el cache de permisos.
     * 
     * Útil cuando se modifican permisos del rol durante la sesión.
     */
    public function invalidatePermissionsCache(): void
    {
        $this->permissionsCache = null;
    }

    /**
     * Obtiene todos los permisos del usuario (para debugging).
     * 
     * @return array Array de slugs de permisos
     */
    public function getAllPermissions(): array
    {
        if ($this->permissionsCache === null) {
            $this->loadPermissions();
        }

        return $this->permissionsCache;
    }

    /**
     * Verifica las credenciales de un usuario y retorna instancia de Auth.
     * 
     * @param PDO $pdo Conexión a la base de datos del tenant
     * @param string $username Nombre de usuario
     * @param string $password Contraseña en texto plano
     * @return self|null Instancia de Auth si las credenciales son válidas
     */
    public static function authenticate(PDO $pdo, string $username, string $password): ?self
    {
        // Buscar usuario por username (selección explícita de columnas)
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.password_hash,
                u.rol_id,
                u.activo
            FROM usuarios u
            WHERE u.username = :username
            LIMIT 1
        ");
        
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            // Usuario no encontrado
            return null;
        }

        if (!$user['activo']) {
            // Usuario desactivado
            throw new UnauthorizedException("Usuario desactivado");
        }

        // Verificar contraseña con bcrypt
        if (!password_verify($password, $user['password_hash'])) {
            // Contraseña incorrecta
            return null;
        }

        // Actualizar último acceso
        $updateStmt = $pdo->prepare("
            UPDATE usuarios 
            SET ultimo_acceso = NOW() 
            WHERE id = :id
        ");
        $updateStmt->execute(['id' => $user['id']]);

        // Crear instancia de Auth con el rol del usuario
        $role = Role::fromId((int)$user['rol_id']);
        
        return new self($pdo, (int)$user['id'], $role);
    }

    /**
     * Registra un intento de acceso no autorizado en logs de auditoría.
     * 
     * @param string $permissionSlug Permiso que se intentó acceder
     * @param string $resource Recurso al que se intentó acceder
     */
    public function logUnauthorizedAccess(string $permissionSlug, string $resource): void
    {
        error_log(sprintf(
            "UNAUTHORIZED_ACCESS: Usuario %d (Rol: %s) intentó acceder a '%s' requiriendo permiso '%s'",
            $this->userId,
            $this->activeRole->value,
            $resource,
            $permissionSlug
        ));

        // TODO: Registrar en tabla de auditoría de seguridad
        // Implementar rate limiting si hay múltiples intentos
    }
}
