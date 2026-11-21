<?php

namespace App\Console\Commands;

use App\Support\Installation\Installation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports the active jurisdiction's holiday calendar.
 */
class PayrollHolidayImport extends Command
{
    protected $signature = 'payroll:holidays:import
        {--year= : Calendar year to import; defaults to the current year}
        {--company= : Company id; omit to seed every company}
        {--fresh : Delete the year\'s holidays for the target company first}';

    protected $description = 'Import the official holiday calendar for the installation country';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?: now()->year);
        $provider = Installation::jurisdiction()->holidays();

        if ($provider->calendar($year) === []) {
            $this->error("No {$year} holiday calendar is bundled for ".Installation::countryCode().'. Add it to the jurisdiction module first.');

            return self::FAILURE;
        }

        $companyIds = $this->option('company')
            ? [(int) $this->option('company')]
            : DB::table('companies')->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($companyIds === []) {
            $this->error('No companies found.');

            return self::FAILURE;
        }

        foreach ($companyIds as $companyId) {
            $count = $provider->import($companyId, $year, (bool) $this->option('fresh'));
            $this->line('Company '.$companyId.': '.$count.' holidays');
        }

        $this->info($year.' holidays imported for '.Installation::countryCode().'.');

        return self::SUCCESS;
    }
}
