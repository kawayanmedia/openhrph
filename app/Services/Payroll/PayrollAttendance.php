<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Setting;
use App\Models\Timesheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Detects employees who have no attendance record for a payroll period.
 */
class PayrollAttendance
{
    /**
     * @return Collection<int, Employee>
     */
    public function missingEmployees(Payroll $payroll): Collection
    {
        $group = $payroll->payrollGroup;

        if ($group === null) {
            return collect();
        }

        $employees = $group->employees()
            ->where('timesheet', true)
            ->get();

        if ($employees->isEmpty()) {
            return collect();
        }

        $recorded = Timesheet::query()
            ->whereIn('employee_id', $employees->modelKeys())
            ->whereBetween('log_date', [$payroll->date_from, $payroll->date_to])
            ->distinct()
            ->pluck('employee_id');

        return $employees->whereNotIn('id', $recorded);
    }

    /**
     * Whether the company pays the period basic when attendance is missing.
     */
    public function paysWithoutTimesheet(?int $companyId = null): bool
    {
        $value = Setting::query()
            ->where('company_id', $companyId ?? Auth::user()?->company_id)
            ->where('key', 'auto_absent_no_timesheet')
            ->value('value');

        return ! in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
