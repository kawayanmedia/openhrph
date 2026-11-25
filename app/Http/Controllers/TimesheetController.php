<?php

namespace App\Http\Controllers;

use App\Jurisdictions\Philippines\Attendance\Dtr\DtrReportBuilder;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use KawayanMedia\DtrPdf\Model\DtrReport;
use KawayanMedia\DtrPdf\Model\Period;

/**
 * Printable Daily Time Record.
 *
 * The page is drawn by kawayanmedia/dtr-pdf from the employee's timesheets;
 * the request only selects the employees and the period.
 */
class TimesheetController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Display a listing of the resource.
     */
    public function index() {}

    /**
     * Stream the DTR PDF for one employee, a list of employees, or an office.
     *
     * Accepts `employee_id` (comma separated), `employee[]`, or `office_id`,
     * plus `month`, `year`, `period_from` and `period_to`.
     */
    public function show(Request $request, DtrReportBuilder $builder)
    {
        $employees = $this->employees($request);

        abort_if($employees->isEmpty(), 404, 'No employees matched the DTR request.');

        $period = $this->period($request);

        $reports = $employees
            ->map(fn (Employee $employee): DtrReport => $builder->report($employee, $period))
            ->all();

        return response($builder->generator()->renderer()->renderMany($reports), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="dtr.pdf"',
        ]);
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employees(Request $request)
    {
        $ids = $request->input('employee_id');

        $ids = is_array($ids) ? $ids : explode(',', (string) $ids);
        $ids = array_merge($ids, (array) $request->input('employee', []));
        $ids = array_values(array_filter(array_map('trim', array_map('strval', $ids))));

        $query = Employee::query()
            ->with(['timesheets' => fn ($timesheets) => $timesheets->orderBy('log_date')])
            ->where('status', 1);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($request->filled('office_id')) {
            $query->where('office_id', $request->input('office_id'));
        } else {
            return Employee::query()->whereRaw('1 = 0')->get();
        }

        return $query->orderBy('last_name')->get();
    }

    private function period(Request $request): Period
    {
        $year = (int) ($request->input('year') ?: now()->year);
        $month = (int) ($request->input('month') ?: now()->month);
        $month = min(12, max(1, $month));

        $lastDay = CarbonImmutable::create($year, $month, 1)->daysInMonth;

        $fromDay = $this->dayNumber($request->input('period_from'), 1);
        $toDay = $this->dayNumber($request->input('period_to'), $lastDay);

        if ($fromDay > $toDay) {
            [$fromDay, $toDay] = [$toDay, $fromDay];
        }

        return DtrReportBuilder::periodFor($year, $month, $fromDay, $toDay);
    }

    /**
     * The legacy screen accepted a day number ("1", "15") or a full date.
     */
    private function dayNumber(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return max(1, (int) $value);
        }

        $parsed = CarbonImmutable::parse((string) $value);

        return (int) $parsed->day;
    }
}
