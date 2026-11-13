<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use App\Models\Employee;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class EmployeeLoan extends Resource
{
    public static string $model = \App\Models\EmployeeLoan::class;

    public static int $priority = 6;

    public static string $title = 'id';

    public static string $group = 'Admin';

    public static array $with = ['employee'];

    public static array $search = ['id'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            BelongsTo::make('Employee', 'employee')
                ->options(Employee::employeeList())
                ->showOnIndex()
                ->withoutTrashed()
                ->searchable()
                ->rules('required'),

            Select::make('Loan', 'loan_id')
                ->options(Loan::pluck('name', 'id')->toArray())
                ->sortable()
                ->searchable()
                ->rules('required')
                ->hideFromIndex()
                ->onlyOnForms(),

            Text::make('Reference Number', 'reference_number')
                ->rules('required')
                ->sortable(),

            Text::make('Bank Name', 'bank_name')
                ->hide()
                ->sortable()
                ->dependsOn(['loan_id'], function (Text $field, Request $request, FormData $formData) {
                    if ($formData->loan_id == 11) {
                        $field->show();
                        $field->rules('required');
                    }
                }),

            Date::make('Approved Date'),

            Currency::make('Principal Amount', 'amount')
                ->rules('required')
                ->sortable(),

            Currency::make('Remaining Balance', 'balance')
                ->rules('required')
                ->sortable(),

            Number::make('Loan Term', 'terms')
                ->rules('required')
                ->help('(Months)')
                ->sortable(),

            Date::make('Repayment start date', 'repayment_start_date')->rules('required'),

            Date::make('Repayment end date', 'repayment_end_date'),

            Select::make('Payment scheme', 'scheme')
                ->options(payment_schemes())
                ->sortable()
                ->rules('required')
                ->hideFromIndex()
                ->onlyOnForms(),

            Currency::make('Monthly amortization', 'monthly_amortization')
                ->rules('required')
                ->sortable(),

            Boolean::make('Active', 'is_active')
                ->default(true),
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
        if ($model->loan->name != 'SSS' or $model->loan->name != 'HDMF') {
            $model->sss_loan_type = null;
            $model->hdmf_loan_type = null;
            $model->save();
        }
    }
}
