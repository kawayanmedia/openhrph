<?php

namespace App\Support\Payroll;

use App\Enums\Sector;
use App\Models\Compensation;
use App\Support\Installation\Installation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The compensation catalogue every company starts with.
 *
 * Rows in the Allowance group that represent a BIR de minimis benefit carry the
 * same `code` the payroll engine looks up in `statutory_de_minimis`, so the
 * non-taxable ceiling is applied instead of exempting the whole amount.
 */
final class CompensationCatalogue
{
    /**
     * The combined 13th month pay and other benefits exclusion (RA 10963), in
     * pesos. The column stores centavos, so rows use the helper below.
     */
    public const OTHER_BENEFITS_EXCLUSION = 90000;

    private const OTHER_BENEFITS_EXCLUSION_CENTAVOS = self::OTHER_BENEFITS_EXCLUSION * 100;

    /**
     * Annual equivalents of the BIR de minimis ceilings under RR 2-98 as
     * amended by RR 29-2025 (https://bir-cdn.bir.gov.ph/BIR/pdf/RR%20No.%2029-2025.pdf),
     * in centavos. The payroll engine prorates the statutory ceiling by its own
     * basis (`statutory_de_minimis`); these values complete the catalogue for
     * the admin screen, whose ceiling is per annum. Monetised leave credits and
     * the overtime meal allowance are capped by days or by the minimum wage, so
     * they carry no peso ceiling.
     *
     * @var array<string, int>
     */
    private const DE_MINIMIS_ANNUAL_CENTAVOS = [
        'rice' => 3000000,                // ₱2,500 per month
        'clothing' => 800000,             // ₱8,000 per year
        'medical' => 400000,              // ₱2,000 per semester
        'laundry' => 480000,              // ₱400 per month
        'medical_assistance' => 1200000,  // ₱12,000 per year
        'achievement_award' => 1200000,   // ₱12,000 per year
        'gifts' => 600000,                // ₱6,000 per year
        'cba_productivity' => 1200000,    // ₱12,000 per year
    ];

    /**
     * The government PERA grant is ₱2,000 per month (DBM Budget Circular
     * 2009-3, https://www.dbm.gov.ph/wp-content/uploads/2012/03/BC-2009-3.pdf,
     * renewed yearly in the GAA), in centavos per year.
     */
    private const PERA_ANNUAL_CENTAVOS = 2400000;

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     tax_class_code: ?string,
     *     tax_class: string,
     *     group: string,
     *     tax_option: string,
     *     is_taxable: bool,
     *     amount_basis?: ?string,
     *     frequency?: ?string,
     *     maximum_non_tax?: int,
     *     sector?: ?string,
     *     status?: string
     * }>
     */
    public static function rows(?Sector $sector = null): array
    {
        $country = Installation::countryCode();

        if ($country !== 'PH') {
            return array_map(static fn (array $row): array => array_merge([
                'code' => $row['code'] ?? '',
                'name' => $row['name'] ?? $row['code'] ?? '',
                'tax_class_code' => $row['tax_class_code'] ?? null,
                'tax_class' => $row['tax_class'] ?? 'Compensation',
                'group' => $row['group'] ?? Compensation::GROUP_COMPENSATION,
                'tax_option' => $row['tax_option'] ?? ((bool) ($row['is_taxable'] ?? false) ? 'fully_taxable' : 'fully_nontaxable'),
                'is_taxable' => (bool) ($row['is_taxable'] ?? false),
            ], $row), array_values(Installation::jurisdiction()->catalogue()->compensationElements($sector)));
        }

        $rows = [
            // Taxable regular compensation.
            ['code' => 'BASIC PAY ADJUSTMENT', 'name' => 'BASIC PAY ADJUSTMENT', 'tax_class_code' => Compensation::TAX_CLASS_BASIC_SALARY, 'tax_class' => 'Basic Salary', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'REG PAY', 'name' => 'REGULAR PAY', 'tax_class_code' => Compensation::TAX_CLASS_BASIC_SALARY, 'tax_class' => 'Basic Salary', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'REPRESENTATION', 'name' => 'REPRESENTATION ALLOWANCE', 'tax_class_code' => Compensation::TAX_CLASS_REPRESENTATION, 'tax_class' => 'Representation', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'TRANSPORTATION', 'name' => 'TRANSPORTATION ALLOWANCE', 'tax_class_code' => Compensation::TAX_CLASS_TRANSPORTATION, 'tax_class' => 'Transportation', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'COLA', 'name' => 'COST OF LIVING ALLOWANCE', 'tax_class_code' => Compensation::TAX_CLASS_COLA, 'tax_class' => 'Cost of living allowance', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'FIXED HOUSING ALLOW', 'name' => 'FIXED HOUSING ALLOWANCE', 'tax_class_code' => Compensation::TAX_CLASS_FIXEDHOUSING, 'tax_class' => 'Fixed housing allowance', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'OTHER TAXABLE 1', 'name' => 'OTHER TAXABLE 1', 'tax_class_code' => Compensation::TAX_CLASS_OTHER1, 'tax_class' => 'Others Regular 1', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'OTHER TAXABLE 2', 'name' => 'OTHER TAXABLE 2', 'tax_class_code' => Compensation::TAX_CLASS_OTHER2, 'tax_class' => 'Others Regular 2', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'PROFIT SHARING', 'name' => 'PROFIT SHARING', 'tax_class_code' => Compensation::TAX_CLASS_PROFITSHARING, 'tax_class' => 'Profit sharing', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'FEES', 'name' => 'FEES', 'tax_class_code' => Compensation::TAX_CLASS_FEES, 'tax_class' => "Fees including Director's fees", 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'HAZARD PAY', 'name' => 'HAZARD PAY', 'tax_class_code' => Compensation::TAX_CLASS_HAZARDPAY, 'tax_class' => 'Hazard pay', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'OTHER SUPPLEMENTARY 1', 'name' => 'OTHER SUPPLEMENTARY 1', 'tax_class_code' => Compensation::TAX_CLASS_OTHSUPP1, 'tax_class' => 'Others Supplementary 1', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'OTHER SUPPLEMENTARY 2', 'name' => 'OTHER SUPPLEMENTARY 2', 'tax_class_code' => Compensation::TAX_CLASS_OTHSUPP2, 'tax_class' => 'Others Supplementary 2', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],

            // Premiums. Exempt only for minimum wage earners (RA 9504); the
            // engine applies that exemption from the employee's wage order.
            ['code' => 'HOLIDAY PAY', 'name' => 'HOLIDAY PAY', 'tax_class_code' => Compensation::TAX_CLASS_HOLIDAYPAY, 'tax_class' => 'Holiday pay', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'OVERTIME PAY', 'name' => 'OVERTIME PAY', 'tax_class_code' => Compensation::TAX_CLASS_OVERTIMEPAY, 'tax_class' => 'Overtime pay', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],
            ['code' => 'NIGHT DIFFERENTIAL', 'name' => 'NIGHT DIFFERENTIAL', 'tax_class_code' => Compensation::TAX_CLASS_NIGHT_DIFFERENTIAL, 'tax_class' => 'Night shift differential', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],

            ['code' => 'NON TAXABLE COMPENSATION', 'name' => 'NON-TAXABLE COMPENSATION', 'tax_class_code' => Compensation::TAX_CLASS_NTAXCOMPENSATION, 'tax_class' => 'Salaries and other forms of compensation', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false],
            ['code' => 'FRINGE BENEFIT', 'name' => 'FRINGE BENEFIT', 'tax_class_code' => Compensation::TAX_CLASS_FRINGEBENEFIT, 'tax_class' => 'Fringe benefit', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true],

            // Separation, retirement and leave. The exemptions are conditional,
            // so each treatment is its own row and the assignment decides.
            ['code' => 'SEPARATION PAY (EXEMPT)', 'name' => 'SEPARATION PAY (EXEMPT)', 'tax_class_code' => Compensation::TAX_CLASS_NTAXCOMPENSATION, 'tax_class' => 'Salaries and other forms of compensation', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'sector' => 'Private'],
            ['code' => 'SEPARATION PAY (TAXABLE)', 'name' => 'SEPARATION PAY (TAXABLE)', 'tax_class_code' => Compensation::TAX_CLASS_OTHSUPP1, 'tax_class' => 'Others Supplementary 1', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true, 'sector' => 'Private'],
            ['code' => 'RETIREMENT PAY', 'name' => 'RETIREMENT PAY', 'tax_class_code' => Compensation::TAX_CLASS_NTAXCOMPENSATION, 'tax_class' => 'Salaries and other forms of compensation', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'sector' => 'Private'],
            ['code' => 'LEAVE CONVERSION (TAXABLE)', 'name' => 'LEAVE CONVERSION (TAXABLE)', 'tax_class_code' => Compensation::TAX_CLASS_OTHSUPP2, 'tax_class' => 'Others Supplementary 2', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true, 'sector' => 'Private'],
            ['code' => 'MONETIZED LEAVE', 'name' => 'MONETIZED UNUSED LEAVE', 'tax_class_code' => Compensation::TAX_CLASS_DEMINIMISBENEFITS, 'tax_class' => 'De minimis benefits', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false],

            // Bonuses.
            ['code' => '13TH MONTH PAY', 'name' => '13TH MONTH PAY', 'tax_class_code' => Compensation::TAX_CLASS_13THMONTH, 'tax_class' => '13th month pay', 'group' => Compensation::GROUP_BONUS, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'amount_basis' => 'Salary Based', 'frequency' => 'One-Time', 'maximum_non_tax' => self::OTHER_BENEFITS_EXCLUSION_CENTAVOS, 'sector' => 'Private'],
            ['code' => 'FIXED TAXABLE BONUS', 'name' => 'Fixed Taxable Bonus', 'tax_class_code' => null, 'tax_class' => 'Bonus', 'group' => Compensation::GROUP_BONUS, 'tax_option' => 'fully_taxable', 'is_taxable' => true, 'amount_basis' => 'Fixed', 'frequency' => 'One-Time'],
            ['code' => 'FIXED NON TAXABLE BONUS', 'name' => 'Fixed Non-Taxable Bonus', 'tax_class_code' => Compensation::TAX_CLASS_13THMONTH, 'tax_class' => '13th month pay', 'group' => Compensation::GROUP_BONUS, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'amount_basis' => 'Fixed', 'frequency' => 'Periodic', 'maximum_non_tax' => self::OTHER_BENEFITS_EXCLUSION_CENTAVOS],

            // Commissions.
            ['code' => 'COMMISSION', 'name' => 'COMMISSION', 'tax_class_code' => Compensation::TAX_CLASS_COMMISSION, 'tax_class' => 'Commission', 'group' => Compensation::GROUP_COMMISSION, 'tax_option' => 'fully_taxable', 'is_taxable' => true, 'amount_basis' => 'Fixed'],
            ['code' => 'FIXED SALES COMMISSION', 'name' => 'Fixed Sales Commission', 'tax_class_code' => Compensation::TAX_CLASS_COMMISSION, 'tax_class' => 'Commission', 'group' => Compensation::GROUP_COMMISSION, 'tax_option' => 'fully_taxable', 'is_taxable' => true, 'amount_basis' => 'Fixed'],
            ['code' => 'SALARY BASED SALES COMMISSION', 'name' => 'Salary Based Sales Commission', 'tax_class_code' => Compensation::TAX_CLASS_COMMISSION, 'tax_class' => 'Commission', 'group' => Compensation::GROUP_COMMISSION, 'tax_option' => 'fully_taxable', 'is_taxable' => true, 'amount_basis' => 'Salary Based'],

            // De minimis benefits. `code` must equal the statutory_de_minimis
            // code so the ceiling in payroll.statutory.de_minimis applies.
            ['code' => 'rice', 'name' => 'Rice Subsidy', 'tax_class_code' => Compensation::TAX_CLASS_DEMINIMISBENEFITS, 'tax_class' => 'De minimis benefits', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'maximum_non_tax' => self::DE_MINIMIS_ANNUAL_CENTAVOS['rice']],
            ['code' => 'clothing', 'name' => 'Uniform and Clothing Allowance', 'tax_class_code' => Compensation::TAX_CLASS_DEMINIMISBENEFITS, 'tax_class' => 'De minimis benefits', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'maximum_non_tax' => self::DE_MINIMIS_ANNUAL_CENTAVOS['clothing']],
            ['code' => 'medical', 'name' => 'Medical Cash Allowance to Dependents', 'tax_class_code' => Compensation::TAX_CLASS_DEMINIMISBENEFITS, 'tax_class' => 'De minimis benefits', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'maximum_non_tax' => self::DE_MINIMIS_ANNUAL_CENTAVOS['medical']],
            ['code' => 'laundry', 'name' => 'Laundry Allowance', 'tax_class_code' => Compensation::TAX_CLASS_DEMINIMISBENEFITS, 'tax_class' => 'De minimis benefits', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'maximum_non_tax' => self::DE_MINIMIS_ANNUAL_CENTAVOS['laundry']],
            ['code' => 'meal', 'name' => 'Meal Allowance for Overtime and Night Shift', 'tax_class_code' => Compensation::TAX_CLASS_DEMINIMISBENEFITS, 'tax_class' => 'De minimis benefits', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false],
            ['code' => 'medical_assistance', 'name' => 'Actual Medical Assistance', 'tax_class_code' => Compensation::TAX_CLASS_DEMINIMISBENEFITS, 'tax_class' => 'De minimis benefits', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'maximum_non_tax' => self::DE_MINIMIS_ANNUAL_CENTAVOS['medical_assistance']],
            ['code' => 'achievement_award', 'name' => 'Employee Achievement Award', 'tax_class_code' => Compensation::TAX_CLASS_DEMINIMISBENEFITS, 'tax_class' => 'De minimis benefits', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'maximum_non_tax' => self::DE_MINIMIS_ANNUAL_CENTAVOS['achievement_award']],
            ['code' => 'gifts', 'name' => 'Christmas and Anniversary Gift', 'tax_class_code' => Compensation::TAX_CLASS_DEMINIMISBENEFITS, 'tax_class' => 'De minimis benefits', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'maximum_non_tax' => self::DE_MINIMIS_ANNUAL_CENTAVOS['gifts']],
            ['code' => 'cba_productivity', 'name' => 'CBA and Productivity Incentive', 'tax_class_code' => Compensation::TAX_CLASS_DEMINIMISBENEFITS, 'tax_class' => 'De minimis benefits', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'maximum_non_tax' => self::DE_MINIMIS_ANNUAL_CENTAVOS['cba_productivity']],

            // Government and other non-taxable allowances.
            ['code' => 'SUBSISTENCE ALLOWANCE', 'name' => 'Subsistence Allowance', 'tax_class_code' => Compensation::TAX_CLASS_NTAXCOMPENSATION, 'tax_class' => 'Salaries and other forms of compensation', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false],
            ['code' => 'PERA', 'name' => 'Personnel Economic Relief Allowance', 'tax_class_code' => Compensation::TAX_CLASS_NTAXCOMPENSATION, 'tax_class' => 'Salaries and other forms of compensation', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'maximum_non_tax' => self::PERA_ANNUAL_CENTAVOS, 'sector' => 'Government'],

            // Government statutory bonuses. Amounts are derived from the
            // employee's basic pay or from the annual DBM issuance, never from
            // this catalogue.
            ['code' => 'MID-YEAR BONUS', 'name' => 'Mid-Year Bonus', 'tax_class_code' => Compensation::TAX_CLASS_13THMONTH, 'tax_class' => '13th month pay', 'group' => Compensation::GROUP_BONUS, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'amount_basis' => 'Salary Based', 'frequency' => 'One-Time', 'maximum_non_tax' => self::OTHER_BENEFITS_EXCLUSION_CENTAVOS, 'sector' => 'Government'],
            ['code' => 'YEAR-END BONUS', 'name' => 'Year-End Bonus', 'tax_class_code' => Compensation::TAX_CLASS_13THMONTH, 'tax_class' => '13th month pay', 'group' => Compensation::GROUP_BONUS, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'amount_basis' => 'Salary Based', 'frequency' => 'One-Time', 'maximum_non_tax' => self::OTHER_BENEFITS_EXCLUSION_CENTAVOS, 'sector' => 'Government'],
            ['code' => 'CASH GIFT', 'name' => 'Cash Gift', 'tax_class_code' => Compensation::TAX_CLASS_13THMONTH, 'tax_class' => '13th month pay', 'group' => Compensation::GROUP_BONUS, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'amount_basis' => 'Fixed', 'frequency' => 'One-Time', 'maximum_non_tax' => self::OTHER_BENEFITS_EXCLUSION_CENTAVOS, 'sector' => 'Government'],
            ['code' => 'SRI', 'name' => 'Service Recognition Incentive', 'tax_class_code' => Compensation::TAX_CLASS_13THMONTH, 'tax_class' => '13th month pay', 'group' => Compensation::GROUP_BONUS, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'amount_basis' => 'Fixed', 'frequency' => 'One-Time', 'maximum_non_tax' => self::OTHER_BENEFITS_EXCLUSION_CENTAVOS, 'sector' => 'Government'],
            ['code' => 'CNA INCENTIVE', 'name' => 'CNA Incentive', 'tax_class_code' => Compensation::TAX_CLASS_13THMONTH, 'tax_class' => '13th month pay', 'group' => Compensation::GROUP_BONUS, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'amount_basis' => 'Fixed', 'frequency' => 'One-Time', 'maximum_non_tax' => self::OTHER_BENEFITS_EXCLUSION_CENTAVOS, 'sector' => 'Government'],
            ['code' => 'PBB', 'name' => 'Performance-Based Bonus', 'tax_class_code' => Compensation::TAX_CLASS_13THMONTH, 'tax_class' => '13th month pay', 'group' => Compensation::GROUP_BONUS, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'amount_basis' => 'Fixed', 'frequency' => 'One-Time', 'maximum_non_tax' => self::OTHER_BENEFITS_EXCLUSION_CENTAVOS, 'sector' => 'Government', 'status' => 'Inactive'],
            ['code' => 'LOYALTY CASH GIFT', 'name' => 'Loyalty Cash Gift', 'tax_class_code' => Compensation::TAX_CLASS_13THMONTH, 'tax_class' => '13th month pay', 'group' => Compensation::GROUP_BONUS, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'amount_basis' => 'Fixed', 'frequency' => 'One-Time', 'maximum_non_tax' => self::OTHER_BENEFITS_EXCLUSION_CENTAVOS, 'sector' => 'Government', 'status' => 'Inactive'],
            ['code' => 'RATA', 'name' => 'Representation and Transportation Allowance', 'tax_class_code' => Compensation::TAX_CLASS_REPRESENTATION, 'tax_class' => 'Representation', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_taxable', 'is_taxable' => true, 'sector' => 'Government', 'status' => 'Inactive'],
            ['code' => 'LAUNDRY ALLOWANCE', 'name' => 'Laundry Allowance (Government)', 'tax_class_code' => Compensation::TAX_CLASS_NTAXCOMPENSATION, 'tax_class' => 'Salaries and other forms of compensation', 'group' => Compensation::GROUP_ALLOWANCE, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'sector' => 'Government', 'status' => 'Inactive'],

            ['code' => 'TERMINAL LEAVE PAY', 'name' => 'Terminal Leave Pay', 'tax_class_code' => Compensation::TAX_CLASS_NTAXCOMPENSATION, 'tax_class' => 'Salaries and other forms of compensation', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_nontaxable', 'is_taxable' => false, 'sector' => 'Government'],

            // Private-sector items that do not apply to GSIS-covered agencies.
            ['code' => 'SERVICE CHARGE', 'name' => 'Service Charge', 'tax_class_code' => Compensation::TAX_CLASS_OTHSUPP1, 'tax_class' => 'Others Supplementary 1', 'group' => Compensation::GROUP_COMPENSATION, 'tax_option' => 'fully_taxable', 'is_taxable' => true, 'sector' => 'Private', 'status' => 'Inactive'],
        ];

        if ($sector === null) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => ! isset($row['sector']) || strcasecmp((string) $row['sector'], $sector->value) === 0,
        ));
    }

    /**
     * Create the missing system rows and refresh the classification of the
     * existing ones. Company overrides live in rows with a company_id and are
     * never touched here.
     */
    public static function sync(?Carbon $now = null, ?Sector $sector = null): int
    {
        if (! Schema::hasTable('compensations')) {
            return 0;
        }

        $now ??= now();
        $synced = 0;
        $country = Installation::countryCode();

        $hasSector = Schema::hasColumn('compensations', 'sector');
        $hasCountry = Schema::hasColumn('compensations', 'country_code');

        foreach (self::rows($sector) as $row) {
            $classification = [
                'code' => $row['code'],
                'name' => $row['name'],
                'tax_class_code' => $row['tax_class_code'],
                'tax_class' => $row['tax_class'],
                'group' => $row['group'],
                'tax_option' => $row['tax_option'],
                'is_taxable' => $row['is_taxable'],
                'status' => $row['status'] ?? 'Active',
                'amount_basis' => $row['amount_basis'] ?? null,
                'frequency' => $row['frequency'] ?? null,
                'maximum_non_tax' => $row['maximum_non_tax'] ?? null,
                'deleted_at' => null,
                'updated_at' => $now,
            ];

            if ($hasSector) {
                $classification['sector'] = $row['sector'] ?? null;
            }

            if ($hasCountry) {
                $classification['country_code'] = $country;
            }

            $query = DB::table('compensations')
                ->whereNull('company_id')
                ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper(trim($row['code']))]);

            if ($hasCountry) {
                $query->where(function ($query) use ($country): void {
                    $query->where('country_code', $country)->orWhereNull('country_code');
                });
            }

            if (! $query->exists()) {
                DB::table('compensations')->insert($classification + [
                    'company_id' => null,
                    'code' => $row['code'],
                    'amount' => 0,
                    'created_at' => $now,
                ]);

                $synced++;

                continue;
            }

            $query->update($classification);

            // Overrides follow the shared classification but keep the values a
            // company is allowed to change.
            $overrideClassification = $classification;
            unset(
                $overrideClassification['code'],
                $overrideClassification['maximum_non_tax'],
                $overrideClassification['deleted_at'],
            );

            $overrideQuery = DB::table('compensations')
                ->whereNotNull('company_id')
                ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper(trim($row['code']))]);

            if ($hasCountry) {
                $overrideQuery->where(function ($query) use ($country): void {
                    $query->where('country_code', $country)->orWhereNull('country_code');
                });
            }

            $overrideQuery->update($overrideClassification);

            $synced++;
        }

        return $synced;
    }
}
