<?php

namespace App\Services\Assistant;

use App\Models\Employee;
use App\Models\PayrollDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the reporting window and aggregates logged hours per employee.
 *
 * Timesheets store hours in hundredths (`regular = 800` is 8.00 hours), so the
 * aggregate is divided before it leaves this class.
 */
class AttendanceHours
{
    /**
     * Resolve the reporting window.
     *
     * Defaults to the open pay cut-off that covers today and falls back to the
     * current calendar month when the company has no cut-off for today.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable, label: string, source: string}
     */
    public function period(?string $start = null, ?string $end = null): array
    {
        if ($start !== null || $end !== null) {
            $from = $start !== null ? CarbonImmutable::parse($start) : CarbonImmutable::today()->startOfMonth();
            $to = $end !== null ? CarbonImmutable::parse($end) : CarbonImmutable::today();

            if ($to->lessThan($from)) {
                [$from, $to] = [$to, $from];
            }

            return $this->window($from, $to, 'requested period');
        }

        $cutoff = PayrollDate::query()
            ->where('status', PayrollDate::STATUS_OPEN)
            ->whereNotNull('attendance_date_start')
            ->whereNotNull('attendance_date_end')
            ->whereDate('attendance_date_start', '<=', CarbonImmutable::today()->toDateString())
            ->whereDate('attendance_date_end', '>=', CarbonImmutable::today()->toDateString())
            ->orderBy('attendance_date_end')
            ->first();

        if ($cutoff !== null) {
            $window = $this->window(
                CarbonImmutable::parse($cutoff->attendance_date_start),
                CarbonImmutable::parse($cutoff->attendance_date_end),
                'current cut-off',
            );

            $window['payroll_group'] = $cutoff->payrollGroup?->name;

            return $window;
        }

        return $this->window(
            CarbonImmutable::today()->startOfMonth(),
            CarbonImmutable::today()->endOfMonth(),
            'current month (no open cut-off covers today)',
        );
    }

    /**
     * Total logged hours per employee for the given window.
     *
     * Employees without timesheets are included with 0 hours so a shortfall is
     * never hidden by missing attendance data.
     *
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $period
     * @return array{
     *     period: array<string, mixed>,
     *     rows: array<int, array<string, mixed>>,
     *     under_count: int,
     *     total_employees: int
     * }
     */
    public function report(array $period, ?float $underHours = null, int $limit = 30): array
    {
        $start = $period['start']->toDateString();
        $end = $period['end']->toDateString();

        $query = Employee::query()
            ->leftJoin('timesheets', function ($join) use ($start, $end): void {
                $join->on('timesheets.employee_id', '=', 'employees.id')
                    ->whereNull('timesheets.deleted_at')
                    ->whereBetween('timesheets.log_date', [$start, $end]);
            })
            ->where(function ($query) use ($end): void {
                $query->whereNull('employees.date_resigned')
                    ->orWhereDate('employees.date_resigned', '>', $end);
            })
            ->select([
                'employees.id',
                'employees.employee_number',
                'employees.first_name',
                'employees.last_name',
                'employees.position',
            ])
            ->selectRaw('COALESCE(SUM(COALESCE(timesheets.regular, 0) + COALESCE(timesheets.ot, 0) + COALESCE(timesheets.nt, 0) + COALESCE(timesheets.nt_ot, 0)), 0) as total_hundredths')
            ->selectRaw('COUNT(timesheets.id) as days_logged')
            ->groupBy('employees.id', 'employees.employee_number', 'employees.first_name', 'employees.last_name', 'employees.position')
            ->orderBy('total_hundredths')
            ->orderBy('employees.last_name')
            ->limit($limit);

        $rows = $query->get()->map(fn (Employee $employee) => [
            'employee_id' => (int) $employee->id,
            'employee_number' => $employee->employee_number,
            'name' => trim(($employee->last_name ?? '').', '.($employee->first_name ?? ''), ', '),
            'position' => $employee->position,
            'hours' => round(((int) $employee->total_hundredths) / 100, 2),
            'days_logged' => (int) $employee->days_logged,
        ])->all();

        $total = (int) Employee::query()
            ->where(function ($query) use ($end): void {
                $query->whereNull('employees.date_resigned')
                    ->orWhereDate('employees.date_resigned', '>', $end);
            })
            ->count();

        $underCount = $underHours === null
            ? 0
            : collect($rows)->filter(fn (array $row) => $row['hours'] < $underHours)->count();

        return [
            'period' => [
                'start' => $start,
                'end' => $end,
                'label' => $period['label'] ?? null,
                'source' => $period['source'] ?? null,
                'payroll_group' => $period['payroll_group'] ?? null,
            ],
            'threshold' => $underHours,
            'rows' => $rows,
            'under_count' => $underCount,
            'total_employees' => $total,
        ];
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, label: string, source: string}
     */
    protected function window(CarbonImmutable $from, CarbonImmutable $to, string $source): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();

        return [
            'start' => $from,
            'end' => $to,
            'label' => $from->format('M j, Y').' – '.$to->format('M j, Y'),
            'source' => $source,
        ];
    }

    /**
     * Hours logged by a single employee in a window.
     */
    public function totalFor(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): float
    {
        $total = DB::table('timesheets')
            ->where('employee_id', $employee->getKey())
            ->whereNull('deleted_at')
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->sum(DB::raw('COALESCE(regular, 0) + COALESCE(ot, 0) + COALESCE(nt, 0) + COALESCE(nt_ot, 0)'));

        return round(((int) $total) / 100, 2);
    }
}
