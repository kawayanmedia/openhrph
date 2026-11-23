<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frozen attendance lines for an approved payroll.
 *
 * A re-run, payslip reprint or audit reads these rows instead of live
 * timesheets, so a later correction cannot change a signed-off payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_attendance_snapshots')) {
            return;
        }

        Schema::create('payroll_attendance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('payroll_id');
            $table->unsignedBigInteger('timesheet_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('log_date');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->string('day_type', 32)->nullable();
            $table->string('day_rate_code', 32)->nullable();
            $table->unsignedInteger('scheduled_minutes')->default(0);
            $table->unsignedInteger('paid_minutes')->default(0);
            $table->unsignedInteger('regular_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('night_minutes')->default(0);
            $table->unsignedInteger('night_ot_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('undertime_minutes')->default(0);
            $table->unsignedInteger('approved_ot_minutes')->default(0);
            $table->unsignedSmallInteger('rule_version')->nullable();
            $table->boolean('is_absent')->default(false);
            $table->boolean('is_leave')->default(false);
            $table->boolean('is_paid_leave')->default(false);
            $table->decimal('leave_fraction', 4, 2)->default(1);
            $table->dateTime('snapshot_at');
            $table->timestamps();

            $table->unique(['payroll_id', 'timesheet_id'], 'payroll_attendance_snapshots_unique');
            $table->index(['company_id', 'employee_id', 'log_date'], 'payroll_attendance_snapshots_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_attendance_snapshots');
    }
};
