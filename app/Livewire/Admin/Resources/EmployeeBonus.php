<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use App\Models\Bonus;
use Illuminate\Http\Request;

class EmployeeBonus extends Resource
{
    public static string $model = \App\Models\EmployeeBonus::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static array $with = ['bonus', 'employee'];

    public static string $group = 'Admin';

    public static array $search = ['id'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Employee', function () {
                return $this->employee?->fullname;
            })->onlyOnIndex(),

            Text::make('Type', function () {
                return $this->bonus->name;
            }),

            Select::make('Bonus Type', 'bonus_id')
                ->options(Bonus::pluck('name', 'id')->toArray())
                ->sortable()
                ->rules('required')
                ->hideFromIndex()
                ->onlyOnForms(),

            Currency::make('Amount')
                ->rules('required')
                ->dependsOn(['bonus_id'], function (Currency $field, Request $request, FormData $formData) {
                    $field->show();

                    $bonus = Bonus::find($formData->bonus_id);

                    if ($bonus && $bonus->amount_basis == Bonus::AMOUNT_BASIS_SALARY_BASED) {
                        $field->rules();
                    }
                }),

            Date::make('Release Date'),

            Select::make('Status', 'status')
                ->options(disburse())
                ->sortable()
                ->default(\App\Models\EmployeeBonus::STATUS_NOT_RELEASE)
                ->onlyOnForms()
                ->rules('required'),

            Text::make('Status', function () {
                return $this->status_format;
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
}
