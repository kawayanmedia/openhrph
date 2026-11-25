<?php

namespace App\Services\Payroll\Engine\Legacy;

use App\Jurisdictions\Contracts\PayrollPolicy;
use App\Jurisdictions\Philippines\Statutory\Bir\TaxBracket;
use App\Jurisdictions\Philippines\Statutory\Bir\WithholdingTaxTable;
use App\Jurisdictions\Philippines\Statutory\ContributionSchedule;
use App\Jurisdictions\Philippines\Statutory\Gsis\GsisSchedule;
use App\Jurisdictions\Philippines\Statutory\PagIbig\PagIbigSchedule;
use App\Jurisdictions\Philippines\Statutory\PayFrequency;
use App\Jurisdictions\Philippines\Statutory\PhilHealth\PhilHealthSchedule;
use App\Jurisdictions\Philippines\Statutory\Sss\SssBracket;
use App\Jurisdictions\Philippines\Statutory\Sss\SssSchedule;
use App\Jurisdictions\Support\PayrollPolicyData;
use App\Models\Compensation;
use App\Models\Timesheet;
use App\Sectors\SectorManager;
use App\Services\Payroll\Computation\BenefitFlags;
use App\Services\Payroll\Computation\Config;
use App\Services\Payroll\Computation\DeMinimisBenefit;
use App\Services\Payroll\Computation\Earning;
use App\Services\Payroll\Computation\Employee;
use App\Services\Payroll\Computation\Holiday;
use App\Services\Payroll\Computation\MinimumWage;
use App\Services\Payroll\Computation\Rules;
use App\Services\Payroll\Computation\TimesheetLine;
use App\Services\Payroll\Computation\YtdTotals;
use App\Services\Payroll\Engine\PayrollEngineException;
use App\Services\Payroll\Statutory\ComponentResolver;
use App\Support\Installation\Installation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads a payroll run and its inputs from the database.
 *
 * Mirrors internal/db/load.go so the PHP driver computes from exactly the rows
 * the Go engine would read.
 */
final class LegacyInput
{
    /**
     * @param  list<Employee>  $employees
     */
    public function __construct(
        public Run $run,
        public Config $config,
        public array $employees,
    ) {}

    public static function load(int $payrollId): self
    {
        $row = DB::selectOne(
            "select p.id, p.company_id, p.payroll_group_id, coalesce(p.status, 'open') as status,
                    coalesce(p.cut_off, '') as cut_off, p.date_from, p.date_to, p.payroll_date,
                    coalesce(p.type, 'regular') as type, p.meta
             from payrolls p
             where p.id = ? and p.deleted_at is null",
            [$payrollId],
        );

        if ($row === null) {
            throw new PayrollEngineException("payroll {$payrollId} not found");
        }

        $meta = [];

        if (is_string($row->meta) && $row->meta !== '') {
            $decoded = json_decode($row->meta, true);
            $meta = is_array($decoded) ? array_map(static fn ($value): string => (string) $value, $decoded) : [];
        } elseif (is_array($row->meta)) {
            $meta = array_map(static fn ($value): string => (string) $value, $row->meta);
        }

        $type = (string) $row->type |> trim(...) |> strtolower(...);

        $run = new Run(
            payrollID: (int) $row->id,
            companyID: (int) $row->company_id,
            groupID: (int) $row->payroll_group_id,
            status: (string) $row->status,
            type: $type === '' ? Config::RUN_REGULAR : $type,
            dateFrom: self::date($row->date_from),
            dateTo: self::date($row->date_to),
            payrollDate: self::date($row->payroll_date),
            meta: $meta,
        );

        // A payroll without a cut-off used to skip every first/last-pay
        // schedule silently. Derive the cut-off from the payroll date instead.
        $run->cutoff = self::normaliseCutoff((string) $row->cut_off, $run->payrollDate);

        $config = self::loadConfig($run);
        $employees = self::loadEmployees($run, $config);

        return new self($run, $config, self::filterEmployees($run, $employees));
    }

    public static function normaliseCutoff(string $cutoff, string $payrollDate): string
    {
        $normalised = ContributionSchedule::fromString($cutoff)->value;

        if ($normalised !== ContributionSchedule::EVERY_PAY->value) {
            return $normalised;
        }

        if (trim($cutoff) !== '' && trim($cutoff) !== '0') {
            return $normalised;
        }

        $day = (int) substr($payrollDate, 8, 2);

        return $day <= 15
            ? ContributionSchedule::FIRST_PAY->value
            : ContributionSchedule::LAST_PAY->value;
    }

    /**
     * Narrow a run to the employees it is allowed to pay. Final-pay runs only
     * settle separated employees, and any run may restrict itself to an
     * explicit employee_ids list in its meta.
     *
     * @param  list<Employee>  $employees
     * @return list<Employee>
     */
    public static function filterEmployees(Run $run, array $employees): array
    {
        $allowed = [];

        if (trim($run->meta['employee_ids'] ?? '') !== '') {
            foreach (explode(',', $run->meta['employee_ids']) as $part) {
                if (is_numeric(trim($part))) {
                    $allowed[(int) trim($part)] = true;
                }
            }
        }

        $filtered = [];

        foreach ($employees as $employee) {
            if ($allowed !== [] && ! isset($allowed[$employee->id])) {
                continue;
            }

            if ($run->type === Config::RUN_FINAL_PAY && $employee->separatedOn === '') {
                continue;
            }

            $filtered[] = $employee;
        }

        return $filtered;
    }

    public static function loadConfig(Run $run): Config
    {
        $config = new Config(
            frequency: Config::FREQUENCY_SEMI_MONTHLY,
            cutoff: $run->cutoff,
            runType: $run->type,
            meta: $run->meta,
            daysPerYear: 261,
            hoursPerDay: 8,
            sssBrackets: SssSchedule::preset2025(),
            philHealth: PhilHealthSchedule::default(),
            pagIbig: PagIbigSchedule::default(),
            gsis: GsisSchedule::default(),
            taxBrackets: WithholdingTaxTable::train(PayFrequency::SEMI_MONTHLY),
            annualTaxBrackets: WithholdingTaxTable::train(PayFrequency::MONTHLY),
            payWithoutTimesheet: true,
            payUnworkedRegularHolidays: true,
            sssBase: 'basic',
            philHealthBase: 'basic',
            pagIbigBase: 'basic',
            gsisBase: 'basic',
            payrollDate: $run->payrollDate,
        );

        $sectorPolicy = app(SectorManager::class)->forCompany($run->companyID);
        $config->sector = $sectorPolicy->sector();
        $config->socialInsuranceAgency = $sectorPolicy->socialInsuranceAgency();
        $config->leaveConversionTaxableDays = $sectorPolicy->leaveConversionTaxableDays();
        $config->isGovernment = $config->sector->isGovernment();
        $config->countryCode = Installation::countryCode();
        $config->statutoryDriver = Installation::statutoryDriver($config->countryCode);
        $config->policy = self::loadPolicy($run, $config->countryCode);

        $groupQuery = DB::table('payroll_groups')
            ->where('id', $run->groupID)
            ->where('company_id', $run->companyID);

        if (Schema::hasColumn('payroll_groups', 'deleted_at')) {
            $groupQuery->whereNull('deleted_at');
        }

        $groupColumns = array_values(array_filter(
            ['frequency', 'days_year', 'hours_day', 'sss_schedule', 'phil_health_schedule', 'hdmf_schedule', 'tax_schedule', 'compute_undertime'],
            static fn (string $column): bool => Schema::hasColumn('payroll_groups', $column),
        ));

        $group = $groupColumns === [] ? null : $groupQuery->first($groupColumns);

        $config->frequency = PayFrequency::fromString(
            $group === null ? null : ($group->frequency ?? 'semi-monthly'),
        )->value;

        if ($group !== null) {
            if ((int) ($group->days_year ?? 0) > 0) {
                $config->daysPerYear = (int) $group->days_year;
            }

            if ((int) ($group->hours_day ?? 0) > 0) {
                $config->hoursPerDay = (int) $group->hours_day;
            }

            $config->sssSchedule = ContributionSchedule::fromString($group->sss_schedule ?? '')->value;
            $config->philHealthSchedule = ContributionSchedule::fromString($group->phil_health_schedule ?? '')->value;
            $config->pagIbigSchedule = ContributionSchedule::fromString($group->hdmf_schedule ?? '')->value;
            $config->taxSchedule = ContributionSchedule::fromString($group->tax_schedule ?? '')->value;
            $config->computeUndertime = (bool) ($group->compute_undertime ?? false);
        } else {
            $config->sssSchedule = ContributionSchedule::EVERY_PAY->value;
            $config->philHealthSchedule = ContributionSchedule::EVERY_PAY->value;
            $config->pagIbigSchedule = ContributionSchedule::EVERY_PAY->value;
            $config->taxSchedule = ContributionSchedule::EVERY_PAY->value;
        }

        if (in_array($config->runType, [Config::RUN_THIRTEENTH_MONTH, Config::RUN_ANNUAL_ADJUSTMENT], true)) {
            // 13th-month and annual adjustment runs are standalone: no
            // attendance or regular statutory contributions.
            $config->payWithoutTimesheet = false;
        }

        $settings = self::loadSettings($run->companyID);

        if (array_key_exists('auto_absent_no_timesheet', $settings)) {
            $config->payWithoutTimesheet = ! self::isTruthy($settings['auto_absent_no_timesheet']);
        }

        if (array_key_exists('days_year_no_weeked', $settings)) {
            $days = (int) trim($settings['days_year_no_weeked']);

            if ($days > 0) {
                $config->daysPerYear = $days;
            }
        }

        if (array_key_exists('pay_unworked_regular_holidays', $settings)) {
            $config->payUnworkedRegularHolidays = self::isTruthy($settings['pay_unworked_regular_holidays']);
        }

        $value = trim($settings['sss_base'] ?? '');

        if ($value !== '') {
            $config->sssBase = $value;
        }

        $value = trim($settings['phil_health_base'] ?? '');

        if ($value !== '') {
            $config->philHealthBase = $value;
        }

        $value = trim($settings['hdmf_base'] ?? '');

        if ($value !== '') {
            $config->pagIbigBase = $value;
        }

        $value = trim($settings['gsis_base'] ?? '');

        if ($value !== '') {
            $config->gsisBase = $value;
        }

        if ($config->statutoryDriver === 'ph') {
            $config->sssBrackets = self::loadSssBrackets($run);
            $config->philHealth = self::loadPhilHealth($run, $config->philHealth);
            $config->pagIbig = self::loadPagIbig($run, $config->pagIbig);

            // Only a GSIS-covered employer needs the GSIS schedule; an SSS
            // payroll must not fail on an unimported table it never uses.
            if ($config->socialInsuranceAgency === 'gsis') {
                $config->gsis = self::loadGsis($run, $config->gsis);
            }

            $config->taxBrackets = self::loadTaxBrackets($run, $config->frequency);
            $config->annualTaxBrackets = self::loadTaxBrackets($run, Config::FREQUENCY_MONTHLY);
            $config->minimumWage = self::loadMinimumWage($run);
            $config->minimumWages = self::loadMinimumWages($run);
            $config->defaultRegion = (string) (Installation::countryProfile()['minimum_wage_region'] ?? 'NCR');
            $config->defaultIndustry = 'NON-AGRICULTURE';
            $config->deMinimis = self::loadDeMinimis($run);
        } else {
            $sector = strtolower($config->sector->value);

            $config->components = ComponentResolver::definitions(
                $run->companyID,
                $config->countryCode,
                $sector,
                $run->payrollDate,
            );

            if ($config->components === []) {
                throw new PayrollEngineException(
                    "statutory components do not cover the payroll date: no active {$config->countryCode} components are configured",
                );
            }

            $config->taxBrackets = [];
            $config->annualTaxBrackets = [];
            $config->minimumWage = 0;
            $config->minimumWages = [];
            $config->deMinimis = [];
            $config->defaultRegion = '';
            $config->defaultIndustry = '';
        }

        $config->holidays = self::loadHolidays($run);

        if ($config->statutoryDriver === 'ph' && $config->taxBrackets === []) {
            $config->taxBrackets = WithholdingTaxTable::train(PayFrequency::fromString($config->frequency));
        }

        return $config;
    }

    private static function loadPolicy(Run $run, string $countryCode): PayrollPolicy
    {
        if ($run->payrollID > 0 && Schema::hasTable('payrolls') && Schema::hasColumn('payrolls', 'rules_snapshot')) {
            $snapshot = DB::table('payrolls')->where('id', $run->payrollID)->value('rules_snapshot');

            if (is_string($snapshot) && $snapshot !== '') {
                $decoded = json_decode($snapshot, true);

                if (is_array($decoded)) {
                    return PayrollPolicyData::fromArray($decoded);
                }
            }
        }

        return Installation::jurisdiction($countryCode)->payrollPolicy();
    }

    /**
     * @return array<string, string>
     */
    public static function loadSettings(int $companyID): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        $settings = [];

        foreach (DB::table('settings')->where('company_id', $companyID)->get(['key', 'value']) as $row) {
            $settings[(string) $row->key] = (string) ($row->value ?? '');
        }

        return $settings;
    }

    public static function isTruthy(?string $value): bool
    {
        return in_array((string) $value |> trim(...) |> strtolower(...), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return list<Employee>
     */
    public static function loadEmployees(Run $run, Config $config): array
    {
        $query = DB::table('employees as e')
            ->where('e.company_id', $run->companyID)
            ->where('e.payroll_group_id', $run->groupID);

        if (Schema::hasColumn('employees', 'deleted_at')) {
            $query->whereNull('e.deleted_at');
        }

        $wanted = [
            'id', 'employee_number', 'first_name', 'last_name',
            'basic_salary', 'daily_rate', 'tax_status', 'dependents',
            'payment_type', 'is_monday_friday', 'is_minimum_wage',
            'rest_day_pay', 'regular_holiday_pay', 'special_holiday_pay',
            'premium_holiday_pay', 'regular_holiday_special_holiday_pay',
            'overtime_pay', 'night_differential', 'deminimis',
            'date_hired', 'date_resigned',
            'total_vacation_leave', 'total_sick_leave',
            'region', 'industry',
        ];

        $columns = array_map(
            static fn (string $column): string => "e.{$column}",
            array_values(array_filter(
                $wanted,
                static fn (string $column): bool => Schema::hasColumn('employees', $column),
            )),
        );

        if (Schema::hasTable('offices') && Schema::hasColumn('employees', 'office_id')) {
            $query->leftJoin('offices as o', 'o.id', '=', 'e.office_id');
            $columns[] = 'o.region as office_region';
            $columns[] = 'o.industry as office_industry';
        }

        $rows = $query->orderBy('e.id')->get($columns);

        $employees = [];
        $index = [];

        foreach ($rows as $row) {
            $paymentType = (string) ($row->payment_type ?? '') |> trim(...) |> strtolower(...);

            $employee = new Employee(
                id: (int) $row->id,
                region: (string) (trim((string) ($row->region ?? '')) !== '' ? $row->region : ($row->office_region ?? '')),
                industry: (string) (trim((string) ($row->industry ?? '')) !== '' ? $row->industry : ($row->office_industry ?? '')),
                number: (string) ($row->employee_number ?? ''),
                name: trim(trim((string) ($row->first_name ?? '')).' '.trim((string) ($row->last_name ?? ''))),
                monthlyBasic: (int) ($row->basic_salary ?? 0),
                dailyRate: (int) ($row->daily_rate ?? 0),
                taxStatus: (string) ($row->tax_status ?? ''),
                dependents: (int) trim((string) ($row->dependents ?? '0')),
                paymentType: $paymentType === '' ? Employee::PAYMENT_MONTHLY : $paymentType,
                isMondayFriday: (int) ($row->is_monday_friday ?? 1) === 1,
                isMinimumWage: (int) ($row->is_minimum_wage ?? 0) === 1,
                benefits: new BenefitFlags(
                    restDayPay: (int) ($row->rest_day_pay ?? 0) === 1,
                    regularHolidayPay: (int) ($row->regular_holiday_pay ?? 0) === 1,
                    specialHolidayPay: (int) ($row->special_holiday_pay ?? 0) === 1,
                    premiumHolidayPay: (int) ($row->premium_holiday_pay ?? 0) === 1,
                    regularHolidaySpecialHolidayPay: (int) ($row->regular_holiday_special_holiday_pay ?? 0) === 1,
                    overtimePay: (int) ($row->overtime_pay ?? 0) === 1,
                    nightDifferential: (int) ($row->night_differential ?? 0) === 1,
                    deMinimis: (int) ($row->deminimis ?? 0) === 1,
                ),
                hiredOn: self::date($row->date_hired ?? ''),
                separatedOn: self::date($row->date_resigned ?? ''),
                leaveBalanceDays: (float) ($row->total_vacation_leave ?? 0) + (float) ($row->total_sick_leave ?? 0),
            );

            if ($employee->isMinimumWage) {
                $employee->minimumWage = self::resolveMinimumWage($config, $employee);

                if ($employee->minimumWage === 0) {
                    $employee->minimumWage = $config->minimumWage;
                }
            }

            $index[$employee->id] = count($employees);
            $employees[] = $employee;
        }

        if ($employees === []) {
            return [];
        }

        self::loadTimesheets($run, $employees, $index);
        self::loadCompensations($run, $config, $employees, $index);
        self::loadDeductions($run, $employees, $index);
        self::loadLoans($run, $config, $employees, $index);
        self::loadYtd($run, $employees);
        self::applyProration($config, $employees);

        return $employees;
    }

    /**
     * Scale earnings flagged is_prorated by the attendance ratio.
     *
     * @param  list<Employee>  $employees
     */
    public static function applyProration(Config $config, array $employees): void
    {
        $periods = $config->periodsPerMonth();
        $expectedDays = $config->daysPerYear / 12 / $periods;
        $expectedHours = $expectedDays * $config->hoursPerDay;

        foreach ($employees as $employee) {
            $workedDays = 0.0;
            $workedHours = 0.0;

            foreach ($employee->timesheets as $line) {
                if ($line->absent || ($line->hours <= 0 && ! $line->paidLeave && ! $line->leave)) {
                    continue;
                }

                $workedDays++;
                $workedHours += $line->hours + $line->overtime;
            }

            $ratioFor = static function (string $by) use ($workedDays, $workedHours, $expectedDays, $expectedHours): float {
                if (strcasecmp($by, 'hour') === 0 && $expectedHours > 0) {
                    return self::clampRatio($workedHours / $expectedHours);
                }

                if ($expectedDays > 0) {
                    return self::clampRatio($workedDays / $expectedDays);
                }

                return 1.0;
            };

            foreach ([$employee->allowances, $employee->compensations] as $list) {
                foreach ($list as $earning) {
                    if (! $earning->prorated) {
                        continue;
                    }

                    $earning->amount = Money::mul($earning->amount, $ratioFor($earning->proratedBy));
                }
            }
        }
    }

    public static function clampRatio(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /**
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $index
     */
    public static function loadTimesheets(Run $run, array $employees, array $index): void
    {
        if (! Schema::hasTable('timesheets')) {
            return;
        }

        if (self::loadSnapshotTimesheets($run, $employees, $index)) {
            return;
        }

        $employeeIds = array_keys($index);

        $query = DB::table('timesheets')->whereIn('employee_id', $employeeIds);

        if (Schema::hasColumn('timesheets', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $columns = array_values(array_filter(
            [
                'employee_id', 'log_date', 'day_type', 'day_rate_code',
                'is_absent', 'is_leave', 'is_paid_leave', 'leave_half_day',
                'actual_regular', 'actual_ot', 'actual_nt', 'actual_nt_ot', 'ut',
                'regular', 'ot', 'nt', 'rule_version',
                'logs', 'am_login', 'am_logout', 'pm_login', 'pm_logout', 'am_out_morning',
            ],
            static fn (string $column): bool => Schema::hasColumn('timesheets', $column),
        ));

        $rows = $query->get($columns);

        foreach ($rows as $row) {
            $position = $index[(int) $row->employee_id] ?? null;

            if ($position === null) {
                continue;
            }

            $date = self::date($row->log_date);

            if ($date < $run->dateFrom || $date > $run->dateTo) {
                continue;
            }

            $hours = (float) ($row->actual_regular ?? 0) / 100;
            $overtime = (float) ($row->actual_ot ?? 0) / 100;
            $night = (float) ($row->actual_nt ?? 0) / 100;
            $nightOT = (float) ($row->actual_nt_ot ?? 0) / 100;
            $undertime = (float) ($row->ut ?? 0) / 100;
            $legacy = false;

            $legacyRegular = (float) ($row->regular ?? 0);
            $legacyOT = (float) ($row->ot ?? 0);

            // Legacy rows stored premium-equivalent hours in regular/ot/nt
            // rather than the actual_* buckets. Reconstruct the ordinary hours
            // instead of reading them as money.
            if ($hours === 0.0 && $legacyRegular !== 0.0) {
                $hours = $legacyRegular / 100;
                $legacy = true;
            }

            // Punch-only rows have neither bucket yet. Reconstruct the worked
            // span from the punch list (or the mirrored am/pm columns) so the
            // day is not paid as zero before attendance is computed.
            if ($hours === 0.0 && $legacyRegular === 0.0) {
                $worked = WorkedHours::fromLogs(self::decodeLogs($row->logs ?? null));

                if ($worked <= 0.0) {
                    $worked = WorkedHours::fromColumns(
                        self::text($row->am_login ?? null),
                        self::text($row->am_logout ?? null),
                        self::text($row->pm_login ?? null),
                        self::text($row->pm_logout ?? null),
                        self::text($row->am_out_morning ?? null),
                    );
                }

                if ($worked > 0.0) {
                    if ($overtime === 0.0 && $legacyOT > 0.0) {
                        $overtime = $legacyOT / 100;
                    }

                    $hours = max(0.0, $worked - $overtime);
                }
            }

            // Night OT is stored inside actual_nt as well; keep the buckets
            // disjoint so the 10% differential is not paid twice.
            $ordinaryNight = max(0.0, $night - $nightOT);

            // New-engine rows already include night overtime in actual_ot.
            // Legacy rows carried it only in actual_nt_ot, so add it there.
            $overtimeIncludesNight = (int) ($row->rule_version ?? 0) >= Timesheet::RULE_VERSION_NIGHT_OT_INCLUDED;

            $employees[$position]->timesheets[] = new TimesheetLine(
                date: $date,
                dayType: Rules::effectiveDayType(
                    (string) ($row->day_type ?? ''),
                    self::text($row->day_rate_code ?? null),
                ),
                hours: $hours,
                overtime: $overtimeIncludesNight ? $overtime : $overtime + $nightOT,
                night: $ordinaryNight,
                nightOT: $nightOT,
                undertime: $undertime,
                absent: (int) ($row->is_absent ?? 0) === 1,
                leave: (int) ($row->is_leave ?? 0) === 1,
                paidLeave: (int) ($row->is_paid_leave ?? 0) === 1,
                leaveFraction: (int) ($row->leave_half_day ?? 0) === 1 ? 0.5 : 1.0,
                legacyHours: $legacy,
            );
        }
    }

    /**
     * Read the frozen attendance lines when this run has been locked.
     *
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $index
     */
    private static function loadSnapshotTimesheets(Run $run, array $employees, array $index): bool
    {
        if ($run->payrollID <= 0 || ! Schema::hasTable('payroll_attendance_snapshots')) {
            return false;
        }

        $query = DB::table('payroll_attendance_snapshots')->where('payroll_id', $run->payrollID);

        if (Schema::hasColumn('payroll_attendance_snapshots', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $rows = $query->orderBy('log_date')->get();

        if ($rows->isEmpty()) {
            return false;
        }

        foreach ($rows as $row) {
            $position = $index[(int) $row->employee_id] ?? null;

            if ($position === null) {
                continue;
            }

            $night = max(0.0, (float) $row->night_minutes - (float) $row->night_ot_minutes) / 60;

            $employees[$position]->timesheets[] = new TimesheetLine(
                date: self::date($row->log_date),
                dayType: Rules::effectiveDayType(
                    (string) ($row->day_type ?? ''),
                    self::text($row->day_rate_code ?? null),
                ),
                hours: (float) $row->regular_minutes / 60,
                overtime: (float) $row->overtime_minutes / 60,
                night: $night,
                nightOT: (float) $row->night_ot_minutes / 60,
                undertime: (float) $row->undertime_minutes / 60,
                absent: (bool) $row->is_absent,
                leave: (bool) $row->is_leave,
                paidLeave: (bool) $row->is_paid_leave,
                leaveFraction: (float) ($row->leave_fraction ?? 1),
                legacyHours: false,
            );
        }

        return true;
    }

    public static function frequencyApplies(string $frequency, Run $run, Config $config): bool
    {
        if (trim($frequency) === '') {
            return true;
        }

        return ContributionSchedule::fromString($frequency)
            ->divisor(PayFrequency::fromString($config->frequency), $run->cutoff) > 0;
    }

    /**
     * Older rows predate the is_recurring column and are recurring.
     */
    private static function deductionIsRecurring(object $row): bool
    {
        return ! property_exists($row, 'is_recurring') || (bool) ($row->is_recurring ?? true);
    }

    /**
     * A one-time deduction applies in the first run whose period reaches its
     * effectivity date; every later run skips it.
     */
    private static function oneTimeDeductionApplies(string $validFrom, Run $run): bool
    {
        if (trim($validFrom) === '' || trim($run->dateFrom) === '') {
            return true;
        }

        return substr($validFrom, 0, 10) >= substr($run->dateFrom, 0, 10);
    }

    /**
     * Load every extra earning from the consolidated employees_compensations
     * table and split it into the payslip bucket named by `compensations.group`.
     *
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $index
     */
    public static function loadCompensations(Run $run, Config $config, array $employees, array $index): void
    {
        if (! Schema::hasTable('employees_compensations') || ! Schema::hasTable('compensations')) {
            return;
        }

        $rows = DB::table('employees_compensations as ec')
            ->leftJoin('compensations as c', 'c.id', '=', 'ec.compensation_id')
            ->whereNull('ec.deleted_at')
            ->where('ec.company_id', $run->companyID)
            ->whereIn('ec.employee_id', array_keys($index))
            ->get([
                'ec.employee_id', 'ec.amount', 'ec.is_taxable', 'ec.is_active',
                'ec.frequency', 'ec.valid_date_from', 'ec.valid_date_to',
                'ec.is_prorated', 'ec.prorated_by', 'ec.release_date', 'ec.status',
                'ec.is_special',
                'c.code', 'c.name', 'c.group', 'c.tax_class_code', 'c.is_taxable as default_taxable',
                'c.maximum_non_tax',
            ]);

        foreach ($rows as $row) {
            $position = $index[(int) $row->employee_id] ?? null;

            if ($position === null
                || (int) ($row->is_active ?? 1) !== 1
                || strcasecmp((string) ($row->status ?? ''), 'cancelled') === 0) {
                continue;
            }

            $name = (string) ($row->name ?? '');
            $code = (string) ($row->code ?? '') ?: self::slug($name);
            $amount = (int) ($row->amount ?? 0);
            $taxable = (int) ($row->is_taxable ?? $row->default_taxable ?? 1) === 1;
            $prorated = (int) ($row->is_prorated ?? 0) === 1;
            $proratedBy = (string) ($row->prorated_by ?? '');
            $group = (string) ($row->group ?? '');
            $taxClass = (string) ($row->tax_class_code ?? '');

            if (strcasecmp($group, Compensation::GROUP_ALLOWANCE) === 0) {
                if (! self::validDates((string) ($row->valid_date_from ?? ''), (string) ($row->valid_date_to ?? ''), $run)) {
                    continue;
                }

                // A special assignment is released once, in the period that
                // contains its release date; a recurring one follows the
                // payment schedule it was given.
                if ((int) ($row->is_special ?? 0) === 1) {
                    if (! self::dateWithinPeriod($row->release_date ?? null, $run)) {
                        continue;
                    }
                } elseif (! self::frequencyApplies((string) ($row->frequency ?? ''), $run, $config)) {
                    continue;
                }

                $employees[$position]->allowances[] = new Earning(
                    code: $code,
                    name: $name,
                    kind: Earning::KIND_ALLOWANCE,
                    amount: $amount,
                    taxable: $taxable,
                    prorated: $prorated,
                    proratedBy: $proratedBy,
                    taxClass: $taxClass,
                );

                continue;
            }

            if (strcasecmp($group, Compensation::GROUP_BONUS) === 0) {
                if (! self::dateWithinPeriod($row->release_date ?? null, $run)) {
                    continue;
                }

                $employees[$position]->bonuses[] = new Earning(
                    code: $code,
                    name: $name,
                    kind: Earning::KIND_BONUS,
                    amount: $amount,
                    taxable: $taxable,
                    nonTaxableCeiling: (int) ($row->maximum_non_tax ?? 0),
                    taxClass: $taxClass,
                );

                continue;
            }

            if (strcasecmp($group, Compensation::GROUP_COMMISSION) === 0) {
                if (! self::dateWithinPeriod($row->release_date ?? null, $run)) {
                    continue;
                }

                $employees[$position]->commissions[] = new Earning(
                    code: $code,
                    name: $name,
                    kind: Earning::KIND_COMMISSION,
                    amount: $amount,
                    taxable: $taxable,
                    nonTaxableCeiling: (int) ($row->maximum_non_tax ?? 0),
                    taxClass: $taxClass,
                );

                continue;
            }

            if (! self::validDates((string) ($row->valid_date_from ?? ''), (string) ($row->valid_date_to ?? ''), $run)
                || ! self::frequencyApplies((string) ($row->frequency ?? ''), $run, $config)) {
                continue;
            }

            $employees[$position]->compensations[] = new Earning(
                code: $code,
                name: $name,
                kind: Earning::KIND_COMPENSATION,
                amount: $amount,
                taxable: $taxable,
                prorated: $prorated,
                proratedBy: $proratedBy,
                taxClass: $taxClass,
            );
        }
    }

    /**
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $index
     */
    public static function loadDeductions(Run $run, array $employees, array $index): void
    {
        if (! Schema::hasTable('employees_deductions')) {
            return;
        }

        $deductionColumns = [
            'code' => 'd.code',
            'calculation' => 'd.calculation',
            'rate' => 'd.rate',
            'base' => 'd.base',
            'reduces_taxable_income' => 'd.reduces_taxable_income',
        ];

        $columns = ['ed.employee_id', 'd.name', 'ed.amount', 'ed.frequency', 'ed.valid_date_from', 'ed.valid_date_to'];

        if (Schema::hasColumn('employees_deductions', 'is_recurring')) {
            $columns[] = 'ed.is_recurring';
        }

        foreach ($deductionColumns as $column => $expression) {
            if (Schema::hasColumn('deductions', $column)) {
                $columns[] = $expression;
            }
        }

        $rows = DB::table('employees_deductions as ed')
            ->leftJoin('deductions as d', 'd.id', '=', 'ed.deduction_id')
            ->whereNull('ed.deleted_at')
            ->where('ed.company_id', $run->companyID)
            ->whereIn('ed.employee_id', array_keys($index))
            ->get($columns);

        foreach ($rows as $row) {
            $position = $index[(int) $row->employee_id] ?? null;

            if ($position === null || ! self::validDates((string) ($row->valid_date_from ?? ''), (string) ($row->valid_date_to ?? ''), $run)) {
                continue;
            }

            // A non-recurring deduction is taken once, in the run that covers
            // its effectivity date.
            if (! self::deductionIsRecurring($row) && ! self::oneTimeDeductionApplies((string) ($row->valid_date_from ?? ''), $run)) {
                continue;
            }

            $employees[$position]->deductions[] = new Earning(
                code: (string) ($row->code ?? ''),
                name: (string) ($row->name ?? ''),
                kind: Earning::KIND_DEDUCTION,
                amount: (int) ($row->amount ?? 0),
                taxable: false,
                calculation: (string) ($row->calculation ?? 'fixed'),
                rate: (float) ($row->rate ?? 0),
                basis: (string) ($row->base ?? ''),
                reducesTaxableIncome: (bool) ($row->reduces_taxable_income ?? false),
            );
        }
    }

    /**
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $index
     */
    public static function loadLoans(Run $run, Config $config, array $employees, array $index): void
    {
        if (! Schema::hasTable('employees_loans')) {
            return;
        }

        $rows = DB::table('employees_loans as el')
            ->leftJoin('loans as l', 'l.id', '=', 'el.loan_id')
            ->whereNull('el.deleted_at')
            ->where('el.company_id', $run->companyID)
            ->whereIn('el.employee_id', array_keys($index))
            ->get([
                'el.employee_id', 'l.code', 'l.name', 'el.monthly_amortization', 'el.balance', 'el.scheme',
                'el.repayment_start_date', 'el.repayment_end_date', 'el.is_active',
            ]);

        foreach ($rows as $row) {
            $position = $index[(int) $row->employee_id] ?? null;
            $active = (int) ($row->is_active ?? 1) === 1;

            if ($position === null || ! $active
                || ! self::validDates((string) ($row->repayment_start_date ?? ''), (string) ($row->repayment_end_date ?? ''), $run)) {
                continue;
            }

            $scheme = (string) ($row->scheme ?? '');

            if ($scheme !== '' && ! self::frequencyApplies($scheme, $run, $config)) {
                continue;
            }

            $amount = Money::splitTruncating((int) ($row->monthly_amortization ?? 0), $config->periodsPerMonth())[0] ?? 0;
            $balance = (float) ($row->balance ?? 0);

            if ($balance > 0 && $amount > (int) $balance) {
                $amount = (int) $balance;
            }

            $employees[$position]->loans[] = new Earning(
                code: (string) ($row->code ?? ''),
                name: (string) ($row->name ?? ''),
                kind: Earning::KIND_LOAN,
                amount: $amount,
            );
        }
    }

    /**
     * @param  list<Employee>  $employees
     */
    public static function loadYtd(Run $run, array $employees): void
    {
        if (! Schema::hasTable('employee_payroll_ytd')) {
            return;
        }

        $rows = DB::table('employee_payroll_ytd')
            ->where('company_id', $run->companyID)
            ->where('year', $run->year())
            ->whereIn('employee_id', array_map(static fn (Employee $employee): int => $employee->id, $employees))
            ->get([
                'employee_id', 'basic_earned', 'gross_earned', 'taxable_income', 'tax_withheld',
                'contributions', 'thirteenth_month_paid', 'other_benefits',
            ]);

        $byEmployee = [];

        foreach ($rows as $row) {
            $byEmployee[(int) $row->employee_id] = new YtdTotals(
                year: $run->year(),
                basicEarned: (int) ($row->basic_earned ?? 0),
                grossEarned: (int) ($row->gross_earned ?? 0),
                taxableIncome: (int) ($row->taxable_income ?? 0),
                taxWithheld: (int) ($row->tax_withheld ?? 0),
                contributions: (int) ($row->contributions ?? 0),
                thirteenthMonthPaid: (int) ($row->thirteenth_month_paid ?? 0),
                otherBenefits: (int) ($row->other_benefits ?? 0),
            );
        }

        foreach ($employees as $employee) {
            if (isset($byEmployee[$employee->id])) {
                $employee->ytd = $byEmployee[$employee->id];
            }
        }

        self::loadDeMinimisUsage($run, $employees);
    }

    /**
     * The de minimis exemption already consumed in each employee's current
     * month, semester and year.
     *
     * @param  list<Employee>  $employees
     */
    private static function loadDeMinimisUsage(Run $run, array $employees): void
    {
        if (! Schema::hasTable('employee_payroll_ytd_de_minimis')) {
            return;
        }

        $index = [];

        foreach ($employees as $employee) {
            $index[$employee->id] = $employee;
        }

        $rows = DB::table('employee_payroll_ytd_de_minimis')
            ->where('company_id', $run->companyID)
            ->where('year', $run->year())
            ->whereIn('employee_id', array_keys($index))
            ->get(['employee_id', 'period', 'code', 'exempt_centavos']);

        foreach ($rows as $row) {
            $employee = $index[(int) $row->employee_id] ?? null;

            if ($employee === null) {
                continue;
            }

            $key = ((string) $row->code |> trim(...) |> strtolower(...)).'|'.(string) $row->period;
            $employee->ytd->deMinimis[$key] = (int) ($row->exempt_centavos ?? 0);
        }

        // The current payroll's own earlier run is already in the ledger. Take
        // it back out so re-processing starts from where it did the first time.
        if (! Schema::hasColumn('payroll_details', 'de_minimis_exempt')) {
            return;
        }

        $posted = DB::table('payroll_details')
            ->where('payroll_id', $run->payrollID)
            ->where('company_id', $run->companyID)
            ->whereNull('deleted_at')
            ->get(['employee_id', 'de_minimis_exempt']);

        foreach ($posted as $detail) {
            $employee = $index[(int) $detail->employee_id] ?? null;
            $usage = json_decode((string) ($detail->de_minimis_exempt ?? ''), true);

            if ($employee === null || ! is_array($usage)) {
                continue;
            }

            foreach ($usage as $key => $amount) {
                $employee->ytd->deMinimis[(string) $key] = max(
                    0,
                    (int) ($employee->ytd->deMinimis[(string) $key] ?? 0) - (int) $amount,
                );
            }
        }
    }

    public static function validDates(string $from, string $to, Run $run): bool
    {
        if ($from !== '' && self::isDate($from) && $from > $run->dateTo) {
            return false;
        }

        if ($to !== '' && self::isDate($to) && $to < $run->dateFrom) {
            return false;
        }

        return true;
    }

    public static function slug(string $value): string
    {
        return str_replace(['-', ' '], '_', $value |> trim(...) |> strtolower(...));
    }

    public static function taxableTaxOption(string $taxOption): bool
    {
        $option = $taxOption |> trim(...) |> strtolower(...);

        if (str_contains($option, 'non') || str_contains($option, 'exempt')) {
            return false;
        }

        return true;
    }

    /**
     * @return list<Holiday>
     */
    public static function loadHolidays(Run $run): array
    {
        if (! Schema::hasTable('holidays')) {
            return [];
        }

        $holidays = [];

        $rows = DB::table('holidays')
            ->whereNull('deleted_at')
            ->where('company_id', $run->companyID)
            ->whereBetween('date', [$run->dateFrom, $run->dateTo])
            ->orderBy('date')
            ->get(['date', 'code']);

        foreach ($rows as $row) {
            $code = (string) ($row->code ?? '');

            if ($code !== '') {
                $holidays[] = new Holiday(date: self::date($row->date), code: $code);
            }
        }

        return $holidays;
    }

    /**
     * @return list<DeMinimisBenefit>
     */
    public static function loadDeMinimis(Run $run): array
    {
        if (! Schema::hasTable('statutory_de_minimis')) {
            return self::defaultDeMinimis();
        }

        $query = DB::table('statutory_de_minimis')
            ->where('country_code', Installation::countryCode())
            ->where(function ($query) use ($run): void {
                $query->whereNull('company_id')->orWhere('company_id', $run->companyID);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $run->payrollDate);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $run->payrollDate);
            })
            ->orderBy('code')
            ->orderByDesc('effective_from');

        $columns = ['code', 'ceiling_centavos'];

        if (Schema::hasColumn('statutory_de_minimis', 'basis')) {
            $columns[] = 'basis';
        }

        if (Schema::hasColumn('statutory_de_minimis', 'percentage')) {
            $columns[] = 'percentage';
        }

        $benefits = [];

        foreach ($query->get($columns) as $row) {
            $basis = (string) ($row->basis ?? '');
            $percentage = (int) ($row->percentage ?? 0);

            $benefits[] = new DeMinimisBenefit(
                code: (string) ($row->code ?? ''),
                ceiling: (int) ($row->ceiling_centavos ?? 0),
                basis: $basis === '' ? DeMinimisBenefit::BASIS_MONTHLY : $basis,
                percentage: $percentage > 0 ? $percentage : DeMinimisBenefit::DEFAULT_HOURLY_OT_PERCENTAGE,
            );
        }

        return $benefits === [] ? self::defaultDeMinimis() : $benefits;
    }

    /**
     * The RR 2-98 ceilings, as amended by RR 29-2025, used when the table is
     * empty.
     *
     * @return list<DeMinimisBenefit>
     */
    public static function defaultDeMinimis(): array
    {
        return [
            new DeMinimisBenefit('rice', Money::fromPesos(2500), DeMinimisBenefit::BASIS_MONTHLY),
            new DeMinimisBenefit('clothing', Money::fromPesos(8000), DeMinimisBenefit::BASIS_ANNUAL),
            new DeMinimisBenefit('medical', Money::fromPesos(2000), DeMinimisBenefit::BASIS_SEMESTER),
            new DeMinimisBenefit('laundry', Money::fromPesos(400), DeMinimisBenefit::BASIS_MONTHLY),
            new DeMinimisBenefit('meal', Money::fromPesos(0), DeMinimisBenefit::BASIS_HOURLY_OT),
            new DeMinimisBenefit('medical_assistance', Money::fromPesos(12000), DeMinimisBenefit::BASIS_ANNUAL),
            new DeMinimisBenefit('achievement_award', Money::fromPesos(12000), DeMinimisBenefit::BASIS_ANNUAL),
            new DeMinimisBenefit('gifts', Money::fromPesos(6000), DeMinimisBenefit::BASIS_ANNUAL),
            new DeMinimisBenefit('cba_productivity', Money::fromPesos(12000), DeMinimisBenefit::BASIS_ANNUAL),
        ];
    }

    /**
     * @return list<SssBracket>
     */
    public static function loadSssBrackets(Run $run): array
    {
        if (! Schema::hasTable('statutory_sss_brackets')) {
            return SssSchedule::preset2025();
        }

        $rows = DB::table('statutory_sss_brackets')
            ->where('country_code', Installation::countryCode())
            ->where(function ($query) use ($run): void {
                $query->whereNull('company_id')->orWhere('company_id', $run->companyID);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $run->payrollDate);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $run->payrollDate);
            })
            ->orderBy('msc_from')
            ->get(['msc_from', 'msc_to', 'msc', 'ee', 'er', 'ec', 'wisp_ee', 'wisp_er']);

        $brackets = [];

        foreach ($rows as $row) {
            $brackets[] = new SssBracket(
                mscFrom: (int) ($row->msc_from ?? 0),
                mscTo: (int) ($row->msc_to ?? 0),
                msc: (int) ($row->msc ?? 0),
                ee: (int) ($row->ee ?? 0),
                er: (int) ($row->er ?? 0),
                ec: (int) ($row->ec ?? 0),
                wispEE: (int) ($row->wisp_ee ?? 0),
                wispER: (int) ($row->wisp_er ?? 0),
            );
        }

        if ($brackets === []) {
            throw new PayrollEngineException(
                "statutory table does not cover the payroll date: statutory_sss_brackets has no schedule effective on {$run->payrollDate}",
            );
        }

        return $brackets;
    }

    public static function loadPhilHealth(Run $run, PhilHealthSchedule $fallback): PhilHealthSchedule
    {
        if (! Schema::hasTable('statutory_philhealth')) {
            return $fallback;
        }

        $row = DB::table('statutory_philhealth')
            ->where('country_code', Installation::countryCode())
            ->where(function ($query) use ($run): void {
                $query->whereNull('company_id')->orWhere('company_id', $run->companyID);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $run->payrollDate);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $run->payrollDate);
            })
            ->orderByDesc('effective_from')
            ->first(['rate', 'floor_centavos', 'ceiling_centavos']);

        if ($row === null) {
            throw new PayrollEngineException(
                "statutory table does not cover the payroll date: statutory_philhealth has no schedule effective on {$run->payrollDate}",
            );
        }

        return new PhilHealthSchedule(
            rate: (float) ($row->rate ?? 0),
            floor: (int) ($row->floor_centavos ?? 0),
            ceiling: (int) ($row->ceiling_centavos ?? 0),
        );
    }

    public static function loadPagIbig(Run $run, PagIbigSchedule $fallback): PagIbigSchedule
    {
        if (! Schema::hasTable('statutory_pagibig')) {
            return $fallback;
        }

        $row = DB::table('statutory_pagibig')
            ->where('country_code', Installation::countryCode())
            ->where(function ($query) use ($run): void {
                $query->whereNull('company_id')->orWhere('company_id', $run->companyID);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $run->payrollDate);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $run->payrollDate);
            })
            ->orderByDesc('effective_from')
            ->first(['rate', 'low_rate', 'low_threshold_centavos', 'max_fund_salary_centavos']);

        if ($row === null) {
            throw new PayrollEngineException(
                "statutory table does not cover the payroll date: statutory_pagibig has no schedule effective on {$run->payrollDate}",
            );
        }

        return new PagIbigSchedule(
            rate: (float) ($row->rate ?? 0),
            lowRate: (float) ($row->low_rate ?? 0),
            lowThreshold: (int) ($row->low_threshold_centavos ?? 0),
            maxFundSalary: (int) ($row->max_fund_salary_centavos ?? 0),
        );
    }

    public static function loadGsis(Run $run, GsisSchedule $fallback): GsisSchedule
    {
        if (! Schema::hasTable('statutory_gsis')) {
            return $fallback;
        }

        $row = DB::table('statutory_gsis')
            ->where('country_code', Installation::countryCode())
            ->where(function ($query) use ($run): void {
                $query->whereNull('company_id')->orWhere('company_id', $run->companyID);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $run->payrollDate);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $run->payrollDate);
            })
            ->orderByDesc('effective_from')
            ->first(['employee_rate', 'employer_rate', 'max_amc_limit_centavos', 'above_limit_employee_rate']);

        if ($row === null) {
            throw new PayrollEngineException(
                "statutory table does not cover the payroll date: statutory_gsis has no schedule effective on {$run->payrollDate}",
            );
        }

        return new GsisSchedule(
            employeeRate: (float) ($row->employee_rate ?? 0),
            employerRate: (float) ($row->employer_rate ?? 0),
            maxAmcLimit: (int) ($row->max_amc_limit_centavos ?? 0),
            aboveLimitEmployeeRate: (float) ($row->above_limit_employee_rate ?? 0),
        );
    }

    /**
     * @return list<TaxBracket>
     */
    public static function loadTaxBrackets(Run $run, string $frequency): array
    {
        if (! Schema::hasTable('statutory_tax_brackets')) {
            return WithholdingTaxTable::train(PayFrequency::fromString($frequency));
        }

        $rows = DB::table('statutory_tax_brackets')
            ->where('country_code', Installation::countryCode())
            ->where('frequency', $frequency)
            ->where(function ($query) use ($run): void {
                $query->whereNull('company_id')->orWhere('company_id', $run->companyID);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $run->payrollDate);
            })
            ->where(function ($query) use ($run): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $run->payrollDate);
            })
            ->orderBy('from_centavos')
            ->get(['from_centavos', 'to_centavos', 'base_tax_centavos', 'rate']);

        $brackets = [];

        foreach ($rows as $row) {
            $brackets[] = new TaxBracket(
                from: (int) ($row->from_centavos ?? 0),
                to: (int) ($row->to_centavos ?? 0),
                baseTax: (int) ($row->base_tax_centavos ?? 0),
                rate: (float) ($row->rate ?? 0),
            );
        }

        if ($brackets === []) {
            throw new PayrollEngineException(
                "statutory table does not cover the payroll date: statutory_tax_brackets has no schedule effective on {$run->payrollDate}",
            );
        }

        return $brackets;
    }

    /**
     * @return list<MinimumWage>
     */
    public static function loadMinimumWages(Run $run): array
    {
        $wages = [];

        if (Schema::hasTable('statutory_min_wage')) {
            $columns = ['region', 'amount_centavos', 'effective_from'];

            foreach (['industry', 'wage_order', 'effective_to'] as $column) {
                if (Schema::hasColumn('statutory_min_wage', $column)) {
                    $columns[] = $column;
                }
            }

            $rows = DB::table('statutory_min_wage')
                ->where('country_code', Installation::countryCode())
                ->where(function ($query) use ($run): void {
                    $query->whereNull('company_id')->orWhere('company_id', $run->companyID);
                })
                ->where(function ($query) use ($run): void {
                    $query->whereNull('effective_from')->orWhere('effective_from', '<=', $run->payrollDate);
                })
                ->when(
                    Schema::hasColumn('statutory_min_wage', 'effective_to'),
                    fn ($query) => $query->where(function ($query) use ($run): void {
                        $query->whereNull('effective_to')->orWhere('effective_to', '>=', $run->payrollDate);
                    }),
                )
                ->orderBy('region')
                ->orderBy('effective_from')
                ->get($columns);

            foreach ($rows as $row) {
                $wages[] = new MinimumWage(
                    region: (string) ($row->region ?? ''),
                    industry: (string) ($row->industry ?? ''),
                    wageOrder: (string) ($row->wage_order ?? ''),
                    amount: (int) ($row->amount_centavos ?? 0),
                    effectiveFrom: self::date($row->effective_from ?? ''),
                    effectiveTo: self::date($row->effective_to ?? ''),
                );
            }
        }

        if ($wages === [] && Schema::hasTable('minimum_wage_rates')) {
            $rows = DB::table('minimum_wage_rates')
                ->whereNull('deleted_at')
                ->where('company_id', $run->companyID)
                ->get(['region', 'industry', 'wage_start']);

            foreach ($rows as $row) {
                $wages[] = new MinimumWage(
                    region: (string) ($row->region ?? ''),
                    industry: (string) ($row->industry ?? ''),
                    amount: (int) ($row->wage_start ?? 0),
                );
            }
        }

        return $wages;
    }

    public static function resolveMinimumWage(Config $config, Employee $employee): int
    {
        $region = $employee->region |> trim(...) |> strtoupper(...);
        $industry = $employee->industry |> trim(...) |> strtoupper(...);

        if ($region === '') {
            $region = $config->defaultRegion |> trim(...) |> strtoupper(...);
        }

        if ($industry === '') {
            $industry = $config->defaultIndustry |> trim(...) |> strtoupper(...);
        }

        $best = 0;
        $bestScore = -1;
        $bestFrom = '';

        foreach ($config->minimumWages as $wage) {
            $wageRegion = $wage->region |> trim(...) |> strtoupper(...);
            $wageIndustry = $wage->industry |> trim(...) |> strtoupper(...);

            $score = match (true) {
                $wageRegion === $region && $wageIndustry === $industry => 4,
                $wageRegion === $region && ($wageIndustry === '' || $wageIndustry === 'GENERAL') => 3,
                $wageRegion === $region => 2,
                $wageRegion === '' || $wageIndustry === 'GENERAL' => 1,
                default => -1,
            };

            if ($score < 0) {
                continue;
            }

            if ($score > $bestScore
                || ($score === $bestScore && ($wage->effectiveFrom > $bestFrom || ($wage->effectiveFrom === $bestFrom && $wage->amount > $best)))) {
                $best = $wage->amount;
                $bestScore = $score;
                $bestFrom = $wage->effectiveFrom;
            }
        }

        return $best;
    }

    public static function loadMinimumWage(Run $run): int
    {
        if (Schema::hasTable('statutory_min_wage')) {
            $row = DB::table('statutory_min_wage')
                ->where(function ($query) use ($run): void {
                    $query->whereNull('company_id')->orWhere('company_id', $run->companyID);
                })
                ->where(function ($query) use ($run): void {
                    $query->whereNull('effective_from')->orWhere('effective_from', '<=', $run->payrollDate);
                })
                ->orderByDesc('effective_from')
                ->first(['amount_centavos']);

            if ($row !== null && (int) ($row->amount_centavos ?? 0) > 0) {
                return (int) $row->amount_centavos;
            }
        }

        if (Schema::hasTable('minimum_wage_rates')) {
            $amount = (int) DB::table('minimum_wage_rates')
                ->whereNull('deleted_at')
                ->where('company_id', $run->companyID)
                ->max('wage_start');

            if ($amount > 0) {
                return $amount;
            }
        }

        return 0;
    }

    public static function dateWithinPeriod(mixed $value, Run $run): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $date = self::date($value);

        if (! self::isDate($date)) {
            return true;
        }

        return $date >= $run->dateFrom && $date <= $run->dateTo;
    }

    public static function date(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }

    public static function isDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    /**
     * @return array<int, mixed>|null
     */
    private static function decodeLogs(mixed $logs): ?array
    {
        if (is_array($logs)) {
            return $logs;
        }

        if (! is_string($logs) || trim($logs) === '') {
            return null;
        }

        $decoded = json_decode($logs, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
