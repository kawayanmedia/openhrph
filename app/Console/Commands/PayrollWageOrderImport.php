<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the 2026 NWPC regional wage orders.
 *
 * NCR rows carry the exact non-agriculture/agriculture rates. Other regions
 * use the high end of the published range as a provisional GENERAL rate until
 * the board's industry-specific table is confirmed.
 */
class PayrollWageOrderImport extends Command
{
    protected $signature = 'payroll:wage-orders:import
        {--fresh : Delete all statutory minimum-wage rows first}';

    protected $description = 'Import the 2026 NWPC regional wage orders as provisional rows';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            DB::table('statutory_min_wage')->whereNull('company_id')->delete();
        }

        $rows = $this->rows();
        $now = now();

        foreach ($rows as $row) {
            DB::table('statutory_min_wage')
                ->whereNull('company_id')
                ->where('region', $row['region'])
                ->where('industry', $row['industry'])
                ->where('effective_from', '<', $row['effective_from'])
                ->whereNull('effective_to')
                ->update([
                    'effective_to' => Carbon::parse($row['effective_from'])->subDay()->toDateString(),
                    'updated_at' => $now,
                ]);

            DB::table('statutory_min_wage')->updateOrInsert(
                [
                    'company_id' => null,
                    'region' => $row['region'],
                    'industry' => $row['industry'],
                    'effective_from' => $row['effective_from'],
                ],
                [
                    'wage_order' => $row['wage_order'],
                    'amount_centavos' => (int) round($row['amount'] * 100),
                    'effective_to' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        DB::table('statutory_sources')->updateOrInsert(
            ['table_name' => 'statutory_min_wage', 'effectivity' => '2026-01-01'],
            [
                'source_url' => 'https://nwpc.dole.gov.ph/',
                'status' => 'provisional',
                'notes' => '2026 NWPC wage-order matrix as of 06 November 2026. NCR-26/27/28 use the published non-agriculture and agriculture rates; other regions use the high end of the published range as a provisional GENERAL rate until the industry-specific table is confirmed.',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $this->info('Wage orders imported: '.count($rows));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{region: string, industry: string, wage_order: string, effective_from: string, amount: float}>
     */
    protected function rows(): array
    {
        $ncr = [
            ['NCR', 'NON-AGRICULTURE', 'NCR-26', '2025-01-01', 695.00],
            ['NCR', 'AGRICULTURE', 'NCR-26', '2025-01-01', 658.00],
            ['NCR', 'NON-AGRICULTURE', 'NCR-27', '2026-07-25', 755.00],
            ['NCR', 'AGRICULTURE', 'NCR-27', '2026-07-25', 718.00],
            ['NCR', 'NON-AGRICULTURE', 'NCR-28', '2026-09-26', 755.00],
            ['NCR', 'AGRICULTURE', 'NCR-28', '2026-09-26', 718.00],
        ];

        $regions = [
            ['CAR', 'CAR-24', '2025-12-30', 505.00],
            ['Region I', 'RB 1-24', '2025-11-19', 505.00],
            ['Region II', 'RTWPB 2-24', '2026-01-03', 500.00],
            ['Region III', 'RBIII-26', '2025-10-30', 600.00],
            ['Region IVA', 'IVA-23', '2026-12-24', 645.00],
            ['Region IVB', 'MIMAROPA-13', '2026-03-01', 455.00],
            ['Region V', 'RBV-23', '2026-04-08', 480.00],
            ['Region VI', 'VI-30', '2027-01-18', 590.00],
            ['Region VII', 'ROVII-27', '2026-10-14', 582.00],
            ['Region VIII', 'RB VIII-25', '2025-12-08', 470.00],
            ['Region IX', 'RIX-24', '2026-01-01', 464.00],
            ['Region X', 'RX-24', '2026-01-16', 500.00],
            ['Region XI', 'RB XI-24', '2026-03-13', 540.00],
            ['Region XII', 'RB XII-26', '2027-01-20', 501.00],
            ['Region XIII', 'RXIII-20', '2026-01-03', 475.00],
            ['BARMM', 'BARMM-05', '2026-08-06', 461.00],
        ];

        $rows = [];

        foreach ($ncr as [$region, $industry, $order, $from, $amount]) {
            $rows[] = compact('region', 'industry', 'order', 'from', 'amount');
        }

        foreach ($regions as [$region, $order, $from, $amount]) {
            $rows[] = [
                'region' => $region,
                'industry' => 'GENERAL',
                'order' => $order,
                'from' => $from,
                'amount' => $amount,
            ];
        }

        return array_map(fn (array $row) => [
            'region' => $row['region'],
            'industry' => $row['industry'],
            'wage_order' => $row['order'],
            'effective_from' => $row['from'],
            'amount' => $row['amount'],
        ], $rows);
    }
}
