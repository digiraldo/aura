# Guía de Instalación: Aura Platform (Nativo + DB Docker)

Esta guía detalla la instalación de **Aura Platform** en un servidor Debian/Ubuntu utilizando **Nginx y PHP 8.2 de forma nativa**, manteniendo la base de datos **MariaDB en Docker**.

## Fase 1: Limpieza Total

Antes de comenzar, eliminamos instalaciones previas y bases de datos para evitar conflictos.

1. **Eliminar archivos del proyecto:**
```bash
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

# Agregar llave y repositorio de PHP
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

# Crear directorios necesarios si no existen (por si acaso)
mkdir -p ~/aura/storage/{logs,cache,uploads,sessions}
mkdir -p ~/aura/plugins

# Ajustar permisos para el usuario www-data de Nginx
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

### 3. Aplicar Parche de "Namespace"

Corregimos el error de la clase `SchemaManager` para que el cargador de PHP la encuentre.

```bash
sed -i 's/new Aura\\Core\\SchemaManager/new Aura\\Core\\Database\\SchemaManager/g' ~/aura/create_tenant.php

```

---

## Fase 4: Configuración del Servidor Web (Nginx)

1. **Crear archivo de sitio:**
```bash
sudo nano /etc/nginx/sites-available/aura

```


2. **Pegar configuración (Virtual Host):**
```nginx
server {
    listen 80; # Cambiar a 7484 si se desea mantener ese puerto
    server_name aura.local *.aura.local;

    root /home/di/aura/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    error_log /var/log/nginx/aura_error.log;
    access_log /var/log/nginx/aura_access.log;
}

```


3. **Activar y Reiniciar:**
```bash
sudo ln -s /etc/nginx/sites-available/aura /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx

```



---

## Fase 5: Finalizar Instalación

Ejecutamos los scripts de Aura utilizando el PHP nativo del sistema:

1. **Instalar Base de Datos Master:**
```bash
php install.php

```


2. **Crear Tenant de prueba:**
```bash
php create_tenant.php empresa_demo

```



---

## Acceso Final

Para entrar desde tu computadora personal, edita el archivo `hosts` de tu sistema (Windows/Mac) y añade:

```text
192.168.68.20  aura.local empresa_demo.aura.local

```

**URL de acceso:** `http://empresa_demo.aura.local`
**Credenciales:** `admin` / `admin123`



1. Limpieza total del entorno
Primero eliminamos los rastros de instalaciones anteriores, bases de datos y el repositorio de Cloudflare que está bloqueando las actualizaciones.

```bash
# Eliminar carpeta del proyecto
rm -rf ~/aura

# Eliminar bases de datos en Docker
sudo docker exec -it mariadb mariadb -u root -e "DROP DATABASE IF EXISTS aura_master; DROP DATABASE IF EXISTS tenant_empresa; DROP DATABASE IF EXISTS tenant_empresa_demo;"

# Eliminar repositorio bloqueado de Cloudflare y limpiar índices
sudo rm -f /etc/apt/sources.list.d/cloudflare-client.list
sudo rm -rf /var/lib/apt/lists/*
```