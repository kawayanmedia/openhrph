<?php

namespace App\Nova\Actions;

use App\Models\Compensation;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Copy a system catalogue row into the company so it can change the amount or
 * the non-taxable ceiling without affecting other companies.
 */
class OverrideCompensation extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $name = 'Override';

    public $confirmText = 'Create a company-specific copy of this compensation?';

    public $confirmButtonText = 'Create override';

    public function handle(ActionFields $fields, Collection $models)
    {
        $companyId = request()->user()?->company_id;

        if ($companyId === null) {
            return Action::danger('A company is required to override a compensation.');
        }

        $created = false;

        foreach ($models as $model) {
            if (! $model instanceof Compensation || $model->isOverride()) {
                continue;
            }

            Compensation::overrideFor((int) $companyId, $model);

            $created = true;
        }

        return $created
            ? Action::message('Override created. Edit the new row to change the amount or ceiling.')
            : Action::message('This compensation is already overridden for your company.');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
