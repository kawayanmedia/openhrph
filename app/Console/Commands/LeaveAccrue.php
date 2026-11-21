<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Leave\LeaveLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class LeaveAccrue extends Command
{
    protected $signature = 'leave:accrue {year? : Calendar year} {--company= : Company id; defaults to every company}';

    protected $description = 'Post the annual or monthly leave accrual for every active employee';

    public function handle(LeaveLedgerService $ledger): int
    {
        $year = (int) ($this->argument('year') ?? now()->year);
        $companies = $this->option('company')
            ? Company::query()->whereKey($this->option('company'))->pluck('id')
            : Company::query()->pluck('id');

        $total = 0;

        foreach ($companies as $companyId) {
            $posted = app(TenantContext::class)->run((int) $companyId, fn (): int => $ledger->accrueYear((int) $companyId, $year));
            $total += $posted;
            $this->line("Company {$companyId}: {$posted} accrual movement(s).");
        }

        $this->info("{$total} accrual movement(s) posted for {$year}.");

        return self::SUCCESS;
    }
}
