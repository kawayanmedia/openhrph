<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Compensation extends Resource
{
    public static string $model = \App\Models\Compensation::class;

    public static int $priority = 4;

    public static string $title = 'id';

    public static string $group = 'Payroll';

    public static array $search = ['code', 'name', 'tax_class'];

    public static function label(): string
    {
        return 'Compensations';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Code', 'code')
                ->sortable()
                ->rules('required'),

            Text::make('Name', 'name')
                ->sortable()
                ->rules('required'),

            Text::make('Tax Class', 'tax_class')
                ->sortable()
                ->rules('required'),

            Boolean::make('Taxable', 'is_taxable')
                ->onlyOnIndex()
                ->default(true),

            Select::make('Tax Option', 'tax_option')
                ->options([
                    'fully_taxable' => 'Fully Taxable',
                    'partially_nontaxable' => 'Partially Non-Taxable',
                    'fully_nontaxable' => 'Fully Non-Taxable',
                ])
                ->sortable()
                ->rules('required')
                ->hideFromIndex()
                ->onlyOnForms(),

            Currency::make('Amount', 'amount')
                ->rules('required')
                ->sortable()
                ->currency(currency_symbol())
                ->readonly()
                ->onlyOnForms()
                ->help('Partially Non Taxable Ceiling Amount (Per Annum)')
                ->dependsOn(['is_taxable', 'tax_option'], function (Currency $field, Request $request, FormData $formData) {
                    $field->hide();

                    if ($formData->tax_option === 'partially_nontaxable') {
                        $field->readonly = false;
                        $field->rules('required');
                        $field->show();
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

    public static function afterUpdate(Request $request, Model $model): void
    {
        $model->company_id = $request->user()->company_id;
        $model->save();
    }
}
