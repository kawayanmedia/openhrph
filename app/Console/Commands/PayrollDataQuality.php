<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Reports payroll master-data issues that would weaken statutory reporting or
 * the minimum-wage-earner exemption.
 */
class PayrollDataQuality extends Command
{
    protected $signature = 'payroll:data-quality
        {--company= : Company id; defaults to the authenticated user}
        {--json : Emit JSON instead of a table}
        {--strict : Exit non-zero when any issue is found}';

    protected $description = 'Report invalid MWE flags, missing government IDs and missing wage-order data';

    public function handle(): int
    {
        $companyId = $this->option('company') ?? Auth::user()?->company_id;

        if (! $companyId) {
            $this->error('A company id is required.');

            return self::FAILURE;
        }

        $employees = DB::table('employees as e')
            ->leftJoin('offices as o', 'o.id', '=', 'e.office_id')
            ->where('e.company_id', $companyId)
            ->whereNull('e.deleted_at')
            ->get([
                'e.id', 'e.employee_number', 'e.first_name', 'e.last_name',
                'e.basic_salary', 'e.daily_rate', 'e.is_minimum_wage',
                'e.tin_number', 'e.sss_number', 'e.philhealth_number', 'e.hdmf_number',
                'e.region as employee_region', 'e.industry as employee_industry',
                'o.region as office_region', 'o.industry as office_industry',
            ]);

        $issues = [];

        foreach ($employees as $employee) {
            $region = $employee->employee_region ?: $employee->office_region;
            $industry = $employee->employee_industry ?: $employee->office_industry;
            $name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''));

            if (! $region || ! $industry) {
                $issues[] = [$employee->employee_number, $name, 'Missing region/industry', 'Wage order cannot be resolved'];
            }

            if ($employee->is_minimum_wage) {
                $daily = (float) $employee->daily_rate > 0
                    ? (float) $employee->daily_rate / 100
                    : ((float) $employee->basic_salary * 12 / 261) / 100;
                $minimum = $this->minimumWage($region, $industry);

                if ($minimum > 0 && $daily > $minimum) {
                    $issues[] = [$employee->employee_number, $name, 'Invalid MWE flag', sprintf('Daily %.2f exceeds minimum %.2f', $daily, $minimum)];
                }
            }

            foreach ([
                'TIN' => [$employee->tin_number, '/^\d{3}-?\d{3}-?\d{3}(-?\d{3,5})?$/'],
                'SSS' => [$employee->sss_number, '/^\d{2}-?\d{7}-?\d$/'],
                'PhilHealth' => [$employee->philhealth_number, '/^\d{2}-?\d{9}-?\d$/'],
                'Pag-IBIG' => [$employee->hdmf_number, '/^\d{4}-?\d{4}-?\d{4}$/'],
            ] as $label => [$value, $pattern]) {
                if (! is_string($value) || trim($value) === '' || ! preg_match($pattern, trim($value))) {
                    $issues[] = [$employee->employee_number, $name, 'Invalid '.$label, $value ?: 'blank'];
                }
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($issues, JSON_PRETTY_PRINT));
        } else {
            $this->table(['Employee no', 'Name', 'Issue', 'Detail'], $issues);
            $this->info(count($issues).' issue(s) across '.$employees->count().' employee(s).');
        }

        if ($this->option('strict') && $issues !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function minimumWage(?string $region, ?string $industry): float
    {
        if (! Schema::hasTable('statutory_min_wage') || ! $region || ! $industry) {
            return 0.0;
        }

        $query = DB::table('statutory_min_wage')
            ->where('region', $region)
            ->where(function ($query) use ($industry) {
                $query->where('industry', $industry)->orWhere('industry', 'GENERAL');
            })
            ->where('effective_from', '<=', now())
            ->where(function ($query) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            })
            ->orderByDesc('effective_from');

        $amount = $query->value('amount_centavos');

        return $amount ? ((float) $amount) / 100 : 0.0;
    }
}
