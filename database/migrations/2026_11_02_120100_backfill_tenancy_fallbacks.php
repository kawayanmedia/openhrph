<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $primary = DB::table('companies')->orderBy('id')->value('id');

        if ($primary === null) {
            return;
        }

        $this->backfillFromParent('leave_applications', 'office_id', 'offices');
        $this->backfillFromParent('payroll_dates', 'payroll_group_id', 'payroll_groups');
        $this->backfillFromParent('applicants', 'job_vacancy_id', 'job_vacancies');
        $this->backfillFromParent('plantilla_items', 'office_id', 'offices');

        $this->fallbackToPrimary([
            'employees_bonuses',
            'employees_commissions',
            'employees_deductions',
            'employees_loans',
            'employees_plantilla',
            'employees_schedules',
            'aa',
        ], $primary);

        foreach (['leave_types', 'salary_grades', 'ranks', 'sanggunian_members', 'companies_banks'] as $table) {
            $this->cloneForEveryCompany($table, $primary);
        }
    }

    public function down(): void
    {
        //
    }

    private function backfillFromParent(string $table, string $foreignKey, string $parent): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
            return;
        }

        DB::table($table)
            ->whereNull('company_id')
            ->whereNotNull($foreignKey)
            ->update([
                'company_id' => DB::raw("(SELECT {$parent}.company_id FROM {$parent} WHERE {$parent}.id = {$table}.{$foreignKey})"),
            ]);
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function fallbackToPrimary(array $tables, int $primary): void
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            $remaining = DB::table($table)->whereNull('company_id')->count();

            if ($remaining === 0) {
                continue;
            }

            Log::warning("Assigning {$remaining} orphaned row(s) in [{$table}] to company [{$primary}].");

            DB::table($table)->whereNull('company_id')->update(['company_id' => $primary]);
        }
    }

    private function cloneForEveryCompany(string $table, int $primary): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
            return;
        }

        $orphans = DB::table($table)->whereNull('company_id')->get();

        if ($orphans->isEmpty()) {
            return;
        }

        DB::table($table)->whereNull('company_id')->update(['company_id' => $primary]);

        $companies = DB::table('companies')->where('id', '!=', $primary)->orderBy('id')->pluck('id');

        foreach ($companies as $companyId) {
            foreach ($orphans as $row) {
                $data = (array) $row;
                unset($data['id']);
                $data['company_id'] = $companyId;

                DB::table($table)->insert($data);
            }
        }
    }
};
