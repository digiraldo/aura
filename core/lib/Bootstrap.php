<?php
declare(strict_types=1);

namespace Aura\Core;

use PDO;
use PDOException;
use Aura\Core\Database\SchemaManager;
use Aura\Core\Plugins\PluginLoader;
use Aura\Core\Auth\Auth;

/**
 * Clase Bootstrap - Inicializador de la Aplicación
 * 
 * Responsable de:
 * - Cargar configuración
 * - Establecer conexión a BD
 * - Identificar tenant y cambiar esquema
 * - Inicializar sistema de plugins
 * - Configurar manejo de errores
 * 
 * @package Aura\Core
 */
final class Bootstrap
{
    private PDO $pdo;
    private SchemaManager $schemaManager;
    private PluginLoader $pluginLoader;
    private ?Auth $auth = null;
    private array $config = [];

    public function __construct()
    {
        // Cargar configuración
        $this->loadConfiguration();
        
        // Configurar PHP
        $this->configurePhp();
        
        // Establecer conexión a base de datos
        $this->connectDatabase();
        
        // Inicializar gestores
        $this->schemaManager = new SchemaManager($this->pdo);
        $this->pluginLoader = new PluginLoader(
            $this->pdo,
            $this->config['paths']['plugins'],
            $this->config['paths']['core']
        );
    }

    /**
     * Inicializa la aplicación para un request.
     * 
     * @return self Para encadenamiento
     */
    public function boot(): self
    {
        // Identificar y cambiar al esquema del tenant
        $this->switchToTenant();
        
        // Iniciar sesión
        $this->startSession();
        
        return $this;
    }

    /**
     * Carga los archivos de configuración.
     */
    private function loadConfiguration(): void
    {
        $configPath = __DIR__ . '/../config/';
        
        $this->config = array_merge(
            require $configPath . 'app.php',
            ['database' => require $configPath . 'database.php']
        );
    }

    /**
     * Configura opciones de PHP para la aplicación.
     */
    private function configurePhp(): void
    {
        // Configurar zona horaria
        date_default_timezone_set($this->config['timezone']);
        
        // Configurar reporte de errores
        if ($this->config['debug']) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
            ini_set('error_log', $this->config['paths']['storage'] . 'logs/php_errors.log');
        }

        // Configurar sesiones
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '0'); // Permitir HTTP (cambiar a 1 en producción con HTTPS)
        ini_set('session.use_strict_mode', '1');
    }

    /**
     * Establece conexión con la base de datos.
     */
    private function connectDatabase(): void
    {
        $dbConfig = $this->config['database'];
        
        $dsn = sprintf(
            "mysql:host=%s;port=%d;charset=%s",
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['charset']
        );

        try {
            $this->pdo = new PDO(
                $dsn,
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['options']
            );
            
        } catch (PDOException $e) {
            // Log del error
            error_log("Error de conexión a BD: " . $e->getMessage());
            
            // Mostrar error genérico al usuario
            $this->showError(
                "Error de Conexión",
                "No se pudo conectar a la base de datos. Por favor, contacte al administrador.",
                500
            );
        }
    }

    /**
     * Identifica el tenant y cambia al esquema correspondiente.
     * 
     * Implementa RF-002: Switch Dinámico de Esquema.
     */
    private function switchToTenant(): void
    {
        $tenantId = $this->identifyTenant();

        if (!$tenantId) {
            // Sin tenant identificado, usar esquema master para login/registro
            try {
                $this->pdo->exec("USE `{$this->config['tenancy']['master_schema']}`");
            } catch (PDOException $e) {
                $this->showError(
                    "Error de Configuración",
                    "Esquema master no encontrado. Por favor, ejecute las migraciones.",
                    500
                );
            }
            return;
        }

        try {
            // RF-002: Switch de esquema con overhead < 5ms
            $this->schemaManager->switchToTenant($tenantId);
            
        } catch (PDOException $e) {
            error_log("Error switching to tenant '{$tenantId}': " . $e->getMessage());
            
            $this->showError(
                "Inquilino No Encontrado",
                "El inquilino solicitado no existe o está inactivo.",
                404
            );
        }
    }

    /**
     * Identifica el tenant según el modo configurado.
     * 
     * @return string|null ID del tenant o null
     */
    private function identifyTenant(): ?string
    {
        $mode = $this->config['tenancy']['mode'];

        if ($mode === 'subdomain') {
            // Extraer tenant del subdominio (ej: empresa.aura.app → empresa)
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $parts = explode('.', $host);
            
            if (count($parts) >= 2) {
                return $parts[0];
            }
            
        } elseif ($mode === 'header') {
            // Extraer tenant del header HTTP
            return $_SERVER['HTTP_X_TENANT_ID'] ?? null;
        }

        return null;
    }

    /**
     * Inicia la sesión de usuario.
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Regenerar ID de sesión periódicamente (seguridad)
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Cada 5 minutos
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }

        // Reconstruir Auth desde sesión si existe
        if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $this->auth === null) {
            try {
                $this->auth = Auth::fromSession(
                    $this->pdo,
                    (int)$_SESSION['user_id'],
                    $_SESSION['role']
                );
                
                // Si el usuario ya no existe o está desactivado, limpiar sesión
                if ($this->auth === null) {
                    unset($_SESSION['user_id'], $_SESSION['role']);
                }
            } catch (\Exception $e) {
                error_log("Error reconstruyendo Auth desde sesión: " . $e->getMessage());
                unset($_SESSION['user_id'], $_SESSION['role']);
                $this->auth = null;
            }
        }
    }

    /**
     * Obtiene la instancia de PDO.
     * 
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Obtiene el SchemaManager.
     * 
     * @return SchemaManager
     */
    public function getSchemaManager(): SchemaManager
    {
        return $this->schemaManager;
    }

    /**
     * Obtiene el PluginLoader.
     * 
     * @return PluginLoader
     */
    public function getPluginLoader(): PluginLoader
    {
        return $this->pluginLoader;
    }

    /**
     * Obtiene la configuración de la aplicación.
     * 
     * @param string|null $key Clave específica o null para toda la config
     * @return mixed
     */
    public function getConfig(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        // Soportar notación de punto (ej: 'database.host')
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Establece la instancia de Auth autenticada.
     * 
     * @param Auth $auth
     */
    public function setAuth(Auth $auth): void
    {
        $this->auth = $auth;
        $_SESSION['user_id'] = $auth->getUserId();
        $_SESSION['role'] = $auth->getActiveRole()->value;
    }

    /**
     * Obtiene la instancia de Auth actual.
     * 
     * @return Auth|null
     */
    public function getAuth(): ?Auth
    {
        return $this->auth;
    }

    /**
     * Verifica si hay un usuario autenticado.
     * 
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->auth !== null && isset($_SESSION['user_id']);
    }

    /**
     * Muestra una página de error y detiene la ejecución.
     * 
     * @param string $title Título del error
     * @param string $message Mensaje del error
     * @param int $code Código HTTP
     */
    private function showError(string $title, string $message, int $code = 500): never
    {
        http_response_code($code);
        
        if ($this->config['debug']) {
            // En modo debug, mostrar detalles
            echo "<h1>{$code} - {$title}</h1>";
            echo "<p>{$message}</p>";
            if (isset($e)) {
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
            }
        } else {
            // En producción, mensaje genérico
            echo "<!DOCTYPE html>
<html>
<head>
    <title>{$code} - Error</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #dc3545; }
    </style>
</head>
<body>
    <h1>{$title}</h1>
    <p>{$message}</p>
</body>
</html>";
        }
        
        exit;
    }
}
