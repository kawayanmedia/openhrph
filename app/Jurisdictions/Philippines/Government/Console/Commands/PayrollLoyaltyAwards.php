<?php

namespace App\Jurisdictions\Philippines\Government\Console\Commands;

use App\Models\Employee;
use App\Services\HrAudit;
use App\Support\Installation\Installation;
use App\Console\Commands\SectorCommand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flags employees who reach a loyalty milestone and, with `--apply`, creates the
 * loyalty cash gift assignment for the current year.
 *
 * The milestone interval and amount come from the Government rules/circular;
 * the amount is left at zero for the payroll administrator to fill in until the
 * current DBM issuance is encoded.
 */
class PayrollLoyaltyAwards extends SectorCommand
{
    protected $signature = 'payroll:loyalty-awards
        {--company= : Limit to one company id}
        {--years= : Years between loyalty awards}
        {--apply : Create the employee compensation assignments}
        {--reason= : Reason recorded in the audit trail}';

    protected $description = 'Report and assign government loyalty cash gifts';

    public function handle(): int
    {
        if (! $this->ensureSector()) {
            return self::FAILURE;
        }

        $policy = Installation::sectorPolicy();

        if (! $policy->supports('loyalty_award')) {
            $this->error('Loyalty awards are a Government rule. This installation is '.$policy->label().'.');

            return self::FAILURE;
        }

        $compensation = $this->compensation();

        if ($compensation === null) {
            $this->error('The LOYALTY CASH GIFT compensation template is missing. Run `php artisan payroll:compensation:sync`.');

            return self::FAILURE;
        }

        $years = (int) ($this->option('years') ?: ($policy->rules()['loyalty_award_years'] ?? 10));
        $query = Employee::withoutTenant()
            ->whereNotNull('date_hired')
            ->whereNull('date_resigned');

        if ($company = $this->option('company')) {
            $query->where('company_id', (int) $company);
        }

        $eligible = $query->orderBy('company_id')->orderBy('id')->get([
            'id', 'company_id', 'employee_number', 'first_name', 'last_name', 'date_hired',
        ])->filter(function (Employee $employee) use ($years): bool {
            $service = (int) Carbon::parse($employee->date_hired)->diffInYears(now());

            return $service > 0 && $service % $years === 0;
        })->values();

        if ($eligible->isEmpty()) {
            $this->info('No employees reached a loyalty milestone.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Company', 'Employee', 'Date hired', 'Milestone'],
            $eligible->map(fn (Employee $employee): array => [
                $employee->id,
                $employee->company_id,
                trim($employee->employee_number.' '.$employee->last_name.', '.$employee->first_name),
                $employee->date_hired,
                Carbon::parse($employee->date_hired)->diffInYears(now()).' years',
            ])->all(),
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run. Re-run with --apply to create the assignments.');

            return self::SUCCESS;
        }

        $created = 0;

        DB::transaction(function () use ($eligible, $compensation, &$created): void {
            foreach ($eligible as $employee) {
                $from = now()->startOfYear();

                $exists = DB::table('employees_compensations')
                    ->where('employee_id', $employee->id)
                    ->where('compensation_id', $compensation->id)
                    ->whereYear('valid_date_from', $from->year)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('employees_compensations')->insert([
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'compensation_id' => $compensation->id,
                    'is_special' => false,
                    'release_date' => null,
                    'amount' => 0,
                    'valid_date_from' => $from->toDateString(),
                    'valid_date_to' => now()->endOfYear()->toDateString(),
                    'is_recurring' => false,
                    'frequency' => 'One-Time',
                    'is_never_expires' => false,
                    'is_prorated' => false,
                    'is_taxable' => false,
                    'is_active' => true,
                    'status' => 'not_release',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created++;

                HrAudit::record(
                    companyId: (int) $employee->company_id,
                    event: 'loyalty_award',
                    employeeId: (int) $employee->id,
                    reason: $this->option('reason') ?: 'Loyalty cash gift',
                );
            }
        });

        $this->info("Loyalty cash gift assignments created: {$created}");

        return self::SUCCESS;
    }

    private function compensation(): ?object
    {
        if (! Schema::hasTable('compensations')) {
            return null;
        }

        return DB::table('compensations')
            ->whereNull('company_id')
            ->whereRaw('UPPER(TRIM(code)) = ?', ['LOYALTY CASH GIFT'])
            ->first();
    }
}
