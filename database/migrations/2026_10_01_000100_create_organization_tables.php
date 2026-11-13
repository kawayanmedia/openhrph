<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organization, office and shared reference tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('user_id');
                $table->string('name', 64)->nullable();
                $table->string('code', 32)->nullable();
                $table->string('trade_name', 64)->nullable();
                $table->string('address_no', 32)->nullable();
                $table->string('street', 64)->nullable();
                $table->integer('barangay_id')->nullable();
                $table->integer('city_id')->nullable();
                $table->integer('province_id')->nullable();
                $table->integer('zip_code')->nullable();
                $table->date('date_registered')->nullable();
                $table->enum('formation', ['Sole Proprietorship', 'Partnership', 'Corporation'])->nullable();
                $table->string('line_business', 128)->nullable();
                $table->enum('industry', ['NON-AGRICULTURE', 'AGRICULTURE (Plantation)', 'AGRICULTURE (Non-plantation)'])->nullable();
                $table->enum('type', ['Private', 'Government', 'Non-Profit']);
                $table->string('tin', 32)->nullable();
                $table->string('rdo', 32)->nullable();
                $table->string('sss', 32)->nullable();
                $table->string('hdmf', 32)->nullable();
                $table->string('phil_health', 32)->nullable();
                $table->string('tel_number', 32)->nullable();
                $table->string('fax_number', 32)->nullable();
                $table->string('email', 64)->nullable();
                $table->string('website', 64)->nullable();
                $table->string('signatory_bir', 128)->nullable();
                $table->string('signatory_sss', 128)->nullable();
                $table->string('signatory_phic', 128)->nullable();
                $table->string('signatory_hdmf', 128)->nullable();
                $table->string('signatory_dole', 128)->nullable();
                $table->boolean('is_subscription')->default(false);
                $table->json('hidden_menu')->nullable();
                $table->string('uuid', 36)->nullable();
                $table->boolean('setup_done')->default(false);
                $table->string('primary_color', 32)->nullable();
                $table->string('logo', 128)->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->json('settings')->nullable();
                $table->string('slug')->nullable();
                $table->string('domain')->nullable();
                $table->string('api_key', 64)->nullable();
                $table->unique(['api_key']);
                $table->index(['code']);
                $table->unique(['domain']);
                $table->unique(['slug']);
                $table->index(['user_id']);
            });
        }

        if (! Schema::hasTable('companies_banks')) {
            Schema::create('companies_banks', function (Blueprint $table): void {
                $table->id();
                $table->integer('company_id');
                $table->string('name', 64)->nullable();
                $table->string('code', 64)->nullable();
                $table->string('account_number', 32)->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('company_user')) {
            Schema::create('company_user', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('user_id');
                $table->boolean('is_owner')->default(false);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unsignedBigInteger('group_id')->nullable();
                $table->index(['group_id']);
                $table->primary(['company_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('code', 10)->nullable();
                $table->string('name', 128);
                $table->string('address', 64)->nullable();
                $table->integer('attendance_approval_employee_id')->nullable();
                $table->integer('overtime_approval_employee_id')->nullable();
                $table->integer('leave_approval_employee_id')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('employment_types')) {
            Schema::create('employment_types', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('code', 10);
                $table->string('name', 128);
                $table->boolean('is_payroll')->default(true);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('groups')) {
            Schema::create('groups', function (Blueprint $table): void {
                $table->id();
                $table->integer('company_id');
                $table->string('name', 64);
                $table->boolean('active')->nullable();
                $table->text('permissions')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('year')->nullable();
                $table->string('name', 128);
                $table->string('holiday_type', 32)->nullable();
                $table->string('code', 8)->nullable();
                $table->date('date')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('minimum_wage_rates')) {
            Schema::create('minimum_wage_rates', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('region', 128);
                $table->string('industry', 32)->nullable();
                $table->integer('wage_start')->nullable();
                $table->integer('wage_end')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('offices')) {
            Schema::create('offices', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('code', 10)->nullable();
                $table->string('name', 128);
                $table->string('account_number', 16)->nullable();
                $table->string('program', 512)->nullable();
                $table->string('address', 64)->nullable();
                $table->string('salary_grade_type', 32)->nullable();
                $table->string('head', 64)->nullable();
                $table->string('employee_id', 16)->nullable();
                $table->string('position', 64)->nullable();
                $table->string('location', 64)->default('internal');
                $table->string('region', 32)->nullable();
                $table->string('industry', 64)->nullable();
                $table->tinyInteger('compensatory')->nullable();
                $table->string('disbursing_officer', 32)->default('');
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('plantilla_items')) {
            Schema::create('plantilla_items', function (Blueprint $table): void {
                $table->id();
                $table->integer('office_id');
                $table->integer('division_id')->nullable();
                $table->string('item_number', 16);
                $table->string('position_title', 64);
                $table->tinyInteger('salary_grade');
                $table->boolean('step');
                $table->integer('sort_order')->nullable();
                $table->boolean('have_budget')->default(true);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('department_id')->nullable();
                $table->string('code', 10);
                $table->string('name', 128);
                $table->double('basic_pay')->nullable();
                $table->boolean('is_vacant')->default(false);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id', 'department_id']);
            });
        }

        if (! Schema::hasTable('ranks')) {
            Schema::create('ranks', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('salary_grades')) {
            Schema::create('salary_grades', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('sg');
                $table->double('step1', 15, 2)->nullable();
                $table->double('step2', 15, 2)->nullable();
                $table->double('step3', 15, 2)->nullable();
                $table->double('step4', 15, 2)->nullable();
                $table->double('step5', 15, 2)->nullable();
                $table->double('step6', 15, 2)->nullable();
                $table->double('step7', 15, 2)->nullable();
                $table->double('step8', 15, 2)->nullable();
                $table->string('year', 4);
                $table->string('salary_grade_type', 16);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('schedules')) {
            Schema::create('schedules', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128);
                $table->string('am_in', 8)->nullable();
                $table->string('am_out', 8)->nullable();
                $table->string('pm_in', 8)->nullable();
                $table->string('pm_out', 8)->nullable();
                $table->string('am_out_morning', 8)->nullable();
                $table->string('break_time_start', 8)->nullable();
                $table->string('break_time_end', 8)->nullable();
                $table->date('effective_date_start')->nullable();
                $table->date('effective_date_end')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('key');
                $table->text('value')->nullable();
            });
        }

        if (! Schema::hasTable('signatories')) {
            Schema::create('signatories', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 64)->nullable();
                $table->integer('employee_id')->nullable();
                $table->integer('position_id')->nullable();
                $table->string('phone', 32)->nullable();
                $table->string('mobile', 32)->nullable();
                $table->string('email', 32)->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('statuses')) {
            Schema::create('statuses', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name');
                $table->text('reason')->nullable();
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['model_type', 'model_id']);
            });
        }

        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name', 64);
                $table->timestamp('created_at');
                $table->timestamp('updated_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['teams', 'statuses', 'signatories', 'settings', 'schedules', 'salary_grades', 'ranks', 'positions', 'plantilla_items', 'offices', 'minimum_wage_rates', 'holidays', 'groups', 'employment_types', 'departments', 'company_user', 'companies_banks', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
