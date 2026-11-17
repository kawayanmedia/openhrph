<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Illuminate\Database\QueryException;

class HostTenantResolver
{
    /**
     * Resolve the tenant from the request host.
     */
    public function resolve(?string $host = null): ?Company
    {
        $host = strtolower($host ?? request()->getHost());

        $mapped = config('tenancy.public_hosts', [])[$host] ?? null;

        try {
            if ($mapped !== null) {
                return Company::query()->find($mapped);
            }

            $company = Company::query()->where('domain', $host)->first();

            if ($company !== null) {
                return $company;
            }

            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $publicCompanyId = config('tenancy.public_company_id');

            if ($appHost !== null && strtolower($appHost) === $host && $publicCompanyId !== null) {
                return Company::query()->find($publicCompanyId);
            }

            return null;
        } catch (QueryException) {
            return null;
        }
    }
}
