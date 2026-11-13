<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll runs, payroll details and payroll grouping tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_dates')) {
            Schema::create('payroll_dates', function (Blueprint $table): void {
                $table->id();
                $table->integer('company_id');
                $table->integer('payroll_group_id');
                $table->date('payroll_date')->nullable();
                $table->boolean('first_second')->nullable();
                $table->date('attendance_date_start')->nullable();
                $table->date('attendance_date_end')->nullable();
                $table->string('status', 32)->default('open');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id', 'payroll_group_id']);
            });
        }

        if (! Schema::hasTable('payroll_details')) {
            Schema::create('payroll_details', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('payroll_id');
                $table->integer('employee_id');
                $table->integer('year')->nullable();
                $table->integer('basic_monthly_salary')->nullable();
                $table->integer('basic_salary')->nullable();
                $table->integer('daily_rate')->nullable();
                $table->json('compensations')->nullable();
                $table->integer('representation_allowance')->nullable();
                $table->integer('transportation_allowance')->nullable();
                $table->integer('cola')->nullable();
                $table->integer('fixed_housing_allowance')->nullable();
                $table->integer('other_taxable_regular')->nullable();
                $table->integer('commission')->nullable();
                $table->integer('profit_sharing')->nullable();
                $table->integer('fees')->nullable();
                $table->integer('hazard_pay')->nullable();
                $table->integer('other_taxable_supplementary')->nullable();
                $table->integer('gross_income')->default(0);
                $table->integer('taxable_income')->default(0);
                $table->integer('non_taxable_income')->nullable();
                $table->integer('withholding_tax')->default(0);
                $table->integer('sss_employee')->default(0);
                $table->integer('sss_provident_employee')->default(0);
                $table->integer('sss_employee_total')->nullable();
                $table->integer('sss_employer')->default(0);
                $table->integer('sss_provident_employer')->default(0);
                $table->integer('sss_employer_ec_er')->nullable();
                $table->integer('sss_employer_total')->nullable();
                $table->integer('sss_total')->nullable();
                $table->integer('hdmf_employee')->default(0);
                $table->integer('hdmf_employer')->default(0);
                $table->integer('hdmf_total')->nullable();
                $table->integer('philhealth_employee')->default(0);
                $table->integer('philhealth_employer')->default(0);
                $table->integer('total_philhealth')->nullable();
                $table->integer('regular_hours')->nullable();
                $table->integer('regular_pay')->nullable();
                $table->integer('overtime_hours')->nullable();
                $table->integer('overtime_pay')->nullable();
                $table->integer('night_hours')->nullable();
                $table->integer('night_pay')->nullable();
                $table->integer('rest_day_hours')->nullable();
                $table->integer('rest_day_pay')->nullable();
                $table->integer('holiday_hours')->nullable();
                $table->integer('holiday_pay')->nullable();
                $table->integer('absent_days')->nullable();
                $table->integer('absent_days_amount')->nullable();
                $table->integer('allowance')->nullable();
                $table->integer('incentive')->nullable();
                $table->integer('total_employee')->default(0);
                $table->integer('total_employer')->default(0);
                $table->integer('total_deductions')->default(0);
                $table->integer('net_pay')->default(0);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->bigInteger('bonus')->default(0);
                $table->bigInteger('thirteenth_month')->default(0);
                $table->bigInteger('leave_conversion')->default(0);
                $table->bigInteger('separation_pay')->default(0);
                $table->bigInteger('loans')->default(0);
                $table->bigInteger('minimum_wage_exemption')->default(0);
                $table->json('warnings')->nullable();
                $table->bigInteger('basic_earned')->default(0);
                $table->bigInteger('benefit_exempt')->default(0);
                $table->index(['company_id', 'payroll_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('payroll_details_pays')) {
            Schema::create('payroll_details_pays', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('payroll_id');
                $table->integer('employee_id');
                $table->string('day_type', 32);
                $table->string('rate_type', 32);
                $table->boolean('is_rest_day')->default(false);
                $table->boolean('is_holiday')->default(false);
                $table->integer('hours')->default(0);
                $table->integer('rate')->default(0);
                $table->integer('equivalent_hours')->default(0);
                $table->integer('daily_rate')->default(0);
                $table->integer('hourly_rate')->nullable();
                $table->integer('total_amount')->default(0);
                $table->boolean('is_regular_pay')->default(false);
                $table->boolean('is_overtime_pay')->default(false);
                $table->boolean('is_night_pay')->nullable()->default(false);
                $table->boolean('is_rest_day_pay')->default(false);
                $table->boolean('is_holiday_pay')->default(false);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id', 'payroll_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('payroll_groups')) {
            Schema::create('payroll_groups', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 32);
                $table->integer('days_year')->nullable();
                $table->integer('hours_day')->nullable();
                $table->string('frequency', 64)->nullable()->default('semi-monthly');
                $table->integer('first_cut_off')->nullable();
                $table->integer('first_pay_date')->nullable();
                $table->integer('second_cut_off')->nullable();
                $table->integer('second_pay_date')->nullable();
                $table->integer('first_run')->nullable();
                $table->integer('second_run')->nullable();
                $table->string('pay_date_non_working', 32)->nullable();
                $table->string('tax_schedule', 32)->default('every_pay');
                $table->string('sss_schedule', 32)->default('every_pay');
                $table->string('phil_health_schedule', 32)->default('every_pay');
                $table->string('hdmf_schedule', 32)->default('every_pay');
                $table->boolean('compute_tardiness')->default(false);
                $table->boolean('compute_overbreak')->default(false);
                $table->boolean('compute_undertime')->default(false);
                $table->boolean('is_default')->default(false);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('payroll_year_date')) {
            Schema::create('payroll_year_date', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('employee_id');
                $table->integer('year')->nullable();
                $table->integer('taxable_gross')->nullable();
                $table->integer('tax')->nullable();
                $table->integer('sss')->nullable();
                $table->integer('philhealth')->nullable();
                $table->integer('hdmf')->nullable();
                $table->integer('gross_income')->nullable();
                $table->integer('non_taxable')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('payrolls')) {
            Schema::create('payrolls', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('payroll_group_id');
                $table->date('payroll_date')->nullable();
                $table->string('schedule', 32)->nullable();
                $table->string('cut_off', 32)->nullable();
                $table->string('type', 32)->default('regular');
                $table->json('meta')->nullable();
                $table->date('date_from')->nullable();
                $table->date('date_to')->nullable();
                $table->integer('gross_amount')->nullable();
                $table->bigInteger('net_amount')->nullable();
                $table->string('status', 32)->default('open');
                $table->string('engine_version', 32)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('override_reason')->nullable();
                $table->unsignedBigInteger('override_by')->nullable();
                $table->timestamp('override_at')->nullable();
                $table->unsignedInteger('processed_employees')->default(0);
                $table->unsignedInteger('total_employees')->default(0);
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->text('failed_reason')->nullable();
                $table->json('warnings')->nullable();
                $table->string('input_hash', 64)->nullable();
                $table->timestamp('reopened_at')->nullable();
                $table->unsignedBigInteger('reopened_by')->nullable();
                $table->text('reopen_reason')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id', 'payroll_group_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['payrolls', 'payroll_year_date', 'payroll_groups', 'payroll_details_pays', 'payroll_details', 'payroll_dates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
