<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;

/**
 * Checks that daily-paid employees carry a usable daily rate.
 *
 * Daily-paid rows store the contracted daily rate in both basic_salary (the
 * field the admin form relabels) and daily_rate. The payroll engine now reads
 * daily_rate for these employees, so a missing or divergent value must be
 * corrected before the next run.
 */
class PayrollNormaliseDailyRate extends Command
{
    protected $signature = 'payroll:data:normalise-daily-rate
        {--company= : Company id; defaults to every company}
        {--fix : Write the corrected daily_rate and hourly_rate}';

    protected $description = 'Report and fix daily-paid employees whose daily_rate is missing or differs from basic_salary';

    public function handle(): int
    {
        $query = Employee::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('payment_type', Employee::PAYMENT_TYPE_DAILY)
            ->when($this->option('company'), fn ($query, $company) => $query->where('company_id', $company));

        $employees = $query->get();
        $rows = [];
        $fixed = 0;

        foreach ($employees as $employee) {
            $daily = (int) $employee->daily_rate;
            $basic = (int) $employee->basic_salary;

            if ($daily > 0 && ($basic === 0 || $daily === $basic)) {
                continue;
            }

            $corrected = $daily > 0 ? $daily : $basic;

            $rows[] = [
                $employee->getKey(),
                $employee->employee_number,
                number_format($basic / 100, 2),
                number_format($daily / 100, 2),
                number_format($corrected / 100, 2),
            ];

            if ($this->option('fix') && $corrected > 0) {
                $employee->daily_rate = $corrected;
                $employee->hourly_rate = (int) round($corrected / 8);
                $employee->save();
                $fixed++;
            }
        }

        $this->table(['ID', 'Employee no', 'Basic field', 'Daily rate', 'Corrected'], $rows);
        $this->info(count($rows).' daily-paid employee(s) need attention; '.$fixed.' corrected.');

        return self::SUCCESS;
    }
}
