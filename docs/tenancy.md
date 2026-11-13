# Multi-tenancy guide

The application uses a **single database with shared tables**, isolating tenants by the
`company_id` column. A tenant is a `App\Models\Company`.

## Core rules

1. **Never read `request()->user()->company_id` to scope a query.** The active tenant lives in
   `App\Support\Tenancy\TenantContext`.
2. A tenant-owned model must use `App\Models\Concerns\BelongsToTenant` and its table must have a
   `company_id` column.
3. The trait adds the global `TenantScope`:
   - with a tenant: `where company_id = ?`
   - without a tenant: **no rows** (fail closed) and a warning
   - `TENANCY_STRICT=true`: throws `TenantNotResolvedException` (enabled in tests)
4. The trait fills `company_id` on create and refuses to move a record between tenants.

```php
use App\Models\Concerns\BelongsToTenant;

class Invoice extends Model
{
    use BelongsToTenant;
}
```

## Cross-tenant operations

Only platform tooling may cross tenants:

```php
Invoice::withoutTenant()->where('company_id', $companyId)->get();

app(TenantContext::class)->run($companyId, function (): void {
    // every scoped query in this closure runs as that company
});
```

Never expose `withoutTenant()` on a request path that a tenant user can reach.

## HTTP requests

`App\Http\Middleware\ResolveTenant` is appended to the `web` and `api` groups and to the
Filament middleware stacks. It:

1. resolves the signed-in user from the `web` or `portal` guard,
2. validates the session company (`tenancy.company_id`) against
   `company_user` membership,
3. falls back to `users.company_id`, then the first membership,
4. for guests, resolves the company from `companies.domain` (or
   `tenancy.public_hosts` / `tenancy.public_company_id`).

The context is cleared in a `finally` block, so Octane workers never leak a tenant.

### Switching companies

`POST`/Livewire `CompanySwitcher` validates membership, updates `users.company_id` (last active)
and the session key. Every panel reads the same session, so a switch in `/app` also
applies to Filament.

## Queues

`TenancyServiceProvider` attaches `company_id` to every queue payload via
`Queue::createPayloadUsing()` and restores it in `JobProcessing`, clearing it in
`JobProcessed` / `JobFailed` / `JobExceptionOccurred`. Jobs do not need per-class changes.

## Console commands

Use `App\Console\Concerns\RunsForTenants`:

```php
$this->forTenantOption(function ($company): void {
    // runs for --company=ID, or once per company
});
```

Commands that operate on a single known record (for example a payroll) should resolve the record
with `withoutTenant()` and then call `TenantContext::set($record->company_id)`.

## Public and integration surfaces

- Unauthenticated tenant pages require a mapped host (`companies.domain`,
  `tenancy.public_hosts`, or `TENANCY_PUBLIC_COMPANY_ID`).
- `contis/*` requires the `X-Company-Key` header. Generate keys with
  `php artisan tenancy:api-key {company}` (adds only the hash; the plain key is shown once).
- Printing routes (`nosa`, `sr`, `timesheet`, `payslip`) require an authenticated guard.

## Cache and storage

- Use `App\Support\Tenancy\TenantCache::remember()/forget()` for tenant data.
- Media is stored under `companies/{company_id}/{media_id}`. Run
  `php artisan tenancy:backfill-media` once after deploying the path generator.

## Adding a new tenant table

1. Add a nullable, indexed `company_id` in a migration.
2. Backfill existing rows from the parent record (`employee_id`, `office_id`, …).
3. Add `BelongsToTenant` to the model.
4. Add a test proving cross-tenant reads return nothing and writes are rejected.
5. Run `php artisan tenancy:audit` — it reports rows without `company_id` and models missing
   the trait.

Tables and models that are intentionally global (statutory rate tables, platform users) are
listed in `config/tenancy.php` under `global_tables` and `unscoped_models`.

## Tests

`Tests\TestCase` clears the context before each test and sets it when `actingAs()` is used.
Create records for another company inside `TenantContext::run($id, ...)`:

```php
$other = app(TenantContext::class)->run(2, fn () => Invoice::create([...]));
```
