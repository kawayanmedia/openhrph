<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollAttendanceSnapshot;
use App\Models\Scopes\TenantScope;
use App\Models\Timesheet;
use Illuminate\Support\Facades\Schema;

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
        $query = $this->query($payroll);
        $locked = (clone $query)->update(['is_locked' => true]);

        if (Schema::hasTable('payroll_attendance_snapshots')) {
            (clone $query)->chunkById(500, function ($timesheets) use ($payroll): void {
                foreach ($timesheets as $timesheet) {
                    $this->snapshot($payroll, $timesheet);
                }
            });
        }

        return $locked;
    }

    public function unlock(Payroll $payroll): int
    {
        $unlocked = $this->query($payroll)->update(['is_locked' => false]);

        // Reopening is an explicit, owner-only action. The old snapshot is
        // removed so the reprocessed run writes a new frozen version.
        if (Schema::hasTable('payroll_attendance_snapshots')) {
            PayrollAttendanceSnapshot::query()
                ->where('payroll_id', $payroll->getKey())
                ->delete();
        }

        return $unlocked;
    }

    private function snapshot(Payroll $payroll, Timesheet $timesheet): void
    {
        $ruleVersion = (int) ($timesheet->rule_version ?? 0);
        $nightOvertime = (float) $timesheet->actual_nt_ot;
        $overtime = (float) $timesheet->actual_ot;

        if ($ruleVersion < Timesheet::RULE_VERSION_NIGHT_OT_INCLUDED) {
            $overtime += $nightOvertime;
        }

        $minutes = fn (float $hours): int => max(0, (int) round($hours * 60));

        PayrollAttendanceSnapshot::query()->updateOrCreate(
            [
                'payroll_id' => $payroll->getKey(),
                'timesheet_id' => $timesheet->getKey(),
            ],
            [
                'company_id' => $payroll->company_id,
                'employee_id' => $timesheet->employee_id,
                'log_date' => $timesheet->log_date?->toDateString(),
                'shift_id' => $timesheet->shift_id,
                'day_type' => $timesheet->day_type,
                'day_rate_code' => $timesheet->day_rate_code,
                'scheduled_minutes' => (int) ($timesheet->scheduled_minutes ?? 0),
                'paid_minutes' => (int) ($timesheet->paid_minutes ?? 0),
                'regular_minutes' => $minutes((float) $timesheet->actual_regular),
                'overtime_minutes' => $minutes($overtime),
                'night_minutes' => $minutes((float) $timesheet->actual_nt),
                'night_ot_minutes' => $minutes($nightOvertime),
                'late_minutes' => (int) ($timesheet->late_minutes ?? 0),
                'undertime_minutes' => (int) ($timesheet->undertime_minutes ?? 0),
                'approved_ot_minutes' => (int) ($timesheet->approved_ot_minutes ?? 0),
                'rule_version' => $timesheet->rule_version,
                'is_absent' => (bool) $timesheet->is_absent,
                'is_leave' => (bool) $timesheet->is_leave,
                'is_paid_leave' => (bool) $timesheet->is_paid_leave,
                'leave_fraction' => $timesheet->leave_half_day ? 0.5 : 1,
                'snapshot_at' => now(),
            ],
        );
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
