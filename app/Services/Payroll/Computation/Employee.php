<?php

namespace App\Services\Payroll\Computation;

/**
 * The payroll input for one person.
 *
 * Mirrors model.Employee in payroll-engine/internal/model.
 */
final class Employee
{
    public const PAYMENT_MONTHLY = 'monthly';

    public const PAYMENT_DAILY = 'daily';

    /**
     * @param  list<TimesheetLine>  $timesheets
     * @param  list<Earning>  $allowances
     * @param  list<Earning>  $compensations
     * @param  list<Earning>  $bonuses
     * @param  list<Earning>  $commissions
     * @param  list<Earning>  $deductions
     * @param  list<Earning>  $loans
     */
    public function __construct(
        public int $id = 0,
        public string $region = '',
        public string $industry = '',
        public string $number = '',
        public string $name = '',
        public int $monthlyBasic = 0,
        public int $dailyRate = 0,
        public string $taxStatus = '',
        public int $dependents = 0,
        public string $paymentType = '',
        public bool $isMondayFriday = false,
        public bool $isMinimumWage = false,
        public int $minimumWage = 0,
        public BenefitFlags $benefits = new BenefitFlags,
        public string $hiredOn = '',
        public string $separatedOn = '',
        public float $leaveBalanceDays = 0.0,
        public YtdTotals $ytd = new YtdTotals,
        public array $timesheets = [],
        public array $allowances = [],
        public array $compensations = [],
        public array $bonuses = [],
        public array $commissions = [],
        public array $deductions = [],
        public array $loans = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            region: (string) ($data['region'] ?? ''),
            industry: (string) ($data['industry'] ?? ''),
            number: (string) ($data['number'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            monthlyBasic: (int) ($data['monthly_basic'] ?? 0),
            dailyRate: (int) ($data['daily_rate'] ?? 0),
            taxStatus: (string) ($data['tax_status'] ?? ''),
            dependents: (int) ($data['dependents'] ?? 0),
            paymentType: (string) ($data['payment_type'] ?? ''),
            isMondayFriday: (bool) ($data['is_monday_friday'] ?? false),
            isMinimumWage: (bool) ($data['is_minimum_wage'] ?? false),
            minimumWage: (int) ($data['minimum_wage'] ?? 0),
            benefits: BenefitFlags::fromArray((array) ($data['benefits'] ?? [])),
            hiredOn: (string) ($data['hired_on'] ?? ''),
            separatedOn: (string) ($data['separated_on'] ?? ''),
            leaveBalanceDays: (float) ($data['leave_balance_days'] ?? 0),
            ytd: YtdTotals::fromArray((array) ($data['ytd'] ?? [])),
            timesheets: array_map(
                static fn (array $line): TimesheetLine => TimesheetLine::fromArray($line),
                array_values((array) ($data['timesheets'] ?? [])),
            ),
            allowances: self::earnings($data['allowances'] ?? []),
            compensations: self::earnings($data['compensations'] ?? []),
            bonuses: self::earnings($data['bonuses'] ?? []),
            commissions: self::earnings($data['commissions'] ?? []),
            deductions: self::earnings($data['deductions'] ?? []),
            loans: self::earnings($data['loans'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'region' => $this->region,
            'industry' => $this->industry,
            'number' => $this->number,
            'name' => $this->name,
            'monthly_basic' => $this->monthlyBasic,
            'daily_rate' => $this->dailyRate,
            'tax_status' => $this->taxStatus,
            'dependents' => $this->dependents,
            'payment_type' => $this->paymentType,
            'is_monday_friday' => $this->isMondayFriday,
            'is_minimum_wage' => $this->isMinimumWage,
            'minimum_wage' => $this->minimumWage,
            'benefits' => $this->benefits->toArray(),
            'hired_on' => $this->hiredOn,
            'separated_on' => $this->separatedOn,
            'leave_balance_days' => $this->leaveBalanceDays,
            'ytd' => $this->ytd->toArray(),
            'timesheets' => array_map(static fn (TimesheetLine $line): array => $line->toArray(), $this->timesheets),
            'allowances' => self::earningsToArray($this->allowances),
            'compensations' => self::earningsToArray($this->compensations),
            'bonuses' => self::earningsToArray($this->bonuses),
            'commissions' => self::earningsToArray($this->commissions),
            'deductions' => self::earningsToArray($this->deductions),
            'loans' => self::earningsToArray($this->loans),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<Earning>
     */
    private static function earnings(array $rows): array
    {
        return array_map(
            static fn (array $row): Earning => Earning::fromArray($row),
            array_values($rows),
        );
    }

    /**
     * @param  list<Earning>  $earnings
     * @return list<array<string, mixed>>
     */
    private static function earningsToArray(array $earnings): array
    {
        return array_map(static fn (Earning $earning): array => $earning->toArray(), $earnings);
    }
}
