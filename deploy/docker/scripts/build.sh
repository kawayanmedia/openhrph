#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCKER_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ROOT_DIR="$(cd "$DOCKER_DIR/../.." && pwd)"

env_value() {
    local key="$1"
    local file="$DOCKER_DIR/.env"

    if [ -f "$file" ]; then
        grep -E "^${key}=" "$file" | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
    fi
}

APP_IMAGE="${APP_IMAGE:-$(env_value APP_IMAGE)}"
APP_IMAGE="${APP_IMAGE:-hris:local}"
APP_VERSION="${APP_VERSION:-$(git -C "$ROOT_DIR" rev-parse --short HEAD 2>/dev/null || echo dev)}"
PLATFORM="${PLATFORM:-linux/amd64}"
PUSH=0

for arg in "$@"; do
    case "$arg" in
        --push) PUSH=1 ;;
        --platform=*) PLATFORM="${arg#*=}" ;;
        *) echo "Unknown argument: $arg" >&2; exit 1 ;;
    esac
done

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker is required." >&2
    exit 1
fi

echo "Building $APP_IMAGE ($PLATFORM, version $APP_VERSION)"

if docker buildx version >/dev/null 2>&1; then
    build_args=(
        buildx build
        --platform "$PLATFORM"
        --build-arg "APP_VERSION=$APP_VERSION"
        --file "$DOCKER_DIR/Dockerfile"
        --tag "$APP_IMAGE"
    )

    if [ "$PUSH" -eq 1 ]; then
        build_args+=(--push)
    else
        build_args+=(--load)
    fi

    docker "${build_args[@]}" "$ROOT_DIR"
elif [ "$PUSH" -eq 1 ]; then
    echo "docker buildx is required to push a multi-platform image." >&2
    exit 1
else
    docker build \
        --build-arg "APP_VERSION=$APP_VERSION" \
        --file "$DOCKER_DIR/Dockerfile" \
        --tag "$APP_IMAGE" \
        "$ROOT_DIR"
fi

echo "Built $APP_IMAGE"
