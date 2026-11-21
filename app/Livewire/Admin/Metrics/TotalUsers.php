<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class TotalUsers extends Metric
{
    public string $name = 'Total Users';

    public function calculate(Request $request): mixed
    {
        $companyId = app(TenantContext::class)->id() ?? $request->user()?->company_id;

        return $companyId === null
            ? 0
            : User::query()->where('company_id', $companyId)->count();
    }
}
