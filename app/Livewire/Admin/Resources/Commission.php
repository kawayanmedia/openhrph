<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Commission extends Resource
{
    public static string $model = \App\Models\Commission::class;

    public static int $priority = 3;

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
                ->options(['Fixed' => 'Fixed'])
                ->default('Fixed')
                ->rules('required'),

            Boolean::make('Taxable?', 'is_taxable')
                ->default(true)
                ->rules('required'),

            Number::make('Maximum non-taxable', 'maximum_non_tax')
                ->rules('required')
                ->readonly()
                ->dependsOn(['is_taxable'], function (Number $field, Request $request, FormData $formData) {
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
        $model->maximum_non_tax = $request->maximum_non_tax;
        $model->save();
    }

    public static function afterUpdate(Request $request, Model $model): void
    {
        $model->maximum_non_tax = $request->maximum_non_tax;
        $model->save();
    }
}
