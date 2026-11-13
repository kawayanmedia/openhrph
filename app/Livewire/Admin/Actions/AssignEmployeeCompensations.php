<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Support\FormData;
use App\Models\Bonus;
use App\Models\EmployeeBonus;
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
        foreach ($models as $model) {
            $employeeBonus = EmployeeBonus::firstOrNew([
                'employee_id' => $model->id,
                'bonus_id' => $fields['bonus_id'],
            ]);

            $employeeBonus->amount = $fields['amount'];
            $employeeBonus->status = $fields['status'];
            $employeeBonus->release_date = $fields['release_date'];
            $employeeBonus->save();
        }

        return self::message('Bonus has been assigned.');
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(Request $request): array
    {
        return [
            Select::make('Type', 'compensation_type')
                ->options([
                    'Bonus' => 'Bonus',
                    'Allowance' => 'Allowance',
                    'Commission' => 'Commission',
                ])->sortable()
                ->rules('required')
                ->default('Bonus')
                ->hideFromIndex()
                ->onlyOnForms(),

            Select::make('Bonus Type', 'bonus_id')
                ->options(compensation_options('Bonus'))
                ->sortable()
                ->rules('required')
                ->onlyOnForms()
                ->dependsOn(['compensation_type'], function (Select $field, Request $request, FormData $formData) {
                    $field->name = match ($formData->compensation_type) {
                        'Allowance' => 'Allowance Type',
                        'Commission' => 'Commission Type',
                        default => 'Bonus Type',
                    };

                    $field->options(compensation_options($formData->compensation_type));
                }),

            Currency::make('Amount', 'amount')
                ->rules('required')
                ->dependsOn(['bonus_id', 'compensation_type'], function (Currency $field, Request $request, FormData $formData) {
                    $field->show();

                    $bonus = Bonus::find($formData->bonus_id);

                    if ($bonus && $bonus->amount_basis == Bonus::AMOUNT_BASIS_SALARY_BASED) {
                        $field->rules();
                        $field->hide();
                    }
                }),

            Date::make('Release Date', 'release_date'),

            Select::make('Status', 'status')
                ->options(disburse())
                ->sortable()
                ->onlyOnForms()
                ->rules('required'),
        ];
    }
}
