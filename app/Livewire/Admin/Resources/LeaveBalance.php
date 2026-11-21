<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\LeaveBalance as LeaveBalanceModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Read-only projection of the leave ledger. Corrections are posted to the
 * ledger and the projection is rebuilt, never edited directly.
 */
class LeaveBalance extends Resource
{
    public static string $model = LeaveBalanceModel::class;

    public static string $title = 'id';

    public static int $priority = 6;

    public static string $group = 'Leave Management';

    public static array $search = ['id'];

    public static array $with = ['employee', 'leaveType'];

    public static function label(): string
    {
        return 'Leave Balances';
    }

    public static function indexQuery(Builder $query): Builder
    {
        return $query->with(['employee', 'leaveType']);
    }

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return false;
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return false;
    }

    public static function canDeleteResource(Model $model): bool
    {
        return false;
    }

    public function title(): string
    {
        $name = trim(($this->resource?->employee?->first_name ?? '').' '.($this->resource?->employee?->last_name ?? ''));

        return $name !== '' ? $name : (string) ($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Employee', function () {
                return trim(($this->employee?->first_name ?? '').' '.($this->employee?->last_name ?? ''));
            }),

            Text::make('Leave Type', function () {
                return $this->leaveType?->name;
            }),

            Text::make('Year', 'year'),

            Text::make('Accrued', 'accrued'),

            Text::make('Used', 'used'),

            Text::make('Carried', 'carried'),

            Text::make('Expired', 'expired'),

            Text::make('Adjusted', 'adjusted'),

            Text::make('Balance', 'balance'),
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
}
