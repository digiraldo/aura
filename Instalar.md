# Guía de Instalación: Aura Platform (Nativo + DB Docker)

Esta guía detalla la instalación de **Aura Platform** en un servidor Debian/Ubuntu utilizando **Nginx y PHP 8.2 de forma nativa**, manteniendo la base de datos **MariaDB en Docker**.

## Fase 1: Limpieza Total

Antes de comenzar, eliminamos instalaciones previas y bases de datos para evitar conflictos.

1. **Eliminar archivos del proyecto:**
```bash
cd ~
rm -rf ~/aura

```


2. **Limpiar base de datos en Docker:**
```bash
sudo docker exec -it mariadb mariadb -u root -e "DROP DATABASE IF EXISTS aura_master; DROP DATABASE IF EXISTS tenant_empresa; DROP DATABASE IF EXISTS tenant_empresa_demo;"

```


3. **Eliminar repositorios con errores (Cloudflare Key Expired):**
```bash
sudo rm -f /etc/apt/sources.list.d/cloudflare-client.list
sudo rm -rf /var/lib/apt/lists/*

```



---

## Fase 2: Instalación de Dependencias (Nativo)

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

### 2. Instalar Nginx y PHP 8.2

```bash
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip -y

```

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

### Opción B: Si NO existe sites-available (tu caso actual)

Usa el directorio `conf.d` que es el estándar en muchas instalaciones:

1. **Crear archivo directamente en conf.d:**
```bash
sudo nano /etc/nginx/conf.d/aura.conf

```

2. **Pegar esta configuración:**
```nginx
server {
    listen 80; # Cambiar a 7474 si se desea mantener ese puerto
    server_name aura.local *.aura.local;

    root /home/di/aura/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    error_log /var/log/nginx/aura_error.log;
    access_log /var/log/nginx/aura_access.log;
}

```

**Nota:** Si `include snippets/fastcgi-php.conf;` no existe en tu sistema, se usa directamente `include fastcgi_params;`

3. **Verificar y Reiniciar:**
```bash
sudo nginx -t && sudo systemctl restart nginx

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
   Host: 192.168.68.20:3306
   Base de datos: aura_master
   Usuario: root

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

**Error: "Connection refused"**
```bash
# Verificar que MariaDB esté corriendo
sudo docker ps | grep mariadb

# Verificar conectividad
telnet 192.168.68.20 3306
```

**Error: "Access denied for user"**
```bash
# Verificar credenciales en .env
cat .env | grep DB_

# Probar conexión manual
mysql -h 192.168.68.20 -u root -p
```

**Error: "Class SchemaManager not found"**
```bash
# Asegurarse de tener la última versión
cd ~/aura
git pull origin main
```

---

## Acceso Final

Para entrar desde tu computadora personal, edita el archivo `hosts` de tu sistema (Windows/Mac) y añade:

```text
192.168.68.20  aura.local empresa_demo.aura.local

```

**URL de acceso:** `http://empresa_demo.aura.local`
**Credenciales:** `admin` / `admin123`