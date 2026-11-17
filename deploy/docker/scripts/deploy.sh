#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCKER_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$DOCKER_DIR"

BUILD=1
TLS=0
REVERB=0

for arg in "$@"; do
    case "$arg" in
        --no-build) BUILD=0 ;;
        --tls) TLS=1 ;;
        --reverb) REVERB=1 ;;
        *) echo "Unknown argument: $arg" >&2; exit 1 ;;
    esac
done

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker is required." >&2
    exit 1
fi

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created deploy/docker/.env from .env.example"
fi
chmod 0600 .env

env_value() {
    grep -E "^${1}=" .env | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

if [ -z "$(env_value APP_KEY)" ]; then
    key="base64:$(openssl rand -base64 32 | tr -d '\n')"
    sed "s|^APP_KEY=.*|APP_KEY=${key}|" .env > .env.tmp
    mv .env.tmp .env
    chmod 0600 .env
    echo "Generated a new APP_KEY in deploy/docker/.env"
fi

if [ "$(env_value DB_PASSWORD)" = "change-me-before-deploying" ] || [ "$(env_value DB_ROOT_PASSWORD)" = "change-root-before-deploying" ]; then
    echo "WARNING: change DB_PASSWORD and DB_ROOT_PASSWORD in deploy/docker/.env before exposing this stack." >&2
fi

if [ "$TLS" -eq 1 ] && [ -z "$(env_value HRIS_DOMAIN)" ]; then
    echo "--tls requires HRIS_DOMAIN in deploy/docker/.env" >&2
    exit 1
fi

compose_files=(-f compose.yaml)
if [ "$TLS" -eq 1 ]; then
    compose_files+=(-f compose.tls.yaml)
fi

profile_args=()
if [ "$REVERB" -eq 1 ]; then
    profile_args+=(--profile reverb)
fi

if [ "$BUILD" -eq 1 ]; then
    docker compose "${compose_files[@]}" build --pull
fi

if [ "${#profile_args[@]}" -gt 0 ]; then
    docker compose "${compose_files[@]}" "${profile_args[@]}" up -d --remove-orphans
else
    docker compose "${compose_files[@]}" up -d --remove-orphans
fi

http_port="$(env_value HTTP_PORT)"
http_port="${http_port:-8080}"

if [ "$TLS" -eq 1 ]; then
    health_url="https://$(env_value HRIS_DOMAIN)/up"
else
    health_url="http://127.0.0.1:${http_port}/up"
fi

echo "Waiting for $health_url"
for _ in $(seq 1 60); do
    if curl -fsS "$health_url" >/dev/null 2>&1; then
        echo "HRIS is healthy at $health_url"
        echo "Create the first administrator with:"
        echo "  docker compose exec -u www-data app php artisan admin:create"
        exit 0
    fi
    sleep 5
done

echo "Health check did not pass. Inspect the stack with:" >&2
echo "  docker compose ps" >&2
echo "  docker compose logs --tail=200 web app init" >&2
exit 1
