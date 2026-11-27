<?php

namespace App\Console\Commands;

use App\Support\Installation\Installation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Seeds the versioned statutory tables.
 *
 * `PH` keeps the built-in Philippine schedules. Any other configured country
 * uses the generic component tables and is imported from a JSON definition
 * (`--file`) so a jurisdiction can be added without code changes.
 *
 * Run this after deploying, and again whenever a new circular is published:
 * existing rows for the same effectivity date and country are replaced, older
 * rows are kept so historical payroll runs stay reproducible.
 */
class PayrollStatutoryImport extends Command
{
    protected $signature = 'payroll:statutory:import
        {--country=PH : ISO 3166-1 alpha-2 country code}
        {--file= : JSON component definition for a generic country}
        {--fresh : Delete the country\'s statutory rows before importing}
        {--effective-from= : Override the effective date for every table}
        {--provisional : Mark the imported rows as provisional}';

    protected $description = 'Import statutory contribution and tax tables for a country';

    protected string $country = 'PH';

    public function handle(): int
    {
        $this->country = (string) ($this->option('country') ?: 'PH') |> trim(...) |> strtoupper(...);

        if (! preg_match('/^[A-Z]{2}$/', $this->country)) {
            $this->error('The country must be a two-letter ISO 3166-1 code, for example PH.');

            return self::FAILURE;
        }

        $driver = Installation::statutoryDriver($this->country);

        if ($this->option('fresh')) {
            $this->deleteCountryRows();
        }

        if ($driver === 'ph') {
            $this->importSss();
            $this->importPhilHealth();
            $this->importPagIBIG();
            $this->importGsis();
            $this->importTax();
            $this->importDeMinimis();
            $this->importSources();
            $this->syncComponents();

            $this->info('Philippine statutory tables imported.');

            return self::SUCCESS;
        }

        try {
            $this->importGenericComponents();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function deleteCountryRows(): void
    {
        foreach (['statutory_sss_brackets', 'statutory_philhealth', 'statutory_pagibig', 'statutory_gsis', 'statutory_tax_brackets', 'statutory_de_minimis', 'statutory_sources'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('country_code', $this->country)->delete();
            }
        }

        if (Schema::hasTable('statutory_components')) {
            $ids = DB::table('statutory_components')->where('country_code', $this->country)->pluck('id');

            if ($ids->isNotEmpty()) {
                DB::table('statutory_component_bands')->whereIn('statutory_component_id', $ids)->delete();
                DB::table('statutory_components')->whereIn('id', $ids)->delete();
            }
        }
    }

    protected function importSss(): void
    {
        $config = config('payroll.statutory.sss');
        $effective = $this->effective($config['effective_from']);
        $now = now();

        DB::table('statutory_sss_brackets')
            ->whereNull('company_id')
            ->where('country_code', $this->country)
            ->where('effective_from', $effective)
            ->delete();

        $this->closePredecessors('statutory_sss_brackets', $effective);

        $rows = [];
        $step = 50000; // ₱500 in centavos
        $halfStep = 25000; // ₱250 in centavos
        $wispThreshold = (int) ($config['msc_wisp_threshold'] * 100);
        $ecThreshold = (int) ($config['ec_high_msc'] * 100);

        $add = function (int $from, ?int $to, int $msc) use (&$rows, $config, $effective, $now, $wispThreshold, $ecThreshold) {
            $regular = min($msc, $wispThreshold);
            $wisp = max(0, $msc - $wispThreshold);
            $ec = $msc >= $ecThreshold ? $config['ec_employer_high'] : $config['ec_employer'];

            $rows[] = [
                'company_id' => null,
                'country_code' => $this->country,
                'effective_from' => $effective,
                'effective_to' => null,
                'msc_from' => $from,
                'msc_to' => $to,
                'msc' => $msc,
                'ee' => (int) round($regular * $config['employee_rate']),
                'er' => (int) round($regular * $config['employer_rate']),
                'ec' => (int) round($ec * 100),
                'wisp_ee' => (int) round($wisp * $config['employee_rate']),
                'wisp_er' => (int) round($wisp * $config['employer_rate']),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        $floor = (int) ($config['msc_floor'] * 100);
        $ceiling = (int) ($config['msc_ceiling'] * 100);

        // The circular bills each band from half a step below its credit, so
        // ₱5,250.00–₱5,749.99 is credited ₱5,500.00 and anything below
        // ₱5,250.00 is billed at the ₱5,000.00 floor.
        $add(0, $floor + $halfStep - 1, $floor);

        for ($msc = $floor + $step; $msc < $ceiling; $msc += $step) {
            $add($msc - $halfStep, $msc + $halfStep - 1, $msc);
        }

        $add($ceiling - $halfStep, null, $ceiling);

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('statutory_sss_brackets')->insert($chunk);
        }

        $this->line('SSS brackets: '.count($rows));
    }

    protected function importPhilHealth(): void
    {
        $config = config('payroll.statutory.philhealth');
        $effective = $this->effective($config['effective_from']);

        $this->closePredecessors('statutory_philhealth', $effective);

        DB::table('statutory_philhealth')
            ->whereNull('company_id')
            ->where('country_code', $this->country)
            ->where('effective_from', $effective)
            ->delete();

        DB::table('statutory_philhealth')->insert([
            'company_id' => null,
            'country_code' => $this->country,
            'effective_from' => $effective,
            'effective_to' => null,
            'rate' => $config['rate'],
            'floor_centavos' => (int) ($config['floor'] * 100),
            'ceiling_centavos' => (int) ($config['ceiling'] * 100),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->line('PhilHealth: '.$config['rate']);
    }

    protected function importPagIBIG(): void
    {
        $config = config('payroll.statutory.pagibig');
        $effective = $this->effective($config['effective_from']);

        $this->closePredecessors('statutory_pagibig', $effective);

        DB::table('statutory_pagibig')
            ->whereNull('company_id')
            ->where('country_code', $this->country)
            ->where('effective_from', $effective)
            ->delete();

        DB::table('statutory_pagibig')->insert([
            'company_id' => null,
            'country_code' => $this->country,
            'effective_from' => $effective,
            'effective_to' => null,
            'rate' => $config['rate'],
            'low_rate' => $config['low_rate'],
            'low_threshold_centavos' => (int) ($config['low_threshold'] * 100),
            'max_fund_salary_centavos' => (int) ($config['max_fund_salary'] * 100),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->line('Pag-IBIG: '.$config['rate']);
    }

    protected function importGsis(): void
    {
        $config = config('payroll.statutory.gsis');
        $effective = $this->effective($config['effective_from']);

        $this->closePredecessors('statutory_gsis', $effective);

        DB::table('statutory_gsis')
            ->whereNull('company_id')
            ->where('country_code', $this->country)
            ->where('effective_from', $effective)
            ->delete();

        DB::table('statutory_gsis')->insert([
            'company_id' => null,
            'country_code' => $this->country,
            'effective_from' => $effective,
            'effective_to' => null,
            'employee_rate' => $config['employee_rate'],
            'employer_rate' => $config['employer_rate'],
            'max_amc_limit_centavos' => (int) round($config['max_amc_limit'] * 100),
            'above_limit_employee_rate' => $config['above_limit_employee_rate'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->line('GSIS: '.$config['employee_rate'].'/'.$config['employer_rate']);
    }

    protected function importTax(): void
    {
        $effective = $this->effective(config('payroll.statutory.tax.effective_from'));

        $this->closePredecessors('statutory_tax_brackets', $effective);

        DB::table('statutory_tax_brackets')
            ->whereNull('company_id')
            ->where('country_code', $this->country)
            ->where('effective_from', $effective)
            ->delete();

        $rows = [];

        foreach ($this->taxBrackets() as $frequency => $brackets) {
            foreach ($brackets as [$from, $to, $base, $rate]) {
                $rows[] = [
                    'company_id' => null,
                    'country_code' => $this->country,
                    'effective_from' => $effective,
                    'effective_to' => null,
                    'frequency' => $frequency,
                    'from_centavos' => (int) round($from * 100),
                    'to_centavos' => $to === null ? null : (int) round($to * 100),
                    'base_tax_centavos' => (int) round($base * 100),
                    'rate' => $rate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('statutory_tax_brackets')->insert($rows);

        $this->line('Tax brackets: '.count($rows));
    }

    /**
     * BIR withholding tax tables effective 2023 onwards (TRAIN law).
     *
     * @return array<string, array<int, array{0: float, 1: ?float, 2: float, 3: float}>>
     */
    protected function taxBrackets(): array
    {
        return [
            'semi-monthly' => [
                [0, 10416.99, 0, 0],
                [10417, 16666.99, 0, 0.15],
                [16667, 33332.99, 937.50, 0.20],
                [33333, 83332.99, 4270.70, 0.25],
                [83333, 333332.99, 16770.70, 0.30],
                [333333, null, 91770.70, 0.35],
            ],
            'monthly' => [
                [0, 20832.99, 0, 0],
                [20833, 33332.99, 0, 0.15],
                [33333, 66666.99, 1875.00, 0.20],
                [66667, 166666.99, 8541.80, 0.25],
                [166667, 666666.99, 33541.80, 0.30],
                [666667, null, 183541.80, 0.35],
            ],
            // The annual table is the statutory table itself; the monthly and
            // semi-monthly tables are its published instalments and are used
            // to annualise supplementary payments.
            'annual' => [
                [0, 250000, 0, 0],
                [250001, 400000, 0, 0.15],
                [400001, 800000, 22500.00, 0.20],
                [800001, 2000000, 102500.00, 0.25],
                [2000001, 8000000, 402500.00, 0.30],
                [8000001, null, 2202500.00, 0.35],
            ],
        ];
    }

    /**
     * Close the effectivity of the schedule a new import supersedes, so a
     * later payroll date cannot silently use a stale table.
     */
    protected function closePredecessors(string $table, string $effective): void
    {
        $predecessorTo = Carbon::parse($effective)->subDay()->toDateString();

        DB::table($table)
            ->whereNull('company_id')
            ->where('country_code', $this->country)
            ->whereNull('effective_to')
            ->where('effective_from', '<', $effective)
            ->update(['effective_to' => $predecessorTo, 'updated_at' => now()]);
    }

    protected function importDeMinimis(): void
    {
        $config = config('payroll.statutory.de_minimis');
        $effective = $this->effective($config['effective_from']);

        $this->closePredecessors('statutory_de_minimis', $effective);

        DB::table('statutory_de_minimis')
            ->whereNull('company_id')
            ->where('country_code', $this->country)
            ->where('effective_from', $effective)
            ->delete();

        $rows = [];

        foreach ($config['benefits'] as $benefit) {
            $rows[] = [
                'company_id' => null,
                'country_code' => $this->country,
                'code' => $benefit['code'],
                'name' => $benefit['name'],
                'ceiling_centavos' => (int) round($benefit['ceiling'] * 100),
                'basis' => $benefit['basis'] ?? 'monthly',
                'percentage' => (int) ($benefit['percentage'] ?? 0),
                'effective_from' => $effective,
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('statutory_de_minimis')->insert($rows);

        $this->line('De minimis benefits: '.count($rows));
    }

    protected function importSources(): void
    {
        $sources = [
            ['table_name' => 'statutory_sss_brackets', 'effectivity' => $this->effective(config('payroll.statutory.sss.effective_from')), 'source_url' => config('payroll.statutory.sss.source'), 'notes' => 'SSS Circular No. 2024-006: 15% total (5% employee, 10% employer), MSC ₱5,000–₱35,000 in ₱500 steps, MPF/WISP above ₱20,000, EC ₱10 up to MSC ₱14,500 and ₱30 from MSC ₱15,000.'],
            ['table_name' => 'statutory_philhealth', 'effectivity' => $this->effective(config('payroll.statutory.philhealth.effective_from')), 'source_url' => config('payroll.statutory.philhealth.source'), 'notes' => '5% premium, ₱10,000 floor, ₱100,000 ceiling, split 50/50.'],
            ['table_name' => 'statutory_pagibig', 'effectivity' => $this->effective(config('payroll.statutory.pagibig.effective_from')), 'source_url' => config('payroll.statutory.pagibig.source'), 'notes' => '2% employee/employer, ₱10,000 maximum fund salary (₱200 cap).'],
            ['table_name' => 'statutory_gsis', 'effectivity' => $this->effective(config('payroll.statutory.gsis.effective_from')), 'source_url' => config('payroll.statutory.gsis.source'), 'notes' => 'RA 8291 Sec. 5(a): 9% personal and 12% government share on the actual monthly compensation, no MSC brackets and no ceiling. Compensation above the Board-set Maximum AMC limit is billed at 2% to the member.'],
            ['table_name' => 'statutory_tax_brackets', 'effectivity' => $this->effective(config('payroll.statutory.tax.effective_from')), 'source_url' => config('payroll.statutory.tax.source'), 'notes' => 'BIR withholding tax table, TRAIN law (RR 11-2018 as amended).'],
            ['table_name' => 'statutory_de_minimis', 'effectivity' => $this->effective(config('payroll.statutory.de_minimis.effective_from')), 'source_url' => config('payroll.statutory.de_minimis.source'), 'notes' => 'BIR RR 2-98 de minimis ceilings as amended by RR 29-2025; excess over the ceiling rolls into the PHP 90,000 13th month and other benefits exclusion.'],
        ];

        foreach ($sources as $source) {
            $source['country_code'] = $this->country;
            $source['status'] = $this->option('provisional') ? 'provisional' : 'verified';

            DB::table('statutory_sources')->updateOrInsert(
                [
                    'table_name' => $source['table_name'],
                    'country_code' => $this->country,
                    'effectivity' => $source['effectivity'],
                ],
                $source + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /**
     * Mirror the country profile's component list into the generic catalogue so
     * the admin sees the agencies the built-in driver computes. Rates stay in
     * the PH tables; these rows are descriptive only.
     */
    protected function syncComponents(): void
    {
        if (! Schema::hasTable('statutory_components')) {
            return;
        }

        $profile = (array) config('payroll.countries.'.$this->country, []);
        $components = (array) ($profile['components'] ?? []);

        if ($components === []) {
            return;
        }

        $codes = array_values(array_filter(array_column($components, 'code')));

        DB::table('statutory_components')
            ->whereNull('company_id')
            ->where('country_code', $this->country)
            ->whereIn('code', $codes)
            ->delete();

        $rows = [];

        foreach ($components as $component) {
            $driverKey = ($component['code'] ?? '') === 'withholding_tax' ? 'tax' : (string) ($component['code'] ?? '');

            $rows[] = [
                'company_id' => null,
                'country_code' => $this->country,
                'sector' => (string) ($component['sector'] ?? 'any'),
                'code' => (string) $component['code'],
                'name' => (string) ($component['name'] ?? $component['code']),
                'category' => (string) ($component['category'] ?? 'contribution'),
                'applies_to' => (string) ($component['applies_to'] ?? 'both'),
                'calculation' => (string) ($component['calculation'] ?? 'percentage'),
                'base' => (string) ($component['base'] ?? 'monthly_basic'),
                'employee_rate' => null,
                'employer_rate' => null,
                'employee_amount_centavos' => null,
                'employer_amount_centavos' => null,
                'min_base_centavos' => null,
                'max_base_centavos' => null,
                'max_employee_centavos' => null,
                'max_employer_centavos' => null,
                'rounding' => 'half_up',
                'schedule' => (string) ($component['schedule'] ?? 'every_pay'),
                'reduces_taxable_income' => (bool) ($component['reduces_taxable_income'] ?? false),
                'employer_tax_deductible' => (bool) ($component['employer_tax_deductible'] ?? true),
                'mandatory' => (bool) ($component['mandatory'] ?? true),
                'is_active' => true,
                'sort_order' => (int) ($component['sort_order'] ?? 100),
                'effective_from' => $this->effective((string) config('payroll.statutory.'.$driverKey.'.effective_from', '2026-01-01')),
                'effective_to' => null,
                'source_url' => config('payroll.statutory.'.$driverKey.'.source'),
                'notes' => 'Managed by the built-in '.$this->country.' statutory driver; rates are read from the agency tables.',
                'metadata' => json_encode(['managed_by' => 'ph']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('statutory_components')->insert($rows);

        $this->line('Statutory components: '.count($rows));
    }

    protected function importGenericComponents(): void
    {
        $file = trim((string) $this->option('file'));

        if ($file === '') {
            $this->warn("No component definition supplied for {$this->country}. Pass --file=<path> with the country's statutory components.");
            $this->line('The payroll engine will refuse to run until the components are configured.');

            return;
        }

        if (! is_file($file)) {
            throw new RuntimeException("Component definition not found: {$file}");
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        if (! is_array($decoded) || ! isset($decoded['components']) || ! is_array($decoded['components'])) {
            throw new RuntimeException('The component file must be a JSON object with a non-empty "components" array.');
        }

        if (! Schema::hasTable('statutory_components')) {
            throw new RuntimeException('The statutory component tables are missing. Run php artisan migrate first.');
        }

        $imported = 0;
        $bands = 0;

        DB::transaction(function () use ($decoded, &$imported, &$bands): void {
            foreach ($decoded['components'] as $component) {
                $this->validateComponent($component);

                $effectiveFrom = $this->effective((string) ($component['effective_from'] ?? '2026-01-01'));

                DB::table('statutory_components')
                    ->whereNull('company_id')
                    ->where('country_code', $this->country)
                    ->where('code', (string) $component['code'])
                    ->where('effective_from', $effectiveFrom)
                    ->delete();

                $componentId = DB::table('statutory_components')->insertGetId([
                    'company_id' => null,
                    'country_code' => $this->country,
                    'sector' => (string) ($component['sector'] ?? 'any'),
                    'code' => (string) $component['code'],
                    'name' => (string) $component['name'],
                    'category' => (string) ($component['category'] ?? 'contribution'),
                    'applies_to' => (string) ($component['applies_to'] ?? 'both'),
                    'calculation' => (string) $component['calculation'],
                    'base' => (string) ($component['base'] ?? 'monthly_basic'),
                    'employee_rate' => $component['employee_rate'] ?? null,
                    'employer_rate' => $component['employer_rate'] ?? null,
                    'employee_amount_centavos' => $component['employee_amount_centavos'] ?? null,
                    'employer_amount_centavos' => $component['employer_amount_centavos'] ?? null,
                    'min_base_centavos' => $component['min_base_centavos'] ?? null,
                    'max_base_centavos' => $component['max_base_centavos'] ?? null,
                    'max_employee_centavos' => $component['max_employee_centavos'] ?? null,
                    'max_employer_centavos' => $component['max_employer_centavos'] ?? null,
                    'rounding' => (string) ($component['rounding'] ?? 'half_up'),
                    'schedule' => (string) ($component['schedule'] ?? 'every_pay'),
                    'reduces_taxable_income' => (bool) ($component['reduces_taxable_income'] ?? false),
                    'employer_tax_deductible' => (bool) ($component['employer_tax_deductible'] ?? true),
                    'mandatory' => (bool) ($component['mandatory'] ?? true),
                    'is_active' => (bool) ($component['is_active'] ?? true),
                    'sort_order' => (int) ($component['sort_order'] ?? 100),
                    'effective_from' => $effectiveFrom,
                    'effective_to' => $component['effective_to'] ?? null,
                    'source_url' => $component['source_url'] ?? null,
                    'notes' => $component['notes'] ?? null,
                    'metadata' => isset($component['metadata']) ? json_encode($component['metadata']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $imported++;

                $rows = [];

                foreach ((array) ($component['bands'] ?? []) as $band) {
                    $rows[] = [
                        'statutory_component_id' => $componentId,
                        'from_centavos' => (int) ($band['from_centavos'] ?? 0),
                        'to_centavos' => $band['to_centavos'] ?? null,
                        'base_centavos' => (int) ($band['base_centavos'] ?? 0),
                        'employee_rate' => $band['employee_rate'] ?? null,
                        'employer_rate' => $band['employer_rate'] ?? null,
                        'employee_amount_centavos' => (int) ($band['employee_amount_centavos'] ?? 0),
                        'employer_amount_centavos' => (int) ($band['employer_amount_centavos'] ?? 0),
                        'sort_order' => (int) ($band['sort_order'] ?? count($rows)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($rows !== []) {
                    DB::table('statutory_component_bands')->insert($rows);
                    $bands += count($rows);
                }
            }

            DB::table('statutory_sources')->insert([
                'country_code' => $this->country,
                'table_name' => 'statutory_components',
                'effectivity' => $this->effective((string) ($decoded['effective_from'] ?? '2026-01-01')),
                'source_url' => (string) ($decoded['source_url'] ?? ''),
                'notes' => (string) ($decoded['notes'] ?? ''),
                'status' => $this->option('provisional') ? 'provisional' : 'verified',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->info("Generic statutory components imported for {$this->country}.");
        $this->line("Components: {$imported} · Bands: {$bands}");
    }

    /**
     * @param  array<string, mixed>  $component
     */
    protected function validateComponent(array $component): void
    {
        foreach (['code', 'name', 'calculation'] as $required) {
            if (! isset($component[$required]) || trim((string) $component[$required]) === '') {
                throw new RuntimeException("Every component needs a {$required}.");
            }
        }

        if (! in_array($component['calculation'], ['percentage', 'bracket', 'fixed'], true)) {
            throw new RuntimeException("Unknown calculation [{$component['calculation']}] for {$component['code']}.");
        }

        if (($component['calculation'] ?? null) === 'bracket' && empty($component['bands'])) {
            throw new RuntimeException("Bracket component [{$component['code']}] needs at least one band.");
        }

        $appliesTo = $component['applies_to'] ?? 'both';

        if (! in_array($appliesTo, ['employee', 'employer', 'both'], true)) {
            throw new RuntimeException("Unknown applies_to [{$appliesTo}] for {$component['code']}.");
        }

        $base = $component['base'] ?? 'monthly_basic';

        if (! in_array($base, ['monthly_basic', 'monthly_gross', 'period_basic', 'period_gross', 'taxable'], true)) {
            throw new RuntimeException("Unknown base [{$base}] for {$component['code']}.");
        }
    }

    /**
     * Resolve the effective date, allowing a one-off override for a new year.
     */
    protected function effective(string $configured): string
    {
        $override = trim((string) $this->option('effective-from'));

        return $override !== '' ? Carbon::parse($override)->toDateString() : $configured;
    }
}
