# Operations

This guide covers the day-two work for the AWS AMI stack: health, logs, deployments, backups, scaling, and security. The [Docker](docker.md), [GCP](gcp.md), and [Azure](azure.md) guides cover the equivalent commands for those paths.

## Health checks

| Check | Command |
| --- | --- |
| Application health | `curl -fsS https://your-host/up` |
| Nginx | `sudo systemctl status nginx` |
| PHP-FPM | `sudo systemctl status php8.3-fpm` |
| Queue worker | `sudo systemctl status hris-queue` |
| Scheduler | `systemctl list-timers hris-scheduler.timer` |
| Backup timer | `systemctl list-timers hris-backup.timer` |
| Payroll engine | `sudo -u www-data php /var/www/hris/artisan payroll:engine --check` |
| ALB target health | `aws elbv2 describe-target-health --target-group-arn <arn>` |

The ALB target group uses `/up` with a 200 matcher. Keep that route healthy; it is the fastest signal that PHP, the database, and the application can serve traffic.

## Logs

```bash
# Application
sudo tail -f /var/www/hris/storage/logs/laravel.log
sudo tail -f /var/www/hris/storage/logs/laravel-$(date +%F).log

# Bootstrap
sudo tail -f /var/log/hris-bootstrap.log

# Nginx
sudo tail -f /var/log/nginx/hris-access.log
sudo tail -f /var/log/nginx/hris-error.log

# PHP-FPM
sudo journalctl -u php8.3-fpm -f
sudo tail -f /var/log/php-fpm/hris-error.log

# Queue and scheduler
sudo journalctl -u hris-queue -f
sudo journalctl -u hris-scheduler -n 100 --no-pager
```

Logs are on the instance. Add the CloudWatch agent or ship them to your observability platform if you need retention beyond the instance lifecycle.

## Deployments

### Golden image deployment

1. Build a new AMI at the release tag.
2. Set `app_ref` in `terraform.tfvars` to the same tag.
3. Run `terraform apply`.
4. Watch `/var/log/hris-bootstrap.log` and `/up`.
5. Keep the previous AMI until the new instance is healthy.

### In-place deployment

```bash
sudo /usr/local/bin/hris-deploy v1.1.0
```

The helper:

1. Fetches the ref.
2. Installs production Composer dependencies.
3. Rebuilds Vite assets.
4. Rebuilds the Go payroll binary.
5. Runs migrations.
6. Imports the statutory tables and 2026 wage orders.
7. Warms config, event, and view caches.
8. Restarts PHP-FPM, Nginx, and the queue worker.

For zero-downtime releases with an ALB, use an Auto Scaling group with instance refresh or a blue/green target group. The current stack is a single instance and will briefly restart services during an in-place deploy.

## Database operations

### Migrations

Schema changes ship as Laravel migrations in `database/migrations`; there is no SQL dump to import. A new database is created entirely by:

```bash
sudo -u www-data php /var/www/hris/artisan migrate --force
```

Always take a snapshot before a release that changes the schema or payroll logic.

#### What the current migrations add

- **Baseline (`2026_10_01_*`)** — organisations, users and access, employees, attendance, compensation, payroll, contributions, and infrastructure, including the Filament tables.
- **Tenancy and infrastructure (`2026_11_02_*`)** — tenant columns, backfills, `NOT NULL` enforcement, and the sessions, cache, and queue tables.
- **Payroll compliance controls (`2026_11_04_*`)** — payroll revisions, audit events, the year-to-date ledger, the de minimis catalogue, and journal entries.
- **Agent conversations (`2026_11_06_*`)** — conversation and message tables for the AI assistant.
- **2026 payroll controls (`2026_11_10_*`)** — statutory source status, wage-order and industry columns, payroll review/override columns, and nullable global minimum-wage rows.
- **Device credentials (`2026_11_12_*`)** — `api_key`, `is_active`, and `last_seen_at` on `devices` for time-clock pushes.

`php artisan migrate --force` applies all of them; no manual SQL is required.

#### Upgrading an existing database

The migrations are safe on databases created before the SQL dumps were retired: they use `Schema::hasTable` and `Schema::hasColumn` guards, so they create only what is missing. Laravel records applied migrations in the `migrations` table, which it creates on the first run.

If a migration still fails because an object already exists outside the migration history:

1. Back up the database.
2. Inspect the failing migration and confirm its changes are already present.
3. Record it as applied and continue:

```bash
sudo -u www-data php /var/www/hris/artisan tinker --execute 'DB::table("migrations")->insert(["migration" => "2026_10_01_000100_create_organization_tables", "batch" => ((int) DB::table("migrations")->max("batch")) + 1]);'
```

4. Rerun `php artisan migrate --force`.

Never mark a migration as applied unless its tables, columns, and indexes are actually present. Replace the example migration name with the one that failed.

### Reference data after a migration

```bash
sudo -u www-data php /var/www/hris/artisan payroll:statutory:import
sudo -u www-data php /var/www/hris/artisan payroll:wage-orders:import
sudo -u www-data php /var/www/hris/artisan payroll:holidays:import
```

All three imports are idempotent. `payroll:holidays:import` is company-scoped and fails until the first company exists, so run it again after `admin:create` on a fresh installation.

### Statutory table refresh

```bash
sudo -u www-data php /var/www/hris/artisan payroll:statutory:import
sudo -u www-data php /var/www/hris/artisan payroll:statutory:import --fresh
```

`--fresh` deletes all statutory rows before importing. Use it only when you intend to replace the whole schedule.

### Backups and restore

RDS automated backups are enabled. For a manual snapshot:

```bash
aws rds create-db-snapshot \
  --db-instance-identifier hris-production-db \
  --db-snapshot-identifier hris-production-manual-$(date +%Y%m%d%H%M)
```

Restore by creating a new RDS instance from the snapshot, then point `DB_HOST` and credentials at it. Update the SSM parameter and rerun the bootstrap or edit `/var/www/hris/.env` and warm caches.

### Restore uploaded files

```bash
aws s3 sync s3://<bucket>/storage/public /var/www/hris/storage/app/public
sudo chown -R www-data:www-data /var/www/hris/storage/app/public
```

## Queues and schedulers

Payroll runs above `PAYROLL_ENGINE_INLINE_THRESHOLD` are queued. Keep at least one worker running:

```bash
sudo systemctl restart hris-queue
sudo journalctl -u hris-queue -n 200 --no-pager
```

The scheduler runs every minute through `hris-scheduler.timer`:

```bash
sudo systemctl start hris-scheduler.timer
systemctl list-timers hris-scheduler.timer
```

If a deploy changes job code, restart the worker so it picks up the new code.

## Scaling

The current stack is a single EC2 instance. Before adding more instances:

- Move sessions and cache to a shared store (the default is already the database; Redis is a better choice at scale).
- Move media to S3 (`MEDIA_DISK=s3`) or a shared EFS mount.
- Run the queue worker and scheduler on exactly one instance, or move them to a separate worker tier.
- Put the instances behind the ALB and use health checks.
- Use RDS Multi-AZ for the database.
- Consider a separate build/deploy pipeline instead of in-place deploys.

Vertical scaling is the simplest first step: increase `instance_type` and `db_instance_class` and apply.

## Security maintenance

- Patch the AMI monthly or when Ubuntu publishes security updates. Rebuild the image and roll it out.
- Keep `APP_DEBUG=false` in production.
- Keep the RDS instance private.
- Use Session Manager instead of opening SSH. If SSH is required, restrict `ssh_allowed_cidrs`.
- Rotate the database password and `APP_KEY` deliberately. Rotating `APP_KEY` can invalidate encrypted data and sessions.
- Rotate secrets in SSM, then rerun the bootstrap or update `/var/www/hris/.env` and warm caches.
- Keep the Terraform state encrypted and access-controlled.
- Review the [open-source release checklist](../security/open-source-checklist.md) before sharing infrastructure code.

## Incident checklist

1. Check `/up` and the ALB target health.
2. Read the latest Laravel and Nginx errors.
3. Confirm the database is reachable and has free storage.
4. Confirm the queue worker and scheduler are running.
5. Confirm disk space: `df -h`.
6. Confirm the payroll binary exists and is executable.
7. If a release caused the incident, redeploy the previous ref or AMI.
8. Restore from the latest RDS snapshot only after preserving the failed state for investigation.

## Teardown

For a disposable environment, set:

```hcl
deletion_protection = false
skip_final_snapshot = true
s3_force_destroy    = true
```

Then run `terraform destroy`. Deregister unused AMIs and delete old snapshots separately.
