<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\LeaveLedger;
use App\Services\Leave\LeaveLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class LeaveRebuildBalances extends Command
{
    protected $signature = 'leave:rebuild-balances
        {year? : Calendar year; defaults to this year}
        {--company= : Company id; defaults to every company}
        {--opening : Seed the ledger from the legacy employee leave columns first}';

    protected $description = 'Rebuild leave balances from the ledger, optionally seeding opening balances';

    public function handle(LeaveLedgerService $ledger): int
    {
        $year = (int) ($this->argument('year') ?? now()->year);

        $companies = $this->option('company')
            ? Company::query()->whereKey($this->option('company'))->pluck('id')
            : Company::query()->pluck('id');

        foreach ($companies as $companyId) {
            app(TenantContext::class)->run((int) $companyId, function () use ($companyId, $year, $ledger): void {
                if ($this->option('opening')) {
                    $this->seedOpeningBalances((int) $companyId, $year, $ledger);
                }

                $groups = LeaveLedger::query()
                    ->where('company_id', $companyId)
                    ->where('period_year', $year)
                    ->select('employee_id', 'leave_type_id')
                    ->distinct()
                    ->get();

                foreach ($groups as $group) {
                    $balance = (float) LeaveLedger::query()
                        ->where('company_id', $companyId)
                        ->where('employee_id', $group->employee_id)
                        ->where('leave_type_id', $group->leave_type_id)
                        ->where('period_year', $year)
                        ->sum('days');

                    LeaveBalance::query()->updateOrCreate(
                        [
                            'company_id' => $companyId,
                            'employee_id' => $group->employee_id,
                            'leave_type_id' => $group->leave_type_id,
                            'year' => $year,
                        ],
                        ['balance' => round($balance, 2)],
                    );
                }

                $this->line("Company {$companyId}: balances rebuilt for {$year}.");
            });
        }

        return self::SUCCESS;
    }

    private function seedOpeningBalances(int $companyId, int $year, LeaveLedgerService $ledger): void
    {
        $types = Leave::query()
            ->withoutGlobalScopes()
            ->whereIn('code', ['VL', 'SL'])
            ->pluck('id', 'code');

        $employees = Employee::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($employees as $employee) {
            foreach (['VL' => 'total_vacation_leave', 'SL' => 'total_sick_leave'] as $code => $column) {
                $typeId = $types[$code] ?? null;
                $days = (float) ($employee->{$column} ?? 0);

                if ($typeId === null || $days <= 0) {
                    continue;
                }

                $ledger->post(
                    $companyId,
                    (int) $employee->getKey(),
                    (int) $typeId,
                    $year,
                    LeaveLedger::MOVEMENT_ADJUSTMENT,
                    $days,
                    "opening:{$year}",
                    'Opening balance from the legacy employee record',
                );
            }
        }
    }
}
