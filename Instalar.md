# Guía de Instalación de Aura Platform

**Sistema Objetivo:** Debian GNU/Linux 13 (Trixie) / Ubuntu 22.04+

**Stack:**
- Nginx (nativo)
- PHP 8.2+ FPM (nativo)
- MariaDB 10.6+ (Docker)
- IP Servidor: 192.168.68.20
- Puerto Web: 7474

---

## Fase 1: Instalar Docker y MariaDB

### 1. Instalar Docker

```bash
sudo apt update
sudo apt install docker.io docker-compose -y
sudo systemctl start docker
sudo systemctl enable docker

# Agregar tu usuario al grupo docker (opcional, para no usar sudo)
sudo usermod -aG docker $USER
# Cerrar sesión y volver a entrar para que surta efecto
```

### 2. Levantar MariaDB en Docker

```bash
docker run -d \
  --name mariadb-aura \
  -e MYSQL_ROOT_PASSWORD=4dm1n1234 \
  -p 3306:3306 \
  --restart unless-stopped \
  mariadb:10.6
```

**Verificar que esté corriendo:**

```bash
docker ps
docker logs mariadb-aura
```

---

## Fase 2: Instalar Software Nativo

### 1. Instalar Git

```bash
sudo apt update
sudo apt install git -y
```

### 2. Instalar Nginx y PHP 8.2

```bash
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip -y
```

---

## Fase 3: Configuración del Proyecto Aura

### 1. Preparar Sistema de Permisos

**IMPORTANTE:** Nginx (usuario `www-data`) necesita acceso a tu directorio home y al socket de PHP-FPM.

```bash
# Agregar www-data al grupo de tu usuario
sudo usermod -aG di www-data

# Permitir que www-data pueda "atravesar" tu directorio home
chmod 755 /home/di
```

### 2. Clonar Repositorio

```bash
cd ~
git clone https://github.com/digiraldo/aura.git
cd aura
```

### 3. Configurar Permisos del Proyecto

```bash
# Asignar propietario (di) y grupo (www-data)
sudo chown -R di:www-data ~/aura

# Permisos para directorios: 755 (rwxr-xr-x)
find ~/aura -type d -exec chmod 755 {} \;

# Permisos para archivos: 644 (rw-r--r--)
find ~/aura -type f -exec chmod 644 {} \;

# Storage y plugins deben ser escribibles por www-data: 775 (rwxrwxr-x)
chmod -R 775 ~/aura/storage
chmod -R 775 ~/aura/plugins

# Crear subdirectorios en storage si no existen
mkdir -p ~/aura/storage/{logs,cache,uploads,sessions}
mkdir -p ~/aura/plugins
```

### 4. Configurar Socket PHP-FPM

Editar configuración del pool de PHP-FPM:

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

**Buscar y modificar (o agregar si no existen) estas líneas:**

```ini
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
```

**Guardar:** `Ctrl+O`, `Enter`, `Ctrl+X`

**Reiniciar PHP-FPM para aplicar cambios:**

```bash
sudo systemctl restart php8.2-fpm

# Verificar que inició correctamente
sudo systemctl status php8.2-fpm

# Verificar permisos del socket
ls -la /var/run/php/php8.2-fpm.sock
# Debería mostrar: srw-rw---- 1 www-data www-data
```

### 5. Configurar Variables de Entorno (.env)

Configuramos la conexión hacia la IP del servidor donde corre MariaDB en Docker.

```bash
cp .env.example .env
sed -i 's/DB_HOST=localhost/DB_HOST=192.168.68.20/g' .env
sed -i 's/DB_PASSWORD=/DB_PASSWORD=4dm1n1234/g' .env
```

---

## Fase 4: Configuración del Servidor Web (Nginx)

### Opción A: Si tienes directorio sites-available (Ubuntu/Debian con configuración estándar)

1. **Verificar si existe el directorio:**

```bash
ls -la /etc/nginx/sites-available/
```

Si existe, continúa con estos pasos:

```bash
sudo nano /etc/nginx/sites-available/aura
```

Pega la configuración y luego activa:

```bash
sudo ln -s /etc/nginx/sites-available/aura /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx
```

### Opción B: Si NO tienes sites-available (Debian Trixie, por ejemplo)

Usa el directorio `conf.d/`:

```bash
sudo nano /etc/nginx/conf.d/aura.conf
```

**Pegar esta configuración en el archivo:**

```nginx
server {
    listen 7474;
    server_name 192.168.68.20 localhost;

    root /home/di/aura/public;
    index index.php index.html;

    # Logs
    access_log /var/log/nginx/aura_access.log;
    error_log /var/log/nginx/aura_error.log;

    # Ruta principal
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Procesar PHP con PHP-FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Denegar acceso a archivos ocultos
    location ~ /\. {
        deny all;
    }
}
```

**Guardar:** `Ctrl+O`, `Enter`, `Ctrl+X`

**Probar configuración y reiniciar:**

```bash
sudo nginx -t
sudo systemctl restart nginx
```

---

## Fase 5: Inicializar Base de Datos

### 1. Crear Base de Datos Maestra

```bash
cd ~/aura
php install.php
```

**Salida esperada:**
```
✅ Base de datos 'aura_master' creada exitosamente
✅ Tabla 'tenants' creada
✅ Tabla 'plugins' creada
✅ Tabla 'configuracion_global' creada
✅ Directorios creados: storage/, plugins/
```

### 2. Crear Primer Tenant

```bash
php create_tenant.php --name="Mi Empresa" --codigo="empresa1"
```

**Salida esperada:**
```
=== Creando Tenant: Mi Empresa (empresa1) ===
✅ Tenant registrado en master con ID: 1
✅ Schema 'aura_empresa1' creado
✅ Tabla 'empresas' creada
✅ Tabla 'usuarios' creada
✅ Tabla 'roles_permisos' creada
... (más tablas)
✅ Usuario admin creado
```

---

## Fase 6: Verificación

### 1. Verificar Servicios

```bash
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
docker ps  # Verificar que mariadb-aura está corriendo
```

### 2. Verificar Permisos

```bash
ls -la ~/aura/public/
ls -la /var/run/php/php8.2-fpm.sock
```

Deberías ver:
- `/home/di/aura/public/` con permisos `drwxr-xr-x`
- Socket con permisos `srw-rw----` y owner `www-data:www-data`

### 3. Probar en Navegador

Abre en tu navegador:

```
http://192.168.68.20:7474
```

**Deberías ver:** Página de login de Aura Platform

**Credenciales del primer tenant:**
- Usuario: `admin`
- Contraseña: `admin123`

---

## Troubleshooting

### Problema 1: Error 502 Bad Gateway

**Síntomas:** Al acceder a `http://192.168.68.20:7474` aparece error 502.

**Diagnóstico:**

```bash
sudo tail -f /var/log/nginx/aura_error.log
```

Si ves:
```
connect() to unix:/var/run/php/php8.2-fpm.sock failed (13: Permission denied)
```

**Solución:**

1. Verificar configuración del socket PHP-FPM:

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

Asegurar estas líneas:
```ini
listen = /var/run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
```

2. Reiniciar PHP-FPM:

```bash
sudo systemctl restart php8.2-fpm
```

3. Si persiste, verificar que www-data tenga acceso al directorio home:

```bash
chmod 755 /home/di
sudo usermod -aG di www-data
```

4. **IMPORTANTE:** Cerrar sesión de www-data para que los cambios de grupo surtan efecto:

```bash
# Reiniciar Nginx para que www-data cargue sus nuevos grupos
sudo systemctl restart nginx
```

### Problema 2: stat() '/home/di/aura/public/' failed (13: Permission denied)

**Síntomas:** Nginx no puede acceder al directorio público.

**Solución:**

```bash
# Dar permiso de ejecución al directorio home (para "atravesar")
chmod 755 /home/di

# Verificar permisos del proyecto
ls -la ~/aura/
# Debería mostrar: drwxr-xr-x di www-data

# Si no, corregir:
sudo chown -R di:www-data ~/aura
find ~/aura -type d -exec chmod 755 {} \;

# Reiniciar Nginx
sudo systemctl restart nginx
```

### Problema 3: Connection refused al conectar a MySQL

**Síntomas:** Scripts PHP no pueden conectar a la base de datos.

**Diagnóstico:**

```bash
# Verificar que MariaDB esté corriendo
docker ps

# Probar conexión desde el host
mysql -h 192.168.68.20 -u root -p4dm1n1234
```

**Soluciones posibles:**

1. Si el contenedor no está corriendo:

```bash
docker start mariadb-aura
```

2. Si MariaDB solo escucha en localhost del contenedor:

```bash
# Recrear el contenedor con bind correcto
docker stop mariadb-aura
docker rm mariadb-aura

docker run -d \
  --name mariadb-aura \
  -e MYSQL_ROOT_PASSWORD=4dm1n1234 \
  -p 3306:3306 \
  --restart unless-stopped \
  mariadb:10.6
```

3. Verificar `.env` tiene la IP correcta:

```bash
cat ~/aura/.env | grep DB_HOST
# Debe mostrar: DB_HOST=192.168.68.20
```

### Problema 4: Class 'Aura\...' not found

**Síntomas:** Error de autoload o namespace incorrecto.

**Solución:**

```bash
cd ~/aura

# Verificar que existe composer.json con autoload PSR-4
cat composer.json

# Si necesitas regenerar autoload
composer dump-autoload
```

### Script de Diagnóstico Automatizado

Creado en `diagnostico.sh`. Para usarlo:

```bash
cd ~/aura
chmod +x diagnostico.sh
./diagnostico.sh
```

Revisa:
- ✅/❌ Servicios corriendo
- ✅/❌ Puertos abiertos
- ✅/❌ Estructura de directorios
- ✅/❌ Permisos
- ✅/❌ Conexión a base de datos
- ✅/❌ Configuración Nginx

---

## Comandos Útiles

### Reiniciar todo el stack

```bash
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
docker restart mariadb-aura
```

### Ver logs en tiempo real

```bash
# Nginx
sudo tail -f /var/log/nginx/aura_error.log

# PHP-FPM
sudo tail -f /var/log/php8.2-fpm.log

# MariaDB
docker logs -f mariadb-aura

# Logs de aplicación Aura
tail -f ~/aura/storage/logs/app.log
```

### Conectar a MySQL desde CLI

```bash
mysql -h 192.168.68.20 -u root -p4dm1n1234
```

Una vez dentro:

```sql
SHOW DATABASES;
USE aura_master;
SHOW TABLES;
SELECT * FROM tenants;
```

### Limpiar y reinstalar

```bash
# Eliminar base de datos
mysql -h 192.168.68.20 -u root -p4dm1n1234 -e "DROP DATABASE IF EXISTS aura_master;"

# Volver a correr instalación
cd ~/aura
php install.php
php create_tenant.php --name="Mi Empresa" --codigo="empresa1"
```

---

## Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENTE (Navegador)                   │
│              http://192.168.68.20:7474                   │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│                  Nginx (Puerto 7474)                     │
│              /home/di/aura/public/                       │
│              Usuario: www-data                           │
└────────────────────┬────────────────────────────────────┘
                     │ Unix Socket
                     ▼
┌─────────────────────────────────────────────────────────┐
│            PHP 8.2 FPM (Socket Unix)                     │
│       /var/run/php/php8.2-fpm.sock                       │
│       Usuario: www-data                                  │
│       ┌─────────────────────────────────┐               │
│       │   /public/index.php (Router)    │               │
│       │   ↓                              │               │
│       │   Core: Auth, SchemaManager,    │               │
│       │   Controllers, Bootstrap        │               │
│       └─────────────────────────────────┘               │
└────────────────────┬────────────────────────────────────┘
                     │ TCP/IP
                     ▼
┌─────────────────────────────────────────────────────────┐
│        MariaDB 10.6 (Docker en Puerto 3306)             │
│              IP: 192.168.68.20                           │
│              Usuario: root / 4dm1n1234                   │
│       ┌─────────────────────────────────┐               │
│       │  aura_master (Registro Global)  │               │
│       │  aura_tenant1 (Empresa 1)       │               │
│       │  aura_tenant2 (Empresa 2)       │               │
│       │  ...                             │               │
│       └─────────────────────────────────┘               │
└─────────────────────────────────────────────────────────┘
```

### Flujo de Autenticación

1. Usuario ingresa credenciales en `/login`
2. PHP consulta `aura_master.tenants` para validar código de tenant
3. Se cambia conexión a schema del tenant (`aura_empresa1`)
4. Se valida usuario/password en `usuarios` del tenant
5. Se cargan permisos desde `roles_permisos`
6. Se crea sesión PHP con datos del tenant y usuario
7. Se redirige a `/dashboard`

### Aislamiento de Datos

- **Nivel 1 (Master):** Base de datos `aura_master` con registro de todos los tenants
- **Nivel 2 (Tenant):** Cada tenant tiene su propio schema MySQL (`aura_empresa1`, `aura_empresa2`, etc.)
- **Nivel 3 (Aplicación):** `SchemaManager` gestiona cambio dinámico de conexión según tenant activo
- **Seguridad:** Un tenant NUNCA puede acceder a datos de otro tenant (separación a nivel schema)

---

## Siguientes Pasos

Una vez que tengas el sistema funcionando:

1. **Personalizar configuración:**
   - Editar `.env` con tus valores de producción
   - Configurar dominio real en Nginx si no usas IP

2. **Crear más tenants:**
   ```bash
   php create_tenant.php --name="Empresa 2" --codigo="empresa2"
   ```

3. **Desarrollar funcionalidades:**
   - Los controladores están en `/core/controllers/`
   - Las vistas en `/core/vistas/`
   - Ver [PRD.md](PRD.md) para conocer todas las funcionalidades planificadas

4. **Implementar plugins:**
   - Directorio `/plugins/` listo para recibir extensiones
   - Ver `PluginLoader.php` para arquitectura de plugins

---

**¡Instalación completada! 🎉**

Si encuentras problemas, consulta la sección **Troubleshooting** o ejecuta `./diagnostico.sh` para obtener un reporte completo del sistema.
