<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Support\Tenancy\TenantCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Rate extends Resource
{
    public static string $model = \App\Models\Rate::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Attendance';

    public static array $search = ['id', 'day_type'];

    public function title(): string
    {
        return (string) ($this->resource?->day_type ?? $this->resource?->getKey() ?? '');
    }

    public static function indexQuery(Builder $query): Builder
    {
        // Company scoping is handled by the model's global CompanyScope.
        return $query;
    }

    public function fields(Request $request): array
    {
        return [
            Text::make('', 'day_type')->hideWhenUpdating(),

            Text::make('Regular', 'regular'),

            Text::make('Overtime', 'ot'),

            Text::make('Night Differential', 'nt'),

            Text::make('Night Differential + Overtime', 'nt_ot'),
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

    public static function afterCreate(Request $request, Model $model): void
    {
        TenantCache::forget('rates_options');
    }

    public static function afterUpdate(Request $request, Model $model): void
    {
        TenantCache::forget('rates_options');
    }

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }

    public static function authorizedToView(?Model $model): ?bool
    {
        return false;
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return true;
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return false;
    }
}
