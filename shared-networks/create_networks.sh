#!/bin/bash
set -e

create_net() {
  local name=$1
  local subnet=$2
  if ! docker network inspect "$name" >/dev/null 2>&1; then
    docker network create "$name" --subnet "$subnet"
    echo "✓ Red creada: $name ($subnet)"
  else
    echo "→ Ya existe: $name"
  fi
}

create_net infra_net 172.30.20.0/24
create_net media_net 172.30.10.0/24
create_net dev_net 172.30.30.0/24
create_net monitor_net 172.30.40.0/24