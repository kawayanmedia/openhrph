<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Scopes\TenantScope;
use App\Models\Timesheet;

/**
 * Locks and unlocks the timesheets that belong to a payroll run.
 *
 * Attendance is locked when the run is approved so a retroactive edit cannot
 * change a payroll that has already been signed off. Reopening the payroll is
 * the only way back, and that path is restricted to the company owner.
 */
class TimesheetPeriodLock
{
    public function lock(Payroll $payroll): int
    {
        return $this->query($payroll)->update(['is_locked' => true]);
    }

    public function unlock(Payroll $payroll): int
    {
        return $this->query($payroll)->update(['is_locked' => false]);
    }

    private function query(Payroll $payroll)
    {
        $query = Timesheet::query()->withoutGlobalScope(TenantScope::class);

        if ($payroll->company_id !== null) {
            $query->where('company_id', $payroll->company_id);
        }

        if ($payroll->payroll_group_id !== null) {
            $employees = Employee::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('company_id', $payroll->company_id)
                ->where('payroll_group_id', $payroll->payroll_group_id)
                ->select('id');

            $query->whereIn('employee_id', $employees);
        }

        if ($payroll->date_from !== null && $payroll->date_to !== null) {
            $query->where('log_date', '>=', $payroll->date_from->format('Y-m-d'))
                ->where('log_date', '<', $payroll->date_to->copy()->addDay()->format('Y-m-d'));
        }

        return $query;
    }
}
