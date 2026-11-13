# Docker deployment

This directory contains the self-hosted Docker Compose deployment for HRIS.

- `Dockerfile` builds one runtime image with PHP-FPM, Nginx, the application, Vite assets, and the Go payroll binary.
- `compose.yaml` runs the web proxy, PHP-FPM, queue worker, scheduler, optional Reverb server, and MySQL 8.4.
- `compose.tls.yaml` adds Caddy with automatic HTTPS.
- `scripts/` contains the build, deploy, backup, and destroy wrappers.

Start with [Docker deployment](../../docs/deployment/docker.md).

```bash
cp .env.example .env
# Set APP_URL, DB_PASSWORD, DB_ROOT_PASSWORD, and any mail/AI keys.
./scripts/deploy.sh
```

The stack listens on `http://localhost:8080` by default. The first boot applies the migrations and imports the statutory tables, wage orders, and holiday calendar. Create the first administrator with:

```bash
docker compose exec -u www-data app php artisan admin:create
```
