<?php

namespace App\Nova\Actions;

use App\Models\EmployeeBonus;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class AssignEmployeeBonus extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
            $employeeBonus = EmployeeBonus::firstOrNew([
                'employee_id' => $model->id,
                'bonus_id' => $fields->bonus_id,
            ]);

            $employeeBonus->amount = $fields->amount;
            $employeeBonus->status = $fields->status;
            $employeeBonus->release_date = $fields->release_date;
            $employeeBonus->save();
        }

        return Action::message('Bonus has been assigned.');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [

            Select::make('Bonus Type', 'bonus_id')
                ->options(
                    \App\Models\Bonus::pluck('name', 'id')->toArray()
                )->sortable()
                ->rules('required')
                ->hideFromIndex()
                ->onlyOnForms(),

            Currency::make('Amount')
                ->rules('required')
                ->dependsOn(
                    ['bonus_id'],
                    function (Currency $field, NovaRequest $request, FormData $formData) {
                        $field->show();

                        $bonus = \App\Models\Bonus::find($formData->bonus_id);
                        if ($bonus) {
                            // Should not required
                            if ($bonus->amount_basis == \App\Models\Bonus::AMOUNT_BASIS_SALARY_BASED) {
                                $field->rules('');
                                $field->hide();
                            }
                        }
                    }
                ),

            Date::make('Release Date'),

            Select::make('Status', 'status')
                ->options(disburse())
                ->sortable()
                ->onlyOnForms()
                ->rules('required'),
        ];
    }
}
