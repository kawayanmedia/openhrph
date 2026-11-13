<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use App\Models\Bonus as BonusModel;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Bonus extends Resource
{
    public static string $model = BonusModel::class;

    public static int $priority = 2;

    public static string $title = 'id';

    public static string $group = 'Payroll';

    public static array $search = ['id'];

    public static function indexQuery(Builder $query): Builder
    {
        $tenantId = app(TenantContext::class)->id();

        return $tenantId === null ? $query : $query->where('company_id', $tenantId);
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name', 'name')
                ->rules('required')
                ->sortable(),

            Select::make('Amount Basis', 'amount_basis')
                ->options([
                    BonusModel::AMOUNT_BASIS_FIXED => BonusModel::AMOUNT_BASIS_FIXED,
                    BonusModel::AMOUNT_BASIS_SALARY_BASED => BonusModel::AMOUNT_BASIS_SALARY_BASED,
                ])
                ->rules('required'),

            Select::make('Frequency', 'frequency')
                ->options([
                    BonusModel::FREQUENCY_ONE_TIME => BonusModel::FREQUENCY_ONE_TIME,
                    BonusModel::FREQUENCY_PERIODIC => BonusModel::FREQUENCY_PERIODIC,
                ])
                ->rules('required'),

            Boolean::make('Taxable?', 'is_taxable')
                ->default(true),

            Currency::make('Maximum non-taxable amount', 'maximum_non_tax')
                ->placeholder('Amount')
                ->readonly()
                ->dependsOn(['is_taxable'], function (Currency $field, Request $request, FormData $formData) {
                    if ($formData->is_taxable === false) {
                        $field->readonly = false;
                        $field->rules('required');
                    }
                }),
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
