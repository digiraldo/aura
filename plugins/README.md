# Directorio de Plugins

Este directorio contiene los plugins extendidos de Aura Platform.

## Estructura de un Plugin

Cada plugin debe tener su propia carpeta con la siguiente estructura:

```
/plugins/mi_plugin/
├── plugin.json          # Metadatos del plugin (obligatorio)
├── /controllers         # Controladores personalizados
├── /models             # Modelos personalizados
├── /vistas             # Vistas que sustituyen al core
├── install.php         # Script de instalación (opcional)
└── uninstall.php       # Script de desinstalación (opcional)
```

## Ejemplo de plugin.json

```json
{
  "name": "mi_plugin",
  "version": "1.0.0",
  "description": "Mi plugin personalizado",
  "author": "Tu Nombre",
  "requires": {
    "aura_core": ">=1.0.0",
    "php": ">=8.2"
  },
  "permissions": [
    "ventas.crear",
    "productos.ver"
  ]
}
```

## Instalación de Plugins

Los plugins se instalan y activan mediante el PluginLoader:

```php
$pluginLoader = $app->getPluginLoader();
$pluginLoader->installPlugin('mi_plugin');
$pluginLoader->activatePlugin('mi_plugin');
```

Para más información, consulta la documentación en [README.md](../README.md).
