<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance corrections and timesheet approval history.
 *
 * Raw punches are never overwritten. A correction is an explicit request that
 * can be approved and applied as a new punch, with its own decision trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_corrections')) {
            Schema::create('attendance_corrections', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('attendance_punch_id')->nullable();
                $table->unsignedBigInteger('timesheet_id')->nullable();
                $table->date('workday_date');
                $table->string('correction_type', 32)->default('wrong_time');
                $table->json('original_values')->nullable();
                $table->json('requested_values');
                $table->text('reason')->nullable();
                $table->string('status', 32)->default('submitted');
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->unsignedBigInteger('workflow_instance_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id', 'workday_date'], 'attendance_corrections_employee_date_index');
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('attendance_correction_decisions')) {
            Schema::create('attendance_correction_decisions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('attendance_correction_id');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 32);
                $table->text('comments')->nullable();
                $table->timestamp('decided_at');
                $table->timestamp('created_at')->nullable();

                $table->index(['company_id', 'attendance_correction_id'], 'attendance_correction_decisions_correction_index');
            });
        }

        if (! Schema::hasTable('timesheet_approvals')) {
            Schema::create('timesheet_approvals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('timesheet_id');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 32);
                $table->text('comments')->nullable();
                $table->timestamp('decided_at');
                $table->timestamp('created_at')->nullable();

                $table->index(['company_id', 'timesheet_id', 'action']);
            });
        }

        if (Schema::hasTable('timesheets')) {
            $columns = [
                'approval_status' => fn (Blueprint $table) => $table->string('approval_status', 32)->nullable()->index(),
                'submitted_at' => fn (Blueprint $table) => $table->timestamp('submitted_at')->nullable(),
                'approved_at' => fn (Blueprint $table) => $table->timestamp('approved_at')->nullable(),
                'approved_by' => fn (Blueprint $table) => $table->unsignedBigInteger('approved_by')->nullable(),
                'locked_at' => fn (Blueprint $table) => $table->timestamp('locked_at')->nullable(),
            ];

            foreach ($columns as $column => $definition) {
                if (Schema::hasColumn('timesheets', $column)) {
                    continue;
                }

                Schema::table('timesheets', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_approvals');
        Schema::dropIfExists('attendance_correction_decisions');
        Schema::dropIfExists('attendance_corrections');

        if (Schema::hasTable('timesheets')) {
            foreach (['approval_status', 'submitted_at', 'approved_at', 'approved_by', 'locked_at'] as $column) {
                if (Schema::hasColumn('timesheets', $column)) {
                    Schema::table('timesheets', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
