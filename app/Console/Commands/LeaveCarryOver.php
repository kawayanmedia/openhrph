<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Leave\LeaveLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class LeaveCarryOver extends Command
{
    protected $signature = 'leave:carry-over
        {from? : Year to close; defaults to last year}
        {to? : Year to open; defaults to this year}
        {--company= : Company id; defaults to every company}';

    protected $description = 'Carry unused leave into the new year and expire the excess';

    public function handle(LeaveLedgerService $ledger): int
    {
        $from = (int) ($this->argument('from') ?? now()->subYear()->year);
        $to = (int) ($this->argument('to') ?? now()->year);

        $companies = $this->option('company')
            ? Company::query()->whereKey($this->option('company'))->pluck('id')
            : Company::query()->pluck('id');

        $total = 0;

        foreach ($companies as $companyId) {
            $posted = app(TenantContext::class)->run(
                (int) $companyId,
                fn (): int => $ledger->carryOver((int) $companyId, $from, $to),
            );
            $total += $posted;
            $this->line("Company {$companyId}: {$posted} carry-over/expiry movement(s).");
        }

        $this->info("{$total} movement(s) posted from {$from} to {$to}.");

        return self::SUCCESS;
    }
}
