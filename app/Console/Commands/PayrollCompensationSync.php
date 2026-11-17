<?php

namespace App\Console\Commands;

use App\Support\Payroll\CompensationCatalogue;
use Illuminate\Console\Command;

/**
 * Refreshes the system-wide compensation catalogue after an upgrade.
 */
class PayrollCompensationSync extends Command
{
    protected $signature = 'payroll:compensations:sync';

    protected $description = 'Synchronise the system-wide compensation catalogue';

    public function handle(): int
    {
        $synced = CompensationCatalogue::sync();

        $this->info("Compensation catalogue synced ({$synced} rows).");

        return self::SUCCESS;
    }
}
