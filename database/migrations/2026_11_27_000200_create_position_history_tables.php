<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Position catalogue detail and effective-dated employee placement history.
 *
 * `employees.position`, `department_id` and `immediate_head_employee_id`
 * remain as the current-value projection used by payroll and attendance.
 * The assignment tables are the history of record.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addPositionColumns();

        if (! Schema::hasTable('position_requirements')) {
            Schema::create('position_requirements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('position_id');
                $table->string('requirement_type', 32)->default('other');
                $table->string('description', 255);
                $table->string('reference', 128)->nullable();
                $table->boolean('is_required')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'position_id']);
                $table->index(['company_id', 'requirement_type']);
            });
        }

        if (! Schema::hasTable('job_descriptions')) {
            Schema::create('job_descriptions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('position_id');
                $table->unsignedInteger('version')->default(1);
                $table->string('title', 255)->nullable();
                $table->longText('content')->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->boolean('is_current')->default(true);
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'position_id', 'is_current']);
                $table->unique(['position_id', 'version']);
            });
        }

        if (! Schema::hasTable('position_assignments')) {
            Schema::create('position_assignments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('position_id')->nullable();
                $table->unsignedBigInteger('organization_unit_id')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('office_id')->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->unsignedBigInteger('cost_center_id')->nullable();
                $table->unsignedBigInteger('supervisor_employee_id')->nullable();
                $table->unsignedBigInteger('employment_type_id')->nullable();
                $table->string('assignment_type', 32)->default('regular');
                $table->string('status', 32)->default('active');
                $table->date('effective_from');
                $table->date('effective_until')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('reference', 128)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employee_id', 'effective_from']);
                $table->index(['company_id', 'position_id', 'status']);
                $table->index(['company_id', 'organization_unit_id']);
            });
        }

        if (! Schema::hasTable('reporting_relationships')) {
            Schema::create('reporting_relationships', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('supervisor_employee_id');
                $table->string('relationship_type', 32)->default('supervisor');
                $table->date('effective_from');
                $table->date('effective_until')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employee_id', 'effective_from'], 'reporting_relationships_employee_effective_index');
                $table->index(['company_id', 'supervisor_employee_id']);
            });
        }

        if (! Schema::hasTable('employment_changes')) {
            Schema::create('employment_changes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('change_type', 32);
                $table->date('effective_date');
                $table->unsignedBigInteger('from_position_id')->nullable();
                $table->unsignedBigInteger('to_position_id')->nullable();
                $table->unsignedBigInteger('from_organization_unit_id')->nullable();
                $table->unsignedBigInteger('to_organization_unit_id')->nullable();
                $table->unsignedBigInteger('from_supervisor_employee_id')->nullable();
                $table->unsignedBigInteger('to_supervisor_employee_id')->nullable();
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('workflow_instance_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employee_id', 'effective_date']);
                $table->index(['company_id', 'change_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_changes');
        Schema::dropIfExists('reporting_relationships');
        Schema::dropIfExists('position_assignments');
        Schema::dropIfExists('job_descriptions');
        Schema::dropIfExists('position_requirements');

        if (! Schema::hasTable('positions')) {
            return;
        }

        foreach (['description', 'job_family', 'level', 'reports_to_position_id', 'is_active'] as $column) {
            if (Schema::hasColumn('positions', $column)) {
                Schema::table('positions', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    protected function addPositionColumns(): void
    {
        if (! Schema::hasTable('positions')) {
            return;
        }

        $columns = [
            'description' => fn (Blueprint $table) => $table->text('description')->nullable(),
            'job_family' => fn (Blueprint $table) => $table->string('job_family', 64)->nullable(),
            'level' => fn (Blueprint $table) => $table->string('level', 32)->nullable(),
            'reports_to_position_id' => fn (Blueprint $table) => $table->unsignedBigInteger('reports_to_position_id')->nullable(),
            'is_active' => fn (Blueprint $table) => $table->boolean('is_active')->default(true),
        ];

        foreach ($columns as $column => $definition) {
            if (Schema::hasColumn('positions', $column)) {
                continue;
            }

            Schema::table('positions', function (Blueprint $blueprint) use ($definition): void {
                $definition($blueprint);
            });
        }
    }
};
