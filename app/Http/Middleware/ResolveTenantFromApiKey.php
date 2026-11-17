<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromApiKey
{
    /**
     * Resolve the tenant from a hashed company API key.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Company-Key');

        if ($key === null || $key === '') {
            abort(401, 'Missing company API key.');
        }

        $company = Company::query()->where('api_key', hash('sha256', $key))->first();

        abort_unless($company !== null, 401, 'Invalid company API key.');

        try {
            app(TenantContext::class)->set($company);

            return $next($request);
        } finally {
            app(TenantContext::class)->forget();
        }
    }
}
