# Homelab Docker

Colección de stacks de Docker Compose independientes para un homelab en Raspberry Pi 5.

## Redes compartidas

odos los stacks se conectan a redes Docker **externas** (creadas una sola vez, fuera de cualquier `docker-compose.yml`) en vez de definir redes propias. Antes de levantar cualquier servicio, hay que crearlas:

```bash
cd shared-networks && ./create_networks.sh
```

| Red | Subred | Uso |
|---|---|---|
| `infra_net` | 172.30.20.0/24 | Portainer, Home Assistant/Mosquitto, JDownloader2, Transmission |
| `media_net` | 172.30.10.0/24 | Jellyfin, Immich, Deemix |
| `dev_net` | 172.30.30.0/24 | Apache, MariaDB, Adminer |
| `monitor_net` | 172.30.40.0/24 | MySpeed, OpenSpeedTest, ServerBox Monitor |

Dos servicios no usan estas redes porque corren en `network_mode: host`: **crafty-4** (necesita un rango amplio de puertos para servidores de Minecraft) y **homeassistant** (necesita mDNS/discovery en la LAN).
