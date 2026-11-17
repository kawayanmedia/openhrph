#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCKER_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$DOCKER_DIR"

REMOVE_VOLUMES=0
ASSUME_YES=0

for arg in "$@"; do
    case "$arg" in
        --volumes) REMOVE_VOLUMES=1 ;;
        --yes) ASSUME_YES=1 ;;
        *) echo "Unknown argument: $arg" >&2; exit 1 ;;
    esac
done

if [ "$ASSUME_YES" -eq 0 ]; then
    echo "This stops the HRIS containers."
    if [ "$REMOVE_VOLUMES" -eq 1 ]; then
        echo "It also deletes the database and uploaded-file volumes."
    fi
    read -r -p "Type 'hris' to continue: " confirmation
    if [ "$confirmation" != "hris" ]; then
        echo "Aborted."
        exit 1
    fi
fi

compose_files=(-f compose.yaml)
if [ -f compose.tls.yaml ] && [ -n "$(grep -E '^HRIS_DOMAIN=.+' .env 2>/dev/null || true)" ]; then
    compose_files+=(-f compose.tls.yaml)
fi

if [ "$REMOVE_VOLUMES" -eq 1 ]; then
    docker compose "${compose_files[@]}" down --volumes --remove-orphans
else
    docker compose "${compose_files[@]}" down --remove-orphans
fi
