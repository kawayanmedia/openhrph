<?php

namespace App\Actions;

use App\Models\Timesheet;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

class SetTimesheetAbsent extends BaseAction
{
    use AsAction;

    /**
     * Flag scheduled working days without attendance as absent.
     *
     * Rest days, holidays, leaves, locked rows and days with any recorded
     * punch are left untouched so payroll never double counts an absence.
     */
    public function handle(string $from, string $to, ?int $officeId = null, ?int $employeeId = null): int
    {
        $endExclusive = CarbonImmutable::parse($to)->addDay()->toDateString();

        return Timesheet::query()
            ->where('log_date', '>=', $from)
            ->where('log_date', '<', $endExclusive)
            ->where('day_type', Timesheet::REGULAR_DAY)
            ->whereNull('holiday_id')
            ->where('is_leave', false)
            ->where('is_locked', false)
            ->where('is_absent', false)
            ->whereNull('logs_rates')
            ->where(function ($query): void {
                $query->whereNull('logs')
                    ->orWhereIn('logs', ['', '[]', 'null']);
            })
            ->whereNull('am_login')
            ->whereNull('pm_login')
            ->whereNotNull('schedule_id')
            ->when($officeId !== null, fn ($query) => $query->where('office_id', $officeId))
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->update(['is_absent' => true]);
    }
}
