#!/bin/bash
# Salir inmediatamente si un comando falla
set -e

# ---- TAREAS DE EJECUCIÓN ----

echo "🔧 Configurando permisos para /var/www/html..."

# 1. Aplicar la propiedad
echo "📁 Asegurando propiedad www-data:www-data..."
# Usamos find para cambiar solo lo necesario (más rápido en reinicios)
find /var/www/html \( ! -user www-data -o ! -group www-data \) -exec chown www-data:www-data {} +

# 2. Aplicar permisos de forma segura
echo "🔒 Configurando permisos (755 para directorios, 644 para archivos)..."
find /var/www/html -type d -exec chmod 755 {} \; 2>/dev/null || chmod -R 755 /var/www/html
find /var/www/html -type f -exec chmod 644 {} \; 2>/dev/null || true

# 3. Configurar ACLs solo si están disponibles
if command -v setfacl &> /dev/null; then
    echo "📝 Configurando ACLs para herencia de permisos..."
    setfacl -R -d -m u::rwx,g::rwx,o::rx /var/www/html 2>/dev/null || echo "⚠️  ACLs no disponibles en este sistema de archivos"
    setfacl -R -m u::rwx,g::rwx,o::rx /var/www/html 2>/dev/null || true
    
    # 4. Activar el bit setgid para la herencia del grupo
    chmod g+s /var/www/html 2>/dev/null || true
    echo "✅ ACLs configuradas correctamente"
else
    echo "⚠️  setfacl no disponible, usando solo permisos estándar"
fi

echo "✅ Configuración de permisos finalizada"
echo ""

# 5. Ejecutar el comando original del contenedor (CMD)
exec "$@"
