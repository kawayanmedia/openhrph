<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the versioned statutory tables with the Philippine schedules.
 *
 * Run this after deploying, and again whenever a new circular is published:
 * existing rows for the same effectivity date are replaced, older rows are kept
 * so historical payroll runs stay reproducible.
 */
class PayrollStatutoryImport extends Command
{
    protected $signature = 'payroll:statutory:import
        {--fresh : Delete all statutory rows before importing}
        {--effective-from= : Override the effective date for every table}
        {--provisional : Mark the imported rows as provisional}';

    protected $description = 'Import Philippine statutory contribution and tax tables';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            foreach (['statutory_sss_brackets', 'statutory_philhealth', 'statutory_pagibig', 'statutory_tax_brackets', 'statutory_de_minimis', 'statutory_sources'] as $table) {
                DB::table($table)->delete();
            }
        }

        $this->importSss();
        $this->importPhilHealth();
        $this->importPagIBIG();
        $this->importTax();
        $this->importDeMinimis();
        $this->importSources();

        $this->info('Statutory tables imported.');

        return self::SUCCESS;
    }

    protected function importSss(): void
    {
        $config = config('payroll.statutory.sss');
        $effective = $this->effective($config['effective_from']);
        $now = now();

        DB::table('statutory_sss_brackets')
            ->whereNull('company_id')
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

        $this->closePredecessors('statutory_philhealth', $this->effective($config['effective_from']));

        DB::table('statutory_philhealth')
            ->whereNull('company_id')
            ->where('effective_from', $this->effective($config['effective_from']))
            ->delete();

        DB::table('statutory_philhealth')->insert([
            'company_id' => null,
            'effective_from' => $this->effective($config['effective_from']),
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

        $this->closePredecessors('statutory_pagibig', $this->effective($config['effective_from']));

        DB::table('statutory_pagibig')
            ->whereNull('company_id')
            ->where('effective_from', $this->effective($config['effective_from']))
            ->delete();

        DB::table('statutory_pagibig')->insert([
            'company_id' => null,
            'effective_from' => $this->effective($config['effective_from']),
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

    protected function importTax(): void
    {
        $effective = $this->effective($this->effective(config('payroll.statutory.tax.effective_from')));

        $this->closePredecessors('statutory_tax_brackets', $effective);

        DB::table('statutory_tax_brackets')
            ->whereNull('company_id')
            ->where('effective_from', $effective)
            ->delete();

        $rows = [];

        foreach ($this->taxBrackets() as $frequency => $brackets) {
            foreach ($brackets as [$from, $to, $base, $rate]) {
                $rows[] = [
                    'company_id' => null,
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
            ->where('effective_from', $effective)
            ->delete();

        $rows = [];

        foreach ($config['benefits'] as $benefit) {
            $rows[] = [
                'company_id' => null,
                'code' => $benefit['code'],
                'name' => $benefit['name'],
                'ceiling_centavos' => (int) round($benefit['ceiling'] * 100),
                'basis' => $benefit['basis'] ?? 'monthly',
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
            ['table_name' => 'statutory_tax_brackets', 'effectivity' => $this->effective(config('payroll.statutory.tax.effective_from')), 'source_url' => config('payroll.statutory.tax.source'), 'notes' => 'BIR withholding tax table, TRAIN law (RR 11-2018 as amended).'],
            ['table_name' => 'statutory_de_minimis', 'effectivity' => $this->effective(config('payroll.statutory.de_minimis.effective_from')), 'source_url' => config('payroll.statutory.de_minimis.source'), 'notes' => 'RR 2-98 de minimis ceilings as amended by RR 11-2018; excess over the ceiling is taxable.'],
        ];

        foreach ($sources as $source) {
            $source['status'] = $this->option('provisional') ? 'provisional' : 'verified';

            DB::table('statutory_sources')->updateOrInsert(
                ['table_name' => $source['table_name'], 'effectivity' => $source['effectivity']],
                $source + ['created_at' => now(), 'updated_at' => now()]
            );
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
