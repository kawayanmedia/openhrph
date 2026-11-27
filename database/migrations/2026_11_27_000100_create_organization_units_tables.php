<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical organizational structure.
 *
 * `organization_units` is the hierarchy new HR features read from. Legacy
 * `departments` and `offices` keep their own rows for payroll and attendance,
 * but gain a nullable link to the unit they represent so both models stay in
 * sync instead of duplicating the tree.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_units')) {
            Schema::create('organization_units', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('code', 32)->nullable();
                $table->string('name', 128);
                $table->string('unit_type', 32)->default('unit');
                $table->string('path', 255)->nullable();
                $table->unsignedSmallInteger('depth')->default(0);
                $table->unsignedBigInteger('manager_employee_id')->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->unsignedBigInteger('cost_center_id')->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'parent_id']);
                $table->index(['company_id', 'unit_type']);
                $table->index(['company_id', 'path']);
                $table->index(['company_id', 'code']);
            });
        }

        if (! Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('code', 32)->nullable();
                $table->string('name', 128);
                $table->string('address_no', 32)->nullable();
                $table->string('street', 128)->nullable();
                $table->string('barangay', 96)->nullable();
                $table->string('city', 96)->nullable();
                $table->string('province', 96)->nullable();
                $table->string('zip_code', 12)->nullable();
                $table->string('country', 2)->default('PH');
                $table->string('timezone', 64)->nullable();
                $table->string('phone', 32)->nullable();
                $table->string('email', 128)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'is_active']);
                $table->unique(['company_id', 'code']);
            });
        }

        if (! Schema::hasTable('cost_centers')) {
            Schema::create('cost_centers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('code', 32)->nullable();
                $table->string('name', 128);
                $table->string('description', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'parent_id']);
                $table->unique(['company_id', 'code']);
            });
        }

        $this->addColumn('departments', 'organization_unit_id', fn (Blueprint $table) => $table->unsignedBigInteger('organization_unit_id')->nullable()->index());
        $this->addColumn('offices', 'organization_unit_id', fn (Blueprint $table) => $table->unsignedBigInteger('organization_unit_id')->nullable()->index());
        $this->addColumn('employees', 'organization_unit_id', fn (Blueprint $table) => $table->unsignedBigInteger('organization_unit_id')->nullable()->index());
        $this->addColumn('employees', 'location_id', fn (Blueprint $table) => $table->unsignedBigInteger('location_id')->nullable()->index());
        $this->addColumn('employees', 'cost_center_id', fn (Blueprint $table) => $table->unsignedBigInteger('cost_center_id')->nullable()->index());
        $this->addColumn('employees', 'lifecycle_status', fn (Blueprint $table) => $table->string('lifecycle_status', 32)->default('active')->index());
        $this->addColumn('positions', 'organization_unit_id', fn (Blueprint $table) => $table->unsignedBigInteger('organization_unit_id')->nullable()->index());
        $this->addColumn('positions', 'location_id', fn (Blueprint $table) => $table->unsignedBigInteger('location_id')->nullable()->index());
        $this->addColumn('positions', 'cost_center_id', fn (Blueprint $table) => $table->unsignedBigInteger('cost_center_id')->nullable()->index());

        $this->backfillUnits();
    }

    public function down(): void
    {
        foreach ([
            'departments' => ['organization_unit_id'],
            'offices' => ['organization_unit_id'],
            'employees' => ['organization_unit_id', 'location_id', 'cost_center_id', 'lifecycle_status'],
            'positions' => ['organization_unit_id', 'location_id', 'cost_center_id'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
                }
            }
        }

        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('organization_units');
    }

    /**
     * @param  callable(Blueprint): mixed  $definition
     */
    protected function addColumn(string $table, string $column, callable $definition): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }

    /**
     * Give every legacy department and office an organization unit so new
     * features can read one canonical tree from day one.
     */
    protected function backfillUnits(): void
    {
        if (! Schema::hasTable('organization_units') || ! Schema::hasTable('departments')) {
            return;
        }

        $now = now();

        if (Schema::hasTable('departments')) {
            DB::table('departments')
                ->whereNull('organization_unit_id')
                ->orderBy('id')
                ->each(function ($department) use ($now): void {
                    $unitId = DB::table('organization_units')->insertGetId([
                        'company_id' => $department->company_id,
                        'code' => $department->code,
                        'name' => $department->name,
                        'unit_type' => 'department',
                        'depth' => 0,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('organization_units')->where('id', $unitId)->update([
                        'path' => (string) $unitId,
                    ]);

                    DB::table('departments')->where('id', $department->id)->update([
                        'organization_unit_id' => $unitId,
                    ]);
                });
        }

        if (Schema::hasTable('offices')) {
            DB::table('offices')
                ->whereNull('organization_unit_id')
                ->orderBy('id')
                ->each(function ($office) use ($now): void {
                    $unitId = DB::table('organization_units')->insertGetId([
                        'company_id' => $office->company_id,
                        'code' => $office->code,
                        'name' => $office->name,
                        'unit_type' => 'office',
                        'depth' => 0,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('organization_units')->where('id', $unitId)->update([
                        'path' => (string) $unitId,
                    ]);

                    DB::table('offices')->where('id', $office->id)->update([
                        'organization_unit_id' => $unitId,
                    ]);
                });
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'organization_unit_id')) {
            DB::table('employees')
                ->whereNull('organization_unit_id')
                ->orderBy('id')
                ->each(function ($employee): void {
                    $unitId = null;

                    if ($employee->department_id !== null) {
                        $unitId = DB::table('departments')->where('id', $employee->department_id)->value('organization_unit_id');
                    }

                    if ($unitId === null && $employee->office_id !== null) {
                        $unitId = DB::table('offices')->where('id', $employee->office_id)->value('organization_unit_id');
                    }

                    if ($unitId !== null) {
                        DB::table('employees')->where('id', $employee->id)->update([
                            'organization_unit_id' => $unitId,
                        ]);
                    }
                });
        }

        if (Schema::hasTable('positions') && Schema::hasColumn('positions', 'organization_unit_id')) {
            DB::table('positions')
                ->whereNull('organization_unit_id')
                ->whereNotNull('department_id')
                ->orderBy('id')
                ->each(function ($position): void {
                    $unitId = DB::table('departments')->where('id', $position->department_id)->value('organization_unit_id');

                    if ($unitId !== null) {
                        DB::table('positions')->where('id', $position->id)->update([
                            'organization_unit_id' => $unitId,
                        ]);
                    }
                });
        }
    }
};
