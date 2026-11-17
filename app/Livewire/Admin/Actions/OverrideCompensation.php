<?php

namespace App\Livewire\Admin\Actions;

use App\Models\Compensation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Copy a system catalogue row into the company so it can change the amount or
 * the non-taxable ceiling without affecting other companies.
 */
class OverrideCompensation extends Action
{
    public string $name = 'Override';

    public string $confirmText = 'Create a company-specific copy of this compensation?';

    public string $confirmButtonText = 'Create override';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $companyId = $request->user()?->company_id;

        if ($companyId === null) {
            return self::message('A company is required to override a compensation.');
        }

        $created = false;

        foreach ($models as $model) {
            if (! $model instanceof Compensation || $model->isOverride()) {
                continue;
            }

            Compensation::overrideFor((int) $companyId, $model);

            $created = true;
        }

        return self::message($created
            ? 'Override created. Edit the new row to change the amount or ceiling.'
            : 'This compensation is already overridden for your company.');
    }
}
