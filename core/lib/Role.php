<?php
declare(strict_types=1);

namespace Aura\Core\Auth;

/**
 * Enum de Roles del Sistema
 * 
 * Define los roles jerárquicos de Aura usando PHP 8.2 Enums.
 * Implementa RF-003 del PRD: Definición de Roles Jerárquicos.
 * 
 * Jerarquía:
 * - ADMIN: Hereda todos los permisos de SELLER + permisos administrativos
 * - SELLER: Permisos operativos de POS
 * - SPECIAL: Rol personalizable vía ExtensionPoints
 * 
 * @package Aura\Core\Auth
 */
enum Role: string
{
    case ADMIN = 'ADMIN';
    case SELLER = 'SELLER';
    case SPECIAL = 'SPECIAL';

    /**
     * Obtiene el ID numérico del rol para relaciones de base de datos.
     * 
     * Los IDs deben coincidir con la tabla 'roles' creada en SchemaManager.
     * 
     * @return int ID del rol (1=ADMIN, 2=SELLER, 3=SPECIAL)
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
     * Verifica si este rol hereda permisos de otro rol.
     * 
     * Implementa la jerarquía de roles donde ADMIN hereda de SELLER.
     * Esto permite aplicar el principio de herencia en RBAC jerárquico.
     * 
     * @param Role $role Rol a verificar si es heredado
     * @return bool True si este rol hereda del rol especificado
     */
    public function inheritsFrom(Role $role): bool
    {
        return match($this) {
            // ADMIN hereda permisos de SELLER y de sí mismo
            self::ADMIN => in_array($role, [self::SELLER, self::ADMIN], true),
            
            // SELLER solo tiene sus propios permisos
            self::SELLER => $role === self::SELLER,
            
            // SPECIAL es independiente (permisos configurables)
            self::SPECIAL => $role === self::SPECIAL,
        };
    }

    /**
     * Obtiene el nombre legible del rol para UI.
     * 
     * @return string Nombre en español
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::ADMIN => 'Administrador',
            self::SELLER => 'Vendedor',
            self::SPECIAL => 'Especial',
        };
    }

    /**
     * Obtiene la descripción del rol.
     * 
     * @return string Descripción del rol
     */
    public function getDescription(): string
    {
        return match($this) {
            self::ADMIN => 'Administrador con control total de la instancia',
            self::SELLER => 'Vendedor con permisos operativos de POS',
            self::SPECIAL => 'Rol personalizable vía ExtensionPoints',
        };
    }

    /**
     * Crea una instancia de Role desde un ID numérico.
     * 
     * @param int $id ID del rol
     * @return self Instancia del enum
     * @throws \ValueError Si el ID no es válido
     */
    public static function fromId(int $id): self
    {
        return match($id) {
            1 => self::ADMIN,
            2 => self::SELLER,
            3 => self::SPECIAL,
            default => throw new \ValueError("ID de rol inválido: {$id}"),
        };
    }

    /**
     * Verifica si este rol tiene permisos administrativos.
     * 
     * @return bool True si es un rol administrativo
     */
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Verifica si este rol puede gestionar usuarios.
     * 
     * @return bool True si puede gestionar usuarios
     */
    public function canManageUsers(): bool
    {
        return $this === self::ADMIN;
    }
}
