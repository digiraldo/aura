# Aura Platform - El WordPress de la Contabilidad

![Fase](https://img.shields.io/badge/Fase-I%20Núcleo%20Blindado-success)
![Versión](https://img.shields.io/badge/Versión-1.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.2+-purple)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange)

Plataforma ERP/CRM multi-tenant de próxima generación con ecosistema extensible de grado bancario.

## 🎯 Visión del Proyecto

Aura es una plataforma que redefine la gestión empresarial mediante:

- **Confianza como Servicio**: Aislamiento multi-tenant de grado bancario
- **Extensibilidad Sin Límites**: Ecosistema de plugins inspirado en WordPress
- **Costo-Eficiencia Operativa**: Arquitectura optimizada para miles de inquilinos

## ✨ Características Principales (Fase I)

### Núcleo Blindado

- ✅ **Multi-Tenancy por Esquemas**: Aislamiento total mediante esquemas MySQL independientes
- ✅ **RBAC Jerárquico**: Control de acceso basado en roles con herencia (ADMIN, SELLER, SPECIAL)
- ✅ **Transacciones ACID**: Integridad garantizada en operaciones críticas de POS
- ✅ **Sistema de Plugins**: Carga prioritaria de archivos con núcleo inmutable
- ✅ **Seguridad**: Inmunidad a SQL Injection mediante prepared statements obligatorios

## 📋 Requisitos del Sistema

- **PHP**: 8.2 o superior
- **Base de Datos**: MySQL 8.0+ / MariaDB 10.5+
- **Servidor Web**: Apache/Nginx con mod_rewrite
- **Extensiones PHP**:
  - PDO
  - pdo_mysql
  - json
  - session

## 🚀 Instalación Rápida

### 1. Clonar el Repositorio

```bash
git clone https://github.com/tu-usuario/aura.git
cd aura



git clone https://github.com/digiraldo/aura.git
```

### 2. Configurar Variables de Entorno

```bash
cp .env.example .env
```

Editar `.env` con tus credenciales de base de datos:

```env
DB_HOST=localhost Sitienen IP ajustala aqui
DB_PORT=3306
DB_DATABASE=aura_master
DB_USERNAME=root
DB_PASSWORD=tu_password
```

### 3. Crear Base de Datos Master

```sql
CREATE DATABASE aura_master CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ejecutar script de instalación:

```bash
php install.php
```

Si esta usando php en docker con el nginx de docker, es posible que necesite ejecutar el comando dentro del contenedor:

```bash
sudo docker exec -it linuxserver-nginx-app-1 php /aura/install.php
```

###

### 4. Configurar Servidor Web

#### Apache (con Laragon)

El proyecto ya incluye `.htaccess` configurado. Asegúrate de que `mod_rewrite` esté habilitado.

Configurar virtual host apuntando a `/aura/public`

#### Nginx

```nginx
server {
    listen 80;
    server_name aura.local;
    root /path/to/aura/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

#### Docker .yaml

```yaml
    volumes:
      - type: bind
        source: /srv/lsio/nginx/config
        target: /config
      # AÑADE ESTA LÍNEA (Ajusta /home/di/aura si la ruta es distinta)
      - type: bind
        source: /home/di/aura
        target: /aura
```

* Configura el Virtual Host
En lugar de crear un archivo nuevo desde cero, la imagen de LinuxServer espera que edites sus archivos de configuración en la ruta del host.

Ve a: `/srv/lsio/nginx/config/nginx/site-confs/`

Edita el archivo llamado `default.conf` (o crea uno nuevo ahí si prefieres).

Usa esta configuración adaptada para el contenedor:

Nginx

```nginx
server {
    listen 80;
    server_name aura.local;
    
    # La ruta es /aura porque es como la mapeamos en el paso 1
    root /aura/public; 
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # En esta imagen de Docker, PHP-FPM escucha habitualmente en 127.0.0.1:9000
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

```nginx
server {
    listen 80;
    server_name aura.local *.aura.local; # Permite subdominios para los tenants
    
    root /aura/public; 
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

###

### 5. Crear Primer Tenant

* Crear tu primer "Inquilino" (Tenant)
Para poder usar el sistema, necesitas crear una base de datos para tu empresa demo. Ejecuta este comando desde tu terminal:

```bash
sudo docker exec -it linuxserver-nginx-app-1 php /aura/create_tenant.php empresa_demo
```

Si te da error de permisos, recuerda ejecutar primero: sudo chown -R di:di /home/di/aura.

```bash
sudo chown -R di:di /home/di/aura
```
Si no estas usando docker, simplemente usa:


```php
<?php
// create_tenant.php
require 'public/index.php';

$schemaManager = $app->getSchemaManager();

$schemaManager->createTenantSchema('empresa_demo', [
    'username' => 'admin',
    'password' => 'admin123',
    'email' => 'admin@empresa.com',
    'nombre_completo' => 'Administrador'
]);

echo "Tenant 'empresa_demo' creado exitosamente!\n";
```

Ejecutar:

```bash
php create_tenant.php
```

### 6. Acceder a la Aplicación

- **URL**: http://empresa_demo.localhost (según configuración de subdominio)
- **Usuario**: admin
- **Contraseña**: admin123

## 📁 Estructura del Proyecto

```
/aura
├── /core                    # Núcleo inmutable
│   ├── /controllers         # Controladores MVC
│   ├── /models             # Modelos de datos (PDO)
│   ├── /vistas             # Vistas con Bootstrap 5
│   ├── /lib                # Librerías del core
│   │   ├── SchemaManager.php
│   │   ├── Auth.php
│   │   ├── Role.php
│   │   ├── PluginLoader.php
│   │   └── Bootstrap.php
│   └── /config             # Configuración
├── /plugins                # Extensiones de terceros
├── /public                 # Punto de entrada web
│   ├── index.php
│   ├── .htaccess
│   └── /assets
├── /storage                # Logs, cache, uploads
└── /tests                  # Tests PHPUnit
```

## 🔐 Seguridad

### Permisos Base

**SELLER** (Vendedor):
- `ventas.crear` - Registrar ventas
- `ventas.listar` - Ver historial
- `productos.ver` - Consultar productos
- `pagos.procesar` - Procesar pagos

**ADMIN** (Administrador):
- Hereda todos los permisos de SELLER
- `usuarios.administrar` - Gestión de usuarios
- `config.modificar` - Configuración del tenant
- `backups.ejecutar` - Generar respaldos

### Buenas Prácticas

1. **Nunca** ejecutar SQL directo en controladores
2. **Siempre** usar prepared statements con PDO
3. **Validar** permisos antes de operaciones sensibles
4. **Registrar** acciones en auditoría

## 🎨 Desarrollo de Plugins

### Estructura de un Plugin

```
/plugins/mi_plugin
├── plugin.json              # Metadata del plugin
├── /controllers             # Controladores personalizados
├── /models                  # Modelos personalizados
├── /vistas                  # Vistas que sustituyen al core
├── install.php              # Script de instalación
└── uninstall.php            # Script de desinstalación
```

### plugin.json

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

### Instalar Plugin

```php
$pluginLoader = $app->getPluginLoader();
$pluginLoader->installPlugin('mi_plugin');
$pluginLoader->activatePlugin('mi_plugin');
```

## 📊 Arquitectura Técnica

### Multi-Tenancy

Cada tenant tiene su propio esquema MySQL:
- Esquema master: `aura_master` (gestión de tenants)
- Esquemas tenant: `tenant_{id}` (datos aislados)

### RBAC Jerárquico

```
ADMIN
  ├─ Hereda permisos de SELLER
  └─ + Permisos administrativos

SELLER
  └─ Permisos operativos base

SPECIAL
  └─ Permisos configurables
```

### Transacciones ACID

```php
try {
    $pdo->beginTransaction();
    
    // 1. Registrar venta
    // 2. Actualizar stock
    // 3. Registrar pago
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
}
```

## 🧪 Testing

```bash
# Ejecutar tests unitarios
./vendor/bin/phpunit

# Con coverage
./vendor/bin/phpunit --coverage-html coverage/
```

## 📚 Documentación

- [Product Requirements Document (PRD)](PRD.md)
- [Informe de Diseño Arquitectónico](Informe%20de%20Diseño%20Arquitectónico.md)
- [Informe de Implementación](Informe%20de%20Implementación.md)
- [Mandato de Arquitectura](Mandato%20de%20Arquitectura.md)

## 🗺️ Roadmap

### ✅ Fase I: Núcleo Blindado (12 semanas) - **COMPLETADA**
- Multi-tenancy por esquemas
- RBAC jerárquico
- Transacciones POS
- Sistema de plugins base

### 🔄 Fase II: Ecosistema de Plugins (16 semanas) - **EN DESARROLLO**
- Event dispatcher con hooks
- Marketplace de plugins
- SDK de desarrollo
- Plugins verticales

### 📅 Fase III: Optimización y Escala (12 semanas)
- Cache con Redis
- Load balancing
- CDN para assets
- Plugins de BI

## 🤝 Contribuir

1. Fork el proyecto
2. Crear feature branch (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -m 'Agregar nueva funcionalidad'`)
4. Push al branch (`git push origin feature/nueva-funcionalidad`)
5. Abrir Pull Request

## 📜 Licencia

Este proyecto está bajo la Licencia MIT. Ver archivo `LICENSE` para más detalles.

## 👥 Equipo

- **Arquitecto de Software**: Definición de arquitectura y estándares
- **Lead Developer**: Implementación del núcleo
- **DevOps**: Infraestructura y despliegue

## 📧 Contacto

Para preguntas o soporte:
- Email: soporte@aura-platform.com
- Documentación: https://docs.aura-platform.com

---

**Aura Platform** - *Transformando la gestión empresarial mediante arquitectura de grado bancario*

---

*Versión del documento: 1.0 | Última actualización: 2 de febrero de 2026*
