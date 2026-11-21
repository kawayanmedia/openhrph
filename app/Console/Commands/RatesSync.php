<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Payroll\RateCatalogue;
use Illuminate\Console\Command;

class RatesSync extends Command
{
    protected $signature = 'rates:sync {--company= : Only sync this company id}';

    protected $description = 'Create the missing day-type rate rows for each company';

    public function handle(): int
    {
        $query = Company::query()->orderBy('id');

        if ($this->option('company') !== null) {
            $query->whereKey((int) $this->option('company'));
        }

        $total = 0;

        $query->each(function (Company $company) use (&$total): void {
            $created = RateCatalogue::sync((int) $company->getKey());
            $total += $created;

            $this->line(sprintf('%s: %d rate(s) created', $company->name, $created));
        });

        $this->info("Created {$total} rate row(s).");

        return self::SUCCESS;
    }
}
