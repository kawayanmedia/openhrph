<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\HrAudit;
use App\Support\Installation\Installation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Advances a government employee one salary step after the required years of
 * satisfactory service.
 *
 * The command is a dry run unless `--apply` is passed. The 3-year interval is
 * configurable because the enabling DBM/CSC issuance is still being verified.
 */
class PayrollStepIncrements extends Command
{
    protected $signature = 'payroll:step-increments
        {--company= : Limit to one company id}
        {--years= : Years required before an increment}
        {--apply : Write the increments; without this option the command only reports}
        {--reason= : Reason recorded in the audit trail}';

    protected $description = 'Advance government employees one salary step when due';

    public function handle(): int
    {
        $policy = Installation::sectorPolicy();

        if (! $policy->supports('step_increment')) {
            $this->error('Step increments are a Government rule. This installation is '.$policy->label().'.');

            return self::FAILURE;
        }

        $years = (int) ($this->option('years') ?: ($policy->rules()['step_increment_years'] ?? 3));
        $cutoff = now()->subYears($years);

        $query = Employee::withoutTenant()
            ->whereNotNull('salary_grade')
            ->whereNotNull('step')
            ->where('step', '<', 8)
            ->whereNull('date_resigned')
            ->where(function ($query) use ($cutoff): void {
                $query->where('last_increment', '<=', $cutoff)
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query->whereNull('last_increment')->where('date_hired', '<=', $cutoff);
                    });
            });

        if ($company = $this->option('company')) {
            $query->where('company_id', (int) $company);
        }

        $employees = $query->orderBy('company_id')->orderBy('id')->get([
            'id', 'company_id', 'employee_number', 'first_name', 'last_name',
            'salary_grade', 'step', 'last_increment', 'date_hired',
        ]);

        if ($employees->isEmpty()) {
            $this->info('No employees are due a step increment.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Company', 'Employee', 'SG', 'Step', 'Last increment', 'Date hired'],
            $employees->map(fn (Employee $employee): array => [
                $employee->id,
                $employee->company_id,
                trim($employee->employee_number.' '.$employee->last_name.', '.$employee->first_name),
                $employee->salary_grade,
                $employee->step,
                $employee->last_increment ?? '-',
                $employee->date_hired ?? '-',
            ])->all(),
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run. Re-run with --apply to write the increments.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($employees, $years): void {
            foreach ($employees as $employee) {
                $next = min(8, (int) $employee->step + 1);

                DB::table('employees')->where('id', $employee->id)->update([
                    'step' => $next,
                    'last_increment' => now(),
                    'updated_at' => now(),
                ]);

                HrAudit::record(
                    companyId: (int) $employee->company_id,
                    event: 'step_increment',
                    employeeId: (int) $employee->id,
                    reason: $this->option('reason') ?: 'Automatic step increment',
                    payload: [
                        'from_step' => (int) $employee->step,
                        'to_step' => $next,
                        'salary_grade' => (int) $employee->salary_grade,
                        'years' => $years,
                    ],
                );
            }
        });

        $this->info('Updated '.$employees->count().' employee step increment(s).');

        return self::SUCCESS;
    }
}
