Fase 1: Limpieza Total (Borrar lo anterior)
Antes de clonar, debemos eliminar los archivos actuales y las bases de datos para que no haya conflictos.

1. Eliminar archivos
Ejecuta este comando para borrar la carpeta del proyecto:

```bash
rm -rf ~/aura
```
2. Eliminar Bases de Datos
Para empezar de cero, borraremos la base de datos maestra y cualquier tenant. Puedes hacerlo desde phpMyAdmin o con este comando de Docker:

```bash
sudo docker exec -it mariadb mariadb -u root -p4dm1n1234 -e "DROP DATABASE IF EXISTS aura_master; DROP DATABASE IF EXISTS tenant_empresa; DROP DATABASE IF EXISTS tenant_empresa_demo;"
```
Fase 2: Instalación Limpia
1. Clonar el repositorio
Sitúate en tu carpeta de usuario y clona el código:

```bash
cd ~
git clone https://github.com/digiraldo/aura.git
cd aura
```
2. Corregir permisos inmediatamente
Como vimos que los archivos suelen bajarse con permisos de root o restringidos, daremos la propiedad a tu usuario di para que Docker pueda trabajar:

```bash
sudo chown -R di:di ~/aura
sudo chmod -R 775 ~/aura/storage
```
Fase 3: Configuración y "Parches" Automáticos
Para que no vuelvas a tropezar con los errores de Namespace y de IP que ya solucionamos, aplica estos cambios antes de instalar:

1. Crear el archivo .env con la IP correcta
```bash
cp .env.example .env
sed -i 's/DB_HOST=localhost/DB_HOST=192.168.68.20/g' .env
sed -i 's/DB_PASSWORD=/DB_PASSWORD=4dm1n1234/g' .env
```
2. Aplicar el parche del "Namespace" (El error de la clase no encontrada)
Ejecuta este comando para que el script de creación de tenants use el nombre de clase correcto que descubrimos:

```bash
sed -i 's/new Aura\\Core\\SchemaManager/new Aura\\Core\\Database\\SchemaManager/g' ~/aura/create_tenant.php
```

1. Instalar Nginx, PHP y extensiones
Primero, instalaremos el servidor web y el motor de PHP con las extensiones necesarias para que Aura (que usa MySQL/MariaDB y transacciones) funcione correctamente.

Si usas Ubuntu:
```bash
sudo apt update
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip -y
```

Si usas Debian:
```bash
sudo apt update
sudo apt install lsb-release apt-transport-https ca-certificates curl -y
sudo curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/nginx/sites-available/php.list
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip -y
```

¿Qué pasa con el error de "archivos de índice"?
Si al hacer sudo apt update sigues viendo mensajes de error en rojo sobre archivos que no se pueden descargar, intenta limpiar la caché de paquetes:

```bash
sudo rm -rf /var/lib/apt/lists/*
sudo apt update
```


Verificar la instalación
Una vez termine, asegúrate de que todo esté en orden con estos dos comandos:

Ver la versión de PHP:

```bash
php -v
```
(Debería decir PHP 8.2.x)

Ver si el servicio FPM (para Nginx) está corriendo:

```bash
sudo systemctl status php8.2-fpm
```

2. Configurar el Virtual Host en Nginx
Ahora crearemos el archivo de configuración para que Nginx sepa dónde está Aura y cómo procesar PHP.

Crea el archivo de configuración:

```bash
sudo nano /etc/nginx/sites-available/aura
```
Pega el siguiente contenido (ajustando el puerto si no quieres usar el 80):

```nginx
server {
    listen 80; # Puedes cambiarlo a 7484 si prefieres
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
Activa el sitio y reinicia Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/aura /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx
```
3. Ajustar Permisos (Crucial)
En una instalación nativa, el usuario que ejecuta la web es www-data. Para que el sistema pueda escribir logs y subir archivos en /storage, debemos darle permisos.

```bash
# Agregamos a www-data al grupo de tu usuario para que pueda leer los archivos
sudo usermod -aG di www-data

# Damos permisos de escritura a las carpetas de sistema de Aura
sudo chown -R di:www-data ~/aura
sudo chmod -R 775 ~/aura/storage
sudo chmod -R 775 ~/aura/plugins
```
4. Ejecutar la Instalación Final
Ahora que PHP ya está instalado en tu sistema, puedes correr los comandos directamente (sin docker exec):

Instalar el Core:

```bash
cd ~/aura
php install.php
```
Crear el Tenant:

```bash
php create_tenant.php empresa_demo
```
5. Configuración del archivo hosts
Dado que ya no usas Docker pero sigues queriendo entrar por subdominio, recuerda que en tu computadora (Windows/Mac) debes apuntar a la IP de tu servidor:

Archivo hosts de tu PC:
192.168.68.20  aura.local empresa_demo.aura.local

Resumen de servicios:
Base de datos: Sigue en Docker (MariaDB puerto 3306).

Servidor Web: Nginx nativo (Puerto 80 o el que elijas).

PHP: Nativo (Procesado por php8.2-fpm).