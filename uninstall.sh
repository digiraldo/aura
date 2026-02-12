#!/bin/bash

###############################################################################
# AURA PLATFORM - SCRIPT DE DESINSTALACIÓN COMPLETA
# 
# Este script elimina completamente Aura Platform del servidor:
# - Bases de datos (aura_master y todos los tenants)
# - Configuraciones de Nginx (Aura y phpMyAdmin)
# - Logs
# - Archivos de sesión
# - Usuario de base de datos
# - Opcionalmente: phpMyAdmin y directorio del proyecto
#
# ⚠️  ADVERTENCIA: Esta acción es IRREVERSIBLE
###############################################################################

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
echo "   - Configuraciones de Nginx (Aura y phpMyAdmin)"
echo "   - Logs de la aplicación"
echo "   - Archivos de sesión"
echo "   - Opcionalmente: phpMyAdmin y directorio ~/aura"
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
TENANT_DBS=$(mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -Nse "SHOW DATABASES LIKE 'tenant_%';" 2>/dev/null || true)

# Construir comandos DROP para tenants solo si existen
TENANT_DROP_COMMANDS=""
if [ ! -z "$TENANT_DBS" ]; then
    TENANT_DROP_COMMANDS=$(echo "$TENANT_DBS" | while IFS= read -r db; do 
        if [ ! -z "$db" ]; then
            echo "DROP DATABASE IF EXISTS \`$db\`;"
        fi
    done)
fi

# Ejecutar eliminación de bases de datos
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<EOF 2>/dev/null || true
-- Eliminar base de datos master
DROP DATABASE IF EXISTS aura_master;

-- Eliminar bases de datos de tenants
$TENANT_DROP_COMMANDS

-- Eliminar usuario aura_admin
DROP USER IF EXISTS 'aura_admin'@'localhost';
DROP USER IF EXISTS 'aura_admin'@'%';

FLUSH PRIVILEGES;
EOF

# Contar tenants eliminados
if [ ! -z "$TENANT_DBS" ]; then
    TENANT_COUNT=$(echo "$TENANT_DBS" | grep -c '^' 2>/dev/null || echo "0")
else
    TENANT_COUNT=0
fi

echo -e "${GREEN}   ✅ Bases de datos eliminadas${NC}"
if [ $TENANT_COUNT -gt 0 ]; then
    echo -e "${GREEN}   ✅ $TENANT_COUNT base(s) de datos tenant eliminadas${NC}"
else
    echo -e "${YELLOW}   ℹ️  No se encontraron bases de datos tenant${NC}"
fi
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
    # Copiar scripts al home antes de eliminar el directorio
    if [ -f ~/aura/install.sh ]; then
        cp ~/aura/install.sh ~/install.sh
        chmod +x ~/install.sh
        echo -e "${BLUE}   📋 install.sh copiado a ~/install.sh${NC}"
    fi
    if [ -f ~/aura/uninstall.sh ]; then
        cp ~/aura/uninstall.sh ~/uninstall.sh
        chmod +x ~/uninstall.sh
        echo -e "${BLUE}   📋 uninstall.sh copiado a ~/uninstall.sh${NC}"
    fi
    # Cambiar al directorio home antes de eliminar
    cd ~ 2>/dev/null || true
    rm -rf ~/aura
    echo -e "${GREEN}   ✅ Directorio ~/aura eliminado${NC}"
else
    echo -e "${YELLOW}   ⚠️  Directorio ~/aura conservado${NC}"
fi

# 7. Desinstalar phpMyAdmin (opcional)
echo ""
read -p "¿Deseas eliminar phpMyAdmin? (s/n): " UNINSTALL_PHPMYADMIN
if [ "$UNINSTALL_PHPMYADMIN" = "s" ] || [ "$UNINSTALL_PHPMYADMIN" = "S" ]; then
    echo "7️⃣  Eliminando phpMyAdmin..."
    
    # Eliminar instalación via apt (si existe)
    if dpkg -l | grep -q phpmyadmin 2>/dev/null; then
        sudo apt-get remove --purge -y phpmyadmin 2>/dev/null || true
        sudo apt-get autoremove -y 2>/dev/null || true
        echo -e "${GREEN}   ✅ phpMyAdmin (apt) desinstalado${NC}"
    fi
    
    # Eliminar instalación manual (si existe)
    if [ -d "/usr/share/phpmyadmin" ]; then
        sudo rm -rf /usr/share/phpmyadmin
        echo -e "${GREEN}   ✅ phpMyAdmin (manual) eliminado${NC}"
    fi
    
    # Limpiar paquetes rotos relacionados con PHP
    echo "🧹 Limpiando paquetes rotos..."
    sudo dpkg --configure -a 2>/dev/null || true
    
    # Eliminar paquetes problemáticos de PHP que no se necesitan
    BROKEN_PACKAGES=$(dpkg -l | grep -E "php.*litespeed" | awk '{print $2}' 2>/dev/null || true)
    if [ ! -z "$BROKEN_PACKAGES" ]; then
        echo "   Eliminando paquetes LiteSpeed innecesarios..."
        for pkg in $BROKEN_PACKAGES; do
            sudo apt-get remove --purge -y "$pkg" 2>/dev/null || true
            sudo dpkg --remove --force-remove-reinstreq "$pkg" 2>/dev/null || true
        done
    fi
    
    sudo apt-get autoremove -y 2>/dev/null || true
    sudo apt-get clean 2>/dev/null || true
    
    echo -e "${GREEN}   ✅ phpMyAdmin eliminado completamente${NC}"
else
    echo -e "${YELLOW}   ⚠️  phpMyAdmin conservado${NC}"
fi

# 8. Reiniciar servicios
echo ""
echo "8️⃣  Reiniciando servicios..."
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
if [ "$UNINSTALL_PHPMYADMIN" = "s" ] || [ "$UNINSTALL_PHPMYADMIN" = "S" ]; then
    echo "   - phpMyAdmin desinstalado"
fi
if [ "$DELETE_DIR" = "s" ] || [ "$DELETE_DIR" = "S" ]; then
    echo "   - Directorio del proyecto eliminado"
    echo "   - Scripts copiados a ~/install.sh y ~/uninstall.sh"
fi
echo ""
if [ "$DELETE_DIR" = "s" ] || [ "$DELETE_DIR" = "S" ]; then
    echo -e "${YELLOW}💡 Para reinstalar Aura Platform, ejecuta: ~/install.sh${NC}"
    echo -e "${YELLOW}💡 Scripts disponibles en: ~/install.sh y ~/uninstall.sh${NC}"
else
    echo -e "${YELLOW}💡 Para reinstalar Aura Platform, ejecuta: ~/aura/install.sh${NC}"
fi
echo ""


# Cambiar al directorio home para salir del directorio del proyecto
cd ~ 2>/dev/null || true
