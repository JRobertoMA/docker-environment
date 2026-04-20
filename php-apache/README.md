# Apache PHP - Configuración con versión configurable

## Cambiar versión de PHP

Edita **únicamente** el archivo `.env`:

```
PHP_VERSION=8.6
```

Luego reconstruye:

```bash
docker compose down && ./deploy.sh
```

---

## Inicio rápido

```bash
./deploy.sh
```

El script construye la imagen, levanta el contenedor y verifica que Apache esté corriendo.

---

## Comandos del día a día

```bash
# Ver logs en vivo
docker compose logs -f

# Detener
docker compose down

# Reiniciar
docker compose restart

# Shell del contenedor
docker exec -it php-apache bash

# Versión de PHP activa
docker exec php-apache php -v

# Extensiones instaladas
docker exec php-apache php -m
```

---

## Estructura de archivos de configuración

| Archivo local | Destino en el contenedor |
|---|---|
| `./htdocs/` | `/var/www/html` |
| `./config/php/php.ini` | `/usr/local/etc/php/php.ini` |
| `./config/apache/000-default.conf` | `/etc/apache2/sites-available/000-default.conf` |
| `./config/apache/apache2.conf` | `/etc/apache2/conf-available/allowoverride.conf` |

---

## Recursos asignados

| Recurso | Valor |
|---|---|
| Puerto | `81` (host) → `80` (contenedor) |
| RAM máxima | 768 MB |
| RAM reservada | 256 MB |
| CPU shares | 90 |

---

## Extensiones PHP incluidas

`bcmath` `curl` `fileinfo` `gd` `imagick` `intl` `mbstring` `memcached` `mysqli` `opcache` `pdo_mysql` `soap` `xml` `zip`

---

## Solución de problemas

### El contenedor no inicia
```bash
docker compose logs
```

### Puerto ya en uso
Detén el contenedor anterior antes de levantar uno nuevo:
```bash
docker stop <nombre-contenedor-viejo> && docker rm <nombre-contenedor-viejo>
docker compose up -d
```

### Problemas de permisos en htdocs
```bash
docker exec -it php-apache ls -la /var/www/html
```

### Verificar configuración de Apache
```bash
docker exec php-apache apache2ctl -t
```

### Ver phpinfo
Crea `htdocs/info.php`:
```php
<?php phpinfo();
```
Luego abre `http://localhost:81/info.php`

---

## Recursos

- [Imágenes Docker PHP](https://hub.docker.com/_/php)
- [Documentación PHP](https://www.php.net/docs.php)
- [Documentación Apache](https://httpd.apache.org/docs/)
