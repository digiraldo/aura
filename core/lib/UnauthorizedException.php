<?php
declare(strict_types=1);

namespace Aura\Core\Auth;

use Exception;

/**
 * Excepción lanzada cuando un usuario no tiene autorización.
 * 
 * Se lanza cuando:
 * - Faltan permisos para ejecutar una acción
 * - El usuario está desactivado
 * - El rol activo no tiene los privilegios necesarios
 * 
 * @package Aura\Core\Auth
 */
class UnauthorizedException extends Exception
{
    public function __construct(string $message = "Acceso no autorizado", int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
