<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee records and employee-related reference data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table): void {
                $table->id();
                $table->integer('company_id');
                $table->string('title', 256)->nullable();
                $table->string('date_time', 32)->nullable();
                $table->text('content');
                $table->string('priority', 32)->nullable();
                $table->string('file', 64)->nullable();
                $table->boolean('is_active')->default(false);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('applicants')) {
            Schema::create('applicants', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->integer('job_vacancy_id')->nullable();
                $table->string('name', 128)->nullable();
                $table->string('gender', 32);
                $table->string('address', 128)->nullable();
                $table->date('birth_date')->nullable();
                $table->string('educational_attainment', 128)->nullable();
                $table->string('position_applying', 128)->nullable();
                $table->string('telephone', 128);
                $table->string('email', 128)->nullable();
                $table->string('eligibility', 128);
                $table->text('training')->nullable();
                $table->text('experience')->nullable();
                $table->string('training_file', 256)->nullable();
                $table->string('experience_file', 256)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('devices')) {
            Schema::create('devices', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('company_id');
                $table->string('name', 128);
                $table->string('ip_address', 32)->nullable();
                $table->string('api_key', 64)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
                $table->unique(['api_key']);
            });
        }

        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table): void {
                $table->id();
                $table->integer('company_id');
                $table->string('employee_number', 10)->nullable();
                $table->string('biometric_id', 32)->nullable();
                $table->string('face_image_uuid', 36)->nullable();
                $table->integer('user_id')->nullable();
                $table->string('salut', 20)->nullable();
                $table->string('first_name', 64)->nullable();
                $table->string('middle_name', 64)->nullable();
                $table->string('last_name', 64)->nullable();
                $table->string('extension', 16)->nullable();
                $table->string('position', 64)->nullable();
                $table->integer('position_id')->nullable();
                $table->integer('assistant_dept_head')->nullable();
                $table->string('salary_grade', 10)->nullable();
                $table->boolean('is_monday_friday')->default(true);
                $table->boolean('is_minimum_wage')->default(false);
                $table->tinyInteger('step')->nullable();
                $table->string('payment_type', 32)->default('monthly');
                $table->integer('basic_salary')->nullable();
                $table->integer('daily_rate')->nullable();
                $table->integer('hourly_rate')->nullable();
                $table->integer('payroll_group_id')->nullable();
                $table->tinyInteger('employment_status')->nullable();
                $table->integer('employment_type_id')->nullable();
                $table->integer('immediate_head_employee_id')->nullable();
                $table->string('other_remarks', 128)->nullable();
                $table->integer('employee_movement_id')->default(1);
                $table->date('date_hired')->nullable();
                $table->date('date_resigned')->nullable();
                $table->string('sex', 4)->nullable();
                $table->integer('office_id')->nullable();
                $table->string('region', 32)->nullable();
                $table->string('industry', 64)->nullable();
                $table->integer('division_id')->nullable();
                $table->integer('section_id')->nullable();
                $table->integer('detailed_office_id')->nullable();
                $table->integer('department_id')->nullable();
                $table->integer('rank_id')->nullable();
                $table->string('password', 40)->nullable();
                $table->string('pics', 40)->nullable();
                $table->string('finger_pics', 200)->nullable();
                $table->longText('base64_pics')->nullable();
                $table->string('tpl', 200)->nullable();
                $table->longText('base64_tpl')->nullable();
                $table->integer('status')->nullable();
                $table->integer('shift_id')->nullable();
                $table->integer('shift_type')->nullable();
                $table->integer('schedule_id')->nullable();
                $table->date('schedule_end_date')->nullable();
                $table->string('friday_exempted', 32)->default('no');
                $table->date('last_update')->nullable();
                $table->boolean('newly_added')->nullable();
                $table->boolean('updated')->nullable();
                $table->string('item_number', 32)->nullable();
                $table->string('last_promotion', 32)->nullable();
                $table->string('last_increment', 32)->nullable()->default('');
                $table->string('level', 64)->nullable();
                $table->string('eligibility', 64)->nullable();
                $table->string('graduated', 32)->nullable();
                $table->string('course', 64)->nullable();
                $table->string('units', 32)->nullable();
                $table->string('post_grad', 64)->nullable();
                $table->date('birth_date')->nullable();
                $table->string('res_address', 200)->nullable();
                $table->string('tax_status', 32)->nullable();
                $table->string('dependents', 32)->nullable();
                $table->date('date_retired')->nullable();
                $table->string('emergency_contact', 32)->nullable()->default('');
                $table->string('emergency_contact_no', 32)->nullable()->default('');
                $table->string('tin_number', 32)->nullable();
                $table->string('sss_number', 32)->nullable();
                $table->string('philhealth_number', 32)->nullable();
                $table->string('hdmf_number', 32)->nullable();
                $table->boolean('timesheet')->default(true);
                $table->boolean('regular_holiday_pay')->default(false);
                $table->boolean('special_holiday_pay')->default(false);
                $table->boolean('premium_holiday_pay')->default(false);
                $table->boolean('regular_holiday_special_holiday_pay')->default(true);
                $table->boolean('rest_day_pay')->default(false);
                $table->boolean('overtime_pay')->default(false);
                $table->boolean('deminimis')->default(false);
                $table->boolean('night_differential')->default(false);
                $table->integer('allowance')->nullable();
                $table->integer('incentive')->nullable();
                $table->integer('total_vacation_leave')->nullable();
                $table->integer('total_sick_leave')->nullable();
                $table->integer('total_personal_leave')->nullable();
                $table->integer('sss_employee')->nullable();
                $table->integer('sss_provident_employee')->nullable();
                $table->integer('sss_employee_total')->nullable();
                $table->integer('sss_employer')->nullable();
                $table->integer('sss_provident_employer')->nullable();
                $table->integer('sss_employer_ec_er')->nullable();
                $table->integer('sss_employer_total')->nullable();
                $table->integer('sss_total')->nullable();
                $table->integer('hdmf_employee')->nullable();
                $table->integer('hdmf_employer')->nullable();
                $table->integer('hdmf_total')->nullable();
                $table->integer('philhealth_employee')->nullable();
                $table->integer('philhealth_employer')->nullable();
                $table->integer('total_philhealth')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id', 'payroll_group_id', 'office_id', 'department_id'], 'employees_company_payroll_office_dept_index');
                $table->index(['employee_number']);
            });
        }

        if (! Schema::hasTable('employees_certificates')) {
            Schema::create('employees_certificates', function (Blueprint $table): void {
                $table->id();
                $table->integer('company_id');
                $table->integer('employee_id');
                $table->string('certicate_number', 64)->nullable();
                $table->string('certicate_title', 128)->nullable();
                $table->date('date')->nullable();
                $table->string('document', 64)->nullable();
                $table->boolean('is_active')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('employees_plantilla')) {
            Schema::create('employees_plantilla', function (Blueprint $table): void {
                $table->id();
                $table->integer('employee_id');
                $table->integer('plantilla_item_id');
                $table->integer('basic_salary')->nullable();
                $table->integer('year')->nullable();
                $table->boolean('active')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['company_id']);
            });
        }

        if (! Schema::hasTable('employees_schedules')) {
            Schema::create('employees_schedules', function (Blueprint $table): void {
                $table->id();
                $table->integer('schedule_id');
                $table->string('employee_id', 20);
                $table->date('from_date')->nullable();
                $table->date('to_date')->nullable();
                $table->integer('shift_type')->nullable();
                $table->integer('office_id')->nullable();
                $table->json('test_j')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->unsignedBigInteger('company_id');
                $table->index(['company_id']);
                $table->index(['schedule_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('employees_trainings')) {
            Schema::create('employees_trainings', function (Blueprint $table): void {
                $table->id();
                $table->integer('company_id');
                $table->integer('employee_id');
                $table->text('description')->nullable();
                $table->string('duration', 32);
                $table->string('status', 32)->default('Pending');
                $table->date('date')->nullable();
                $table->string('document', 64)->nullable();
                $table->boolean('is_active')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('job_vacancies')) {
            Schema::create('job_vacancies', function (Blueprint $table): void {
                $table->id();
                $table->integer('company_id');
                $table->text('title')->nullable();
                $table->string('type', 64);
                $table->text('summary')->nullable();
                $table->string('salary', 32)->nullable();
                $table->string('shift', 64)->nullable();
                $table->boolean('is_active')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->dateTime('deleted_at')->nullable();
                $table->index(['company_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['job_vacancies', 'employees_trainings', 'employees_schedules', 'employees_plantilla', 'employees_certificates', 'employees', 'devices', 'applicants', 'announcements'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
