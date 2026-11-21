<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Country scope for tables that were previously Philippine-only.
 *
 * Columns are additive and default to PH so an existing installation keeps
 * working before the installer is re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumn('companies', 'country_code', fn (Blueprint $table) => $table->string('country_code', 2)->nullable()->index());
        $this->addColumn('holidays', 'country_code', fn (Blueprint $table) => $table->string('country_code', 2)->default('PH')->index());
        $this->addColumn('holidays', 'type', fn (Blueprint $table) => $table->string('type', 32)->nullable()->index());
        $this->addColumn('leave_types', 'country_code', fn (Blueprint $table) => $table->string('country_code', 2)->nullable()->index());
        $this->addColumn('compensations', 'country_code', fn (Blueprint $table) => $table->string('country_code', 2)->nullable()->index());
        $this->addColumn('payrolls', 'country_code', fn (Blueprint $table) => $table->string('country_code', 2)->nullable()->index());
        $this->addColumn('payrolls', 'policy_version', fn (Blueprint $table) => $table->string('policy_version', 64)->nullable());
        $this->addColumn('payrolls', 'rules_snapshot', fn (Blueprint $table) => $table->json('rules_snapshot')->nullable());

        if (! Schema::hasTable('employee_identifiers')) {
            Schema::create('employee_identifiers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('country_code', 2)->default('PH')->index();
                $table->string('type', 32);
                $table->string('number', 64);
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['employee_id', 'type']);
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_identifiers');

        $this->dropColumn('payrolls', 'rules_snapshot');
        $this->dropColumn('payrolls', 'policy_version');
        $this->dropColumn('payrolls', 'country_code');
        $this->dropColumn('compensations', 'country_code');
        $this->dropColumn('leave_types', 'country_code');
        $this->dropColumn('holidays', 'type');
        $this->dropColumn('holidays', 'country_code');
        $this->dropColumn('companies', 'country_code');
    }

    private function addColumn(string $table, string $column, callable $definition): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }

    private function dropColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropColumn($column);
        });
    }

    private function backfill(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'country_code')) {
            DB::table('companies')->whereNull('country_code')->update(['country_code' => 'PH']);
        }

        if (Schema::hasTable('leave_types') && Schema::hasColumn('leave_types', 'country_code')) {
            DB::table('leave_types')->whereNull('country_code')->update(['country_code' => 'PH']);
        }

        if (Schema::hasTable('compensations') && Schema::hasColumn('compensations', 'country_code')) {
            DB::table('compensations')->whereNull('country_code')->update(['country_code' => 'PH']);
        }

        if (! Schema::hasTable('employee_identifiers') || ! Schema::hasTable('employees')) {
            return;
        }

        $now = now();
        $columns = ['tin_number', 'sss_number', 'gsis_number', 'philhealth_number', 'hdmf_number'];

        foreach ($columns as $column) {
            if (! Schema::hasColumn('employees', $column)) {
                continue;
            }

            DB::table('employees')
                ->select(['id', 'company_id', $column])
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderBy('id')
                ->chunk(500, function ($employees) use ($column, $now): void {
                    foreach ($employees as $employee) {
                        DB::table('employee_identifiers')->updateOrInsert(
                            ['employee_id' => $employee->id, 'type' => $column],
                            [
                                'company_id' => $employee->company_id,
                                'country_code' => 'PH',
                                'number' => (string) $employee->{$column},
                                'created_at' => $now,
                                'updated_at' => $now,
                            ],
                        );
                    }
                });
        }
    }
};
