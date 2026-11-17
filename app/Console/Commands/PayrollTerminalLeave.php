<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\HrAudit;
use App\Support\Installation\Installation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts the unused leave balances of separated government employees into a
 * terminal leave assignment.
 *
 * The commutation formula is configurable policy, not settled law in this
 * codebase: the daily rate falls back to monthly basic x 12 / 261 when the
 * employee has no daily rate. Review the generated assignments before release.
 */
class PayrollTerminalLeave extends Command
{
    protected $signature = 'payroll:terminal-leave
        {--company= : Limit to one company id}
        {--days-per-year=261 : Working days used for the monthly-to-daily conversion}
        {--apply : Create the employee compensation assignments}
        {--reason= : Reason recorded in the audit trail}';

    protected $description = 'Compute terminal leave pay for separated government employees';

    public function handle(): int
    {
        $policy = Installation::sectorPolicy();

        if (! $policy->supports('terminal_leave')) {
            $this->error('Terminal leave is a Government rule. This installation is '.$policy->label().'.');

            return self::FAILURE;
        }

        $compensation = $this->compensation();

        if ($compensation === null) {
            $this->error('The TERMINAL LEAVE PAY compensation template is missing. Run `php artisan payroll:compensation:sync`.');

            return self::FAILURE;
        }

        $daysPerYear = max(1, (int) $this->option('days-per-year'));

        $query = Employee::withoutTenant()
            ->whereNotNull('date_resigned')
            ->where(function ($query): void {
                $query->where('total_vacation_leave', '>', 0)
                    ->orWhere('total_sick_leave', '>', 0);
            });

        if ($company = $this->option('company')) {
            $query->where('company_id', (int) $company);
        }

        $rows = $query->orderBy('company_id')->orderBy('id')->get([
            'id', 'company_id', 'employee_number', 'first_name', 'last_name',
            'date_resigned', 'total_vacation_leave', 'total_sick_leave',
            'daily_rate', 'basic_salary',
        ])->map(function (Employee $employee) use ($daysPerYear): array {
            $days = (float) $employee->total_vacation_leave + (float) $employee->total_sick_leave;
            $daily = (float) $employee->daily_rate;
            $monthly = (float) $employee->basic_salary;

            if ($daily <= 0 && $monthly > 0) {
                $daily = $monthly * 12 / $daysPerYear;
            }

            $amount = round($days * $daily, 2);

            return [
                'employee' => $employee,
                'days' => $days,
                'daily' => $daily,
                'amount' => $amount,
                'centavos' => (int) round($amount * 100),
            ];
        })->filter(fn (array $row): bool => $row['amount'] > 0)->values();

        if ($rows->isEmpty()) {
            $this->info('No separated employees have convertible leave balances.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Company', 'Employee', 'Date resigned', 'Days', 'Daily rate', 'Amount'],
            $rows->map(fn (array $row): array => [
                $row['employee']->id,
                $row['employee']->company_id,
                trim($row['employee']->employee_number.' '.$row['employee']->last_name.', '.$row['employee']->first_name),
                $row['employee']->date_resigned,
                number_format($row['days'], 2),
                number_format($row['daily'], 2),
                number_format($row['amount'], 2),
            ])->all(),
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run. Re-run with --apply to create the assignments.');

            return self::SUCCESS;
        }

        $created = 0;

        DB::transaction(function () use ($rows, $compensation, &$created): void {
            foreach ($rows as $row) {
                /** @var Employee $employee */
                $employee = $row['employee'];

                $exists = DB::table('employees_compensations')
                    ->where('employee_id', $employee->id)
                    ->where('compensation_id', $compensation->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('employees_compensations')->insert([
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'compensation_id' => $compensation->id,
                    'is_special' => true,
                    'release_date' => $employee->date_resigned,
                    'amount' => $row['centavos'],
                    'valid_date_from' => $employee->date_resigned,
                    'valid_date_to' => $employee->date_resigned,
                    'is_recurring' => false,
                    'frequency' => 'One-Time',
                    'is_never_expires' => true,
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
                    event: 'terminal_leave',
                    employeeId: (int) $employee->id,
                    reason: $this->option('reason') ?: 'Terminal leave conversion',
                    payload: ['days' => (float) $row['days']],
                );
            }
        });

        $this->info("Terminal leave assignments created: {$created}");

        return self::SUCCESS;
    }

    private function compensation(): ?object
    {
        if (! Schema::hasTable('compensations')) {
            return null;
        }

        return DB::table('compensations')
            ->whereNull('company_id')
            ->whereRaw('UPPER(TRIM(code)) = ?', ['TERMINAL LEAVE PAY'])
            ->first();
    }
}
