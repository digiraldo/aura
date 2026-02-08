# Guía de Instalación: Aura Platform (Stack Nativo Completo)

Esta guía detalla la instalación de **Aura Platform** en un servidor Debian/Ubuntu utilizando **todo el stack de forma nativa**: Nginx, PHP 8.2, MariaDB y phpMyAdmin.

## Fase 1: Limpieza Total (Opcional)

Si tienes instalaciones previas, puedes limpiarlas:

```bash
# Eliminar archivos del proyecto anterior si existe
cd ~
rm -rf ~/aura

# Limpiar bases de datos previas si existen (solo si ya tienes MariaDB instalado)
mysql -u root -p -e "DROP DATABASE IF EXISTS aura_master; DROP DATABASE IF EXISTS tenant_empresa; DROP DATABASE IF EXISTS tenant_empresa_demo;"
```

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

**Opción B: Configuración manual de seguridad (si el comando no existe)**

```bash
sudo mysql
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

### 4. Crear Usuario de Base de Datos

```bash
sudo mysql -u root -p
```

Dentro de MySQL, ejecuta:

```sql
CREATE USER 'aura_admin'@'localhost' IDENTIFIED BY 'Admin1234';
CREATE USER 'aura_admin'@'%' IDENTIFIED BY 'Admin1234';
GRANT ALL PRIVILEGES ON *.* TO 'aura_admin'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'aura_admin'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EXIT;
```

### 5. Instalar Nginx y PHP 8.2

```bash
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip -y
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

**Ajustar permisos para el usuario www-data de Nginx**
```bash
sudo chown -R di:www-data ~/aura
sudo chmod -R 775 ~/aura/storage
sudo chmod -R 775 ~/aura/plugins
```

### 2. Configurar Variables de Entorno (.env)

Configuramos la conexión hacia MariaDB local.

```bash
cp .env.example .env
sed -i 's/DB_HOST=.*/DB_HOST=localhost/g' .env
sed -i 's/DB_USER=.*/DB_USER=aura_admin/g' .env
sed -i 's/DB_PASSWORD=/DB_PASSWORD=Admin1234/g' .env
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

**Error: "Class SchemaManager not found"**
```bash
# Asegurarse de tener la última versión
cd ~/aura
git pull origin main
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