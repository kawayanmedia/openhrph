<?php

namespace App\Observers;

use App\Models\Payroll;
use Illuminate\Validation\ValidationException;

/**
 * Stops a second payroll run for the same company, group, date and type.
 *
 * The database also has a unique key; this observer turns the violation into a
 * validation message the admin screens can display.
 */
class PayrollObserver
{
    public function creating(Payroll $payroll): void
    {
        if ($payroll->payroll_date === null || $payroll->payroll_group_id === null) {
            return;
        }

        $exists = Payroll::withoutGlobalScopes()
            ->where('company_id', $payroll->company_id)
            ->where('payroll_group_id', $payroll->payroll_group_id)
            ->whereDate('payroll_date', $payroll->payroll_date)
            ->where('type', $payroll->type ?: Payroll::TYPE_REGULAR)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'payroll_date' => 'A payroll run already exists for this group, payroll date and type. Open the existing run instead of creating a duplicate.',
            ]);
        }
    }
}
