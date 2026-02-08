<?php
declare(strict_types=1);

namespace Aura\Core\Plugins;

use PDO;

/**
 * Gestor de Carga de Plugins
 * 
 * Implementa RF-007: Carga Prioritaria de Archivos de Plugins.
 * 
 * Sistema inspirado en FacturaScripts donde los plugins pueden
 * sustituir archivos del core mediante rutas idénticas.
 * 
 * Filosofía: Núcleo inmutable, extensiones dinámicas.
 * 
 * @package Aura\Core\Plugins
 */
final class PluginLoader
{
    private array $activePlugins = [];
    private array $fileResolutionCache = [];
    
    private string $pluginsDir;
    private string $coreDir;

    public function __construct(
        private readonly PDO $pdo,
        string $pluginsDir,
        string $coreDir
    ) {
        $this->pluginsDir = rtrim($pluginsDir, '/\\');
        $this->coreDir = rtrim($coreDir, '/\\');
        $this->loadActivePlugins();
    }

    /**
     * Resuelve la ruta de un archivo, priorizando plugins sobre core.
     * 
     * Implementa la lógica de carga prioritaria (RF-007):
     * 1. Busca en cada plugin activo (en orden de prioridad)
     * 2. Si no existe, usa el archivo del core
     * 3. Cachea resoluciones para rendimiento
     * 
     * @param string $relativePath Ruta relativa (ej: 'vistas/POS.php')
     * @return string Ruta absoluta del archivo a cargar
     * @throws \RuntimeException Si el archivo no existe
     */
    public function resolveFile(string $relativePath): string
    {
        // Normalizar separadores de directorio
        $relativePath = str_replace('\\', '/', $relativePath);
        
        // Verificar cache
        if (isset($this->fileResolutionCache[$relativePath])) {
            return $this->fileResolutionCache[$relativePath];
        }

        // Buscar en plugins activos (orden de prioridad)
        foreach ($this->activePlugins as $plugin) {
            $pluginFile = $this->pluginsDir . '/' . $plugin['nombre'] . '/' . $relativePath;
            
            if (file_exists($pluginFile)) {
                // Log para auditoría (solo en modo debug)
                if ($_ENV['APP_DEBUG'] ?? false) {
                    error_log("Plugin '{$plugin['nombre']}' sustituye: {$relativePath}");
                }
                
                // Cachear resolución
                $this->fileResolutionCache[$relativePath] = $pluginFile;
                return $pluginFile;
            }
        }

        // Fallback al core
        $coreFile = $this->coreDir . '/' . $relativePath;
        
        if (!file_exists($coreFile)) {
            throw new \RuntimeException(
                "Archivo no encontrado: {$relativePath} " .
                "(buscado en " . count($this->activePlugins) . " plugins y core)"
            );
        }

        // Cachear resolución
        $this->fileResolutionCache[$relativePath] = $coreFile;
        return $coreFile;
    }

    /**
     * Carga un archivo PHP resuelto mediante el sistema de plugins.
     * 
     * @param string $relativePath Ruta relativa del archivo
     * @param array $variables Variables a extraer en el scope del archivo
     * @return mixed Valor retornado por el archivo incluido
     */
    public function loadFile(string $relativePath, array $variables = []): mixed
    {
        $filePath = $this->resolveFile($relativePath);
        
        // Extraer variables al scope local
        extract($variables);
        
        return require $filePath;
    }

    /**
     * Carga la lista de plugins activos desde la base de datos.
     * 
     * Los plugins se ordenan por prioridad (menor número = mayor prioridad).
     */
    private function loadActivePlugins(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT nombre, version, prioridad 
                FROM plugins 
                WHERE activo = TRUE 
                ORDER BY prioridad ASC
            ");
            
            $stmt->execute();
            $this->activePlugins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (\PDOException $e) {
            // Si la tabla no existe (primera ejecución), inicializar vacío
            $this->activePlugins = [];
        }
    }

    /**
     * Instala un nuevo plugin.
     * 
     * Proceso:
     * 1. Validar metadata (plugin.json)
     * 2. Verificar compatibilidad de versiones
     * 3. Ejecutar instalador del plugin (install.php)
     * 4. Registrar en base de datos
     * 
     * @param string $pluginName Nombre del directorio del plugin
     * @return bool True si la instalación fue exitosa
     * @throws \InvalidArgumentException Si el plugin es inválido
     * @throws \RuntimeException Si falla la instalación
     */
    public function installPlugin(string $pluginName): bool
    {
        $pluginPath = $this->pluginsDir . '/' . $pluginName;
        $metadataFile = $pluginPath . '/plugin.json';

        // Validar existencia del directorio
        if (!is_dir($pluginPath)) {
            throw new \InvalidArgumentException("Plugin no encontrado: {$pluginName}");
        }

        // Validar metadata
        if (!file_exists($metadataFile)) {
            throw new \InvalidArgumentException("plugin.json no encontrado en {$pluginName}");
        }

        $metadata = json_decode(file_get_contents($metadataFile), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("plugin.json inválido: " . json_last_error_msg());
        }

        // Validar metadata
        $this->validateMetadata($metadata);

        // Verificar compatibilidad de versión de Aura
        if (version_compare($metadata['requires']['aura_core'], AURA_VERSION, '>')) {
            throw new \RuntimeException(
                "Plugin requiere Aura Core {$metadata['requires']['aura_core']}, " .
                "versión actual: " . AURA_VERSION
            );
        }

        // Ejecutar instalador del plugin si existe
        $installerFile = $pluginPath . '/install.php';
        if (file_exists($installerFile)) {
            try {
                require_once $installerFile;
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    "Error ejecutando instalador del plugin: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        // Registrar plugin en base de datos
        $stmt = $this->pdo->prepare("
            INSERT INTO plugins (nombre, version, descripcion, autor, activo, prioridad)
            VALUES (:nombre, :version, :descripcion, :autor, FALSE, 100)
        ");

        $result = $stmt->execute([
            'nombre' => $metadata['name'],
            'version' => $metadata['version'],
            'descripcion' => $metadata['description'],
            'autor' => $metadata['author']
        ]);

        if ($result) {
            error_log("Plugin '{$metadata['name']}' v{$metadata['version']} instalado correctamente");
        }

        return $result;
    }

    /**
     * Activa un plugin previamente instalado.
     * 
     * @param string $pluginName Nombre del plugin
     * @return bool True si se activó correctamente
     */
    public function activatePlugin(string $pluginName): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE plugins 
            SET activo = TRUE 
            WHERE nombre = :nombre
        ");

        $result = $stmt->execute(['nombre' => $pluginName]);

        if ($result) {
            // Invalidar cache de plugins activos
            $this->loadActivePlugins();
            $this->fileResolutionCache = [];
            
            error_log("Plugin '{$pluginName}' activado");
        }

        return $result;
    }

    /**
     * Desactiva un plugin.
     * 
     * @param string $pluginName Nombre del plugin
     * @return bool True si se desactivó correctamente
     */
    public function deactivatePlugin(string $pluginName): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE plugins 
            SET activo = FALSE 
            WHERE nombre = :nombre
        ");

        $result = $stmt->execute(['nombre' => $pluginName]);

        if ($result) {
            // Invalidar cache
            $this->loadActivePlugins();
            $this->fileResolutionCache = [];
            
            error_log("Plugin '{$pluginName}' desactivado");
        }

        return $result;
    }

    /**
     * Desinstala un plugin (elimina de DB, NO borra archivos).
     * 
     * @param string $pluginName Nombre del plugin
     * @return bool True si se desinstaló correctamente
     */
    public function uninstallPlugin(string $pluginName): bool
    {
        // Ejecutar desinst alador del plugin si existe
        $pluginPath = $this->pluginsDir . '/' . $pluginName;
        $uninstallerFile = $pluginPath . '/uninstall.php';
        
        if (file_exists($uninstallerFile)) {
            try {
                require_once $uninstallerFile;
            } catch (\Exception $e) {
                error_log("Error ejecutando desinstalador del plugin: " . $e->getMessage());
            }
        }

        // Eliminar de base de datos
        $stmt = $this->pdo->prepare("
            DELETE FROM plugins 
            WHERE nombre = :nombre
        ");

        $result = $stmt->execute(['nombre' => $pluginName]);

        if ($result) {
            $this->loadActivePlugins();
            error_log("Plugin '{$pluginName}' desinstalado");
        }

        return $result;
    }

    /**
     * Valida la estructura del metadata de un plugin.
     * 
     * @param array $metadata Metadata del plugin
     * @throws \InvalidArgumentException Si falta algún campo requerido
     */
    private function validateMetadata(array $metadata): void
    {
        $required = ['name', 'version', 'description', 'author', 'requires'];
        
        foreach ($required as $field) {
            if (!isset($metadata[$field])) {
                throw new \InvalidArgumentException("Campo requerido faltante en metadata: {$field}");
            }
        }

        // Validar que 'requires' tenga 'aura_core'
        if (!isset($metadata['requires']['aura_core'])) {
            throw new \InvalidArgumentException("Metadata debe especificar 'requires.aura_core'");
        }

        // Validar formato de versión semántica
        if (!preg_match('/^\d+\.\d+\.\d+/', $metadata['version'])) {
            throw new \InvalidArgumentException(
                "Versión inválida: {$metadata['version']}. " .
                "Debe seguir Semantic Versioning (ej: 1.0.0)"
            );
        }
    }

    /**
     * Lista todos los plugins (activos e inactivos).
     * 
     * @return array Array de plugins
     */
    public function listAllPlugins(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                nombre,
                version,
                descripcion,
                autor,
                activo,
                prioridad,
                created_at
            FROM plugins
            ORDER BY prioridad ASC, nombre ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista solo los plugins activos.
     * 
     * @return array Array de plugins activos
     */
    public function listActivePlugins(): array
    {
        return $this->activePlugins;
    }

    /**
     * Descubre plugins en el directorio /plugins que no están registrados.
     * 
     * @return array Array de nombres de plugins no registrados
     */
    public function discoverUnregisteredPlugins(): array
    {
        $registeredPlugins = array_column($this->listAllPlugins(), 'nombre');
        $unregistered = [];

        if (!is_dir($this->pluginsDir)) {
            return $unregistered;
        }

        $directories = scandir($this->pluginsDir);

        foreach ($directories as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $fullPath = $this->pluginsDir . '/' . $dir;

            if (is_dir($fullPath) && !in_array($dir, $registeredPlugins, true)) {
                // Verificar que tenga plugin.json
                if (file_exists($fullPath . '/plugin.json')) {
                    $unregistered[] = $dir;
                }
            }
        }

        return $unregistered;
    }

    /**
     * Invalida el cache de resolución de archivos.
     * 
     * Útil cuando se activan/desactivan plugins.
     */
    public function clearCache(): void
    {
        $this->fileResolutionCache = [];
    }
}
