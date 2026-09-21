# Apache PHP - Configuración con versión configurable

## Cambiar versión de PHP

Edita **únicamente** el archivo `.env`:

```
PHP_VERSION=8.5
```

Luego reconstruye:

```bash
docker compose down && ./deploy.sh
```

---

El script construye la imagen, levanta el contenedor y verifica que Apache esté corriendo.

---

## Estructura de archivos de configuración

| Archivo local | Destino en el contenedor |
|---|---|
| `./htdocs/` | `/var/www/html` |
| `./config/php/php.ini` | `/usr/local/etc/php/php.ini` |
| `./config/apache/000-default.conf` | `/etc/apache2/sites-available/000-default.conf` |

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

### Problemas de permisos en htdocs
```bash
docker exec -it php-apache ls -la /var/www/html
```

### Verificar configuración de Apache
```bash
docker exec php-apache apache2ctl -t
```

---

## Recursos

- [Imágenes Docker PHP](https://hub.docker.com/_/php)
- [Documentación PHP](https://www.php.net/docs.php)
- [Documentación Apache](https://httpd.apache.org/docs/)
