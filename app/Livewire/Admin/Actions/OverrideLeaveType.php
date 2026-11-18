<?php

namespace App\Livewire\Admin\Actions;

use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Copy a system leave type into the company so it can change the allowance,
 * deductibility or active flag without affecting other companies.
 */
class OverrideLeaveType extends Action
{
    public string $name = 'Override';

    public string $confirmText = 'Create a company-specific copy of this leave type?';

    public string $confirmButtonText = 'Create override';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $companyId = $request->user()?->company_id;

        if ($companyId === null) {
            return self::message('A company is required to override a leave type.');
        }

        $created = false;

        foreach ($models as $model) {
            if (! $model instanceof Leave || $model->isOverride()) {
                continue;
            }

            Leave::overrideFor((int) $companyId, $model);

            $created = true;
        }

        return self::message($created
            ? 'Override created. Edit the new row to change the allowance or deductibility.'
            : 'This leave type is already overridden for your company.');
    }
}
