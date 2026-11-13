<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verifies that every statutory table has a row covering a payroll date and
 * reports whether the row is verified or only provisional.
 */
class PayrollStatutoryVerify extends Command
{
    protected $signature = 'payroll:statutory:verify
        {date : Payroll date to verify (Y-m-d)}
        {--company= : Company id for company-specific rows}
        {--strict : Fail when any table is provisional or unconfirmed}';

    protected $description = 'Verify statutory table coverage and confirmation status for a payroll date';

    public function handle(): int
    {
        $date = Carbon::parse($this->argument('date'))->toDateString();
        $companyId = $this->option('company');

        $tables = [
            'statutory_sss_brackets' => 'SSS',
            'statutory_philhealth' => 'PhilHealth',
            'statutory_pagibig' => 'Pag-IBIG',
            'statutory_tax_brackets' => 'BIR withholding',
            'statutory_de_minimis' => 'De minimis',
            'statutory_min_wage' => 'Minimum wage',
        ];

        $rows = [];
        $missing = false;
        $provisional = false;

        foreach ($tables as $table => $label) {
            if (! Schema::hasTable($table)) {
                $rows[] = [$label, 'missing table', '-', 'unconfirmed', '-'];
                $missing = true;

                continue;
            }

            $query = DB::table($table)
                ->where('effective_from', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
                });

            if ($companyId !== null) {
                $query->where(function ($query) use ($companyId) {
                    $query->whereNull('company_id')->orWhere('company_id', $companyId);
                });
            } else {
                $query->whereNull('company_id');
            }

            $count = (clone $query)->count();
            $effectivity = (clone $query)->max('effective_from');

            $source = Schema::hasTable('statutory_sources')
                ? DB::table('statutory_sources')
                    ->where('table_name', $table)
                    ->where('effectivity', '<=', $date)
                    ->orderByDesc('effectivity')
                    ->first()
                : null;

            $status = $source->status ?? 'unconfirmed';

            if ($count === 0) {
                $missing = true;
                $status = 'missing';
            }

            if ($status !== 'verified') {
                $provisional = true;
            }

            $rows[] = [$label, (string) $count, $effectivity ?? '-', $status, $source->source_url ?? '-'];
        }

        $this->table(['Table', 'Rows', 'Effectivity', 'Status', 'Source'], $rows);

        if ($missing) {
            $this->error('One or more statutory tables do not cover '.$date.'.');

            return self::FAILURE;
        }

        if ($provisional) {
            $this->warn('One or more statutory tables are provisional or unconfirmed for '.$date.'.');

            if ($this->option('strict')) {
                return self::FAILURE;
            }
        }

        $this->info('Statutory coverage exists for '.$date.'.');

        return self::SUCCESS;
    }
}
