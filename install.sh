#!/bin/bash

###############################################################################
# AURA PLATFORM - SCRIPT DE INSTALACIÓN AUTOMÁTICA
# 
# Este script instala Aura Platform desde cero:
# - Crea las bases de datos necesarias
# - Configura el usuario de base de datos
# - Ejecuta las migraciones
# - Crea el tenant de prueba con usuario admin personalizado
# - Verifica que todo funcione correctamente
#
# Requisitos previos:
# - Nginx, PHP 8.2, MariaDB ya instalados
# - Git instalado
# - Proyecto clonado en ~/aura
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

cd ~/aura || {
    echo -e "${RED}❌ ERROR: Directorio ~/aura no encontrado.${NC}"
    echo "   Por favor, clona el proyecto primero:"
    echo "   git clone https://github.com/digiraldo/aura.git ~/aura"
    exit 1
}

# Verificar archivos clave
if [ ! -f "install.php" ]; then
    echo -e "${RED}❌ ERROR: Archivo install.php no encontrado${NC}"
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
# PASO 5: EJECUTAR INSTALACIÓN DE AURA
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 5: Instalando base de datos master ═══${NC}"
echo ""

php install.php

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ ERROR durante la instalación de la base de datos master${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Base de datos master instalada${NC}"

# ============================================================================
# PASO 6: CORREGIR CONFIGURACIÓN DE SESIONES (HTTP)
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 6: Configurando sesiones para HTTP ═══${NC}"
echo ""

# Cambiar session.cookie_secure de '1' a '0' en Bootstrap.php
sed -i "s/ini_set('session.cookie_secure', '1'); \/\/ Solo HTTPS/ini_set('session.cookie_secure', '0'); \/\/ Permitir HTTP (cambiar a 1 en producción con HTTPS)/" ~/aura/core/lib/Bootstrap.php

if grep -q "cookie_secure', '0'" ~/aura/core/lib/Bootstrap.php; then
    echo -e "${GREEN}✅ Configuración de sesiones corregida (HTTP habilitado)${NC}"
else
    echo -e "${YELLOW}⚠️  Advertencia: No se pudo actualizar la configuración de sesiones${NC}"
fi

# ============================================================================
# PASO 7: CREAR TENANT CON USUARIO ADMIN PERSONALIZADO
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 7: Creando tenant '$TENANT_NAME' ═══${NC}"
echo ""

# Crear script PHP temporal para crear el usuario con las credenciales correctas
cat > /tmp/create_custom_tenant.php <<'EOPHP'
<?php
declare(strict_types=1);

// Obtener argumentos
$tenantName = $argv[1] ?? null;
$adminUsername = $argv[2] ?? 'admin';
$adminPassword = $argv[3] ?? 'admin123';
$adminEmail = $argv[4] ?? 'admin@empresa.local';

if (!$tenantName) {
    die("ERROR: Nombre del tenant requerido\n");
}

// Cargar .env
$env = [];
$envLines = file(__DIR__ . '/aura/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
require_once __DIR__ . '/aura/core/lib/Database/SchemaManager.php';

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

# Ejecutar script de creación de tenant
php /tmp/create_custom_tenant.php "$TENANT_NAME" "$ADMIN_USERNAME" "$ADMIN_PASSWORD" "$ADMIN_EMAIL"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Tenant '$TENANT_NAME' creado exitosamente${NC}"
else
    echo -e "${RED}❌ ERROR al crear el tenant${NC}"
    rm /tmp/create_custom_tenant.php
    exit 1
fi

rm /tmp/create_custom_tenant.php

# ============================================================================
# PASO 8: VERIFICAR USUARIO EN BASE DE DATOS
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 8: Verificando usuario en base de datos ═══${NC}"
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
# PASO 9: CONFIGURAR NGINX
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 9: Configurando Nginx ═══${NC}"
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
# PASO 10: CONFIGURAR /etc/hosts en el servidor
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 10: Configurando /etc/hosts ═══${NC}"
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
# PASO 11: PRUEBA DE AUTENTICACIÓN
# ============================================================================

echo ""
echo -e "${CYAN}═══ PASO 11: Probando autenticación ═══${NC}"
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
