<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Resource;
use App\Models\Payroll;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PaySlip extends Resource
{
    public static string $model = Payroll::class;

    public static int $priority = 1;

    public static string $title = 'name';

    public static string $group = 'Payroll';

    public static array $search = ['id'];

    public static function label(): string
    {
        return 'Pay Slip';
    }

    public static function indexQuery(Builder $query): Builder
    {
        $tenantId = app(TenantContext::class)->id();

        return $tenantId === null ? $query : $query->where('company_id', $tenantId);
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
        ];
    }

    public function cards(Request $request): array
    {
        return [];
    }

    public function filters(Request $request): array
    {
        return [];
    }

    public function lenses(Request $request): array
    {
        return [];
    }

    public function actions(Request $request): array
    {
        return [];
    }

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return false;
    }
}
