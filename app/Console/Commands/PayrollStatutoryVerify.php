<?php

namespace App\Console\Commands;

use App\Support\Installation\Installation;
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
        {--country=PH : ISO 3166-1 alpha-2 country code}
        {--company= : Company id for company-specific rows}
        {--strict : Fail when any table is provisional or unconfirmed}';

    protected $description = 'Verify statutory table coverage and confirmation status for a payroll date';

    public function handle(): int
    {
        $date = Carbon::parse($this->argument('date'))->toDateString();
        $country = (string) ($this->option('country') ?: 'PH') |> trim(...) |> strtoupper(...);
        $driver = Installation::statutoryDriver($country);

        if ($driver !== 'ph') {
            return $this->verifyGeneric($country, $date);
        }

        $companyId = $this->option('company');

        $tables = [
            'statutory_sss_brackets' => 'SSS',
            'statutory_philhealth' => 'PhilHealth',
            'statutory_pagibig' => 'Pag-IBIG',
            'statutory_gsis' => 'GSIS',
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
                ->where('country_code', $country)
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
                    ->where('country_code', $country)
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

    protected function verifyGeneric(string $country, string $date): int
    {
        if (! Schema::hasTable('statutory_components')) {
            $this->error('The statutory component tables are missing. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $components = DB::table('statutory_components')
            ->where('country_code', $country)
            ->where('is_active', true)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
            })
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        if ($components->isEmpty()) {
            $this->error("No active statutory components are configured for {$country}. Import a component definition with --file.");

            return self::FAILURE;
        }

        $rows = [];
        $missingBands = false;

        foreach ($components as $component) {
            $bands = DB::table('statutory_component_bands')
                ->where('statutory_component_id', $component->id)
                ->count();

            if ($component->calculation === 'bracket' && $bands === 0) {
                $missingBands = true;
            }

            $rows[] = [
                $component->name,
                $component->code,
                ucfirst((string) $component->calculation),
                (string) $bands,
                (string) ($component->source_url ?? '-'),
            ];
        }

        $this->table(['Component', 'Code', 'Calculation', 'Bands', 'Source'], $rows);

        if ($missingBands) {
            $this->error('One or more bracket components have no bands.');

            return self::FAILURE;
        }

        $this->info(count($components)." statutory components cover {$date} for {$country}.");

        return self::SUCCESS;
    }
}
