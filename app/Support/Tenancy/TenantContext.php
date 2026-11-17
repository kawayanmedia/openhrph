<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Closure;

final class TenantContext
{
    private ?int $tenantId = null;

    private ?Company $company = null;

    /**
     * Set the current tenant.
     */
    public function set(Company|int $tenant): void
    {
        $id = $tenant instanceof Company ? (int) $tenant->getKey() : (int) $tenant;

        if ($this->tenantId === $id) {
            return;
        }

        $this->tenantId = $id;
        $this->company = $tenant instanceof Company ? $tenant : null;
    }

    /**
     * The current tenant id, if one has been resolved.
     */
    public function id(): ?int
    {
        return $this->tenantId;
    }

    /**
     * Whether a tenant has been resolved.
     */
    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * The current tenant model.
     */
    public function company(): ?Company
    {
        if ($this->tenantId === null) {
            return null;
        }

        return $this->company ??= Company::query()->find($this->tenantId);
    }

    /**
     * Run a callback inside the given tenant, restoring the previous context.
     *
     * @template TReturn
     *
     * @param  Closure(Company|null): TReturn  $callback
     * @return TReturn
     */
    public function run(Company|int $tenant, Closure $callback): mixed
    {
        $previousId = $this->tenantId;
        $previousCompany = $this->company;

        $this->set($tenant);

        try {
            return $callback($this->company);
        } finally {
            $this->tenantId = $previousId;
            $this->company = $previousCompany;
        }
    }

    /**
     * Forget the current tenant. Called between requests by middleware.
     */
    public function forget(): void
    {
        $this->tenantId = null;
        $this->company = null;
    }
}
