#!/bin/bash

###############################################################################
# AURA PLATFORM - REPARADOR DE PAQUETES ROTOS
# 
# Este script limpia paquetes rotos que pueden quedar después de 
# desinstalar phpMyAdmin o por conflictos de dependencias PHP.
###############################################################################

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   AURA PLATFORM - REPARADOR DE PAQUETES                 ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

echo "🔍 Detectando paquetes rotos..."
echo ""

# Verificar estado de dpkg
BROKEN_COUNT=$(dpkg -l | grep -c "^iF" 2>/dev/null || echo "0")

if [ "$BROKEN_COUNT" -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Se encontraron $BROKEN_COUNT paquetes con problemas${NC}"
else
    echo -e "${GREEN}✅ No se detectaron paquetes rotos${NC}"
fi

# Buscar paquetes LiteSpeed problemáticos
LITESPEED_PACKAGES=$(dpkg -l | grep -E "php.*litespeed" | awk '{print $2}' 2>/dev/null || true)

if [ ! -z "$LITESPEED_PACKAGES" ]; then
    echo ""
    echo -e "${YELLOW}📦 Paquetes LiteSpeed encontrados:${NC}"
    echo "$LITESPEED_PACKAGES"
    echo ""
    read -p "¿Deseas eliminar estos paquetes? (s/n): " REMOVE_LITESPEED
    
    if [ "$REMOVE_LITESPEED" = "s" ] || [ "$REMOVE_LITESPEED" = "S" ]; then
        echo ""
        echo "🗑️  Eliminando paquetes LiteSpeed..."
        
        for pkg in $LITESPEED_PACKAGES; do
            echo "   Eliminando: $pkg"
            
            # Detener servicio si existe
            sudo systemctl stop "$pkg" 2>/dev/null || true
            sudo systemctl disable "$pkg" 2>/dev/null || true
            
            # Intentar eliminación normal
            sudo apt-get remove --purge -y "$pkg" 2>/dev/null || true
            
            # Forzar eliminación si es necesario
            sudo dpkg --remove --force-remove-reinstreq "$pkg" 2>/dev/null || true
            sudo dpkg --purge --force-remove-reinstreq "$pkg" 2>/dev/null || true
        done
        
        echo -e "${GREEN}   ✅ Paquetes LiteSpeed eliminados${NC}"
    fi
fi

# Configurar paquetes pendientes
echo ""
echo "🔧 Configurando paquetes pendientes..."
sudo dpkg --configure -a

# Forzar corrección de dependencias
echo ""
echo "🔧 Corrigiendo dependencias rotas..."
sudo apt-get install -f -y

# Limpiar paquetes huérfanos
echo ""
echo "🧹 Eliminando paquetes huérfanos..."
sudo apt-get autoremove -y

# Limpiar caché
echo ""
echo "🧹 Limpiando caché de apt..."
sudo apt-get clean

echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   ✅ REPARACIÓN COMPLETADA                               ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

# Verificar estado final
FINAL_BROKEN=$(dpkg -l | grep -c "^iF" 2>/dev/null || echo "0")

if [ "$FINAL_BROKEN" -eq 0 ]; then
    echo -e "${GREEN}✅ Sistema limpio - No hay paquetes rotos${NC}"
else
    echo -e "${YELLOW}⚠️  Aún quedan $FINAL_BROKEN paquetes con problemas${NC}"
    echo ""
    echo "Si el problema persiste, ejecuta:"
    echo "  sudo dpkg --list | grep '^iF'"
    echo "  sudo dpkg --purge --force-all <nombre_paquete>"
fi

echo ""
