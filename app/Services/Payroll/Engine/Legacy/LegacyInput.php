<?php

namespace App\Services\Payroll\Engine\Legacy;

use App\Services\Payroll\Computation\BenefitFlags;
use App\Services\Payroll\Computation\Config;
use App\Services\Payroll\Computation\DeMinimisBenefit;
use App\Services\Payroll\Computation\Earning;
use App\Services\Payroll\Computation\Employee;
use App\Services\Payroll\Computation\Holiday;
use App\Services\Payroll\Computation\MinimumWage;
use App\Services\Payroll\Computation\Money;
use App\Services\Payroll\Computation\PagIbigConfig;
use App\Services\Payroll\Computation\PhilHealthConfig;
use App\Services\Payroll\Computation\SssBracket;
use App\Services\Payroll\Computation\Statutory;
use App\Services\Payroll\Computation\TaxBracket;
use App\Services\Payroll\Computation\TimesheetLine;
use App\Services\Payroll\Computation\YtdTotals;
use App\Services\Payroll\Engine\PayrollEngineException;
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

        $type = strtolower(trim((string) $row->type));

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
        $normalised = Statutory::normalizeSchedule($cutoff);

        if ($normalised !== Statutory::SCHEDULE_EVERY_PAY) {
            return $normalised;
        }

        if (trim($cutoff) !== '' && trim($cutoff) !== '0') {
            return $normalised;
        }

        $day = (int) substr($payrollDate, 8, 2);

        return $day <= 15 ? Statutory::SCHEDULE_FIRST_PAY : Statutory::SCHEDULE_LAST_PAY;
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
            sssBrackets: Statutory::sss2025Schedule(),
            philHealth: Statutory::defaultPhilHealthConfig(),
            pagIbig: Statutory::defaultPagIbigConfig(),
            taxBrackets: Statutory::trainTaxBrackets(Config::FREQUENCY_SEMI_MONTHLY),
            annualTaxBrackets: Statutory::trainTaxBrackets(Config::FREQUENCY_MONTHLY),
            payWithoutTimesheet: true,
            payUnworkedRegularHolidays: true,
            sssBase: 'basic',
            philHealthBase: 'basic',
            pagIbigBase: 'basic',
        );

        $groupQuery = DB::table('payroll_groups')
            ->where('id', $run->groupID)
            ->where('company_id', $run->companyID);

        if (Schema::hasColumn('payroll_groups', 'deleted_at')) {
            $groupQuery->whereNull('deleted_at');
        }

        $groupColumns = array_values(array_filter(
            ['frequency', 'days_year', 'hours_day', 'sss_schedule', 'phil_health_schedule', 'hdmf_schedule', 'tax_schedule'],
            static fn (string $column): bool => Schema::hasColumn('payroll_groups', $column),
        ));

        $group = $groupColumns === [] ? null : $groupQuery->first($groupColumns);

        $config->frequency = Statutory::normalizeFrequency(
            $group === null ? null : ($group->frequency ?? 'semi-monthly'),
        );

        if ($group !== null) {
            if ((int) ($group->days_year ?? 0) > 0) {
                $config->daysPerYear = (int) $group->days_year;
            }

            if ((int) ($group->hours_day ?? 0) > 0) {
                $config->hoursPerDay = (int) $group->hours_day;
            }

            $config->sssSchedule = Statutory::normalizeSchedule($group->sss_schedule ?? '');
            $config->philHealthSchedule = Statutory::normalizeSchedule($group->phil_health_schedule ?? '');
            $config->pagIbigSchedule = Statutory::normalizeSchedule($group->hdmf_schedule ?? '');
            $config->taxSchedule = Statutory::normalizeSchedule($group->tax_schedule ?? '');
        } else {
            $config->sssSchedule = Statutory::normalizeSchedule('');
            $config->philHealthSchedule = Statutory::normalizeSchedule('');
            $config->pagIbigSchedule = Statutory::normalizeSchedule('');
            $config->taxSchedule = Statutory::normalizeSchedule('');
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

        $config->sssBrackets = self::loadSssBrackets($run);
        $config->philHealth = self::loadPhilHealth($run, $config->philHealth);
        $config->pagIbig = self::loadPagIbig($run, $config->pagIbig);
        $config->taxBrackets = self::loadTaxBrackets($run, $config->frequency);
        $config->annualTaxBrackets = self::loadTaxBrackets($run, Config::FREQUENCY_MONTHLY);

        $config->minimumWage = self::loadMinimumWage($run);
        $config->minimumWages = self::loadMinimumWages($run);
        $config->defaultRegion = 'NCR';
        $config->defaultIndustry = 'NON-AGRICULTURE';
        $config->holidays = self::loadHolidays($run);
        $config->deMinimis = self::loadDeMinimis($run);

        if ($config->taxBrackets === []) {
            $config->taxBrackets = Statutory::trainTaxBrackets($config->frequency);
        }

        return $config;
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
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
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
            $paymentType = strtolower(trim((string) ($row->payment_type ?? '')));

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
        self::loadAllowances($run, $employees, $index);
        self::loadCompensations($run, $config, $employees, $index);
        self::loadBonuses($run, $employees, $index);
        self::loadCommissions($run, $employees, $index);
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

        $employeeIds = array_keys($index);

        $query = DB::table('timesheets')->whereIn('employee_id', $employeeIds);

        if (Schema::hasColumn('timesheets', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $columns = array_values(array_filter(
            [
                'employee_id', 'log_date', 'day_type', 'is_absent', 'is_leave', 'is_paid_leave',
                'actual_regular', 'actual_ot', 'actual_nt', 'actual_nt_ot',
                'regular', 'ot', 'nt',
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
            $legacy = false;

            $legacyRegular = (float) ($row->regular ?? 0);
            $legacyOT = (float) ($row->ot ?? 0);
            $legacyNight = (float) ($row->nt ?? 0);

            // Legacy rows stored premium-equivalent hours in regular/ot/nt
            // rather than the actual_* buckets. Reconstruct the ordinary hours
            // instead of reading them as money.
            if ($hours === 0.0 && $overtime === 0.0 && $night === 0.0 && $nightOT === 0.0
                && ($legacyRegular !== 0.0 || $legacyOT !== 0.0 || $legacyNight !== 0.0)) {
                $hours = $legacyRegular / 100;
                $legacy = true;
            }

            // Night OT is stored inside actual_nt as well; keep the buckets
            // disjoint so the 10% differential is not paid twice.
            $ordinaryNight = max(0.0, $night - $nightOT);

            $employees[$position]->timesheets[] = new TimesheetLine(
                date: $date,
                dayType: (string) ($row->day_type ?? ''),
                hours: $hours,
                overtime: $overtime + $nightOT,
                night: $ordinaryNight,
                nightOT: $nightOT,
                absent: (int) ($row->is_absent ?? 0) === 1,
                leave: (int) ($row->is_leave ?? 0) === 1,
                paidLeave: (int) ($row->is_paid_leave ?? 0) === 1,
                legacyHours: $legacy,
            );
        }
    }

    public static function frequencyApplies(string $frequency, Run $run, Config $config): bool
    {
        if (trim($frequency) === '') {
            return true;
        }

        return Statutory::divisor(Statutory::normalizeSchedule($frequency), $config->frequency, $run->cutoff) > 0;
    }

    /**
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $index
     */
    public static function loadAllowances(Run $run, array $employees, array $index): void
    {
        if (! Schema::hasTable('employees_allowances')) {
            return;
        }

        $rows = DB::table('employees_allowances as ea')
            ->leftJoin('allowances as a', 'a.id', '=', 'ea.allowance_id')
            ->whereNull('ea.deleted_at')
            ->where('ea.company_id', $run->companyID)
            ->whereIn('ea.employee_id', array_keys($index))
            ->get([
                'ea.employee_id', 'a.name', 'ea.amount', 'a.tax_option', 'ea.frequency',
                'ea.valid_date_from', 'ea.valid_date_to', 'ea.is_prorated', 'ea.prorated_by',
            ]);

        foreach ($rows as $row) {
            $position = $index[(int) $row->employee_id] ?? null;

            if ($position === null || ! self::validDates((string) ($row->valid_date_from ?? ''), (string) ($row->valid_date_to ?? ''), $run)) {
                continue;
            }

            $name = (string) ($row->name ?? '');

            $employees[$position]->allowances[] = new Earning(
                code: self::slug($name),
                name: $name,
                kind: Earning::KIND_ALLOWANCE,
                amount: (int) ($row->amount ?? 0),
                taxable: self::taxableTaxOption((string) ($row->tax_option ?? '')),
                prorated: (int) ($row->is_prorated ?? 0) === 1,
                proratedBy: (string) ($row->prorated_by ?? ''),
            );
        }
    }

    /**
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $index
     */
    public static function loadCompensations(Run $run, Config $config, array $employees, array $index): void
    {
        if (! Schema::hasTable('employees_compensations')) {
            return;
        }

        $rows = DB::table('employees_compensations as ec')
            ->leftJoin('compensations as c', 'c.id', '=', 'ec.compensation_id')
            ->whereNull('ec.deleted_at')
            ->where('ec.company_id', $run->companyID)
            ->whereIn('ec.employee_id', array_keys($index))
            ->get([
                'ec.employee_id', 'c.name', 'ec.amount', 'ec.is_taxable', 'c.is_taxable as default_taxable',
                'ec.frequency', 'ec.valid_date_from', 'ec.valid_date_to',
                'ec.is_prorated', 'ec.prorated_by', 'ec.is_active',
            ]);

        foreach ($rows as $row) {
            $position = $index[(int) $row->employee_id] ?? null;
            $active = (int) ($row->is_active ?? 1) === 1;

            if ($position === null || ! $active
                || ! self::validDates((string) ($row->valid_date_from ?? ''), (string) ($row->valid_date_to ?? ''), $run)
                || ! self::frequencyApplies((string) ($row->frequency ?? ''), $run, $config)) {
                continue;
            }

            $employees[$position]->compensations[] = new Earning(
                name: (string) ($row->name ?? ''),
                kind: Earning::KIND_COMPENSATION,
                amount: (int) ($row->amount ?? 0),
                taxable: (int) ($row->is_taxable ?? $row->default_taxable ?? 1) === 1,
                prorated: (int) ($row->is_prorated ?? 0) === 1,
                proratedBy: (string) ($row->prorated_by ?? ''),
            );
        }
    }

    /**
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $index
     */
    public static function loadBonuses(Run $run, array $employees, array $index): void
    {
        if (! Schema::hasTable('employees_bonuses')) {
            return;
        }

        $rows = DB::table('employees_bonuses as eb')
            ->leftJoin('bonuses as b', 'b.id', '=', 'eb.bonus_id')
            ->whereNull('eb.deleted_at')
            ->where('eb.company_id', $run->companyID)
            ->whereIn('eb.employee_id', array_keys($index))
            ->get([
                'eb.employee_id', 'b.name', 'eb.amount', 'b.is_taxable',
                'b.maximum_non_tax', 'eb.release_date', 'eb.status',
            ]);

        foreach ($rows as $row) {
            $position = $index[(int) $row->employee_id] ?? null;

            if ($position === null || strcasecmp((string) ($row->status ?? ''), 'cancelled') === 0) {
                continue;
            }

            if (! self::dateWithinPeriod($row->release_date ?? null, $run)) {
                continue;
            }

            $employees[$position]->bonuses[] = new Earning(
                name: (string) ($row->name ?? ''),
                kind: Earning::KIND_BONUS,
                amount: (int) ($row->amount ?? 0),
                taxable: (int) ($row->is_taxable ?? 1) === 1,
                nonTaxableCeiling: (int) ($row->maximum_non_tax ?? 0),
            );
        }
    }

    /**
     * @param  list<Employee>  $employees
     * @param  array<int, int>  $index
     */
    public static function loadCommissions(Run $run, array $employees, array $index): void
    {
        if (! Schema::hasTable('employees_commissions')) {
            return;
        }

        $rows = DB::table('employees_commissions as ecm')
            ->leftJoin('commissions as c', 'c.id', '=', 'ecm.commission_id')
            ->whereNull('ecm.deleted_at')
            ->where('ecm.company_id', $run->companyID)
            ->whereIn('ecm.employee_id', array_keys($index))
            ->get([
                'ecm.employee_id', 'c.name', 'ecm.amount', 'c.is_taxable',
                'c.maximum_non_tax', 'ecm.release_date', 'ecm.status',
            ]);

        foreach ($rows as $row) {
            $position = $index[(int) $row->employee_id] ?? null;

            if ($position === null || strcasecmp((string) ($row->status ?? ''), 'cancelled') === 0) {
                continue;
            }

            if (! self::dateWithinPeriod($row->release_date ?? null, $run)) {
                continue;
            }

            $employees[$position]->commissions[] = new Earning(
                name: (string) ($row->name ?? ''),
                kind: Earning::KIND_COMMISSION,
                amount: (int) ($row->amount ?? 0),
                taxable: (int) ($row->is_taxable ?? 1) === 1,
                nonTaxableCeiling: (int) ($row->maximum_non_tax ?? 0),
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

        $rows = DB::table('employees_deductions as ed')
            ->leftJoin('deductions as d', 'd.id', '=', 'ed.deduction_id')
            ->whereNull('ed.deleted_at')
            ->where('ed.company_id', $run->companyID)
            ->whereIn('ed.employee_id', array_keys($index))
            ->get([
                'ed.employee_id', 'd.name', 'ed.amount', 'ed.frequency',
                'ed.valid_date_from', 'ed.valid_date_to',
            ]);

        foreach ($rows as $row) {
            $position = $index[(int) $row->employee_id] ?? null;

            if ($position === null || ! self::validDates((string) ($row->valid_date_from ?? ''), (string) ($row->valid_date_to ?? ''), $run)) {
                continue;
            }

            $employees[$position]->deductions[] = new Earning(
                name: (string) ($row->name ?? ''),
                kind: Earning::KIND_DEDUCTION,
                amount: (int) ($row->amount ?? 0),
                taxable: false,
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
                'el.employee_id', 'l.name', 'el.monthly_amortization', 'el.balance', 'el.scheme',
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
        return str_replace(['-', ' '], '_', strtolower(trim($value)));
    }

    public static function taxableTaxOption(string $taxOption): bool
    {
        $option = strtolower(trim($taxOption));

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

        $benefits = [];

        foreach ($query->get($columns) as $row) {
            $basis = (string) ($row->basis ?? '');

            $benefits[] = new DeMinimisBenefit(
                code: (string) ($row->code ?? ''),
                ceiling: (int) ($row->ceiling_centavos ?? 0),
                basis: $basis === '' ? DeMinimisBenefit::BASIS_MONTHLY : $basis,
            );
        }

        return $benefits === [] ? self::defaultDeMinimis() : $benefits;
    }

    /**
     * The RR 2-98 ceilings used when the table is empty.
     *
     * @return list<DeMinimisBenefit>
     */
    public static function defaultDeMinimis(): array
    {
        return [
            new DeMinimisBenefit('rice', Money::fromPesos(2000), DeMinimisBenefit::BASIS_MONTHLY),
            new DeMinimisBenefit('clothing', Money::fromPesos(6000), DeMinimisBenefit::BASIS_ANNUAL),
            new DeMinimisBenefit('medical', Money::fromPesos(1500), DeMinimisBenefit::BASIS_SEMESTER),
            new DeMinimisBenefit('laundry', Money::fromPesos(300), DeMinimisBenefit::BASIS_MONTHLY),
            new DeMinimisBenefit('meal', Money::fromPesos(0), DeMinimisBenefit::BASIS_HOURLY_OT),
            new DeMinimisBenefit('medical_assistance', Money::fromPesos(10000), DeMinimisBenefit::BASIS_ANNUAL),
        ];
    }

    /**
     * @return list<SssBracket>
     */
    public static function loadSssBrackets(Run $run): array
    {
        if (! Schema::hasTable('statutory_sss_brackets')) {
            return Statutory::sss2025Schedule();
        }

        $rows = DB::table('statutory_sss_brackets')
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

    public static function loadPhilHealth(Run $run, PhilHealthConfig $fallback): PhilHealthConfig
    {
        if (! Schema::hasTable('statutory_philhealth')) {
            return $fallback;
        }

        $row = DB::table('statutory_philhealth')
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

        return new PhilHealthConfig(
            rate: (float) ($row->rate ?? 0),
            floor: (int) ($row->floor_centavos ?? 0),
            ceiling: (int) ($row->ceiling_centavos ?? 0),
        );
    }

    public static function loadPagIbig(Run $run, PagIbigConfig $fallback): PagIbigConfig
    {
        if (! Schema::hasTable('statutory_pagibig')) {
            return $fallback;
        }

        $row = DB::table('statutory_pagibig')
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

        return new PagIbigConfig(
            rate: (float) ($row->rate ?? 0),
            lowRate: (float) ($row->low_rate ?? 0),
            lowThreshold: (int) ($row->low_threshold_centavos ?? 0),
            maxFundSalary: (int) ($row->max_fund_salary_centavos ?? 0),
        );
    }

    /**
     * @return list<TaxBracket>
     */
    public static function loadTaxBrackets(Run $run, string $frequency): array
    {
        if (! Schema::hasTable('statutory_tax_brackets')) {
            return Statutory::trainTaxBrackets($frequency);
        }

        $rows = DB::table('statutory_tax_brackets')
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
        $region = strtoupper(trim($employee->region));
        $industry = strtoupper(trim($employee->industry));

        if ($region === '') {
            $region = strtoupper(trim($config->defaultRegion));
        }

        if ($industry === '') {
            $industry = strtoupper(trim($config->defaultIndustry));
        }

        $best = 0;
        $bestScore = -1;
        $bestFrom = '';

        foreach ($config->minimumWages as $wage) {
            $wageRegion = strtoupper(trim($wage->region));
            $wageIndustry = strtoupper(trim($wage->industry));

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
}
