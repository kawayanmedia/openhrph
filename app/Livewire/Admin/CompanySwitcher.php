<?php

namespace App\Livewire\Admin;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CompanySwitcher extends Component
{
    /**
     * Switch the active company for the signed-in user.
     */
    public function switchTo(int $companyId)
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->belongsToCompany($companyId), 403);

        $user->forceFill(['company_id' => $companyId])->save();

        session([config('tenancy.session_key') => $companyId]);

        app(TenantContext::class)->set($companyId);

        return redirect()->route('admin.dashboard');
    }

    public function render()
    {
        return view('livewire.admin.company-switcher', [
            'companies' => Auth::user()?->companyMemberships() ?? collect(),
            'activeCompanyId' => app(TenantContext::class)->id() ?? Auth::user()?->company_id,
        ]);
    }
}
