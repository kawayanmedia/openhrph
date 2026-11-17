<?php

namespace App\Models;

use App\Models\Scopes\CompensationCatalogueScope;
use App\Scopes\FilterScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Compensation extends Model
{
    use FilterScope;
    use SoftDeletes;

    // https://www.bir.gov.ph/tax_calculator/wt_calculator.html

    // Taxable
    const TAX_CLASS_BASIC_SALARY = 'BASICSALARY';

    const TAX_CLASS_REPRESENTATION = 'REPRESENTATION';

    const TAX_CLASS_TRANSPORTATION = 'TRANSPORTATION';

    const TAX_CLASS_COLA = 'COLA';

    const TAX_CLASS_FIXEDHOUSING = 'FIXEDHOUSING';

    const TAX_CLASS_OTHER1 = 'OTHER1';

    const TAX_CLASS_OTHER2 = 'OTHER2';

    const TAX_CLASS_COMMISSION = 'COMMISSION';

    const TAX_CLASS_PROFITSHARING = 'PROFITSHARING';

    const TAX_CLASS_FEES = 'FEES';

    const TAX_CLASS_HAZARDPAY = 'HAZARDPAY';

    const TAX_CLASS_OTHSUPP1 = 'OTHSUPP1';

    const TAX_CLASS_OTHSUPP2 = 'OTHSUPP2';

    const TAX_CLASS_NTAXCOMPENSATION = 'NTAXCOMPENSATION';

    const TAX_CLASS_13THMONTH = '13THMONTH'; // max of 90k per year. excess should fall on taxable income

    const TAX_CLASS_FRINGEBENEFIT = 'FRINGEBENEFIT';

    const TAX_CLASS_DEMINIMISBENEFITS = 'DEMINIMISBENEFITS';

    // Not taxable provided that minimum wage earner
    const TAX_CLASS_HOLIDAYPAY = 'HOLIDAYPAY';

    const TAX_CLASS_OVERTIMEPAY = 'OVERTIMEPAY';

    const TAX_CLASS_NIGHT_DIFFERENTIAL = 'NIGHT_SHIFT_DIFFERENTIAL';

    // Earnings category stored in the `group` column. Allowances, bonuses and
    // commissions all live in this catalogue; `group` says how payroll treats
    // the earning.
    const GROUP_ALLOWANCE = 'Allowance';

    const GROUP_BONUS = 'Bonus';

    const GROUP_COMMISSION = 'Commission';

    const GROUP_COMPENSATION = 'Compensation';

    /**
     * Payroll detail column each tax class is broken out into.
     *
     * Only classes without another source of truth are mapped: basic salary,
     * commission, 13th month and the time-based premiums are already written
     * from their own earning bucket, so routing a compensation there would
     * count the same peso twice.
     *
     * @var array<string, string>
     */
    const TAX_CLASS_COLUMNS = [
        self::TAX_CLASS_REPRESENTATION => 'representation_allowance',
        self::TAX_CLASS_TRANSPORTATION => 'transportation_allowance',
        self::TAX_CLASS_COLA => 'cola',
        self::TAX_CLASS_FIXEDHOUSING => 'fixed_housing_allowance',
        self::TAX_CLASS_OTHER1 => 'other_taxable_regular',
        self::TAX_CLASS_OTHER2 => 'other_taxable_supplementary',
        self::TAX_CLASS_PROFITSHARING => 'profit_sharing',
        self::TAX_CLASS_FEES => 'fees',
        self::TAX_CLASS_HAZARDPAY => 'hazard_pay',
        self::TAX_CLASS_OTHSUPP1 => 'other_taxable_supplementary',
        self::TAX_CLASS_OTHSUPP2 => 'other_taxable_supplementary',
    ];

    /**
     * Human labels for the payroll detail columns above.
     *
     * @var array<string, string>
     */
    const TAX_CLASS_COLUMN_LABELS = [
        'representation_allowance' => 'Representation Allowance',
        'transportation_allowance' => 'Transportation Allowance',
        'cola' => 'Cost of Living Allowance',
        'fixed_housing_allowance' => 'Fixed Housing Allowance',
        'other_taxable_regular' => 'Other Taxable Regular',
        'profit_sharing' => 'Profit Sharing',
        'fees' => 'Fees',
        'hazard_pay' => 'Hazard Pay',
        'other_taxable_supplementary' => 'Other Taxable Supplementary',
    ];

    /**
     * BIR tax class codes with the label stored in `tax_class`.
     *
     * @var array<string, string>
     */
    const TAX_CLASS_OPTIONS = [
        self::TAX_CLASS_BASIC_SALARY => 'Basic Salary',
        self::TAX_CLASS_REPRESENTATION => 'Representation',
        self::TAX_CLASS_TRANSPORTATION => 'Transportation',
        self::TAX_CLASS_COLA => 'Cost of living allowance',
        self::TAX_CLASS_FIXEDHOUSING => 'Fixed housing allowance',
        self::TAX_CLASS_OTHER1 => 'Others Regular 1',
        self::TAX_CLASS_OTHER2 => 'Others Regular 2',
        self::TAX_CLASS_COMMISSION => 'Commission',
        self::TAX_CLASS_PROFITSHARING => 'Profit sharing',
        self::TAX_CLASS_FEES => "Fees including Director's fees",
        self::TAX_CLASS_HAZARDPAY => 'Hazard pay',
        self::TAX_CLASS_OTHSUPP1 => 'Others Supplementary 1',
        self::TAX_CLASS_OTHSUPP2 => 'Others Supplementary 2',
        self::TAX_CLASS_NTAXCOMPENSATION => 'Salaries and other forms of compensation',
        self::TAX_CLASS_13THMONTH => '13th month pay',
        self::TAX_CLASS_FRINGEBENEFIT => 'Fringe benefit',
        self::TAX_CLASS_DEMINIMISBENEFITS => 'De minimis benefits',
        self::TAX_CLASS_HOLIDAYPAY => 'Holiday pay',
        self::TAX_CLASS_OVERTIMEPAY => 'Overtime pay',
        self::TAX_CLASS_NIGHT_DIFFERENTIAL => 'Night shift differential',
    ];

    protected $fillable = [
        'code',
        'name',
        'tax_class',
        'tax_class_code',
        'group',
        'tax_option',
        'is_taxable',
        'amount',
        'status',
        'amount_basis',
        'frequency',
        'maximum_non_tax',
    ];

    protected $table = 'compensations';

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new CompensationCatalogueScope);

        static::created(function (Compensation $model) {
            set_current_timestamp($model);
        });

        // Keep the human label in step with the selected BIR tax class code so
        // the catalogue reads consistently whichever screen saved it.
        static::saving(function (Compensation $model): void {
            $code = (string) $model->tax_class_code;

            if (isset(self::TAX_CLASS_OPTIONS[$code])) {
                $model->tax_class = self::TAX_CLASS_OPTIONS[$code];
            }
        });
    }

    public function employees()
    {
        return $this->hasMany(EmployeeCompensation::class);
    }

    /**
     * Whether this row overrides a system catalogue row for one company.
     */
    public function isOverride(): bool
    {
        return $this->getAttribute('company_id') !== null;
    }

    public function scopeSystem(Builder $query): void
    {
        $query->whereNull($query->getModel()->qualifyColumn('company_id'));
    }

    public function scopeOverrides(Builder $query): void
    {
        $query->whereNotNull($query->getModel()->qualifyColumn('company_id'));
    }

    /**
     * The catalogue the active company sees: system rows, except the codes it
     * overrides, plus its own override rows.
     */
    public function scopeEffective(Builder $query): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            $query->whereNull($query->getModel()->qualifyColumn('company_id'));

            return;
        }

        $outer = $query->getModel()->getTable();

        $query->where(function (Builder $query) use ($tenantId, $outer): void {
            $query->whereNotNull($outer.'.company_id')
                ->orWhere(function (Builder $query) use ($tenantId, $outer): void {
                    $query->whereNull($outer.'.company_id')
                        ->whereNotExists(function ($sub) use ($tenantId, $outer): void {
                            $sub->select(DB::raw(1))
                                ->from('compensations as overrides')
                                ->whereRaw('UPPER(TRIM(overrides.code)) = UPPER(TRIM('.$outer.'.code))')
                                ->where('overrides.company_id', $tenantId)
                                ->whereNull('overrides.deleted_at');
                        });
                });
        });
    }

    /**
     * Copy a system row into a company override, or return the existing one.
     */
    public static function overrideFor(int $companyId, self $system): self
    {
        $existing = self::withoutGlobalScope(CompensationCatalogueScope::class)
            ->where('company_id', $companyId)
            ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper(trim((string) $system->code))])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $override = $system->replicate();
        $override->company_id = $companyId;
        $override->save();

        return $override;
    }

    public function scopeOfGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * @return array<string, string>
     */
    public static function groupOptions(): array
    {
        return [
            self::GROUP_ALLOWANCE => 'Allowance',
            self::GROUP_BONUS => 'Bonus',
            self::GROUP_COMMISSION => 'Commission',
            self::GROUP_COMPENSATION => 'Compensation',
        ];
    }

    /**
     * Options for the employee compensation form, grouped in the label.
     *
     * @return array<int, string>
     */
    public static function selectOptions(): array
    {
        return self::query()
            ->effective()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (self $compensation): array => [
                $compensation->getKey() => '['.$compensation->group.'] '.$compensation->name,
            ])
            ->all();
    }

    /**
     * Effective catalogue options for one earning group.
     *
     * @return array<int, string>
     */
    public static function optionsForGroup(string $group): array
    {
        return self::query()
            ->effective()
            ->where('group', $group)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100
        );
    }

    public function maximumNonTax(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : $value / 100,
            set: fn ($value) => $value === null ? null : $value * 100
        );
    }

    /**
     * The payroll detail column this tax class is broken out into, if any.
     */
    public static function columnForTaxClass(?string $taxClass): ?string
    {
        $code = strtoupper(trim((string) $taxClass));

        return $code === '' ? null : (self::TAX_CLASS_COLUMNS[$code] ?? null);
    }

    /**
     * @return list<string>
     */
    public static function taxClassColumns(): array
    {
        return array_values(array_unique(self::TAX_CLASS_COLUMNS));
    }

    public static function taxClassColumnLabel(string $column): string
    {
        return self::TAX_CLASS_COLUMN_LABELS[$column] ?? $column;
    }
}
