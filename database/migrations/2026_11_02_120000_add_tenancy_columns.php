<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant tables that only need the column because they hang off an
     * employee record.
     *
     * @var array<int, string>
     */
    private array $employeeChildTables = [
        'employees_allowances',
        'employees_allowances_recurrings',
        'employees_bonuses',
        'employees_commissions',
        'employees_deductions',
        'employees_loans',
        'employees_schedules',
        'employees_plantilla',
        'timesheets',
        'leave_applications',
    ];

    /**
     * Tenant tables without a parent employee relation.
     *
     * @var array<int, string>
     */
    private array $standaloneTables = [
        'leave_types',
        'salary_grades',
        'plantilla_items',
        'sanggunian_members',
        'aa',
        'action_events',
    ];

    public function up(): void
    {
        foreach (array_merge($this->employeeChildTables, $this->standaloneTables) as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }

        $this->backfillEmployeeChildren();
        $this->backfillStandaloneTables();
        $this->addCompanyMetadata();
        $this->backfillCompanyMemberships();
    }

    public function down(): void
    {
        foreach ($this->companyColumns() as $table => $column) {
            if ($column !== 'company_id') {
                continue;
            }

            $this->dropColumnWithIndexes($table, $column);
        }

        foreach (['companies', 'company_user'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (['slug', 'domain', 'api_key', 'group_id'] as $column) {
                $this->dropColumnWithIndexes($table, $column);
            }
        }
    }

    /**
     * Drop a column after dropping any single-column index covering it, which
     * SQLite refuses to leave dangling.
     */
    private function dropColumnWithIndexes(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] !== [$column]) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                $blueprint->dropIndex($index['name']);
            });
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropColumn($column);
        });
    }

    private function backfillEmployeeChildren(): void
    {
        foreach ($this->employeeChildTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            DB::table($table)
                ->whereNull('company_id')
                ->whereNotNull('employee_id')
                ->update([
                    'company_id' => DB::raw("(SELECT employees.company_id FROM employees WHERE employees.id = {$table}.employee_id)"),
                ]);

            $this->warnRemaining($table);
        }
    }

    private function backfillStandaloneTables(): void
    {
        if (Schema::hasTable('leave_types') && Schema::hasColumn('leave_types', 'company_id')) {
            DB::table('leave_types')
                ->whereNull('company_id')
                ->update([
                    'company_id' => DB::raw('(SELECT MIN(leave_requests.company_id) FROM leave_requests WHERE leave_requests.leave_type_id = leave_types.id)'),
                ]);

            $this->warnRemaining('leave_types');
        }

        if (Schema::hasTable('plantilla_items') && Schema::hasColumn('plantilla_items', 'company_id')) {
            DB::table('plantilla_items')
                ->whereNull('company_id')
                ->whereNotNull('office_id')
                ->update([
                    'company_id' => DB::raw('(SELECT offices.company_id FROM offices WHERE offices.id = plantilla_items.office_id)'),
                ]);

            $this->warnRemaining('plantilla_items');
        }

        if (Schema::hasTable('action_events') && Schema::hasColumn('action_events', 'company_id')) {
            DB::table('action_events')
                ->whereNull('company_id')
                ->whereNotNull('user_id')
                ->update([
                    'company_id' => DB::raw('(SELECT users.company_id FROM users WHERE users.id = action_events.user_id)'),
                ]);

            $this->warnRemaining('action_events');
        }

        foreach (['salary_grades', 'sanggunian_members', 'aa'] as $table) {
            $this->warnRemaining($table);
        }
    }

    private function addCompanyMetadata(): void
    {
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table): void {
                if (! Schema::hasColumn('companies', 'slug')) {
                    $table->string('slug')->nullable()->unique();
                }

                if (! Schema::hasColumn('companies', 'domain')) {
                    $table->string('domain')->nullable()->unique();
                }

                if (! Schema::hasColumn('companies', 'api_key')) {
                    $table->string('api_key', 64)->nullable()->unique();
                }
            });

            DB::table('companies')->whereNull('slug')->orderBy('id')->get()->each(function ($company): void {
                DB::table('companies')
                    ->where('id', $company->id)
                    ->update(['slug' => 'company-'.$company->id]);
            });
        }

        if (Schema::hasTable('company_user') && ! Schema::hasColumn('company_user', 'group_id')) {
            Schema::table('company_user', function (Blueprint $table): void {
                $table->unsignedBigInteger('group_id')->nullable()->index();
            });
        }
    }

    private function backfillCompanyMemberships(): void
    {
        if (! Schema::hasTable('company_user') || ! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('company_id')
            ->orderBy('id')
            ->get()
            ->each(function ($user): void {
                DB::table('company_user')->insertOrIgnore([
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'is_owner' => (bool) $user->owner,
                    'group_id' => $user->group_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('company_user')
            ->whereNull('group_id')
            ->update([
                'group_id' => DB::raw('(SELECT users.group_id FROM users WHERE users.id = company_user.user_id AND users.company_id = company_user.company_id)'),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function companyColumns(): array
    {
        $columns = [];

        foreach (array_merge($this->employeeChildTables, $this->standaloneTables) as $table) {
            $columns[$table] = 'company_id';
        }

        return $columns;
    }

    private function warnRemaining(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
            return;
        }

        $remaining = DB::table($table)->whereNull('company_id')->count();

        if ($remaining > 0) {
            Log::warning("Tenancy backfill left {$remaining} row(s) without company_id in [{$table}].");
        }
    }
};
