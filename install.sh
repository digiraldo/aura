#!/bin/bash

###############################################################################
# AURA PLATFORM - SCRIPT DE INSTALACIÓN AUTOMÁTICA
# 
# Este script instala Aura Platform desde cero:
# - Opcionalmente instala phpMyAdmin (método manual, sin apt)
# - Crea las bases de datos necesarias
# - Configura el usuario de base de datos
# - Ejecuta las migraciones
# - Crea el tenant de prueba con usuario admin personalizado
# - Verifica que todo funcione correctamente
#
# Requisitos previos:
# - Nginx, PHP 8.2, MariaDB ya instalados
# - Git instalado
# - Proyecto clonado en ~/aura (o se clonará automáticamente)
###############################################################################

set -e  # Salir si hay errores

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

clear
echo -e "${BLUE}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   AURA PLATFORM - INSTALACIÓN AUTOMÁTICA                ║${NC}"
echo -e "${BLUE}║   El WordPress de la Contabilidad                        ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

# ============================================================================
# PASO 1: RECOLECTAR INFORMACIÓN
# ============================================================================

echo -e "${CYAN}═══ PASO 1: Configuración de Credenciales ═══${NC}"
sudo rm -rf uninstall.sh
echo ""

# Contraseña de MySQL root
read -sp "🔐 Ingresa la contraseña de MySQL root: " MYSQL_ROOT_PASSWORD
echo ""

# Verificar conexión
if ! mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1;" &>/dev/null; then
    echo -e "${RED}❌ ERROR: No se pudo conectar a MySQL. Verifica la contraseña.${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Conexión a MySQL verificada${NC}"
echo ""

# Credenciales del usuario admin
echo -e "${YELLOW}Configura las credenciales del administrador:${NC}"
echo ""

read -p "📧 Email del administrador (ej: admin@tuempresa.com): " ADMIN_EMAIL
if [ -z "$ADMIN_EMAIL" ]; then
    echo -e "${RED}❌ ERROR: El email no puede estar vacío${NC}"
    exit 1
fi

read -p "👤 Usuario del administrador (ej: admin): " ADMIN_USERNAME
if [ -z "$ADMIN_USERNAME" ]; then
    ADMIN_USERNAME="admin"
    echo -e "${YELLOW}   Usando usuario por defecto: admin${NC}"
fi

read -sp "🔑 Contraseña del administrador (min 8 caracteres): " ADMIN_PASSWORD
echo ""

if [ ${#ADMIN_PASSWORD} -lt 8 ]; then
    echo -e "${RED}❌ ERROR: La contraseña debe tener al menos 8 caracteres${NC}"
    exit 1
fi

read -sp "🔑 Confirma la contraseña: " ADMIN_PASSWORD_CONFIRM
echo ""

if [ "$ADMIN_PASSWORD" != "$ADMIN_PASSWORD_CONFIRM" ]; then
    echo -e "${RED}❌ ERROR: Las contraseñas no coinciden${NC}"
    exit 1
fi

# Nombre del tenant
echo ""
read -p "🏢 Nombre del tenant (ej: empresa_demo, solo minúsculas y guiones bajos): " TENANT_NAME
if [ -z "$TENANT_NAME" ]; then
    TENANT_NAME="empresa_demo"
    echo -e "${YELLOW}   Usando tenant por defecto: empresa_demo${NC}"
fi

# Validar formato del tenant
if ! [[ "$TENANT_NAME" =~ ^[a-z0-9_]+$ ]]; then
    echo -e "${RED}❌ ERROR: El nombre del tenant solo puede contener letras minúsculas, números y guiones bajos${NC}"
    exit 1
fi

# Obtener IP del servidor
SERVER_IP=$(hostname -I | awk '{print $1}')

echo ""
echo -e "${GREEN}═══ Resumen de Configuración ═══${NC}"
echo ""
echo "🌐 Servidor: $SERVER_IP"
echo "👤 Usuario Admin: $ADMIN_USERNAME"
echo "📧 Email: $ADMIN_EMAIL"
echo "🏢 Tenant: $TENANT_NAME"
echo ""
read -p "¿Continuar con la instalación? (s/n): " CONFIRM

if [ "$CONFIRM" != "s" ] && [ "$CONFIRM" != "S" ]; then
    echo -e "${YELLOW}⛔ Instalación cancelada${NC}"
    exit 0
fi

echo ""

# ============================================================================
# PASO 2: VERIFICAR DIRECTORIO DEL PROYECTO
# ============================================================================

echo -e "${CYAN}═══ PASO 2: Verificando estructura del proyecto ═══${NC}"
echo ""

# Verificar si existe el directorio, si no, clonarlo
if [ ! -d ~/aura ]; then
    echo -e "${YELLOW}📦 Directorio ~/aura no encontrado. Clonando repositorio...${NC}"
    
    # Verificar si git está instalado
    if ! command -v git &> /dev/null; then
        echo -e "${RED}❌ ERROR: Git no está instalado.${NC}"
        echo "   Instala git primero: sudo apt install git -y"
        exit 1
    fi
    
    # Clonar el repositorio
    cd ~ || exit 1
    git clone https://github.com/digiraldo/aura.git ~/aura
    
    if [ $? -ne 0 ]; then
        echo -e "${RED}❌ ERROR: No se pudo clonar el repositorio${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}✅ Repositorio clonado exitosamente${NC}"
    
    # Actualizar scripts en el home si este script está en ~/install.sh
    SCRIPT_PATH="$(realpath "${BASH_SOURCE[0]}" 2>/dev/null || echo "${BASH_SOURCE[0]}")"
    if [[ "$SCRIPT_PATH" == "$HOME/install.sh" ]] && [ -f ~/aura/install.sh ]; then
        echo -e "${BLUE}📋 Actualizando script de instalación...${NC}"
        cp ~/aura/install.sh ~/install.sh
        chmod +x ~/install.sh
        if [ -f ~/aura/uninstall.sh ]; then
            cp ~/aura/uninstall.sh ~/uninstall.sh
            chmod +x ~/uninstall.sh
        fi
        echo -e "${GREEN}✅ Scripts actualizados en ~/install.sh y ~/uninstall.sh${NC}"
    fi
    echo ""
fi

# Cambiar al directorio del proyecto
cd ~/aura || {
    echo -e "${RED}❌ ERROR: No se pudo acceder al directorio ~/aura${NC}"
    exit 1
}

# Verificar archivos clave
if [ ! -f "install.php" ]; then
    echo -e "${RED}❌ ERROR: Archivo install.php no encontrado${NC}"
    echo "   El repositorio puede estar incompleto. Intenta:"
    echo "   rm -rf ~/aura && git clone https://github.com/digiraldo/aura.git ~/aura"
    exit 1
fi

if [ ! -f "create_tenant.php" ]; then
    echo -e "${RED}❌ ERROR: Archivo create_tenant.php no encontrado${NC}"
    exit 1
fi

if [ ! -f ".env.example" ]; then
    echo -e "${RED}❌ ERROR: Archivo .env.example no encontrado${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Estructura del proyecto verificada${NC}"

# ============================================================================
# PASO 3: CONFIGURAR .env
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 3: Configurando variables de entorno ═══${NC}"
echo ""

cp .env.example .env

# Configurar variables de base de datos
sed -i "s/DB_HOST=.*/DB_HOST=localhost/g" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=aura_admin/g" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=Admin1234/g" .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=aura_master/g" .env

echo -e "${GREEN}✅ Archivo .env configurado${NC}"

# ============================================================================
# PASO 4: CONFIGURAR BASE DE DATOS
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 4: Configurando base de datos ═══${NC}"
echo ""

echo "🔧 Creando usuario 'aura_admin'..."

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<EOF
-- Eliminar usuarios previos si existen
DROP USER IF EXISTS 'aura_admin'@'localhost';
DROP USER IF EXISTS 'aura_admin'@'%';

-- Crear usuarios nuevos
CREATE USER 'aura_admin'@'localhost' IDENTIFIED BY 'Admin1234';
CREATE USER 'aura_admin'@'%' IDENTIFIED BY 'Admin1234';
GRANT ALL PRIVILEGES ON *.* TO 'aura_admin'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'aura_admin'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Usuario 'aura_admin' creado exitosamente${NC}"
else
    echo -e "${RED}❌ ERROR al crear el usuario de base de datos${NC}"
    exit 1
fi

# ============================================================================
# PASO 5: INSTALAR Y CONFIGURAR phpMyAdmin
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 5: Instalando phpMyAdmin ═══${NC}"
echo ""

# Preguntar si desea instalar phpMyAdmin
read -p "¿Deseas instalar phpMyAdmin? (s/n): " INSTALL_PHPMYADMIN
echo ""

if [ "$INSTALL_PHPMYADMIN" != "s" ] && [ "$INSTALL_PHPMYADMIN" != "S" ]; then
    echo -e "${YELLOW}⏭️  Instalación de phpMyAdmin omitida${NC}"
    PHPMYADMIN_INSTALLED="no"
else
    # Limpiar paquetes rotos antes de instalar
    echo "🧹 Verificando estado de paquetes..."
    sudo dpkg --configure -a 2>/dev/null || true
    
    # Eliminar paquetes LiteSpeed problemáticos si existen
    LITESPEED_PACKAGES=$(dpkg -l | grep -E "php.*litespeed" | awk '{print $2}' 2>/dev/null || true)
    if [ ! -z "$LITESPEED_PACKAGES" ]; then
        echo "   Eliminando paquetes LiteSpeed conflictivos..."
        for pkg in $LITESPEED_PACKAGES; do
            sudo apt-get remove --purge -y "$pkg" 2>/dev/null || true
            sudo dpkg --remove --force-remove-reinstreq "$pkg" 2>/dev/null || true
        done
        sudo apt-get autoremove -y 2>/dev/null || true
    fi
    
    # Verificar si phpMyAdmin ya está instalado
    if [ -d "/usr/share/phpmyadmin" ] && [ -f "/usr/share/phpmyadmin/index.php" ]; then
        echo -e "${YELLOW}⚠️  phpMyAdmin ya está instalado${NC}"
        PHPMYADMIN_INSTALLED="yes"
    else
        echo "🔧 Instalando phpMyAdmin (método manual)..."
        
        # Instalar dependencias PHP necesarias
        echo "📦 Instalando extensiones PHP necesarias..."
        sudo apt-get update -qq
        sudo apt-get install -y wget unzip php-mbstring php-zip php-gd php-json php-curl -qq
        
        # Descargar phpMyAdmin
        PHPMYADMIN_VERSION="5.2.1"
        PHPMYADMIN_URL="https://files.phpmyadmin.net/phpMyAdmin/${PHPMYADMIN_VERSION}/phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages.zip"
        
        echo "⬇️  Descargando phpMyAdmin ${PHPMYADMIN_VERSION}..."
        cd /tmp
        wget -q --show-progress "${PHPMYADMIN_URL}" -O phpmyadmin.zip
        
        if [ $? -ne 0 ]; then
            echo -e "${RED}❌ ERROR al descargar phpMyAdmin${NC}"
            echo -e "${YELLOW}   Puedes instalarlo manualmente más tarde${NC}"
            PHPMYADMIN_INSTALLED="no"
        else
            # Extraer y mover a /usr/share
            echo "📂 Extrayendo phpMyAdmin..."
            sudo unzip -q phpmyadmin.zip
            sudo rm -rf /usr/share/phpmyadmin
            sudo mv "phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages" /usr/share/phpmyadmin
            
            # Crear directorio de configuración
            sudo mkdir -p /usr/share/phpmyadmin/tmp
            sudo chown -R www-data:www-data /usr/share/phpmyadmin/tmp
            sudo chmod 777 /usr/share/phpmyadmin/tmp
            
            # Crear archivo de configuración
            sudo cp /usr/share/phpmyadmin/config.sample.inc.php /usr/share/phpmyadmin/config.inc.php
            
            # Generar blowfish_secret
            BLOWFISH_SECRET=$(openssl rand -base64 32)
            sudo sed -i "s/\$cfg\['blowfish_secret'\] = '';/\$cfg['blowfish_secret'] = '${BLOWFISH_SECRET}';/" /usr/share/phpmyadmin/config.inc.php
            
            # Configurar TempDir
            echo "\$cfg['TempDir'] = '/usr/share/phpmyadmin/tmp';" | sudo tee -a /usr/share/phpmyadmin/config.inc.php > /dev/null
            
            # Limpiar archivos temporales
            rm -f phpmyadmin.zip
            
            # Volver al directorio del proyecto
            cd ~/aura || {
                echo -e "${RED}❌ ERROR: No se pudo volver al directorio ~/aura${NC}"
                exit 1
            }
            
            echo -e "${GREEN}✅ phpMyAdmin instalado manualmente${NC}"
            PHPMYADMIN_INSTALLED="yes"
        fi
    fi
fi

# Configurar Nginx para phpMyAdmin en puerto 8998 (solo si se instaló)
if [ "$PHPMYADMIN_INSTALLED" = "yes" ]; then
    echo ""
    echo "🔧 Configurando Nginx para phpMyAdmin..."

    if [ -f "/etc/nginx/conf.d/phpmyadmin.conf" ]; then
        echo -e "${YELLOW}⚠️  Configuración de phpMyAdmin ya existe${NC}"
    else
        sudo tee /etc/nginx/conf.d/phpmyadmin.conf > /dev/null <<'PHPMYADMIN_CONF'
server {
    listen 8998;
    server_name _;
    
    root /usr/share/phpmyadmin;
    index index.php index.html index.htm;
    
    location / {
        try_files $uri $uri/ =404;
    }
    
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param QUERY_STRING $query_string;
        fastcgi_param REQUEST_METHOD $request_method;
        fastcgi_param CONTENT_TYPE $content_type;
        fastcgi_param CONTENT_LENGTH $content_length;
        include fastcgi_params;
    }
    
    location ~ /\.ht {
        deny all;
    }
    
    error_log /var/log/nginx/phpmyadmin_error.log;
    access_log /var/log/nginx/phpmyadmin_access.log;
}
PHPMYADMIN_CONF
        
        echo -e "${GREEN}✅ Configuración de Nginx para phpMyAdmin creada${NC}"
    fi

    # Probar configuración de Nginx
    echo ""
    echo "🔧 Verificando configuración de Nginx..."
    sudo nginx -t

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Configuración de Nginx válida${NC}"
        sudo systemctl reload nginx
        echo -e "${GREEN}✅ Nginx recargado${NC}"
    else
        echo -e "${RED}❌ ERROR en la configuración de Nginx${NC}"
        exit 1
    fi

    echo ""
    echo -e "${GREEN}✅ phpMyAdmin instalado y configurado${NC}"
    echo -e "${BLUE}   Acceso: http://${SERVER_IP}:8998/${NC}"
    echo -e "${BLUE}   Usuario: aura_admin (o root)${NC}"
    echo -e "${BLUE}   Contraseña: Admin1234 (o la de root)${NC}"
fi

# ============================================================================
# PASO 6: EJECUTAR INSTALACIÓN DE AURA
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 6: Instalando base de datos master ═══${NC}"
echo ""

# Asegurarse de estar en el directorio correcto
cd ~/aura || {
    echo -e "${RED}❌ ERROR: No se pudo acceder al directorio ~/aura${NC}"
    exit 1
}

php install.php

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ ERROR durante la instalación de la base de datos master${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Base de datos master instalada${NC}"

# ============================================================================
# PASO 7: CORREGIR CONFIGURACIÓN DE SESIONES (HTTP)
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 7: Configurando sesiones para HTTP ═══${NC}"
echo ""

# Cambiar session.cookie_secure de '1' a '0' en Bootstrap.php
sed -i "s/ini_set('session.cookie_secure', '1'); \/\/ Solo HTTPS/ini_set('session.cookie_secure', '0'); \/\/ Permitir HTTP (cambiar a 1 en producción con HTTPS)/" ~/aura/core/lib/Bootstrap.php

if grep -q "cookie_secure', '0'" ~/aura/core/lib/Bootstrap.php; then
    echo -e "${GREEN}✅ Configuración de sesiones corregida (HTTP habilitado)${NC}"
else
    echo -e "${YELLOW}⚠️  Advertencia: No se pudo actualizar la configuración de sesiones${NC}"
fi

# ============================================================================
# PASO 8: CREAR TENANT CON USUARIO ADMIN PERSONALIZADO
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 8: Creando tenant '$TENANT_NAME' ═══${NC}"
echo ""

# Crear script PHP temporal para crear el usuario con las credenciales correctas
cat > /tmp/create_custom_tenant.php <<'EOPHP'
<?php
declare(strict_types=1);

// Obtener argumentos
$projectPath = $argv[1] ?? null;
$tenantName = $argv[2] ?? null;
$adminUsername = $argv[3] ?? 'admin';
$adminPassword = $argv[4] ?? 'admin123';
$adminEmail = $argv[5] ?? 'admin@empresa.local';

if (!$projectPath || !$tenantName) {
    die("ERROR: Ruta del proyecto y nombre del tenant requeridos\n");
}

// Cargar .env
$env = [];
$envFile = $projectPath . '/.env';
if (!file_exists($envFile)) {
    die("ERROR: Archivo .env no encontrado en: $envFile\n");
}

$envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($envLines as $line) {
    $line = trim($line);
    if (empty($line) || str_starts_with($line, '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value);
}

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbDatabase = $env['DB_DATABASE'] ?? 'aura_master';
$dbUsername = $env['DB_USERNAME'] ?? 'root';
$dbPassword = $env['DB_PASSWORD'] ?? '';

// Incluir SchemaManager
$schemaManagerPath = $projectPath . '/core/lib/Database/SchemaManager.php';
if (!file_exists($schemaManagerPath)) {
    die("ERROR: SchemaManager no encontrado en: $schemaManagerPath\n");
}
require_once $schemaManagerPath;

try {
    // Conectar a master
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbDatabase};charset=utf8mb4";
    $pdoMaster = new PDO($dsn, $dbUsername, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Verificar si ya existe
    $stmt = $pdoMaster->prepare("SELECT id FROM tenants WHERE nombre = ?");
    $stmt->execute([$tenantName]);
    if ($stmt->fetch()) {
        die("ERROR: El tenant '$tenantName' ya existe\n");
    }
    
    // Conectar sin DB específica
    $dsnRoot = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdoRoot = new PDO($dsnRoot, $dbUsername, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Crear tenant
    $schemaManager = new Aura\Core\Database\SchemaManager($pdoRoot, $pdoMaster);
    
    echo "Creando tenant...\n";
    
    $tenantId = $schemaManager->createTenantSchema($tenantName, [
        'username' => $adminUsername,
        'password' => $adminPassword,
        'email' => $adminEmail,
        'nombre_completo' => 'Administrador Principal'
    ]);
    
    echo "Tenant creado con ID: $tenantId\n";
    echo "SUCCESS\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
EOPHP

# Ejecutar script de creación de tenant con la ruta del proyecto
php /tmp/create_custom_tenant.php "$HOME/aura" "$TENANT_NAME" "$ADMIN_USERNAME" "$ADMIN_PASSWORD" "$ADMIN_EMAIL"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Tenant '$TENANT_NAME' creado exitosamente${NC}"
else
    echo -e "${RED}❌ ERROR al crear el tenant${NC}"
    rm /tmp/create_custom_tenant.php
    exit 1
fi

rm /tmp/create_custom_tenant.php

# ============================================================================
# PASO 9: VERIFICAR USUARIO EN BASE DE DATOS
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 9: Verificando usuario en base de datos ═══${NC}"
echo ""

# Verificar que el usuario existe y tiene los campos correctos
VERIFY_USER=$(mysql -u aura_admin -pAdmin1234 -D "tenant_${TENANT_NAME}" -Nse "SELECT username, email FROM usuarios WHERE username='${ADMIN_USERNAME}' LIMIT 1;")

if [ -n "$VERIFY_USER" ]; then
    echo -e "${GREEN}✅ Usuario verificado en base de datos${NC}"
    echo "   $VERIFY_USER"
else
    echo -e "${RED}❌ ERROR: Usuario no encontrado en la base de datos${NC}"
    exit 1
fi

# ============================================================================
# PASO 10: CONFIGURAR NGINX
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 10: Configurando Nginx ═══${NC}"
echo ""

# Verificar si ya existe configuración
if [ -f "/etc/nginx/conf.d/aura.conf" ]; then
    echo -e "${YELLOW}⚠️  Configuración de Nginx ya existe, omitiendo...${NC}"
else
    sudo tee /etc/nginx/conf.d/aura.conf > /dev/null <<EOF
server {
    listen 7474;
    
    server_name $SERVER_IP aura.local *.aura.local localhost;

    root /home/$(whoami)/aura/public;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        try_files \$uri =404;
        
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        
        fastcgi_read_timeout 300;
    }

    location ~ /\. {
        deny all;
    }

    error_log /var/log/nginx/aura_error.log;
    access_log /var/log/nginx/aura_access.log;
}
EOF

    echo -e "${GREEN}✅ Configuración de Nginx creada${NC}"
fi

# Probar configuración
echo ""
echo "🔧 Probando configuración de Nginx..."
sudo nginx -t

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Configuración de Nginx válida${NC}"
    
    # Reiniciar servicios
    echo ""
    echo "🔄 Reiniciando servicios..."
    sudo systemctl restart php8.2-fpm
    sudo systemctl restart nginx
    
    echo -e "${GREEN}✅ Servicios reiniciados${NC}"
else
    echo -e "${RED}❌ ERROR en la configuración de Nginx${NC}"
    exit 1
fi

# ============================================================================
# PASO 11: CONFIGURAR /etc/hosts en el servidor
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 11: Configurando /etc/hosts ═══${NC}"
echo ""

# Verificar si ya existe la entrada
if grep -q "${TENANT_NAME}.aura.local" /etc/hosts; then
    echo -e "${YELLOW}⚠️  Entrada ya existe en /etc/hosts${NC}"
else
    echo "127.0.0.1       aura.local" | sudo tee -a /etc/hosts > /dev/null
    echo "127.0.0.1       ${TENANT_NAME}.aura.local" | sudo tee -a /etc/hosts > /dev/null
    echo "$SERVER_IP    aura.local" | sudo tee -a /etc/hosts > /dev/null
    echo "$SERVER_IP    ${TENANT_NAME}.aura.local" | sudo tee -a /etc/hosts > /dev/null
    
    echo -e "${GREEN}✅ Archivo /etc/hosts actualizado${NC}"
fi

# ============================================================================
# PASO 12: PRUEBA DE AUTENTICACIÓN
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 12: Probando autenticación ═══${NC}"
echo ""

# Crear script de prueba
cat > /tmp/test_auth.php <<EOPHP
<?php
\$pdo = new PDO('mysql:host=localhost;dbname=tenant_${TENANT_NAME}', 'aura_admin', 'Admin1234');
\$stmt = \$pdo->prepare("SELECT id, username, email, password_hash FROM usuarios WHERE username = ?");
\$stmt->execute(['${ADMIN_USERNAME}']);
\$user = \$stmt->fetch(PDO::FETCH_ASSOC);

if (\$user) {
    \$passwordMatch = password_verify('${ADMIN_PASSWORD}', \$user['password_hash']);
    echo "✅ Usuario encontrado: " . \$user['username'] . "\n";
    echo "✅ Email: " . \$user['email'] . "\n";
    echo (\$passwordMatch ? "✅ Contraseña verificada correctamente\n" : "❌ ERROR: Contraseña incorrecta\n");
    exit(\$passwordMatch ? 0 : 1);
} else {
    echo "❌ ERROR: Usuario no encontrado\n";
    exit(1);
}
EOPHP

php /tmp/test_auth.php

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Autenticación verificada correctamente${NC}"
else
    echo -e "${RED}❌ ERROR: Falló la verificación de autenticación${NC}"
    rm /tmp/test_auth.php
    exit 1
fi

rm /tmp/test_auth.php

# ============================================================================
# FINALIZACIÓN
# ============================================================================

echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   ✅ INSTALACIÓN COMPLETADA EXITOSAMENTE                 ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${CYAN}📋 Información de Acceso:${NC}"
echo ""
echo -e "${BLUE}🌐 URL de Acceso:${NC}"
echo "   http://${TENANT_NAME}.aura.local:7474/"
echo "   http://${SERVER_IP}:7474/ (después de configurar DNS en tu PC)"
echo ""
echo -e "${BLUE}👤 Credenciales de Administrador:${NC}"
echo "   Usuario: ${ADMIN_USERNAME}"
echo "   Email: ${ADMIN_EMAIL}"
echo "   Contraseña: ${ADMIN_PASSWORD}"
echo ""
echo -e "${BLUE}🗄️  Base de Datos:${NC}"
echo "   Master: aura_master"
echo "   Tenant: tenant_${TENANT_NAME}"
echo "   Usuario DB: aura_admin"
echo "   Contraseña DB: Admin1234"
echo ""
if [ "$PHPMYADMIN_INSTALLED" = "yes" ]; then
    echo -e "${BLUE}🛠️  phpMyAdmin:${NC}"
    echo "   URL: http://${SERVER_IP}:8998/"
    echo "   Usuario: aura_admin (o root)"
    echo "   Contraseña: Admin1234 (o la de root)"
    echo ""
fi
echo -e "${YELLOW}📝 Próximos pasos:${NC}"
echo ""
echo "1. En tu PC Windows, configura el archivo hosts:"
echo "   C:\\Windows\\System32\\drivers\\etc\\hosts"
echo ""
echo "   Agrega estas líneas (como Administrador):"
echo "   ${SERVER_IP}    aura.local"
echo "   ${SERVER_IP}    ${TENANT_NAME}.aura.local"
echo ""
echo "2. Limpia el caché DNS de Windows:"
echo "   ipconfig /flushdns"
echo ""
echo "3. Accede desde tu navegador:"
echo "   http://${TENANT_NAME}.aura.local:7474/"
echo ""
echo "4. Inicia sesión con las credenciales mostradas arriba"
echo ""
echo -e "${GREEN}🎉 ¡Disfruta de Aura Platform!${NC}"
echo ""

# ============================================================================
# INSTRUCCIONES DETALLADAS PARA CONFIGURACIÓN DEL CLIENTE
# ============================================================================

echo -e "${CYAN}═══════════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  📖 GUÍA PASO A PASO - Configuración en PC Windows       ${NC}"
echo -e "${CYAN}═══════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${YELLOW}1️⃣  Configurar archivo hosts (como Administrador)${NC}"
echo ""
echo "   a) Abre PowerShell como Administrador:"
echo "      • Click derecho en el botón 'Inicio'"
echo "      • Selecciona 'Terminal (Administrador)' o 'Windows PowerShell (Administrador)'"
echo ""
echo "   b) Edita el archivo hosts:"
echo "      notepad C:\\Windows\\System32\\drivers\\etc\\hosts"
echo ""
echo "   c) Agrega estas líneas al final del archivo:"
echo -e "      ${GREEN}${SERVER_IP}    aura.local${NC}"
echo -e "      ${GREEN}${SERVER_IP}    ${TENANT_NAME}.aura.local${NC}"
echo ""
echo "   d) Guarda y cierra el archivo (Ctrl+S, luego cierra Notepad)"
echo ""

echo -e "${YELLOW}2️⃣  Limpiar caché DNS${NC}"
echo ""
echo "   En la misma ventana de PowerShell (Administrador), ejecuta:"
echo "      ipconfig /flushdns"
echo ""
echo "   Deberías ver: 'Se vació correctamente la caché de resolución de DNS.'"
echo ""

echo -e "${YELLOW}3️⃣  Acceder a la aplicación${NC}"
echo ""
echo "   Abre tu navegador (Chrome, Firefox, Edge) y ve a:"
echo ""
echo -e "      ${GREEN}http://${TENANT_NAME}.aura.local:7474/${NC}"
echo ""

echo -e "${YELLOW}4️⃣  Iniciar sesión${NC}"
echo ""
echo "   Credenciales:"
echo -e "   • Usuario: ${BLUE}${ADMIN_USERNAME}${NC}"
echo -e "   • Contraseña: ${BLUE}${ADMIN_PASSWORD}${NC}"
echo ""

echo -e "${RED}═══════════════════════════════════════════════════════════${NC}"
echo -e "${RED}  🛠️  Solución de Problemas                               ${NC}"
echo -e "${RED}═══════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${YELLOW}❌ Error 'No se puede acceder al sitio':${NC}"
echo "   1. Verifica que guardaste el archivo hosts correctamente"
echo "   2. Ejecuta 'ipconfig /flushdns' nuevamente"
echo "   3. Prueba con 'ping ${TENANT_NAME}.aura.local' en CMD"
echo "      (debería responder ${SERVER_IP})"
echo ""

echo -e "${YELLOW}❌ Error 502 Bad Gateway:${NC}"
echo "   1. Verifica Nginx: sudo systemctl status nginx"
echo "   2. Verifica PHP-FPM: sudo systemctl status php8.2-fpm"
echo "   3. Revisa logs: sudo tail -50 /var/log/nginx/aura_error.log"
echo ""

echo -e "${YELLOW}❌ Página de login no aparece:${NC}"
echo "   1. Verifica logs: sudo tail -f /var/log/nginx/aura_error.log"
echo "   2. Verifica permisos: ls -la ~/aura/storage/"
echo ""

echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  🎯 ¿Qué puedes hacer ahora?                             ${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo ""
echo "   Una vez dentro de Aura Platform:"
echo "   ✅ Explorar el dashboard"
echo "   ✅ Crear productos"
echo "   ✅ Registrar ventas"
echo "   ✅ Administrar usuarios"
echo "   ✅ Configurar el sistema según tus necesidades"
echo ""
echo -e "${GREEN}   ¡Todo está listo para usar! 🚀${NC}"
echo ""
sudo rm -rf install.sh