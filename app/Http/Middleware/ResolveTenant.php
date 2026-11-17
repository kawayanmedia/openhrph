<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\HostTenantResolver;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly HostTenantResolver $hosts,
    ) {}

    /**
     * Resolve the tenant for the request and clear it afterwards.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->resolve($request);

            return $next($request);
        } finally {
            $this->tenant->forget();
        }
    }

    private function resolve(Request $request): void
    {
        $user = $this->authenticatedUser();

        if ($user instanceof User) {
            $company = $this->companyFor($request, $user);

            if ($company) {
                $this->tenant->set($company);

                if ($request->hasSession()) {
                    $request->session()->put(config('tenancy.session_key'), $company->getKey());
                }

                return;
            }

            if ($user->company_id !== null) {
                $this->tenant->set((int) $user->company_id);
            }

            return;
        }

        if ($company = $this->hosts->resolve($request->getHost())) {
            $this->tenant->set($company);
        }
    }

    private function authenticatedUser(): ?Authenticatable
    {
        foreach (['web', 'nova', 'portal'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return Auth::guard($guard)->user();
            }
        }

        return null;
    }

    private function companyFor(Request $request, User $user): ?Company
    {
        $companies = $user->companyMemberships();
        $sessionKey = config('tenancy.session_key');
        $requested = $request->hasSession() ? $request->session()->get($sessionKey) : null;

        if ($requested !== null) {
            $company = $companies->firstWhere('id', (int) $requested);

            if ($company) {
                return $company;
            }
        }

        return $companies->firstWhere('id', (int) $user->company_id)
            ?? $companies->first();
    }
}
