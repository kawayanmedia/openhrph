<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Resolves the payroll cut-off that an attendance entry belongs to and tells
 * whether that cut-off still accepts punches.
 */
class AttendancePeriod
{
    /**
     * The open cut-off that covers the given date for the employee's payroll group.
     */
    public function openCutoffFor(Employee $employee, ?CarbonInterface $date = null): ?PayrollDate
    {
        return $this->cutoffQuery($employee, $date)
            ->where('status', PayrollDate::STATUS_OPEN)
            ->first();
    }

    /**
     * The cut-off (any status) that covers the given date.
     */
    public function cutoffFor(Employee $employee, ?CarbonInterface $date = null): ?PayrollDate
    {
        return $this->cutoffQuery($employee, $date)->first();
    }

    /**
     * Determine whether attendance for the date is already locked by payroll.
     */
    public function isClosedFor(Employee $employee, ?CarbonInterface $date = null): bool
    {
        $day = $this->normalize($date);

        $cutoff = $this->cutoffQuery($employee, $day)->first();

        if ($cutoff !== null && $cutoff->status !== PayrollDate::STATUS_OPEN) {
            return true;
        }

        if ($employee->payroll_group_id === null) {
            return false;
        }

        return Payroll::query()
            ->where('payroll_group_id', $employee->payroll_group_id)
            ->whereIn('status', [Payroll::STATUS_PROCESSED, Payroll::STATUS_APPROVED, Payroll::STATUS_CLOSED])
            ->whereNotNull('date_from')
            ->whereNotNull('date_to')
            ->whereDate('date_from', '<=', $day->toDateString())
            ->whereDate('date_to', '>=', $day->toDateString())
            ->exists();
    }

    /**
     * The period shown on the portal: the open cut-off, or the current month.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable, label: string}
     */
    public function windowFor(Employee $employee, ?CarbonInterface $date = null): array
    {
        $day = $this->normalize($date);

        $cutoff = $this->openCutoffFor($employee, $day)
            ?? $this->cutoffQuery($employee, $day)->first();

        if ($cutoff !== null && $cutoff->attendance_date_start !== null && $cutoff->attendance_date_end !== null) {
            return [
                'start' => CarbonImmutable::parse($cutoff->attendance_date_start)->startOfDay(),
                'end' => CarbonImmutable::parse($cutoff->attendance_date_end)->startOfDay(),
                'label' => $cutoff->attendance_date_start->format('M d, Y').' - '.$cutoff->attendance_date_end->format('M d, Y'),
            ];
        }

        return [
            'start' => $day->startOfMonth(),
            'end' => $day->endOfMonth()->startOfDay(),
            'label' => $day->format('F Y'),
        ];
    }

    private function cutoffQuery(Employee $employee, ?CarbonInterface $date = null)
    {
        $day = $this->normalize($date);

        return PayrollDate::query()
            ->when(
                $employee->payroll_group_id === null,
                fn ($query) => $query->whereRaw('1 = 0'),
                fn ($query) => $query->where('payroll_group_id', $employee->payroll_group_id),
            )
            ->whereNotNull('attendance_date_start')
            ->whereNotNull('attendance_date_end')
            ->whereDate('attendance_date_start', '<=', $day->toDateString())
            ->whereDate('attendance_date_end', '>=', $day->toDateString())
            ->orderBy('attendance_date_end');
    }

    private function normalize(?CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date ?? now())->startOfDay();
    }
}
