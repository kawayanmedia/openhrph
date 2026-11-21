<?php

namespace App\Services\Payroll;

use App\Models\Payroll;
use App\Models\PayrollDate;

/**
 * Keeps the generated pay period in step with the payroll run that consumes it.
 *
 * A period is open until processing starts, running while the engine works, and
 * closed once the payroll is approved. Only the owner's reopen returns it to
 * open.
 */
final class PayrollPeriodState
{
    public function mark(Payroll $payroll, string $status): void
    {
        if ($payroll->type !== Payroll::TYPE_REGULAR || $payroll->payroll_date === null) {
            return;
        }

        PayrollDate::withoutTenant()
            ->where('company_id', $payroll->company_id)
            ->where('payroll_group_id', $payroll->payroll_group_id)
            ->whereDate('payroll_date', $payroll->payroll_date)
            ->update(['status' => $status]);
    }
}
