<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the 2026 holiday calendar from Proclamation No. 1006.
 *
 * Pay codes follow the engine's day-type vocabulary: rh (regular holiday),
 * sh (special non-working day), and regular (special working day, no premium).
 */
class PayrollHolidayImport extends Command
{
    protected $signature = 'payroll:holidays:import
        {--year=2026 : Calendar year to import}
        {--company= : Company id; omit to seed every company}
        {--fresh : Delete the year\'s holidays for the target company first}';

    protected $description = 'Import the official Philippine holiday calendar';

    public function handle(): int
    {
        $year = (int) $this->option('year');

        if ($year !== 2026) {
            $this->error('Only the 2026 Proclamation No. 1006 calendar is bundled. Add the new proclamation first.');

            return self::FAILURE;
        }

        $companyIds = $this->option('company')
            ? [(int) $this->option('company')]
            : DB::table('companies')->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($companyIds === []) {
            $this->error('No companies found.');

            return self::FAILURE;
        }

        $rows = $this->rows();
        $now = now();

        foreach ($companyIds as $companyId) {
            if ($this->option('fresh')) {
                DB::table('holidays')->where('company_id', $companyId)->where('year', $year)->delete();
            }

            foreach ($rows as $row) {
                DB::table('holidays')->updateOrInsert(
                    ['company_id' => $companyId, 'date' => $row['date']],
                    [
                        'year' => $year,
                        'name' => $row['name'],
                        'holiday_type' => $row['holiday_type'],
                        'code' => $row['code'],
                        'deleted_at' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            $this->line('Company '.$companyId.': '.count($rows).' holidays');
        }

        $this->info('2026 holidays imported from Proclamation No. 1006.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{date: string, name: string, holiday_type: string, code: string}>
     */
    protected function rows(): array
    {
        $rows = [
            ['2026-01-01', "New Year's Day", 'regular', 'rh'],
            ['2026-04-02', 'Maundy Thursday', 'regular', 'rh'],
            ['2026-04-03', 'Good Friday', 'regular', 'rh'],
            ['2026-04-09', 'Araw ng Kagitingan', 'regular', 'rh'],
            ['2026-05-01', 'Labor Day', 'regular', 'rh'],
            ['2026-06-12', 'Independence Day', 'regular', 'rh'],
            ['2026-08-31', 'National Heroes Day', 'regular', 'rh'],
            ['2026-11-30', 'Bonifacio Day', 'regular', 'rh'],
            ['2026-12-25', 'Christmas Day', 'regular', 'rh'],
            ['2026-12-30', 'Rizal Day', 'regular', 'rh'],

            ['2026-08-21', 'Ninoy Aquino Day', 'special_non_working', 'sh'],
            ['2026-11-01', "All Saints' Day", 'special_non_working', 'sh'],
            ['2026-12-08', 'Feast of the Immaculate Conception of Mary', 'special_non_working', 'sh'],
            ['2026-12-31', 'Last Day of the Year', 'special_non_working', 'sh'],

            ['2026-02-25', 'EDSA People Power Revolution Anniversary', 'special_working', 'regular'],

            ['2026-02-17', 'Chinese New Year', 'additional_special_non_working', 'sh'],
            ['2026-04-04', 'Black Saturday', 'additional_special_non_working', 'sh'],
            ['2026-11-02', "All Souls' Day", 'additional_special_non_working', 'sh'],
            ['2026-12-24', 'Christmas Eve', 'additional_special_non_working', 'sh'],
        ];

        return array_map(fn (array $row) => [
            'date' => $row[0],
            'name' => $row[1],
            'holiday_type' => $row[2],
            'code' => $row[3],
        ], $rows);
    }
}
