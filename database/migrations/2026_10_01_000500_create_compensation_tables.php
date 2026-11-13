<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compensation catalogues and employee compensation assignments.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('allowances')) {
            Schema::create('allowances', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128)->nullable();
                $table->integer('amount')->nullable();
                $table->string('tax_option', 32)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('bonuses')) {
            Schema::create('bonuses', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128);
                $table->string('amount_basis', 32)->nullable();
                $table->string('frequency', 32)->nullable();
                $table->boolean('is_taxable')->default(false);
                $table->integer('maximum_non_tax')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('commissions')) {
            Schema::create('commissions', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128);
                $table->string('amount_basis', 32)->nullable();
                $table->boolean('is_taxable')->default(false);
                $table->integer('maximum_non_tax')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('compensations')) {
            Schema::create('compensations', function (Blueprint $table): void {
                $table->id();
                $table->integer('company_id');
                $table->string('code', 128);
                $table->string('name', 64);
                $table->string('tax_class_code', 64)->nullable();
                $table->string('tax_class', 128)->nullable();
                $table->string('group', 128)->nullable();
                $table->string('tax_option', 64)->nullable();
                $table->boolean('is_taxable')->default(true);
                $table->double('amount')->default(0);
                $table->enum('status', ['Active', 'Inactive'])->default('Active');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('deductions')) {
            Schema::create('deductions', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('deductions_pagibig')) {
            Schema::create('deductions_pagibig', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('basic_salary_start')->nullable();
                $table->integer('basic_salary_end')->nullable();
                $table->integer('premium_rate')->nullable();
                $table->integer('employee_share')->nullable();
                $table->integer('employer_share')->nullable();
                $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('deductions_philhealth')) {
            Schema::create('deductions_philhealth', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('monthly_basic', 64);
                $table->date('effectivity_date')->nullable();
                $table->date('effectivity_date_to')->nullable();
                $table->integer('basic_salary_start')->nullable();
                $table->integer('basic_salary_end')->nullable();
                $table->integer('premium_rate')->nullable();
                $table->integer('monthly_share')->nullable();
                $table->integer('employee_share')->nullable();
                $table->integer('employer_share')->nullable();
                $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('employees_allowances')) {
            Schema::create('employees_allowances', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('employee_id')->nullable();
                $table->integer('allowance_id');
                $table->boolean('is_special')->default(true);
                $table->date('release_date')->nullable();
                $table->integer('amount');
                $table->date('valid_date_from')->nullable();
                $table->date('valid_date_to')->nullable();
                $table->boolean('is_recurring')->default(false);
                $table->string('frequency', 32)->nullable();
                $table->boolean('is_never_expires')->default(false);
                $table->boolean('is_prorated')->default(false);
                $table->string('prorated_by', 8)->nullable();
                $table->boolean('on_paid_leave')->default(false);
                $table->boolean('on_unpaid_leave')->default(false);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['employee_id', 'allowance_id']);
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('employees_allowances_recurrings')) {
            Schema::create('employees_allowances_recurrings', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('employee_id')->nullable();
                $table->integer('employee_allowance_id');
                $table->integer('allowance_id');
                $table->date('release_date')->nullable();
                $table->integer('amount');
                $table->string('status', 32)->nullable()->default('not_release');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['employee_id', 'employee_allowance_id'], 'ear_employee_allowance_index');
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('employees_bonuses')) {
            Schema::create('employees_bonuses', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('employee_id')->nullable();
                $table->integer('bonus_id')->nullable();
                $table->integer('amount')->nullable();
                $table->date('release_date')->nullable();
                $table->string('status', 32)->default('not_release');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['employee_id', 'bonus_id']);
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('employees_commissions')) {
            Schema::create('employees_commissions', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('employee_id')->nullable();
                $table->integer('commission_id')->nullable();
                $table->integer('amount')->nullable();
                $table->date('release_date')->nullable();
                $table->string('status', 32)->default('not_release');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['employee_id', 'commission_id']);
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('employees_compensations')) {
            Schema::create('employees_compensations', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('employee_id')->nullable();
                $table->integer('compensation_id');
                $table->boolean('is_special')->default(true);
                $table->date('release_date')->nullable();
                $table->integer('amount');
                $table->date('valid_date_from')->nullable();
                $table->date('valid_date_to')->nullable();
                $table->boolean('is_recurring')->default(false);
                $table->string('frequency', 32)->nullable();
                $table->boolean('is_never_expires')->default(false);
                $table->boolean('is_prorated')->default(false);
                $table->string('prorated_by', 8)->nullable();
                $table->boolean('on_paid_leave')->default(false);
                $table->boolean('on_unpaid_leave')->default(false);
                $table->boolean('is_taxable')->default(true);
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['employee_id']);
            });
        }

        if (! Schema::hasTable('employees_deductions')) {
            Schema::create('employees_deductions', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('employee_id')->nullable();
                $table->integer('deduction_id')->nullable();
                $table->integer('amount')->default(0);
                $table->boolean('is_recurring')->default(false);
                $table->string('frequency', 16)->default('every_pay');
                $table->date('valid_date_from')->nullable();
                $table->date('valid_date_to')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['employee_id', 'deduction_id']);
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('employees_loans')) {
            Schema::create('employees_loans', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('employee_id')->nullable();
                $table->integer('loan_id')->nullable();
                $table->string('bank_name', 128)->nullable();
                $table->string('reference_number', 128);
                $table->string('type', 128)->nullable();
                $table->string('sss_loan_type', 32)->nullable();
                $table->string('hdmf_loan_type', 32)->nullable();
                $table->date('approved_date')->nullable();
                $table->integer('amount');
                $table->integer('balance')->nullable();
                $table->integer('terms');
                $table->date('repayment_start_date')->nullable();
                $table->date('repayment_end_date')->nullable();
                $table->string('scheme', 32);
                $table->integer('monthly_amortization')->default(0);
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['employee_id', 'loan_id']);
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('loans')) {
            Schema::create('loans', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('loan_agency', 32)->nullable();
                $table->string('code', 32);
                $table->string('name', 128);
                $table->boolean('is_default')->nullable()->default(true);
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('rates')) {
            Schema::create('rates', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('code', 32);
                $table->string('day_type', 43)->nullable();
                $table->boolean('is_rest_day')->default(false);
                $table->boolean('is_holiday')->default(true);
                $table->integer('regular')->nullable();
                $table->integer('ot')->nullable();
                $table->integer('nt')->nullable();
                $table->integer('nt_ot')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('users_compensations')) {
            Schema::create('users_compensations', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('user_id');
                $table->integer('compensation_id');
                $table->string('effectivity_date', 32);
                $table->string('ineffectivity_date', 32);
                $table->string('amount', 16);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['users_compensations', 'rates', 'loans', 'employees_loans', 'employees_deductions', 'employees_compensations', 'employees_commissions', 'employees_bonuses', 'employees_allowances_recurrings', 'employees_allowances', 'deductions_philhealth', 'deductions_pagibig', 'deductions', 'compensations', 'commissions', 'bonuses', 'allowances'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
