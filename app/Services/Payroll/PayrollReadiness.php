<?php

namespace App\Services\Payroll;

use App\Actions\GeneratePayrollDateAction;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\EmploymentType;
use App\Models\Holiday;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollDate;
use App\Models\PayrollGroup;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Timesheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Computes the setup checklist for a company that wants to run payroll.
 *
 * Every check reads the company's own data through the tenant global scope, so
 * the checklist always reflects what the payroll engine would actually find.
 */
class PayrollReadiness
{
    protected ?PayrollGroup $focusGroup = null;

    protected bool $focusGroupResolved = false;

    /**
     * The full readiness report.
     *
     * @return array{
     *     sections: array<int, array{key: string, label: string, description: string, items: array<int, array<string, mixed>>}>,
     *     required_total: int,
     *     required_complete: int,
     *     required_pending: int,
     *     recommended_total: int,
     *     recommended_complete: int,
     *     percent: int,
     *     is_ready: bool,
     *     next_action: array{label: string, url: string}|null,
     *     warnings: array<int, string>,
     *     focus_group: string|null
     * }
     */
    public function summary(): array
    {
        $group = $this->focusGroup();
        $employees = $this->includedEmployees($group);
        $period = $this->focusPeriod($group);

        $sections = [
            $this->organizationSection(),
            $this->payrollSection($group, $period),
            $this->peopleSection($group, $employees),
            $this->attendanceSection($group, $employees, $period),
            $this->runSection(),
        ];

        $items = collect($sections)->flatMap(fn (array $section) => $section['items']);
        $required = $items->where('required', true);
        $recommended = $items->where('required', false);

        // An "upcoming" step cannot be acted on yet (e.g. attendance for a
        // cut-off that has not started), so it does not block readiness.
        $requiredComplete = $required
            ->filter(fn (array $item) => in_array($item['status'], ['complete', 'upcoming'], true))
            ->count();
        $requiredTotal = $required->count();

        return [
            'sections' => $sections,
            'required_total' => $requiredTotal,
            'required_complete' => $requiredComplete,
            'required_pending' => $required->where('status', 'pending')->count(),
            'recommended_total' => $recommended->count(),
            'recommended_complete' => $recommended->where('status', 'complete')->count(),
            'percent' => $requiredTotal === 0 ? 100 : (int) round(($requiredComplete / $requiredTotal) * 100),
            'is_ready' => $requiredTotal > 0
                && $required->every(fn (array $item) => in_array($item['status'], ['complete', 'upcoming'], true)),
            'next_action' => $this->nextAction($required->values()->all()),
            'warnings' => $this->warnings($employees),
            'focus_group' => $group?->name,
        ];
    }

    /**
     * The payroll group the checklist is based on.
     */
    public function focusGroup(): ?PayrollGroup
    {
        if ($this->focusGroupResolved) {
            return $this->focusGroup;
        }

        $this->focusGroupResolved = true;

        $this->focusGroup = PayrollGroup::query()
            ->where('is_default', true)
            ->orderBy('id')
            ->first()
            ?? PayrollGroup::query()->orderBy('id')->first();

        return $this->focusGroup;
    }

    /**
     * Regenerates the open pay dates for the focus group.
     */
    public function generatePayrollDates(): bool
    {
        $group = $this->focusGroup();

        if ($group === null) {
            return false;
        }

        GeneratePayrollDateAction::run($group, true);

        return true;
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, items: array<int, array<string, mixed>>}>
     */
    protected function organizationSection(): array
    {
        $officeCount = Office::query()->count();
        $employmentTypeCount = EmploymentType::query()->count();
        $profile = $this->companyProfileCount();

        return [
            'key' => 'organization',
            'label' => 'Organization',
            'description' => 'The company records every employee and payroll run depends on.',
            'items' => [
                $this->item(
                    key: 'office',
                    title: 'Add an office or location',
                    description: 'Employees must belong to an office before they can be added.',
                    complete: $officeCount > 0,
                    required: true,
                    meta: $officeCount > 0 ? $this->plural($officeCount, 'office') : null,
                    action: $officeCount > 0
                        ? $this->link('Manage offices', 'admin.resource.index', ['resource' => 'office'])
                        : $this->link('Add office', 'admin.resource.create', ['resource' => 'office']),
                ),
                $this->item(
                    key: 'employment-type',
                    title: 'Create an employment type',
                    description: 'Classifies employees as regular, probationary, contractual and so on.',
                    complete: $employmentTypeCount > 0,
                    required: true,
                    meta: $employmentTypeCount > 0 ? $this->plural($employmentTypeCount, 'employment type') : null,
                    action: $employmentTypeCount > 0
                        ? $this->link('Manage employment types', 'admin.resource.index', ['resource' => 'employment-type'])
                        : $this->link('Add employment type', 'admin.resource.create', ['resource' => 'employment-type']),
                ),
                $this->item(
                    key: 'company-profile',
                    title: 'Complete the company profile',
                    description: 'TIN and employer SSS, PhilHealth and Pag-IBIG numbers appear on payslips and statutory reports.',
                    complete: $profile >= 4,
                    required: false,
                    meta: "{$profile} of 4 employer numbers recorded",
                    action: $this->link('Open general settings', 'admin.settings', ['page' => 'general']),
                ),
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, description: string, items: array<int, array<string, mixed>>}
     */
    protected function payrollSection(?PayrollGroup $group, ?PayrollDate $period): array
    {
        $detailsComplete = $this->groupDetailsComplete($group);
        $scheduleCount = $group === null ? 0 : PayrollDate::query()
            ->where('payroll_group_id', $group->getKey())
            ->where('status', PayrollDate::STATUS_OPEN)
            ->count();
        $year = $period?->payroll_date?->year ?? now()->year;
        [$statutoryComplete, $statutoryMeta] = $this->statutoryStatus($year);
        $wageOrderCount = $this->wageOrderCount($year);

        return [
            'key' => 'payroll',
            'label' => 'Payroll setup',
            'description' => 'How often you pay, the cut-off dates and the periods you can run.',
            'items' => [
                $this->item(
                    key: 'payroll-group',
                    title: 'Create a payroll group',
                    description: 'Defines the pay frequency, cut-off and pay dates used for every payroll run.',
                    complete: $group !== null,
                    required: true,
                    meta: $group?->name,
                    action: $group === null
                        ? $this->link('Create payroll group', 'admin.resource.create', ['resource' => 'pay-roll-group'])
                        : $this->link('Edit payroll group', 'admin.resource.edit', [
                            'resource' => 'pay-roll-group',
                            'resourceId' => $group->getKey(),
                        ]),
                ),
                $this->item(
                    key: 'payroll-group-details',
                    title: 'Complete the payroll group details',
                    description: 'Frequency, working days per year, hours per day and both pay dates must be set.',
                    complete: $detailsComplete,
                    required: true,
                    meta: $this->groupDetailsMeta($group),
                    action: $group === null ? null : $this->link('Review payroll group', 'admin.resource.edit', [
                        'resource' => 'pay-roll-group',
                        'resourceId' => $group->getKey(),
                    ]),
                ),
                $this->item(
                    key: 'pay-schedule',
                    title: 'Generate the pay schedule',
                    description: 'Payroll periods are generated from the group so you can pick one when creating a run.',
                    complete: $scheduleCount > 0,
                    required: true,
                    meta: $scheduleCount > 0
                        ? $this->plural($scheduleCount, 'open period')
                        : ($group === null ? 'Waiting for a payroll group' : 'No periods generated yet'),
                    action: $group === null ? null : ($scheduleCount > 0
                        ? $this->link('Manage payroll groups', 'admin.resource.index', ['resource' => 'pay-roll-group'])
                        : $this->link('Generate schedule', null, null, 'generatePayrollDates')),
                ),
                $this->item(
                    key: 'statutory-schedules',
                    title: 'Review statutory contribution schedules',
                    description: 'Choose whether SSS, PhilHealth and Pag-IBIG are deducted every pay or on a specific cut-off.',
                    complete: $this->statutorySchedulesConfigured(),
                    required: false,
                    meta: 'Defaults to every pay',
                    action: $this->link('Open payroll settings', 'admin.settings', ['page' => 'payroll']),
                ),
                $this->item(
                    key: 'minimum-wage',
                    title: 'Load the regional wage orders',
                    description: 'Employees flagged as minimum wage earners need the wage order for their region and industry.',
                    complete: $wageOrderCount > 0,
                    required: false,
                    meta: $wageOrderCount > 0 ? $this->plural($wageOrderCount, 'wage order') : "No {$year} wage orders",
                    action: $this->link('Manage minimum wage rates', 'admin.resource.index', ['resource' => 'minimum-wage-rate']),
                ),
                $this->item(
                    key: 'statutory-verification',
                    title: 'Verify the statutory tables for the payroll year',
                    description: 'Rates must be confirmed for the payroll date; provisional rows need a CPA sign-off.',
                    complete: $statutoryComplete,
                    required: false,
                    meta: $statutoryMeta,
                    action: $this->link('Open payroll settings', 'admin.settings', ['page' => 'payroll']),
                ),
            ],
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{key: string, label: string, description: string, items: array<int, array<string, mixed>>}
     */
    protected function peopleSection(?PayrollGroup $group, Collection $employees): array
    {
        $assigned = Employee::query()->whereNotNull('payroll_group_id')->count();
        $payable = $employees->filter(fn (Employee $employee) => $this->hasPayDetails($employee));
        $governmentIds = $employees->filter(fn (Employee $employee) => $this->hasGovernmentIds($employee));
        $taxStatus = $employees->filter(fn (Employee $employee) => filled($employee->tax_status));

        return [
            'key' => 'people',
            'label' => 'People',
            'description' => 'Who gets paid and the pay details the engine reads.',
            'items' => [
                $this->item(
                    key: 'employees',
                    title: 'Add employees to the payroll group',
                    description: 'Only employees assigned to this payroll group are included in the run.',
                    complete: $assigned > 0,
                    required: true,
                    meta: $assigned > 0
                        ? $this->plural($assigned, 'employee').' assigned'
                        : ($group === null ? 'Waiting for a payroll group' : 'No employees assigned yet'),
                    action: $this->link('Manage employees', 'admin.resource.index', ['resource' => 'employee']),
                ),
                $this->item(
                    key: 'employee-pay',
                    title: 'Set basic salary or daily rate',
                    description: 'Every included employee needs a rate and a payment type (monthly or daily).',
                    complete: $employees->isNotEmpty() && $payable->count() === $employees->count(),
                    required: true,
                    meta: $employees->isEmpty()
                        ? 'Waiting for employees'
                        : "{$payable->count()} of {$employees->count()} employees ready",
                    action: $this->link('Review employees', 'admin.resource.index', ['resource' => 'employee']),
                ),
                $this->item(
                    key: 'government-ids',
                    title: 'Record government IDs',
                    description: 'SSS, PhilHealth, Pag-IBIG and TIN numbers are needed on payslips and statutory reports.',
                    complete: $employees->isNotEmpty() && $governmentIds->count() === $employees->count(),
                    required: false,
                    meta: $employees->isEmpty()
                        ? null
                        : "{$governmentIds->count()} of {$employees->count()} employees complete",
                    action: $this->link('Review employees', 'admin.resource.index', ['resource' => 'employee']),
                ),
                $this->item(
                    key: 'tax-status',
                    title: 'Set tax status and dependants',
                    description: 'Used for BIR withholding summaries and annual tax reports.',
                    complete: $employees->isNotEmpty() && $taxStatus->count() === $employees->count(),
                    required: false,
                    meta: $employees->isEmpty()
                        ? null
                        : "{$taxStatus->count()} of {$employees->count()} employees complete",
                    action: $this->link('Review employees', 'admin.resource.index', ['resource' => 'employee']),
                ),
            ],
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{key: string, label: string, description: string, items: array<int, array<string, mixed>>}
     */
    protected function attendanceSection(?PayrollGroup $group, Collection $employees, ?PayrollDate $period): array
    {
        $tracked = $employees->filter(fn (Employee $employee) => (bool) $employee->timesheet);
        $paidWithoutTimesheet = $this->settingIsTruthy('auto_absent_no_timesheet');
        $holidayYear = $period?->payroll_date?->year ?? now()->year;
        $holidayCount = Holiday::query()->where('year', $holidayYear)->count();

        $attendanceComplete = false;
        $attendanceStatus = 'pending';
        $attendanceMeta = 'Waiting for employees';

        if ($employees->isNotEmpty() && $tracked->isEmpty()) {
            $attendanceComplete = true;
            $attendanceMeta = 'Timesheet tracking is disabled for all employees';
        } elseif ($employees->isNotEmpty() && $paidWithoutTimesheet) {
            $attendanceComplete = true;
            $attendanceMeta = 'Missing attendance is paid on basic salary';
        } elseif ($period === null) {
            $attendanceMeta = 'Waiting for a pay schedule';
        } else {
            $present = Timesheet::query()
                ->whereIn('employee_id', $tracked->modelKeys())
                ->whereBetween('log_date', [$period->attendance_date_start, $period->attendance_date_end])
                ->distinct()
                ->count('employee_id');

            $range = $this->periodLabel($period);

            if ($present >= $tracked->count()) {
                $attendanceComplete = true;
                $attendanceMeta = "{$range} · {$present} of {$tracked->count()} employees recorded";
            } elseif ($period->attendance_date_start?->isFuture()) {
                $attendanceStatus = 'upcoming';
                $attendanceMeta = "{$range} · starts ".$period->attendance_date_start->format('M j');
            } else {
                $attendanceMeta = "{$range} · {$present} of {$tracked->count()} employees recorded";
            }
        }

        return [
            'key' => 'attendance',
            'label' => 'Time and attendance',
            'description' => 'Attendance drives daily-paid earnings, absences and holiday premiums.',
            'items' => [
                $this->item(
                    key: 'attendance',
                    title: 'Record attendance for the next cut-off',
                    description: 'Import biometric logs or generate timesheets before processing the run.',
                    complete: $attendanceComplete,
                    required: true,
                    meta: $attendanceMeta,
                    status: $attendanceComplete ? 'complete' : $attendanceStatus,
                    action: $this->link('Open timesheets', 'admin.resource.index', ['resource' => 'timesheet']),
                ),
                $this->item(
                    key: 'work-schedule',
                    title: 'Assign work schedules',
                    description: 'Schedules and shifts determine tardiness, undertime and night differential.',
                    complete: Schedule::query()->exists() || EmployeeSchedule::query()->exists(),
                    required: false,
                    meta: null,
                    action: $this->link('Manage schedules', 'admin.resource.index', ['resource' => 'schedule']),
                ),
                $this->item(
                    key: 'holidays',
                    title: 'Add company holidays',
                    description: 'Holiday premiums are only applied to dates recorded here.',
                    complete: $holidayCount > 0,
                    required: false,
                    meta: $holidayCount > 0 ? $this->plural($holidayCount, 'holiday') : "No {$holidayYear} holidays",
                    action: $this->link('Manage holidays', 'admin.resource.index', ['resource' => 'holiday']),
                ),
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, description: string, items: array<int, array<string, mixed>>}
     */
    protected function runSection(): array
    {
        $payroll = Payroll::query()->orderByDesc('id')->first();

        return [
            'key' => 'run',
            'label' => 'Run payroll',
            'description' => 'Create the period, preview it, then process and approve.',
            'items' => [
                $this->item(
                    key: 'payroll-run',
                    title: 'Create your first payroll run',
                    description: 'Pick a generated pay period; the engine previews it before anything is committed.',
                    complete: $payroll !== null,
                    required: true,
                    meta: $payroll === null ? null : $this->payrollLabel($payroll),
                    action: $payroll === null
                        ? $this->link('Create payroll run', 'admin.resource.create', ['resource' => 'pay-roll'])
                        : $this->link('Open payroll run', 'admin.payroll.run', ['payroll' => $payroll->getKey()]),
                ),
            ],
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array<int, string>
     */
    protected function warnings(Collection $employees): array
    {
        $warnings = [];

        $missingPay = $employees
            ->reject(fn (Employee $employee) => $this->hasPayDetails($employee))
            ->take(5)
            ->map(fn (Employee $employee) => trim($employee->first_name.' '.$employee->last_name).' has no basic salary or daily rate');

        foreach ($missingPay as $warning) {
            $warnings[] = $warning;
        }

        $unassigned = Employee::query()
            ->whereNull('payroll_group_id')
            ->count();

        if ($unassigned > 0) {
            $warnings[] = $unassigned === 1
                ? '1 employee is not assigned to a payroll group and will be skipped'
                : $unassigned.' employees are not assigned to a payroll group and will be skipped';
        }

        return $warnings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{label: string, url: string}|null
     */
    protected function nextAction(array $items): ?array
    {
        foreach ($items as $item) {
            if ($item['status'] === 'pending' && $item['action'] !== null) {
                return $item['action'];
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Employee>
     */
    protected function includedEmployees(?PayrollGroup $group): Collection
    {
        if ($group === null) {
            return collect();
        }

        return Employee::query()
            ->where('payroll_group_id', $group->getKey())
            ->orderBy('id')
            ->get();
    }

    /**
     * The next open pay period for the group.
     */
    protected function focusPeriod(?PayrollGroup $group): ?PayrollDate
    {
        if ($group === null) {
            return null;
        }

        return PayrollDate::query()
            ->where('payroll_group_id', $group->getKey())
            ->where('status', PayrollDate::STATUS_OPEN)
            ->whereNotNull('attendance_date_start')
            ->whereNotNull('attendance_date_end')
            ->whereDate('attendance_date_end', '>=', now()->toDateString())
            ->orderBy('attendance_date_start')
            ->first()
            ?? PayrollDate::query()
                ->where('payroll_group_id', $group->getKey())
                ->whereNotNull('attendance_date_start')
                ->whereNotNull('attendance_date_end')
                ->orderByDesc('payroll_date')
                ->first();
    }

    protected function hasPayDetails(Employee $employee): bool
    {
        $hasRate = (int) $employee->basic_salary > 0 || (int) $employee->daily_rate > 0;

        return $hasRate && filled($employee->payment_type);
    }

    protected function hasGovernmentIds(Employee $employee): bool
    {
        return filled($employee->sss_number)
            && filled($employee->philhealth_number)
            && filled($employee->hdmf_number)
            && filled($employee->tin_number);
    }

    protected function groupDetailsComplete(?PayrollGroup $group): bool
    {
        if ($group === null) {
            return false;
        }

        $frequency = (string) $group->frequency;
        $isSemiMonthly = $frequency === PayrollGroup::FREQUENCY_SEMI_MONTHLY;

        if (! in_array($frequency, [
            PayrollGroup::FREQUENCY_MONTHLY,
            PayrollGroup::FREQUENCY_SEMI_MONTHLY,
            PayrollGroup::FREQUENCY_WEEKLY,
            PayrollGroup::FREQUENCY_DAILY,
        ], true)) {
            return false;
        }

        return (int) $group->days_year > 0
            && (int) $group->hours_day > 0
            && (int) $group->first_pay_date > 0
            && (! $isSemiMonthly || (int) $group->second_pay_date > 0);
    }

    protected function groupDetailsMeta(?PayrollGroup $group): ?string
    {
        if ($group === null) {
            return 'Waiting for a payroll group';
        }

        if ($this->groupDetailsComplete($group)) {
            return ucfirst((string) $group->frequency).' · '.$group->days_year.' days/year · '.$group->hours_day.' hrs/day';
        }

        $missing = [];

        if ((int) $group->days_year <= 0) {
            $missing[] = 'working days per year';
        }

        if ((int) $group->hours_day <= 0) {
            $missing[] = 'hours per day';
        }

        if ((int) $group->first_pay_date <= 0) {
            $missing[] = 'first pay date';
        }

        if ($group->frequency === PayrollGroup::FREQUENCY_SEMI_MONTHLY && (int) $group->second_pay_date <= 0) {
            $missing[] = 'second pay date';
        }

        return $missing === [] ? null : 'Missing '.implode(', ', $missing);
    }

    protected function statutorySchedulesConfigured(): bool
    {
        $keys = ['sss_schedule', 'phil_health_schedule', 'hdmf_schedule'];

        $configured = Setting::query()
            ->whereIn('key', $keys)
            ->get()
            ->filter(fn (Setting $setting) => filled($setting->value))
            ->count();

        return $configured === count($keys);
    }

    protected function companyProfileCount(): int
    {
        return Setting::query()
            ->whereIn('key', ['tin', 'sss', 'phil_health', 'hdmf'])
            ->get()
            ->filter(fn (Setting $setting) => filled($setting->value))
            ->count();
    }

    protected function settingIsTruthy(string $key): bool
    {
        $setting = Setting::query()->where('key', $key)->orderByDesc('id')->first();

        if ($setting === null) {
            return false;
        }

        return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
    }

    protected function periodLabel(PayrollDate $period): string
    {
        return $period->attendance_date_start?->format('M j').' – '.$period->attendance_date_end?->format('M j');
    }

    protected function payrollLabel(Payroll $payroll): string
    {
        $range = $payroll->date_from && $payroll->date_to
            ? $payroll->date_from->format('M j').' – '.$payroll->date_to->format('M j')
            : $payroll->payroll_date?->format('M j, Y');

        return trim(($range ?? 'Payroll').' · '.str_replace('_', ' ', (string) $payroll->status));
    }

    protected function plural(int $count, string $noun): string
    {
        return $count.' '.$noun.($count === 1 ? '' : 's');
    }

    /**
     * @return array{0: bool, 1: string}
     */
    protected function statutoryStatus(int $year): array
    {
        if (! Schema::hasTable('statutory_sources')) {
            return [false, 'Statutory sources table missing'];
        }

        $statuses = DB::table('statutory_sources')
            ->whereYear('effectivity', '<=', $year)
            ->pluck('status');

        if ($statuses->isEmpty()) {
            return [false, 'No statutory source records'];
        }

        $provisional = $statuses->filter(fn ($status) => $status !== 'verified')->count();

        return $provisional > 0
            ? [false, $provisional.' of '.$statuses->count().' statutory records are provisional']
            : [true, $statuses->count().' statutory records verified'];
    }

    protected function wageOrderCount(int $year): int
    {
        if (! Schema::hasTable('statutory_min_wage')) {
            return 0;
        }

        return DB::table('statutory_min_wage')
            ->where('effective_from', '<=', now()->endOfYear())
            ->where(function ($query) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()->startOfYear());
            })
            ->count();
    }

    protected function link(string $label, ?string $route, ?array $parameters = null, ?string $method = null): array
    {
        return [
            'label' => $label,
            'url' => $route === null ? null : route($route, $parameters ?? []),
            'method' => $method,
        ];
    }

    /**
     * Builds a single checklist item.
     *
     * @return array{key: string, title: string, description: string, status: string, required: bool, meta: string|null, action: array<string, mixed>|null}
     */
    protected function item(
        string $key,
        string $title,
        string $description,
        bool $complete,
        bool $required,
        ?string $meta = null,
        ?array $action = null,
        ?string $status = null,
    ): array {
        if ($status === null) {
            $status = $complete ? 'complete' : ($required ? 'pending' : 'optional');
        }

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'required' => $required,
            'meta' => $meta,
            'action' => $action,
        ];
    }
}
