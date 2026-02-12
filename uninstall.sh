#!/bin/bash

###############################################################################
# AURA PLATFORM - SCRIPT DE DESINSTALACIÓN COMPLETA
# 
# Este script elimina completamente Aura Platform del servidor:
# - Bases de datos (aura_master y todos los tenants)
# - Configuraciones de Nginx
# - Logs
# - Archivos de sesión
# - Usuario de base de datos
#
# ⚠️  ADVERTENCIA: Esta acción es IRREVERSIBLE
###############################################################################

set -e  # Salir si hay errores

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   AURA PLATFORM - DESINSTALACIÓN COMPLETA               ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

# Leer contraseña de MySQL root
read -sp "🔐 Ingresa la contraseña de MySQL root: " MYSQL_ROOT_PASSWORD
echo ""
echo ""

# Verificar conexión a MySQL
if ! mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1;" &>/dev/null; then
    echo -e "${RED}❌ ERROR: No se pudo conectar a MySQL. Verifica la contraseña.${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Conexión a MySQL verificada${NC}"
echo ""

# Confirmar desinstalación
echo -e "${YELLOW}⚠️  Esta acción eliminará:${NC}"
echo "   - Todas las bases de datos de Aura (aura_master, tenant_*)"
echo "   - Usuario de base de datos 'aura_admin'"
echo "   - Configuraciones de Nginx"
echo "   - Logs de la aplicación"
echo "   - Archivos de sesión"
echo ""
read -p "¿Estás SEGURO de continuar? Escribe 'SI ELIMINAR' para confirmar: " CONFIRM

if [ "$CONFIRM" != "SI ELIMINAR" ]; then
    echo -e "${YELLOW}⛔ Desinstalación cancelada.${NC}"
    exit 0
fi

echo ""
echo -e "${BLUE}🗑️  Iniciando desinstalación...${NC}"
echo ""

# 1. Detener servicios
echo "1️⃣  Deteniendo servicios..."
sudo systemctl stop nginx 2>/dev/null || true
sudo systemctl stop php8.2-fpm 2>/dev/null || true
echo -e "${GREEN}   ✅ Servicios detenidos${NC}"

# 2. Eliminar bases de datos
echo ""
echo "2️⃣  Eliminando bases de datos..."

# Obtener lista de bases de datos tenant
TENANT_DBS=$(mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -Nse "SHOW DATABASES LIKE 'tenant_%';")

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<EOF
-- Eliminar base de datos master
DROP DATABASE IF EXISTS aura_master;

-- Eliminar bases de datos de tenants
$(echo "$TENANT_DBS" | while read db; do echo "DROP DATABASE IF EXISTS \`$db\`;"; done)

-- Eliminar usuario aura_admin
DROP USER IF EXISTS 'aura_admin'@'localhost';
DROP USER IF EXISTS 'aura_admin'@'%';

FLUSH PRIVILEGES;
EOF

echo -e "${GREEN}   ✅ Base de datos 'aura_master' eliminada${NC}"
echo -e "${GREEN}   ✅ $(echo "$TENANT_DBS" | wc -l) bases de datos tenant eliminadas${NC}"
echo -e "${GREEN}   ✅ Usuario 'aura_admin' eliminado${NC}"

# 3. Eliminar configuraciones de Nginx
echo ""
echo "3️⃣  Eliminando configuraciones de Nginx..."
sudo rm -f /etc/nginx/conf.d/aura.conf
sudo rm -f /etc/nginx/conf.d/phpmyadmin.conf
sudo rm -f /etc/nginx/sites-enabled/aura 2>/dev/null || true
sudo rm -f /etc/nginx/sites-available/aura 2>/dev/null || true
echo -e "${GREEN}   ✅ Configuraciones de Nginx eliminadas${NC}"

# 4. Eliminar logs
echo ""
echo "4️⃣  Eliminando logs..."
sudo rm -f /var/log/nginx/aura_*.log
sudo rm -f /var/log/nginx/phpmyadmin_*.log
echo -e "${GREEN}   ✅ Logs eliminados${NC}"

# 5. Limpiar archivos de sesión y storage
echo ""
echo "5️⃣  Limpiando archivos de sesión..."
rm -rf ~/aura/storage/sessions/* 2>/dev/null || true
rm -rf ~/aura/storage/logs/* 2>/dev/null || true
rm -rf ~/aura/storage/cache/* 2>/dev/null || true
echo -e "${GREEN}   ✅ Archivos de sesión limpiados${NC}"

# 6. Eliminar directorio del proyecto (opcional)
echo ""
read -p "¿Deseas eliminar el directorio ~/aura completamente? (s/n): " DELETE_DIR
if [ "$DELETE_DIR" = "s" ] || [ "$DELETE_DIR" = "S" ]; then
    rm -rf ~/aura
    echo -e "${GREEN}   ✅ Directorio ~/aura eliminado${NC}"
else
    echo -e "${YELLOW}   ⚠️  Directorio ~/aura conservado${NC}"
fi

# 7. Reiniciar servicios
echo ""
echo "7️⃣  Reiniciando servicios..."
sudo systemctl start nginx
sudo systemctl start php8.2-fpm
echo -e "${GREEN}   ✅ Servicios reiniciados${NC}"

echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   ✅ DESINSTALACIÓN COMPLETADA EXITOSAMENTE             ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}📝 Resumen:${NC}"
echo "   - Bases de datos eliminadas"
echo "   - Usuario MySQL eliminado"
echo "   - Configuraciones de Nginx eliminadas"
echo "   - Logs limpiados"
if [ "$DELETE_DIR" = "s" ] || [ "$DELETE_DIR" = "S" ]; then
    echo "   - Directorio del proyecto eliminado"
fi
echo ""
echo -e "${YELLOW}💡 Ahora puedes ejecutar ./install.sh para reinstalar Aura Platform${NC}"
echo ""
