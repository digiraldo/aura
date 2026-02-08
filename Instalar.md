# Guía de Instalación: Aura Platform (Stack Nativo Completo)

Esta guía detalla la instalación de **Aura Platform** en un servidor Debian/Ubuntu utilizando **todo el stack de forma nativa**: Nginx, PHP 8.2, MariaDB y phpMyAdmin.

## Fase 1: Limpieza del Sistema

### Opción A: Primera Instalación (Limpieza Básica)

Si es tu **primera vez** instalando Aura Platform en este servidor, ejecuta esto para limpiar cualquier instalación previa básica:

```bash
# Eliminar directorio del proyecto si existe
cd ~
rm -rf ~/aura

# Si ya tienes MariaDB instalado con contraseña, limpiar bases de datos previas
# (Si no tienes MariaDB aún, omite este paso)
mysql -u root -pAdmin1234 -e "DROP DATABASE IF EXISTS aura_master;" 2>/dev/null || true
mysql -u aura_admin -pAdmin1234 -e "DROP DATABASE IF EXISTS tenant_empresa_demo;" 2>/dev/null || true
```

---

### Opción B: Limpieza Total (Reinstalación Completa)

Si **ya tienes Aura instalado** y quieres empezar completamente de cero, ejecuta esta limpieza completa:

```bash
# 1. Detener servicios
sudo systemctl stop nginx
sudo systemctl stop php8.2-fpm
sudo systemctl stop mariadb

# 2. Eliminar proyecto
cd ~
rm -rf ~/aura
rm -rf ~/aura.backup 2>/dev/null || true

# 3. Limpiar configuraciones de Nginx
sudo rm -f /etc/nginx/conf.d/aura.conf
sudo rm -f /etc/nginx/conf.d/phpmyadmin.conf
sudo rm -f /etc/nginx/sites-enabled/aura 2>/dev/null || true
sudo rm -f /etc/nginx/sites-available/aura 2>/dev/null || true

# 4. Limpiar logs
sudo rm -f /var/log/nginx/aura_*.log
sudo rm -f /var/log/nginx/phpmyadmin_*.log

# 5. IMPORTANTE: Limpiar bases de datos (ESTO BORRARÁ TODOS TUS DATOS)
sudo systemctl start mariadb
mysql -u root -pAdmin1234 << EOF
DROP DATABASE IF EXISTS aura_master;
DROP DATABASE IF EXISTS tenant_empresa;
DROP DATABASE IF EXISTS tenant_empresa_demo;
DROP USER IF EXISTS 'aura_admin'@'localhost';
DROP USER IF EXISTS 'aura_admin'@'%';
FLUSH PRIVILEGES;
EOF

# 6. Reiniciar MariaDB
sudo systemctl restart mariadb

# 7. Verificar limpieza
mysql -u root -pAdmin1234 -e "SHOW DATABASES;"
# No deberías ver aura_master ni tenant_* en la lista

echo "✅ Limpieza completa finalizada. Ahora puedes continuar con la Fase 2."
```

**⚠️ ADVERTENCIA:** La Opción B **eliminará TODAS las bases de datos y configuraciones** de Aura. Úsala solo si estás seguro de querer empezar desde cero.

---

## Fase 2: Instalación del Stack Completo (Nativo)

### 1. Preparar Repositorios de PHP (Debian Trixie/Testing)

Instalamos el repositorio de **Ondřej Surý** para obtener PHP 8.2 correctamente.

```bash
sudo apt update
sudo apt install lsb-release apt-transport-https ca-certificates curl -y
```
**Agregar llave y repositorio de PHP**

```bash
sudo curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list
```

### 2. Instalar MariaDB

```bash
sudo apt update
sudo apt install mariadb-server mariadb-client -y

# Iniciar y habilitar MariaDB
sudo systemctl start mariadb
sudo systemctl enable mariadb

# Verificar que está corriendo
sudo systemctl status mariadb
```

### 3. Configurar Seguridad de MariaDB

**IMPORTANTE: Primero verifica si MariaDB ya tiene contraseña configurada**

```bash
# Probar acceso sin contraseña
sudo mysql -u root

# Si funciona (entra a MariaDB), usa la Opción A o B
# Si da error "Access denied", tu MariaDB ya tiene contraseña, usa la Opción C
```

---

**Opción A: Usar el asistente de seguridad (si está disponible)**

```bash
sudo mysql_secure_installation
```

**Si el comando anterior da error "command not found", usa la Opción B.**

**Responde a las preguntas:**
- Switch to unix_socket authentication? **N**
- Change the root password? **Y** (usar: `Admin1234`)
- Remove anonymous users? **Y**
- Disallow root login remotely? **N** (si necesitas acceso remoto)
- Remove test database? **Y**
- Reload privilege tables now? **Y**

---

**Opción B: Configuración manual de seguridad (si el comando no existe Y no hay contraseña previa)**

```bash
# Solo si "sudo mysql -u root" funciona sin contraseña
sudo mysql -u root
```

Dentro de MySQL, ejecuta estos comandos:

```sql
-- Establecer contraseña para root
ALTER USER 'root'@'localhost' IDENTIFIED BY 'Admin1234';

-- Eliminar usuarios anónimos
DELETE FROM mysql.user WHERE User='';

-- Eliminar base de datos de prueba
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';

-- Permitir acceso remoto a root (opcional)
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY 'Admin1234' WITH GRANT OPTION;

-- Recargar privilegios
FLUSH PRIVILEGES;
EXIT;
```

---

**Opción C: MariaDB ya tiene contraseña configurada (instalación previa)**

Si obtienes **"Access denied"** al intentar `sudo mysql -u root`, significa que ya hay una contraseña.

```bash
# Intentar con la contraseña que usaste antes
sudo mysql -u root -pAdmin1234

# Si funciona, salta directamente al paso 4
```

**Si NO recuerdas la contraseña, resetéala:**

```bash
# 1. Detener MariaDB
sudo systemctl stop mariadb

# 2. Iniciar MariaDB en modo seguro (sin contraseña)
sudo mariadbd-safe --skip-grant-tables --skip-networking &

# 3. Esperar 5 segundos y conectar sin contraseña
sleep 5
sudo mysql -u root

# 4. Dentro de MySQL, resetea la contraseña
FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED BY 'Admin1234';
FLUSH PRIVILEGES;
EXIT;

# 5. Detener el proceso seguro y reiniciar MariaDB normalmente
sudo pkill mariadbd
sudo systemctl start mariadb

# 6. Verificar que funciona con la nueva contraseña
sudo mysql -u root -pAdmin1234 -e "SELECT 1;"
```

**Salida esperada del último comando:**
```
+---+
| 1 |
+---+
| 1 |
+---+
```

### 4. Crear Usuario de Base de Datos

```bash
# Conectar a MySQL con la contraseña
sudo mysql -u root -pAdmin1234
```

Dentro de MySQL, ejecuta:

```sql
-- Eliminar usuarios previos si existen (instalación limpia)
DROP USER IF EXISTS 'aura_admin'@'localhost';
DROP USER IF EXISTS 'aura_admin'@'%';

-- Crear usuarios nuevos
CREATE USER 'aura_admin'@'localhost' IDENTIFIED BY 'Admin1234';
CREATE USER 'aura_admin'@'%' IDENTIFIED BY 'Admin1234';
GRANT ALL PRIVILEGES ON *.* TO 'aura_admin'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'aura_admin'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;

-- Verificar que el usuario fue creado
SELECT User, Host FROM mysql.user WHERE User='aura_admin';

EXIT;
```

**Verificar la conexión con el nuevo usuario:**

```bash
# Probar conexión con aura_admin
mysql -u aura_admin -pAdmin1234 -e "SHOW DATABASES;"
```

**Salida esperada:**
```
+--------------------+
| Database           |
+--------------------+
| information_schema |
| mysql              |
| performance_schema |
| sys                |
+--------------------+
```

### 5. Instalar Nginx y PHP 8.2

```bash
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip -y

# Establecer PHP 8.2 como versión por defecto
sudo update-alternatives --set php /usr/bin/php8.2

# Verificar que las extensiones PHP están instaladas
php -m | grep -E "pdo|mysql|mysqli"

# Verificar versión de PHP
php --version

# Si no aparecen las extensiones o hay error "could not find driver":
php --ini | head -3
# Debe mostrar: Configuration File (php.ini) Path: /etc/php/8.2/cli

# Si muestra otra versión (8.5, etc.), ejecutar:
# sudo update-alternatives --set php /usr/bin/php8.2
```

**Salida esperada:**
```
PHP 8.2.30 (cli) (built: Dec 18 2025 23:21:10) (NTS)
mysqli
PDO
pdo_mysql
```

### 6. Instalar phpMyAdmin
**Recuerde seguir los pasos durante la instalación.**
```bash
sudo apt install phpmyadmin -y
```

**Durante la instalación:**
- Servidor web: Selecciona **apache2** (presiona espacio) y luego **Enter** (lo configuraremos para Nginx manualmente)
- Configurar base de datos con dbconfig-common? **Sí**
- Password de MySQL para phpmyadmin: `Admin1234`
- Password de aplicación: `Admin1234`

### 7. Configurar phpMyAdmin en Puerto 8998

**Crear configuración dedicada para phpMyAdmin:**

```bash
sudo nano /etc/nginx/conf.d/phpmyadmin.conf
```

**Pegar esta configuración:**

```nginx
server {
    listen 8998;
    server_name 192.168.68.20 localhost;

    root /usr/share/phpmyadmin;
    index index.php index.html;

    # Logs específicos para phpMyAdmin
    access_log /var/log/nginx/phpmyadmin_access.log;
    error_log /var/log/nginx/phpmyadmin_error.log;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

**Guardar:** `Ctrl+O`, `Enter`, `Ctrl+X`

**Verificar y reiniciar Nginx:**

```bash
# Probar configuración
sudo nginx -t

# Reiniciar Nginx
sudo systemctl restart nginx

# Verificar que está escuchando en 8998
sudo ss -tlnp | grep 8998
```

**Abrir puerto en firewall (si está activo):**

```bash
# Verificar si ufw está instalado
which ufw

# Si ufw existe, usar:
sudo ufw allow 8998/tcp
sudo ufw reload

# Si no existe ufw, verificar con iptables:
sudo iptables -L -n | grep 8998

# O verificar si usa nftables (Debian moderno):
sudo nft list ruleset | grep 8998
```

**Nota:** En Debian Trixie, el firewall puede no estar activo por defecto. Si estás en una red local confiable, puedes continuar sin configurar el firewall.

### 8. Solución de Problemas 502 en phpMyAdmin

Si obtienes **502 Bad Gateway** al acceder a phpMyAdmin, sigue estos pasos:

**1. Verificar que PHP-FPM está corriendo:**
```bash
sudo systemctl status php8.2-fpm

# Si NO está corriendo (inactive/dead), iniciarlo:
sudo systemctl start php8.2-fpm
sudo systemctl enable php8.2-fpm
```

**2. Ver logs de error específicos de phpMyAdmin:**
```bash
sudo tail -50 /var/log/nginx/phpmyadmin_error.log
```

**Si ves errores como "Permission denied" al socket PHP-FPM, continúa con el paso 3.**

**3. SOLUCIÓN: Ajustar permisos del socket PHP-FPM (Error más común)**

```bash
# Verificar configuración actual del pool
sudo grep -E "^listen|^listen\." /etc/php/8.2/fpm/pool.d/www.conf

# Agregar configuración de permisos del socket automáticamente
sudo bash -c 'cat >> /etc/php/8.2/fpm/pool.d/www.conf << EOF

; Configuración de permisos del socket
listen.owner = www-data
listen.group = www-data
listen.mode = 0666
EOF'

# Reiniciar PHP-FPM para aplicar la configuración
sudo systemctl restart php8.2-fpm

# Verificar permisos del socket (debería mostrar www-data:www-data)
ls -la /var/run/php/php8.2-fpm.sock

# Reiniciar Nginx
sudo systemctl restart nginx
```

**4. Verificar que funciona:**
```bash
# Este comando debería devolver "HTTP/1.1 200 OK"
curl -I http://localhost:8998/

# Verificar que el puerto está escuchando
sudo ss -tlnp | grep 8998
```

**5. Probar en navegador:**
Abre: `http://192.168.68.20:8998/`

Deberías ver la página de login de phpMyAdmin.

**Credenciales:**
- Usuario: `aura_admin`
- Contraseña: `Admin1234`

---

## Fase 3: Configuración del Proyecto Aura

### 1. Clonar y Permisos

```bash
cd ~
git clone https://github.com/digiraldo/aura.git
cd aura
```

**Crear directorios necesarios si no existen (por si acaso)**
```bash
mkdir -p ~/aura/storage/{logs,cache,uploads,sessions}
mkdir -p ~/aura/plugins
```

**Verificar usuario del servidor web Nginx**
```bash
# Verificar con qué usuario corre Nginx
ps aux | grep nginx | grep -v grep | head -2

# O revisar la configuración
grep "^user" /etc/nginx/nginx.conf

# Si muestra "user nginx;", usa 'nginx' en lugar de 'www-data'
# Si muestra "user www-data;", usa 'www-data'
```

**Ajustar permisos según el usuario de Nginx**
```bash
# Si Nginx usa el usuario 'nginx':
sudo chown -R di:nginx ~/aura
sudo chmod -R 775 ~/aura/storage
sudo chmod -R 775 ~/aura/plugins

# Si Nginx usa el usuario 'www-data':
# sudo chown -R di:www-data ~/aura
# sudo chmod -R 775 ~/aura/storage
# sudo chmod -R 775 ~/aura/plugins
```

**IMPORTANTE: Configurar permisos del directorio home (Error 404/502)**
```bash
# El servidor web necesita poder atravesar tu directorio home
# para llegar a ~/aura/public
sudo chmod o+x /home/di/

# Verificar permisos
ls -ld /home/di/
# Debería mostrar: drwxr-x--x (el último x es crucial)
```

### 2. Configurar Variables de Entorno (.env)

Configuramos la conexión hacia MariaDB local.

```bash
cp .env.example .env

# Configurar base de datos
sed -i 's/DB_HOST=.*/DB_HOST=localhost/g' .env
sed -i 's/DB_USERNAME=.*/DB_USERNAME=aura_admin/g' .env
sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=Admin1234/g' .env
sed -i 's/DB_DATABASE=.*/DB_DATABASE=aura_master/g' .env

# Verificar la configuración
cat .env | grep DB_
```

**Salida esperada:**
```
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=aura_master
DB_USERNAME=aura_admin
DB_PASSWORD=Admin1234
```

### 3. Corregir Errores de Código (IMPORTANTE)

El código clonado tiene dos errores que causan el error 500. Corregimos aquí:

**A. Corregir ruta de configuración en Bootstrap.php**
```bash
cd ~/aura

# Verificar el error actual
grep -n "__DIR__ . '/config/'" core/lib/Bootstrap.php

# Corregir la ruta (de '/config/' a '/../config/')
sed -i "s|__DIR__ . '/config/'|__DIR__ . '/../config/'|g" core/lib/Bootstrap.php

# Verificar que se aplicó el cambio
grep -n "configPath = " core/lib/Bootstrap.php
# Debería mostrar: $configPath = __DIR__ . '/../config/';
```

**B. Corregir declaraciones 'use' en index.php**
```bash
# Crear backup del archivo
cp public/index.php public/index.php.backup

# Agregar las declaraciones use después de la línea 62
sed -i '62 a\use Aura\\Core\\Controllers\\SalesController;\nuse Aura\\Core\\Models\\VentaModel;\nuse Aura\\Core\\Models\\StockModel;' public/index.php

# Eliminar las declaraciones use del bloque try (ahora en líneas 222-224)
sed -i '222,224d' public/index.php

# Verificar las declaraciones use al inicio (líneas 60-70)
sed -n '60,70p' public/index.php

# Verificar que se eliminaron del bloque try (líneas 215-230)
sed -n '215,230p' public/index.php
```

**C. Reorganizar archivos según namespaces PSR-4**

**NOTA:** Los archivos ya deberían estar en su ubicación correcta desde la última versión del repositorio. Verifica primero:

```bash
# Verificar que los archivos YA están en su lugar
ls -la core/lib/Database/
ls -la core/lib/Plugins/
ls -la core/lib/Auth/

# Si los archivos YA están ahí (SchemaManager.php, PluginLoader.php, Auth.php, Role.php)
# NO ejecutes los comandos mv, continúa al paso D
```

**Solo si los archivos NO están en los subdirectorios, ejecuta:**

```bash
# Crear directorios para la estructura correcta
mkdir -p core/lib/Database
mkdir -p core/lib/Plugins
mkdir -p core/lib/Auth

# Mover archivos a sus directorios correspondientes (solo si aún no lo están)
mv core/lib/SchemaManager.php core/lib/Database/SchemaManager.php 2>/dev/null || true
mv core/lib/PluginLoader.php core/lib/Plugins/PluginLoader.php 2>/dev/null || true
mv core/lib/Auth.php core/lib/Auth/Auth.php 2>/dev/null || true
mv core/lib/Role.php core/lib/Auth/Role.php 2>/dev/null || true
```

**D. Verificar que los cambios funcionan**
```bash
# Probar sintaxis PHP
php -l public/index.php
# Debería mostrar: No syntax errors detected
```

**Salida esperada:**
```
No syntax errors detected in public/index.php
```

**✅ Fase 3 completada. Ahora DEBES continuar con la Fase 4 para configurar Nginx.**

**⚠️ IMPORTANTE:** Sin la configuración de Nginx (Fase 4), la aplicación NO será accesible. Si intentas acceder ahora verás el error `ERR_CONNECTION_REFUSED`.

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

### Opción B: Si NO existe sites-available (tu caso actual)

Usa el directorio `conf.d` que es el estándar en muchas instalaciones:

1. **Crear archivo directamente en conf.d:**
```bash
sudo nano /etc/nginx/conf.d/aura.conf

```

2. **Pegar esta configuración:**

**IMPORTANTE:** Ajusta la IP según tu servidor. Si accedes desde otra máquina, NO uses `localhost`.

```nginx
server {
    listen 7474;
    
    # Acepta conexiones por IP, subdominio o localhost
    server_name 192.168.68.20 aura.local *.aura.local localhost;

    root /home/di/aura/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        # Verificar que el archivo existe
        try_files $uri =404;
        
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Timeout para scripts largos
        fastcgi_read_timeout 300;
    }

    # Denegar acceso a archivos ocultos
    location ~ /\. {
        deny all;
    }

    error_log /var/log/nginx/aura_error.log;
    access_log /var/log/nginx/aura_access.log;
}

```

3. **Verificar y Reiniciar:**
```bash
# Probar configuración
sudo nginx -t

# Si hay errores, revisar el archivo
sudo tail -20 /var/log/nginx/aura_error.log

# Verificar que PHP-FPM está corriendo
sudo systemctl status php8.2-fpm

# Si no está corriendo, iniciarlo
sudo systemctl start php8.2-fpm
sudo systemctl enable php8.2-fpm

# Reiniciar Nginx
sudo systemctl restart nginx

# Verificar que Nginx escucha en el puerto 7474
sudo netstat -tlnp | grep 7474
# O con ss:
sudo ss -tlnp | grep 7474

```

**Salida esperada de netstat:**
```
tcp   0   0 0.0.0.0:7474   0.0.0.0:*   LISTEN   1234/nginx
```



---

## Fase 5: Finalizar Instalación

**IMPORTANTE: Verificar versión de PHP antes de continuar**

```bash
# Verificar que estás usando PHP 8.2
php --version

# Si muestra PHP 8.5 u otra versión:
sudo update-alternatives --set php /usr/bin/php8.2

# Verificar drivers MySQL disponibles
php -r "print_r(PDO::getAvailableDrivers());"
# Debe mostrar: Array ( [0] => mysql )
```

Ejecutamos los scripts de Aura utilizando el PHP nativo del sistema:

1. **Instalar Base de Datos Master:**
```bash
cd ~/aura
php install.php

```

**Salida esperada:**
```
╔═══════════════════════════════════════════════╗
║   AURA PLATFORM - INSTALACIÓN AUTOMÁTICA    ║
║      El WordPress de la Contabilidad         ║
╚═══════════════════════════════════════════════╝

📋 Configuración detectada:
   Host: localhost:3306
   Base de datos: aura_master
   Usuario: aura_admin

🔌 Conectando a MySQL...
✅ Conexión exitosa.

🗄️  Verificando base de datos master...
✅ Base de datos 'aura_master' creada.

📊 Creando tabla de tenants...
✅ Tabla 'tenants' creada.
...
```

2. **Crear Tenant de prueba:**
```bash
php create_tenant.php empresa_demo

```

**Salida esperada:**
```
╔═══════════════════════════════════════════════╗
║      AURA PLATFORM - CREACIÓN DE TENANT      ║
╚═══════════════════════════════════════════════╝

📋 Información del Tenant:
   Nombre: empresa_demo
   Usuario Admin: admin
   Contraseña: ********

¿Desea continuar? (s/n): s

🔌 Conectando a base de datos master...
✅ Conectado a aura_master

🏗️  Creando tenant...
   (esto puede tardar unos segundos)

✅ Tenant creado exitosamente!
...
```

### Solución de Problemas Comunes

#### � Script de Diagnóstico Automático

Ejecuta este script para obtener un reporte completo del estado del sistema:

```bash
cd ~/aura
bash diagnostico.sh
```

El script verificará:
- Estado de servicios (Nginx, PHP-FPM)
- Puertos abiertos
- Permisos de archivos
- Conexión a base de datos
- Configuración de Nginx
- Últimos errores en logs

---

#### �🔴 Error: "502 Bad Gateway" (Tu caso actual)

Este error significa que Nginx no puede comunicarse con PHP-FPM.

**Diagnóstico paso a paso:**

1. **Verificar que PHP-FPM está corriendo:**
```bash
sudo systemctl status php8.2-fpm

# Si muestra "inactive (dead)", iniciarlo:
sudo systemctl start php8.2-fpm
sudo systemctl enable php8.2-fpm
```

2. **Verificar que el socket existe:**
```bash
ls -la /var/run/php/php8.2-fpm.sock

# Debería mostrar algo como:
# srw-rw---- 1 www-data www-data 0 Feb 7 22:30 /var/run/php/php8.2-fpm.sock
```

3. **Verificar permisos del socket:**
```bash
# El usuario www-data debe poder acceder
sudo chmod 666 /var/run/php/php8.2-fpm.sock
```

4. **Ver errores de PHP-FPM:**
```bash
sudo tail -50 /var/log/php8.2-fpm.log
# O si no existe ese archivo:
sudo journalctl -u php8.2-fpm -n 50
```

5. **Ver errores de Nginx:**
```bash
sudo tail -50 /var/log/nginx/aura_error.log
```

6. **Probar PHP manualmente:**
```bash
# Crear archivo de prueba
echo "<?php phpinfo(); ?>" | sudo tee /home/di/aura/public/test.php

# Acceder desde navegador:
# http://192.168.68.20:7474/test.php

# Si funciona, el problema está en el routing de la app
```

7. **Reiniciar servicios:**
```bash
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

**Causa común:** PHP-FPM no está corriendo o el socket tiene permisos incorrectos.

#### 🔴 Error: "Connection refused" con localhost

**Problema:** Intentas acceder a `empresa_demo.localhost` desde otra máquina.

**Solución:** Usa la IP del servidor:
```
http://192.168.68.20:7474/
```

El dominio `localhost` solo funciona desde el propio servidor.

#### 🔴 Error: "Connection refused"
```bash
# Verificar que MariaDB esté corriendo
sudo systemctl status mariadb

# Si no está activo, iniciarlo
sudo systemctl start mariadb

# Verificar conectividad local
telnet localhost 3306
```

**Error: "Access denied for user"**
```bash
# Verificar credenciales en .env
cat .env | grep DB_

# Probar conexión manual
mysql -u aura_admin -pAdmin1234

# Si falla, recrear el usuario
sudo mysql -u root -p
# Luego ejecutar:
# DROP USER IF EXISTS 'aura_admin'@'localhost';
# CREATE USER 'aura_admin'@'localhost' IDENTIFIED BY 'Admin1234';
# GRANT ALL PRIVILEGES ON *.* TO 'aura_admin'@'localhost' WITH GRANT OPTION;
# FLUSH PRIVILEGES;
```

**Error: "could not find driver" (PDO MySQL)**
```bash
# Verificar qué versión de PHP está usando el comando 'php'
php --version
php --ini | head -3

# Si muestra PHP 8.5 u otra versión diferente a 8.2:
# Verificar versiones instaladas
ls -la /usr/bin/php*

# Verificar que PHP 8.2 tiene los drivers
php8.2 -m | grep -E "PDO|pdo_mysql|mysqli"

# Si PHP 8.2 tiene los drivers, establecerlo como versión por defecto
sudo update-alternatives --set php /usr/bin/php8.2

# Verificar que ahora usa 8.2
php --version

# Verificar drivers disponibles
php -r "print_r(PDO::getAvailableDrivers());"
# Debe mostrar: Array ( [0] => mysql )

# Intentar instalación nuevamente
cd ~/aura
php install.php
```

**Si no hay ninguna versión de PHP con los drivers:**
```bash
# Instalar/reinstalar php8.2-mysql
sudo apt install --reinstall php8.2-mysql -y

# Habilitar extensiones
sudo phpenmod pdo_mysql
sudo phpenmod mysqli

# Reiniciar PHP-FPM
sudo systemctl restart php8.2-fpm

# Establecer PHP 8.2 por defecto
sudo update-alternatives --set php /usr/bin/php8.2

# Verificar
php -m | grep -E "PDO|pdo_mysql|mysqli"
```

**Error: "Class SchemaManager not found"**
```bash
# Asegurarse de tener la última versión del repositorio
cd ~/aura
git pull origin main

# Si el problema persiste, verificar estructura PSR-4
ls -la core/lib/Database/SchemaManager.php
ls -la core/lib/Plugins/PluginLoader.php
ls -la core/lib/Auth/Auth.php
```

**Error: "Failed to open stream: No such file or directory" en create_tenant.php**
```bash
# Este error indica que create_tenant.php usa rutas antiguas
# Actualizar desde el repositorio:
cd ~/aura
git pull origin main

# Luego volver a ejecutar
php create_tenant.php empresa_demo
```

#### 🔴 Puerto 7474 Bloqueado en Firewall

```bash
# Verificar si el firewall está activo
sudo ufw status

# Si está activo, abrir el puerto 7474
sudo ufw allow 7474/tcp
sudo ufw reload

# O con firewalld (CentOS/RHEL)
sudo firewall-cmd --permanent --add-port=7474/tcp
sudo firewall-cmd --reload
```

#### 🔴 Verificar que Nginx Escucha en 7474

```bash
# Ver puertos abiertos
sudo netstat -tlnp | grep nginx

# Debería mostrar algo como:
# tcp   0   0 0.0.0.0:7474   0.0.0.0:*   LISTEN   1234/nginx
```

#### 🛠️ Comandos Útiles de Diagnóstico

```bash
# Estado de servicios
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mariadb

# Reiniciar todo el stack
sudo systemctl restart mariadb php8.2-fpm nginx

# Ver logs en tiempo real
sudo tail -f /var/log/nginx/aura_error.log
sudo tail -f /var/log/nginx/aura_access.log
sudo tail -f /var/log/mysql/error.log

# Probar conectividad a base de datos
mysql -u aura_admin -pAdmin1234 -e "SHOW DATABASES;"

# Verificar permisos de archivos
ls -la /home/di/aura/public/
ls -la /home/di/aura/storage/

# Probar Nginx con curl
curl -v http://localhost:7474/
curl -v http://192.168.68.20:7474/

# Acceder a phpMyAdmin (Puerto 8998)
curl -v http://localhost:8998/
```

#### 📋 Checklist Final

Antes de pedir ayuda, verifica:

- [ ] PHP 8.2 está como versión por defecto: `php --version`
- [ ] Drivers MySQL disponibles: `php -r "print_r(PDO::getAvailableDrivers());"`
- [ ] MariaDB está corriendo: `sudo systemctl status mariadb`
- [ ] PHP-FPM está corriendo: `sudo systemctl status php8.2-fpm`
- [ ] Nginx está corriendo: `sudo systemctl status nginx`
- [ ] Puerto 7474 está abierto: `sudo netstat -tlnp | grep 7474`
- [ ] Firewall permite el puerto: `sudo ufw status`
- [ ] Permisos correctos en storage: `ls -la ~/aura/storage/`
- [ ] Base de datos accesible: `mysql -u aura_admin -pAdmin1234`
- [ ] Archivo .env configurado: `cat ~/aura/.env`
- [ ] Logs de error revisados: `sudo tail -50 /var/log/nginx/aura_error.log`
- [ ] phpMyAdmin accesible: `http://192.168.68.20:8998/`
- [ ] Puerto 8998 abierto para phpMyAdmin: `sudo netstat -tlnp | grep 8998`

---

## Fase 6: Acceso y Configuración del Cliente

### Acceso desde tu Computadora Personal

#### Opción 1: Acceso Directo por IP (Recomendado para empezar)

Accede directamente sin configurar hosts:
```
http://192.168.68.20:7474/
```

**Credenciales Aura:** `admin` / `admin123`

**Acceder a phpMyAdmin (Puerto 8998):**
```
http://192.168.68.20:8998/
```

**Credenciales phpMyAdmin:**
- Usuario: `aura_admin`
- Contraseña: `Admin1234`

#### Opción 2: Acceso por Subdominio (Requiere configuración adicional)

1. **En tu PC Windows/Mac**, edita el archivo `hosts`:

**Windows:** `C:\Windows\System32\drivers\etc\hosts`
**Mac/Linux:** `/etc/hosts`

Agregar estas líneas:
```text
192.168.68.20  aura.local
192.168.68.20  empresa_demo.aura.local
```

2. **Acceder a:**
```
http://empresa_demo.aura.local:7474/
```

**Credenciales:** `admin` / `admin123`

### Verificación Rápida

Desde el servidor, prueba:
```bash
# Probar PHP desde línea de comandos
php ~/aura/public/index.php

# Probar acceso local
curl http://localhost:7474/

# Ver logs en tiempo real
sudo tail -f /var/log/nginx/aura_error.log
```