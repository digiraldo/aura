# Scripts de Instalación/Desinstalación de Aura Platform

Este documento describe cómo usar los scripts automatizados para instalar o desinstalar Aura Platform en tu servidor Linux.

## 📋 Requisitos Previos

Antes de ejecutar los scripts, asegúrate de tener instalado:

- ✅ Debian/Ubuntu Linux
- ✅ Nginx
- ✅ PHP 8.2 con extensiones: `php8.2-fpm`, `php8.2-mysql`, `php8.2-xml`, `php8.2-mbstring`, `php8.2-curl`, `php8.2-zip`
- ✅ MariaDB Server
- ✅ Git

**Nota:** Ya **NO necesitas** clonar el proyecto manualmente. El script `install.sh` lo clonará automáticamente si no existe.

Si aún no tienes el stack instalado, sigue la **Fase 2** del archivo `Instalar.md`.

---

## 🔄 Reinstalación Completa (Recomendado)

Si ya tienes Aura instalado y quieres empezar de cero, sigue estos pasos:

### Paso 1: Desinstalar versión anterior

```bash
cd ~/aura
chmod +x uninstall.sh
./uninstall.sh
```

El script te pedirá:
- Contraseña de MySQL root
- Confirmación escribiendo `SI ELIMINAR`
- Si deseas eliminar el directorio `~/aura` completamente

Esto eliminará:
- ✅ Todas las bases de datos (aura_master, tenant_*)
- ✅ Usuario de base de datos `aura_admin`
- ✅ Configuraciones de Nginx
- ✅ Logs de la aplicación

**💡 Importante:** Si eliges eliminar el directorio `~/aura`, el script copiará automáticamente `install.sh` y `uninstall.sh` a tu directorio home (`~/`).

### Paso 2: Instalar versión limpia

Después de desinstalar, ejecuta:

```bash
~/install.sh
```

**El script automáticamente:**
- 🔄 Clonará el repositorio en `~/aura` si no existe
- 📋 Actualizará los scripts en `~/install.sh` y `~/uninstall.sh`
- ▶️ Iniciará el proceso de instalación interactivo

El script te preguntará de forma interactiva:

1. **Contraseña de MySQL root** (para configurar la base de datos)
2. **Email del administrador** (ej: `admin@tuempresa.com`)
3. **Usuario del administrador** (ej: `admin`, por defecto si presionas Enter)
4. **Contraseña del administrador** (mínimo 8 caracteres)
5. **Confirmación de contraseña**
6. **Nombre del tenant** (ej: `empresa_demo`, solo minúsculas y guiones bajos)

El script realizará automáticamente:

✅ Configuración del archivo `.env`  
✅ Creación del usuario de base de datos `aura_admin`  
✅ Instalación de la base de datos master  
✅ Corrección de configuración de sesiones (HTTP)  
✅ Creación del tenant con tus credenciales personalizadas  
✅ Configuración de Nginx en el puerto 7474  
✅ Verificación de autenticación  
✅ Configuración de `/etc/hosts` en el servidor  

---

## 🎯 Primera Instalación

Si es tu primera vez instalando Aura Platform, tienes dos opciones:

### Opción A: Usando el script directamente (Recomendado)

Descarga y ejecuta el script:

```bash
cd ~
wget https://raw.githubusercontent.com/digiraldo/aura/main/install.sh
chmod +x install.sh
./install.sh
```

El script **clonará automáticamente** el repositorio si no existe en `~/aura`.

### Opción B: Clonando el repositorio primero

```bash
cd ~
git clone https://github.com/digiraldo/aura.git
cd ~/aura
chmod +x install.sh
./install.sh
```

Ambas opciones te guiarán con instrucciones interactivas.

---

## 📦 Flujo de Trabajo: Desinstalación → Reinstalación

Cuando ejecutas `uninstall.sh` y eliminas el directorio `~/aura`, el proceso es el siguiente:

1. **Desinstalación** (`./uninstall.sh`):
   ```bash
   cd ~/aura
   ./uninstall.sh
   # Elige "s" para eliminar el directorio completamente
   ```
   
   Resultado:
   - 🗑️ Directorio `~/aura` eliminado
   - 📋 Scripts copiados a `~/install.sh` y `~/uninstall.sh`

2. **Reinstalación** (`~/install.sh`):
   ```bash
   ~/install.sh
   ```
   
   El script automáticamente:
   - 🔍 Detecta que `~/aura` no existe
   - 📥 Clona el repositorio: `git clone https://github.com/digiraldo/aura.git ~/aura`
   - 🔄 Actualiza los scripts en `~/` con las versiones del repositorio
   - ▶️ Continúa con la instalación normal

**💡 Ventaja:** Siempre obtendrás la versión más reciente del código al reinstalar.

---

## 🌐 Configurar Acceso desde tu PC Windows

Después de ejecutar `install.sh`, sigue estos pasos en tu PC Windows:

### 1. Abrir PowerShell como Administrador

- Click derecho en "Inicio"
- Selecciona "Terminal (Administrador)" o "Windows PowerShell (Administrador)"

### 2. Editar archivo hosts

```powershell
notepad C:\Windows\System32\drivers\etc\hosts
```

### 3. Agregar las siguientes líneas al final

Reemplaza `<IP_DEL_SERVIDOR>` con la IP que te mostró el script:

```
<IP_DEL_SERVIDOR>    aura.local
<IP_DEL_SERVIDOR>    <NOMBRE_TENANT>.aura.local
```

Ejemplo:
```
192.168.68.20    aura.local
192.168.68.20    empresa_demo.aura.local
```

### 4. Limpiar caché DNS

```powershell
ipconfig /flushdns
```

### 5. Acceder desde el navegador

Abre tu navegador y ve a:

```
http://<NOMBRE_TENANT>.aura.local:7474/
```

Ejemplo:
```
http://empresa_demo.aura.local:7474/
```

Inicia sesión con las credenciales que configuraste durante la instalación.

---

## 🛠️ Solución de Problemas

### El script falla en "Verificando usuario en base de datos"

**Causa:** El usuario no se creó correctamente en la base de datos del tenant.

**Solución:**
```bash
# Verificar manualmente
mysql -u aura_admin -pAdmin1234 -D tenant_<NOMBRE_TENANT> -e "SHOW TABLES;"
mysql -u aura_admin -pAdmin1234 -D tenant_<NOMBRE_TENANT> -e "SELECT * FROM usuarios;"
```

### No puedo conectarme desde el navegador Windows

**Causa:** El archivo hosts no se configuró correctamente.

**Solución:**
1. Verifica que editaste el archivo hosts **como Administrador**
2. Verifica que las líneas se agregaron correctamente
3. Ejecuta `ipconfig /flushdns` en PowerShell
4. Prueba con `ping <NOMBRE_TENANT>.aura.local` desde CMD

### El login no funciona, me devuelve a /login

**Causa:** Las cookies de sesión tienen problemas.

**Solución:**
1. Cierra TODOS los navegadores completamente
2. Borra las cookies del sitio (F12 → Application → Cookies)
3. Abre el navegador nuevamente e intenta iniciar sesión

Si aún no funciona, verifica:
```bash
grep "cookie_secure" ~/aura/core/lib/Bootstrap.php
```

Debe decir `'0'`, no `'1'`.

### Error "Access denied for user 'aura_admin'"

**Causa:** El usuario de base de datos no se creó correctamente.

**Solución:**
```bash
# Volver a ejecutar solo la creación del usuario
mysql -u root -p<CONTRASEÑA_ROOT> <<EOF
DROP USER IF EXISTS 'aura_admin'@'localhost';
DROP USER IF EXISTS 'aura_admin'@'%';
CREATE USER 'aura_admin'@'localhost' IDENTIFIED BY 'Admin1234';
CREATE USER 'aura_admin'@'%' IDENTIFIED BY 'Admin1234';
GRANT ALL PRIVILEGES ON *.* TO 'aura_admin'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'aura_admin'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF
```

---

## 📝 Logs Útiles

Si algo falla, revisa estos logs:

```bash
# Logs de Nginx
sudo tail -50 /var/log/nginx/aura_error.log

# Logs de PHP-FPM
sudo journalctl -u php8.2-fpm -n 50

# Verificar servicios
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mariadb
```

---

## 🔒 Seguridad

**⚠️ IMPORTANTE para producción:**

1. **Cambiar contraseña por defecto del usuario admin**
2. **Usar HTTPS en producción:**
   - Cambiar `session.cookie_secure` a `'1'` en `Bootstrap.php`
   - Configurar certificado SSL
3. **Cambiar contraseña de base de datos** de `Admin1234` a algo más seguro
4. **Configurar firewall** para permitir solo puertos necesarios

---

## 📞 Soporte

Si encuentras problemas no cubiertos en esta guía:

1. Revisa el archivo `Instalar.md` completo
2. Ejecuta el script de diagnóstico: `bash diagnostico.sh`
3. Revisa los logs mencionados arriba
4. Abre un issue en GitHub con la salida completa del error

---

## 📄 Licencia

Aura Platform - El WordPress de la Contabilidad  
© 2026 - Todos los derechos reservados
