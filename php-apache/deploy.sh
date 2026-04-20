#!/bin/bash
# Script de despliegue rápido para PHP Apache
# Uso: chmod +x deploy.sh && ./deploy.sh

set -e

# Cargar variables de entorno
source "$(dirname "$0")/.env"
CONTAINER_NAME="php-apache"

echo "🐘 Despliegue de PHP ${PHP_VERSION} Apache"
echo "================================"
echo ""

# Colores para mensajes
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verificar que Docker está instalado
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker no está instalado${NC}"
    exit 1
fi

# Verificar que Docker Compose está instalado
if ! command -v docker compose &> /dev/null && ! docker compose version &> /dev/null 2>&1; then
    echo -e "${RED}❌ Docker Compose no está instalado${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Docker y Docker Compose detectados${NC}"
echo ""

# Preguntar si desea reconstruir la imagen
read -p "¿Deseas reconstruir la imagen desde cero? (s/n): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[SsYy]$ ]]; then
    echo -e "${YELLOW}🔨 Construyendo imagen (sin caché)...${NC}"
    docker compose build --no-cache
else
    echo -e "${YELLOW}🔨 Construyendo imagen...${NC}"
    docker compose build
fi

echo ""
echo -e "${GREEN}✅ Imagen construida correctamente${NC}"
echo ""

# Iniciar el contenedor
echo -e "${YELLOW}🚀 Iniciando contenedor...${NC}"
docker compose up -d

echo ""
echo -e "${GREEN}✅ Contenedor iniciado correctamente${NC}"
echo ""

# Esperar unos segundos para que Apache se inicie
echo "⏳ Esperando a que Apache se inicie..."
sleep 3

# Verificar el estado
if docker ps | grep -q "${CONTAINER_NAME}"; then
    echo -e "${GREEN}✅ PHP ${PHP_VERSION} Apache está corriendo${NC}"
    echo ""
    echo "📊 Información del contenedor:"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    docker ps --filter "name=${CONTAINER_NAME}" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
    echo ""
    echo -e "${GREEN}🌐 Accede a tu servidor en: http://localhost:81${NC}"
    echo ""
    echo "📝 Comandos útiles:"
    echo "  • Ver logs:        docker compose logs -f"
    echo "  • Detener:         docker compose down"
    echo "  • Reiniciar:       docker compose restart"
    echo "  • Acceder al bash: docker exec -it ${CONTAINER_NAME} bash"
    echo "  • Ver extensiones: docker exec ${CONTAINER_NAME} php -m"
else
    echo -e "${RED}❌ Hubo un problema al iniciar el contenedor${NC}"
    echo "Ver los logs con: docker compose logs"
    exit 1
fi
