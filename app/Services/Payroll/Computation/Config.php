<?php

namespace App\Services\Payroll\Computation;

use App\Enums\Sector;
use App\Services\Statutory\Philippines\Bir\TaxBracket;
use App\Services\Statutory\Philippines\Gsis\GsisSchedule;
use App\Services\Statutory\Philippines\PagIbig\PagIbigSchedule;
use App\Services\Statutory\Philippines\PayFrequency;
use App\Services\Statutory\Philippines\PhilHealth\PhilHealthSchedule;
use App\Services\Statutory\Philippines\Sss\SssBracket;

/**
 * Everything the computation needs that is not employee-specific.
 *
 * Mirrors model.Config in payroll-engine/internal/model.
 */
final class Config
{
    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_SEMI_MONTHLY = 'semi-monthly';

    public const RUN_REGULAR = 'regular';

    public const RUN_THIRTEENTH_MONTH = 'thirteenth_month';

    public const RUN_FINAL_PAY = 'final_pay';

    public const RUN_ANNUAL_ADJUSTMENT = 'annual_adjustment';

    /**
     * @param  array<string, string>  $meta
     * @param  list<SssBracket>  $sssBrackets
     * @param  list<TaxBracket>  $taxBrackets
     * @param  list<TaxBracket>  $annualTaxBrackets
     * @param  list<MinimumWage>  $minimumWages
     * @param  list<DeMinimisBenefit>  $deMinimis
     * @param  list<Holiday>  $holidays
     */
    public function __construct(
        public string $frequency = self::FREQUENCY_SEMI_MONTHLY,
        public string $cutoff = '',
        public string $runType = self::RUN_REGULAR,
        public array $meta = [],
        public bool $isGovernment = false,
        public int $daysPerYear = 0,
        public int $hoursPerDay = 0,
        public string $sssSchedule = '',
        public string $philHealthSchedule = '',
        public string $pagIbigSchedule = '',
        public string $taxSchedule = '',
        public array $sssBrackets = [],
        public PhilHealthSchedule $philHealth = new PhilHealthSchedule,
        public PagIbigSchedule $pagIbig = new PagIbigSchedule,
        public GsisSchedule $gsis = new GsisSchedule,
        public array $taxBrackets = [],
        public array $annualTaxBrackets = [],
        public int $minimumWage = 0,
        public array $minimumWages = [],
        public string $defaultRegion = '',
        public string $defaultIndustry = '',
        public string $sssBase = '',
        public string $philHealthBase = '',
        public string $pagIbigBase = '',
        public string $gsisBase = '',
        public array $deMinimis = [],
        public array $holidays = [],
        public bool $payWithoutTimesheet = false,
        public bool $payUnworkedRegularHolidays = false,
        public string $countryCode = 'PH',
        public string $statutoryDriver = 'ph',
        public array $components = [],
        public Sector $sector = Sector::Private,
    ) {}

    public function periodsPerYear(): int
    {
        return PayFrequency::fromString($this->frequency)->periodsPerYear();
    }

    public function periodsPerMonth(): int
    {
        return PayFrequency::fromString($this->frequency)->periodsPerMonth();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $sector = Sector::tryFromValue(
            is_string($data['sector'] ?? null) ? $data['sector'] : null,
        ) ?? ((bool) ($data['is_government'] ?? false) ? Sector::Government : Sector::Private);

        return new self(
            frequency: (string) ($data['frequency'] ?? self::FREQUENCY_SEMI_MONTHLY),
            cutoff: (string) ($data['cutoff'] ?? ''),
            runType: (string) ($data['run_type'] ?? self::RUN_REGULAR),
            meta: array_map(static fn ($value): string => (string) $value, (array) ($data['meta'] ?? [])),
            isGovernment: $sector->isGovernment(),
            daysPerYear: (int) ($data['days_per_year'] ?? 0),
            hoursPerDay: (int) ($data['hours_per_day'] ?? 0),
            sssSchedule: (string) ($data['sss_schedule'] ?? ''),
            philHealthSchedule: (string) ($data['philhealth_schedule'] ?? ''),
            pagIbigSchedule: (string) ($data['pagibig_schedule'] ?? ''),
            taxSchedule: (string) ($data['tax_schedule'] ?? ''),
            sssBrackets: array_map(
                static fn (array $row): SssBracket => SssBracket::fromArray($row),
                array_values((array) ($data['sss_brackets'] ?? [])),
            ),
            philHealth: PhilHealthSchedule::fromArray((array) ($data['philhealth'] ?? [])),
            pagIbig: PagIbigSchedule::fromArray((array) ($data['pagibig'] ?? [])),
            gsis: GsisSchedule::fromArray((array) ($data['gsis'] ?? [])),
            taxBrackets: self::brackets($data['tax_brackets'] ?? []),
            annualTaxBrackets: self::brackets($data['annual_tax_brackets'] ?? []),
            minimumWage: (int) ($data['minimum_wage'] ?? 0),
            minimumWages: array_map(
                static fn (array $row): MinimumWage => MinimumWage::fromArray($row),
                array_values((array) ($data['minimum_wages'] ?? [])),
            ),
            defaultRegion: (string) ($data['default_region'] ?? ''),
            defaultIndustry: (string) ($data['default_industry'] ?? ''),
            sssBase: (string) ($data['sss_base'] ?? ''),
            philHealthBase: (string) ($data['philhealth_base'] ?? ''),
            pagIbigBase: (string) ($data['pagibig_base'] ?? ''),
            deMinimis: array_map(
                static fn (array $row): DeMinimisBenefit => DeMinimisBenefit::fromArray($row),
                array_values((array) ($data['de_minimis'] ?? [])),
            ),
            holidays: array_map(
                static fn (array $row): Holiday => Holiday::fromArray($row),
                array_values((array) ($data['holidays'] ?? [])),
            ),
            payWithoutTimesheet: (bool) ($data['pay_without_timesheet'] ?? false),
            payUnworkedRegularHolidays: (bool) ($data['pay_unworked_regular_holidays'] ?? false),
            countryCode: (string) ($data['country_code'] ?? 'PH'),
            statutoryDriver: (string) ($data['statutory_driver'] ?? 'ph'),
            components: array_values((array) ($data['components'] ?? [])),
            sector: $sector,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'frequency' => $this->frequency,
            'cutoff' => $this->cutoff,
            'run_type' => $this->runType,
            'meta' => $this->meta,
            'sector' => $this->sector->value,
            'is_government' => $this->isGovernment || $this->sector->isGovernment(),
            'days_per_year' => $this->daysPerYear,
            'hours_per_day' => $this->hoursPerDay,
            'sss_schedule' => $this->sssSchedule,
            'philhealth_schedule' => $this->philHealthSchedule,
            'pagibig_schedule' => $this->pagIbigSchedule,
            'tax_schedule' => $this->taxSchedule,
            'sss_brackets' => array_map(static fn (SssBracket $row): array => $row->toArray(), $this->sssBrackets),
            'philhealth' => $this->philHealth->toArray(),
            'pagibig' => $this->pagIbig->toArray(),
            'gsis' => $this->gsis->toArray(),
            'tax_brackets' => self::bracketsToArray($this->taxBrackets),
            'annual_tax_brackets' => self::bracketsToArray($this->annualTaxBrackets),
            'minimum_wage' => $this->minimumWage,
            'minimum_wages' => array_map(static fn (MinimumWage $row): array => $row->toArray(), $this->minimumWages),
            'default_region' => $this->defaultRegion,
            'default_industry' => $this->defaultIndustry,
            'sss_base' => $this->sssBase,
            'philhealth_base' => $this->philHealthBase,
            'pagibig_base' => $this->pagIbigBase,
            'gsis_base' => $this->gsisBase,
            'de_minimis' => array_map(static fn (DeMinimisBenefit $row): array => $row->toArray(), $this->deMinimis),
            'holidays' => array_map(static fn (Holiday $row): array => $row->toArray(), $this->holidays),
            'pay_without_timesheet' => $this->payWithoutTimesheet,
            'pay_unworked_regular_holidays' => $this->payUnworkedRegularHolidays,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<TaxBracket>
     */
    private static function brackets(array $rows): array
    {
        return array_map(
            static fn (array $row): TaxBracket => TaxBracket::fromArray($row),
            array_values($rows),
        );
    }

    /**
     * @param  list<TaxBracket>  $brackets
     * @return list<array<string, mixed>>
     */
    private static function bracketsToArray(array $brackets): array
    {
        return array_map(static fn (TaxBracket $bracket): array => $bracket->toArray(), $brackets);
    }
}
