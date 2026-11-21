<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Leave;
use App\Models\LeavePolicy as LeavePolicyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class LeavePolicy extends Resource
{
    public static string $model = LeavePolicyModel::class;

    public static string $title = 'id';

    public static int $priority = 6;

    public static string $group = 'Leave Management';

    public static array $search = ['id'];

    public static array $with = ['leaveType'];

    public static function label(): string
    {
        return 'Leave Policies';
    }

    public static function indexQuery(Builder $query): Builder
    {
        return $query->with('leaveType');
    }

    public function title(): string
    {
        return (string) ($this->resource?->leaveType?->name ?? $this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Leave Type', function () {
                return $this->leaveType?->name;
            }),

            Text::make('Entitlement (days)', 'entitlement_days'),

            Text::make('Accrual', 'accrual'),

            Text::make('Carry-over cap', 'carry_over_days'),

            Text::make('Expiry (months)', 'expiry_months'),

            Boolean::make('Paid', 'is_paid'),

            Boolean::make('Active', 'is_active'),

            Select::make('Leave Type', 'leave_type_id')
                ->options(Leave::selectOptions())
                ->rules('required')
                ->onlyOnForms(),

            Number::make('Entitlement (days)', 'entitlement_days')
                ->default(0)
                ->onlyOnForms(),

            Select::make('Accrual', 'accrual')
                ->options(['annual' => 'Annual', 'monthly' => 'Monthly', 'none' => 'None'])
                ->default('annual')
                ->onlyOnForms(),

            Number::make('Carry-over cap (days)', 'carry_over_days')
                ->default(0)
                ->onlyOnForms(),

            Number::make('Expiry (months)', 'expiry_months')
                ->default(0)
                ->onlyOnForms(),

            Boolean::make('Half day allowed', 'half_day_allowed')->default(true)->onlyOnForms(),

            Boolean::make('Paid', 'is_paid')->default(true)->onlyOnForms(),

            Number::make('Minimum service (months)', 'min_service_months')
                ->default(0)
                ->onlyOnForms(),

            Boolean::make('Requires evidence', 'requires_evidence')->default(false)->onlyOnForms(),

            Boolean::make('Active', 'is_active')->default(true)->onlyOnForms(),
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
        $model->company_id = $request->user()->company_id;
        $model->save();
    }
}
