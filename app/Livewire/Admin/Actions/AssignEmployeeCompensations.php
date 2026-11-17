<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Support\FormData;
use App\Models\Compensation;
use App\Models\EmployeeCompensation;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;

class AssignEmployeeCompensations extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * Perform the action on the given models.
     */
    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $compensation = Compensation::find($fields['compensation_id'] ?? null);

        if ($compensation === null) {
            return self::message('Compensation was not found.');
        }

        foreach ($models as $model) {
            $employeeCompensation = EmployeeCompensation::firstOrNew([
                'employee_id' => $model->id,
                'compensation_id' => $compensation->getKey(),
                'release_date' => $fields['release_date'] ?? null,
            ]);

            $employeeCompensation->amount = $fields['amount'] ?? null;
            $employeeCompensation->status = $fields['status'] ?? EmployeeCompensation::STATUS_NOT_RELEASE;
            $employeeCompensation->is_special = true;
            $employeeCompensation->is_active = true;
            $employeeCompensation->is_taxable = $compensation->is_taxable;
            $employeeCompensation->save();
        }

        return self::message('Compensation has been assigned.');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            Select::make('Type', 'compensation_type')
                ->options([
                    'Allowance' => 'Allowance',
                    'Bonus' => 'Bonus',
                    'Commission' => 'Commission',
                    'Compensation' => 'Compensation',
                ])
                ->sortable()
                ->rules('required')
                ->default('Compensation')
                ->hideFromIndex()
                ->onlyOnForms(),

            Select::make('Compensation', 'compensation_id')
                ->options(compensation_options('Compensation'))
                ->sortable()
                ->rules('required')
                ->onlyOnForms()
                ->dependsOn(['compensation_type'], function (Select $field, Request $request, FormData $formData) {
                    $field->options(compensation_options($formData->compensation_type));
                }),

            Currency::make('Amount', 'amount')
                ->dependsOn(['compensation_id'], function (Currency $field, Request $request, FormData $formData) {
                    $compensation = Compensation::find($formData->compensation_id);

                    if ($compensation?->group === Compensation::GROUP_BONUS
                        && $compensation->amount_basis === 'Salary Based') {
                        $field->rules();

                        return;
                    }

                    $field->rules('required');
                }),

            Date::make('Release Date', 'release_date'),

            Select::make('Status', 'status')
                ->options(disburse())
                ->sortable()
                ->default(EmployeeCompensation::STATUS_NOT_RELEASE)
                ->onlyOnForms()
                ->rules('required'),
        ];
    }
}
