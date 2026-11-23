<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-class shifts and effective-dated schedule assignments.
 *
 * A shift carries its working segments and unpaid breaks so a fixed, split,
 * overnight or 12-hour shift is described the same way. Assignments add a
 * rotation cycle and an effective date range without touching the employee
 * record, which keeps historical days stable when someone changes shift.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_shifts')) {
            Schema::create('attendance_shifts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('name', 128);
                $table->string('code', 32)->nullable();
                $table->string('type', 32)->default('fixed');
                $table->json('segments');
                $table->json('breaks')->nullable();
                $table->string('night_window_start', 5)->nullable();
                $table->string('night_window_end', 5)->nullable();
                $table->unsignedSmallInteger('grace_minutes')->default(0);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('attendance_schedule_assignments')) {
            Schema::create('attendance_schedule_assignments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('shift_id')->nullable();
                $table->json('rotation')->nullable();
                $table->date('cycle_anchor')->nullable();
                $table->json('weekdays')->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('reason', 191)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employee_id', 'effective_from'], 'attendance_assignments_employee_from_index');
            });
        }

        if (! Schema::hasTable('attendance_schedule_overrides')) {
            Schema::create('attendance_schedule_overrides', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->date('date');
                $table->unsignedBigInteger('shift_id')->nullable();
                $table->boolean('is_rest_day')->default(false);
                $table->string('reason', 191)->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'employee_id', 'date'], 'attendance_overrides_employee_date_unique');
            });
        }

        if (Schema::hasTable('timesheets')) {
            Schema::table('timesheets', function (Blueprint $table): void {
                if (! Schema::hasColumn('timesheets', 'scheduled_minutes')) {
                    $table->unsignedInteger('scheduled_minutes')->nullable()->after('shift_id');
                }

                if (! Schema::hasColumn('timesheets', 'paid_minutes')) {
                    $table->unsignedInteger('paid_minutes')->nullable()->after('scheduled_minutes');
                }

                if (! Schema::hasColumn('timesheets', 'late_minutes')) {
                    $table->unsignedInteger('late_minutes')->nullable()->after('paid_minutes');
                }

                if (! Schema::hasColumn('timesheets', 'undertime_minutes')) {
                    $table->unsignedInteger('undertime_minutes')->nullable()->after('late_minutes');
                }

                if (! Schema::hasColumn('timesheets', 'approved_ot_minutes')) {
                    $table->unsignedInteger('approved_ot_minutes')->nullable()->after('undertime_minutes');
                }

                if (! Schema::hasColumn('timesheets', 'computed_at')) {
                    $table->dateTime('computed_at')->nullable()->after('approved_ot_minutes');
                }

                if (! Schema::hasColumn('timesheets', 'rule_version')) {
                    $table->unsignedSmallInteger('rule_version')->nullable()->after('computed_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('timesheets')) {
            Schema::table('timesheets', function (Blueprint $table): void {
                foreach (['rule_version', 'computed_at', 'approved_ot_minutes', 'undertime_minutes', 'late_minutes', 'paid_minutes', 'scheduled_minutes'] as $column) {
                    if (Schema::hasColumn('timesheets', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('attendance_schedule_overrides');
        Schema::dropIfExists('attendance_schedule_assignments');
        Schema::dropIfExists('attendance_shifts');
    }
};
