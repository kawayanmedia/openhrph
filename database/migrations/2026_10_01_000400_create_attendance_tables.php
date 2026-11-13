<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timesheets, leave, overtime and schedule requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_applications')) {
            Schema::create('leave_applications', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('employee_id')->nullable();
                $table->string('employee_number', 32)->nullable();
                $table->integer('office_id');
                $table->string('multiple', 64);
                $table->unsignedInteger('month');
                $table->integer('year');
                $table->integer('leave_type_id');
                $table->string('month5', 2);
                $table->string('year5', 4);
                $table->string('multiple5', 64);
                $table->integer('special_priv_id');
                $table->double('days');
                $table->integer('mone');
                $table->date('date_encode');
                $table->integer('allow_sat_sun');
                $table->boolean('is_abroad')->default(false);
                $table->boolean('in_hospital')->default(false);
                $table->string('details', 128)->nullable();
                $table->boolean('is_new_form')->default(false);
                $table->string('username', 16);
                $table->boolean('approved');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('employee_id');
                $table->date('date_filed');
                $table->integer('leave_type_id');
                $table->date('date_from');
                $table->date('date_to');
                $table->integer('days_with_pay')->nullable();
                $table->integer('days_without_pay')->nullable();
                $table->string('reason', 128)->nullable();
                $table->string('attachments', 128)->nullable();
                $table->boolean('is_whole_day')->default(true);
                $table->string('remarks', 128)->nullable();
                $table->integer('approved_user_id')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('reject_reason', 128)->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->string('status', 32)->nullable()->default('Pending');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('code', 32);
                $table->text('name');
                $table->boolean('deductable');
                $table->integer('deduct_to');
                $table->integer('allow_per_year');
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('leaves')) {
            Schema::create('leaves', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128);
                $table->string('code', 32)->nullable();
                $table->boolean('is_leave_credits')->nullable()->default(true);
                $table->boolean('is_payable')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('leaves_assignments')) {
            Schema::create('leaves_assignments', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128);
                $table->boolean('is_default')->nullable()->default(true);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('official_business_requests')) {
            Schema::create('official_business_requests', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('employee_id');
                $table->date('date_filed');
                $table->string('location', 128)->nullable();
                $table->date('date_from');
                $table->date('date_to')->nullable();
                $table->time('time_from');
                $table->time('time_to')->nullable();
                $table->string('purpose', 128)->nullable();
                $table->string('attachments', 128)->nullable();
                $table->string('remarks', 128)->nullable();
                $table->integer('approved_user_id')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('reject_reason', 128)->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->string('status', 32)->nullable()->default('Pending');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('overtime_requests')) {
            Schema::create('overtime_requests', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('employee_id');
                $table->date('date_filed');
                $table->string('type', 32)->default('ot');
                $table->date('date');
                $table->time('time_from');
                $table->time('time_to')->nullable();
                $table->integer('hours')->nullable();
                $table->string('reason', 128)->nullable();
                $table->string('remarks', 128)->nullable();
                $table->integer('approved_user_id')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('reject_reason', 128)->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->string('status', 32)->nullable()->default('Pending');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('schedule_adjustment_requests')) {
            Schema::create('schedule_adjustment_requests', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('employee_id');
                $table->date('date_filed');
                $table->date('date_from');
                $table->date('date_to')->nullable();
                $table->time('time_from')->nullable();
                $table->time('break_time_start')->nullable();
                $table->time('break_time_end')->nullable();
                $table->time('time_to')->nullable();
                $table->string('remarks', 128)->nullable();
                $table->integer('schedule_id')->nullable();
                $table->boolean('is_rd')->default(false);
                $table->integer('approved_user_id')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->string('reject_reason', 128)->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->string('status', 32)->nullable()->default('Pending');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('tardiness')) {
            Schema::create('tardiness', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128);
                $table->integer('grace_period')->nullable();
                $table->integer('start_deducting')->nullable();
                $table->json('affected')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('timesheet_requests')) {
            Schema::create('timesheet_requests', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('employee_id');
                $table->string('type', 128);
                $table->string('reason', 128)->nullable();
                $table->date('log_date')->nullable();
                $table->time('time')->nullable();
                $table->tinyInteger('log_type')->default(0);
                $table->string('status', 32)->default('Pending');
                $table->date('date_filed');
                $table->string('remarks', 128)->nullable();
                $table->integer('approved_user_id')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('timesheets')) {
            Schema::create('timesheets', function (Blueprint $table): void {
                $table->id();
                $table->integer('employee_id');
                $table->string('employee_number', 32);
                $table->integer('biometric_id')->nullable();
                $table->string('rate_code', 32)->nullable();
                $table->string('day_type', 32)->nullable();
                $table->string('holiday_type', 32)->nullable();
                $table->integer('holiday_id')->nullable();
                $table->string('holiday_code', 32)->nullable();
                $table->string('holiday_name', 64)->nullable();
                $table->date('log_date')->nullable();
                $table->json('logs')->nullable();
                $table->json('logs_rates')->nullable();
                $table->string('am_login', 8)->nullable();
                $table->string('am_logout', 8)->nullable();
                $table->string('pm_login', 8)->nullable();
                $table->string('pm_logout', 8)->nullable();
                $table->string('ot_login', 8)->nullable();
                $table->text('ot_logout')->nullable();
                $table->string('am_out_morning', 8)->nullable();
                $table->integer('regular')->nullable();
                $table->integer('ot')->nullable();
                $table->integer('nt')->nullable();
                $table->integer('nt_ot')->nullable();
                $table->integer('ut')->nullable();
                $table->integer('regular_rate')->nullable();
                $table->integer('ot_rate')->nullable();
                $table->integer('nt_rate')->nullable();
                $table->integer('nt_ot_rate')->nullable();
                $table->integer('ut_rate')->nullable();
                $table->integer('actual_regular')->nullable();
                $table->integer('actual_ot')->nullable();
                $table->integer('actual_nt')->nullable();
                $table->integer('actual_nt_ot')->nullable();
                $table->boolean('is_locked')->default(false);
                $table->integer('leave_type_id')->nullable();
                $table->boolean('leave_half_day')->nullable();
                $table->boolean('is_leave')->default(false);
                $table->boolean('is_paid_leave')->default(false);
                $table->boolean('is_absent')->default(false);
                $table->integer('office_id')->nullable();
                $table->integer('department_id')->nullable();
                $table->integer('compensatory_timeoff_id')->nullable();
                $table->integer('schedule_id')->nullable();
                $table->integer('shift_id')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['employee_id']);
                $table->index(['company_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['timesheets', 'timesheet_requests', 'tardiness', 'schedule_adjustment_requests', 'overtime_requests', 'official_business_requests', 'leaves_assignments', 'leaves', 'leave_types', 'leave_requests', 'leave_applications'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
