<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Authenticated;

class SetDefaultTenant
{
    /**
     * Remember the user's company for the session after authentication.
     */
    public function handle(Authenticated $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $companies = $event->user->companyMemberships();

        if ($companies->isEmpty()) {
            if ($event->user->company_id !== null) {
                app(TenantContext::class)->set((int) $event->user->company_id);
            }

            return;
        }

        $sessionKey = config('tenancy.session_key');
        $request = request();
        $requested = $request->hasSession() ? $request->session()->get($sessionKey) : null;

        $company = $companies->firstWhere('id', (int) $requested)
            ?? $companies->firstWhere('id', (int) $event->user->company_id)
            ?? $companies->first();

        app(TenantContext::class)->set($company);

        if ($request->hasSession()) {
            $request->session()->put($sessionKey, $company->getKey());
        }
    }
}
