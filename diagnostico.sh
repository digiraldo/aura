#!/bin/bash

# Script de Diagnóstico para Aura Platform
# Ejecutar: bash diagnostico.sh

echo "═══════════════════════════════════════════════════"
echo "  AURA PLATFORM - DIAGNÓSTICO DEL SISTEMA"
echo "═══════════════════════════════════════════════════"
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para mostrar OK o ERROR
check_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $2"
    else
        echo -e "${RED}✗${NC} $2"
    fi
}

echo "📋 1. INFORMACIÓN DEL SISTEMA"
echo "─────────────────────────────────────────────────"
echo "Sistema Operativo: $(lsb_release -d | cut -f2)"
echo "Hostname: $(hostname)"
echo "IP: $(hostname -I | awk '{print $1}')"
echo ""

echo "📦 2. VERSIONES DE SOFTWARE"
echo "─────────────────────────────────────────────────"
nginx -v 2>&1 | head -1
php --version | head -1
mysql --version 2>/dev/null || echo "MySQL/MariaDB no encontrado en PATH"
echo ""

echo "🔧 3. ESTADO DE SERVICIOS"
echo "─────────────────────────────────────────────────"

# Nginx
systemctl is-active --quiet nginx
check_status $? "Nginx"

# PHP-FPM
systemctl is-active --quiet php8.2-fpm
check_status $? "PHP-FPM 8.2"

# Verificar socket PHP-FPM
if [ -S /var/run/php/php8.2-fpm.sock ]; then
    echo -e "${GREEN}✓${NC} Socket PHP-FPM existe: /var/run/php/php8.2-fpm.sock"
    ls -l /var/run/php/php8.2-fpm.sock
else
    echo -e "${RED}✗${NC} Socket PHP-FPM NO existe"
fi
echo ""

echo "🌐 4. PUERTOS ABIERTOS"
echo "─────────────────────────────────────────────────"
sudo netstat -tlnp 2>/dev/null | grep -E ':(80|7474|3306|443)' || sudo ss -tlnp | grep -E ':(80|7474|3306|443)'
echo ""

echo "📁 5. ESTRUCTURA DEL PROYECTO"
echo "─────────────────────────────────────────────────"
if [ -d ~/aura ]; then
    echo -e "${GREEN}✓${NC} Directorio ~/aura existe"
    echo "Tamaño: $(du -sh ~/aura 2>/dev/null | cut -f1)"
    
    # Verificar directorios clave
    [ -d ~/aura/public ] && echo -e "${GREEN}✓${NC} public/" || echo -e "${RED}✗${NC} public/"
    [ -d ~/aura/core ] && echo -e "${GREEN}✓${NC} core/" || echo -e "${RED}✗${NC} core/"
    [ -d ~/aura/storage ] && echo -e "${GREEN}✓${NC} storage/" || echo -e "${RED}✗${NC} storage/"
    [ -f ~/aura/.env ] && echo -e "${GREEN}✓${NC} .env" || echo -e "${YELLOW}⚠${NC} .env (no existe)"
    [ -f ~/aura/public/index.php ] && echo -e "${GREEN}✓${NC} public/index.php" || echo -e "${RED}✗${NC} public/index.php"
else
    echo -e "${RED}✗${NC} Directorio ~/aura NO existe"
fi
echo ""

echo "🔐 6. PERMISOS"
echo "─────────────────────────────────────────────────"
if [ -d ~/aura ]; then
    echo "Propietario de ~/aura:"
    ls -ld ~/aura
    echo ""
    echo "Permisos de storage/:"
    ls -ld ~/aura/storage 2>/dev/null || echo "storage/ no existe"
fi
echo ""

echo "🗄️  7. CONEXIÓN A BASE DE DATOS"
echo "─────────────────────────────────────────────────"
if [ -f ~/aura/.env ]; then
    DB_HOST=$(grep DB_HOST ~/aura/.env | cut -d'=' -f2)
    DB_USER=$(grep DB_USERNAME ~/aura/.env | cut -d'=' -f2)
    DB_PASS=$(grep DB_PASSWORD ~/aura/.env | cut -d'=' -f2)
    DB_NAME=$(grep DB_DATABASE ~/aura/.env | cut -d'=' -f2)
    
    echo "Host: $DB_HOST"
    echo "Usuario: $DB_USER"
    echo "Base de datos: $DB_NAME"
    echo ""
    echo "Probando conexión..."
    
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT VERSION();" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓${NC} Conexión exitosa"
        echo "Bases de datos de tenants:"
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SHOW DATABASES LIKE 'tenant_%';" 2>/dev/null
    else
        echo -e "${RED}✗${NC} Error de conexión"
    fi
else
    echo -e "${YELLOW}⚠${NC} Archivo .env no encontrado"
fi
echo ""

echo "📝 8. CONFIGURACIÓN DE NGINX"
echo "─────────────────────────────────────────────────"
if [ -f /etc/nginx/conf.d/aura.conf ]; then
    echo -e "${GREEN}✓${NC} /etc/nginx/conf.d/aura.conf existe"
    echo "Puerto configurado:"
    grep "listen" /etc/nginx/conf.d/aura.conf | head -1
    echo "Server name:"
    grep "server_name" /etc/nginx/conf.d/aura.conf | head -1
    echo "Root:"
    grep "root" /etc/nginx/conf.d/aura.conf | head -1
elif [ -f /etc/nginx/sites-available/aura ]; then
    echo -e "${GREEN}✓${NC} /etc/nginx/sites-available/aura existe"
    echo "Puerto configurado:"
    grep "listen" /etc/nginx/sites-available/aura | head -1
else
    echo -e "${RED}✗${NC} Configuración de Nginx no encontrada"
fi
echo ""

echo "🔍 9. ÚLTIMOS ERRORES"
echo "─────────────────────────────────────────────────"
echo "Nginx (últimas 5 líneas):"
sudo tail -5 /var/log/nginx/aura_error.log 2>/dev/null || echo "Log no encontrado"
echo ""
echo "PHP-FPM (últimas 5 líneas):"
sudo journalctl -u php8.2-fpm -n 5 --no-pager 2>/dev/null || echo "Log no disponible"
echo ""

echo "🧪 10. PRUEBA DE CONECTIVIDAD"
echo "─────────────────────────────────────────────────"
echo "Probando acceso local al servidor..."
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:7474/ 2>/dev/null)
if [ "$RESPONSE" = "200" ]; then
    echo -e "${GREEN}✓${NC} HTTP 200 - Servidor responde correctamente"
elif [ "$RESPONSE" = "502" ]; then
    echo -e "${RED}✗${NC} HTTP 502 - Bad Gateway (PHP-FPM no responde)"
elif [ "$RESPONSE" = "000" ]; then
    echo -e "${RED}✗${NC} No se puede conectar al servidor"
else
    echo -e "${YELLOW}⚠${NC} HTTP $RESPONSE"
fi
echo ""

echo "═══════════════════════════════════════════════════"
echo "  FIN DEL DIAGNÓSTICO"
echo "═══════════════════════════════════════════════════"
echo ""
echo "💡 Siguiente paso:"
echo "   Si hay errores, copia esta salida y consulta la"
echo "   sección de Troubleshooting en Instalar.md"
echo ""
