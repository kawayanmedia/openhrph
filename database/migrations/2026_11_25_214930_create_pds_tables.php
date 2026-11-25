<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CS Form 212 (Personal Data Sheet) sections.
 *
 * These legacy tables were never ported into the new schema, so the PDS
 * models, the kawayanmedia/pds editor and the PDF layout had no storage. The
 * column names follow the legacy rows the package reads and writes; the
 * education table carries `level` so each pre-printed row is unique.
 *
 * The legacy application renamed `pds_organization` to `pds_voluntary_works`
 * and `pds_training` to `pds_trainings`, so those are the names used here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createSection('pds_personal_info', function (Blueprint $table): void {
            $table->string('lname', 64)->nullable();
            $table->string('fname', 64)->nullable();
            $table->string('mname', 64)->nullable();
            $table->string('extension', 16)->nullable();
            $table->string('birth_date', 16)->nullable();
            $table->string('birth_place', 128)->nullable();
            $table->string('sex', 4)->nullable();
            $table->string('civil_status', 32)->nullable();
            $table->string('citizenship', 64)->nullable();
            $table->string('height', 32)->nullable();
            $table->string('weight', 32)->nullable();
            $table->string('blood_type', 8)->nullable();
            $table->string('gsis', 32)->nullable();
            $table->string('pagibig', 32)->nullable();
            $table->string('philhealth', 32)->nullable();
            $table->string('sss', 32)->nullable();
            $table->string('res_address', 255)->nullable();
            $table->string('res_zip', 16)->nullable();
            $table->string('res_tel', 32)->nullable();
            $table->string('permanent_address', 255)->nullable();
            $table->string('permanent_zip', 16)->nullable();
            $table->string('permanent_tel', 32)->nullable();
            $table->string('email', 128)->nullable();
            $table->string('cp', 32)->nullable();
            $table->string('agency_employee_no', 32)->nullable();
            $table->string('tin', 32)->nullable();
        });

        $this->createSection('pds_family_background', function (Blueprint $table): void {
            $table->string('spouse_lname', 64)->nullable();
            $table->string('spouse_fname', 64)->nullable();
            $table->string('spouse_mname', 64)->nullable();
            $table->string('spouse_occupation', 128)->nullable();
            $table->string('spouse_employer', 128)->nullable();
            $table->string('spouse_biz_ad', 255)->nullable();
            $table->string('spouse_tel', 32)->nullable();
            $table->string('father_lname', 64)->nullable();
            $table->string('father_fname', 64)->nullable();
            $table->string('father_mname', 64)->nullable();
            $table->string('mother_lname', 64)->nullable();
            $table->string('mother_fname', 64)->nullable();
            $table->string('mother_mname', 64)->nullable();
        });

        $this->createSection('pds_children', function (Blueprint $table): void {
            $table->string('children', 128)->nullable();
            $table->string('birth_date', 16)->nullable();
        });

        $this->createSection('pds_education_background', function (Blueprint $table): void {
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('school_name', 128)->nullable();
            $table->string('degree_course', 128)->nullable();
            $table->string('year_graduated', 16)->nullable();
            $table->string('highest_grade', 32)->nullable();
            $table->string('attend_from', 16)->nullable();
            $table->string('attend_to', 16)->nullable();
            $table->string('scholarship', 128)->nullable();

            $table->unique(['company_id', 'employee_id', 'level'], 'pds_education_level_unique');
        });

        $this->createSection('pds_eligibility', function (Blueprint $table): void {
            $table->string('type', 128)->nullable();
            $table->string('rating', 32)->nullable();
            $table->string('date_exam_conferment', 16)->nullable();
            $table->string('place_exam_conferment', 128)->nullable();
            $table->string('license_no', 64)->nullable();
            $table->string('license_release_date', 16)->nullable();
        });

        $this->createSection('pds_work_experience', function (Blueprint $table): void {
            $table->string('inclusive_date_from', 32)->nullable();
            $table->string('inclusive_date_to', 32)->nullable();
            $table->string('position', 128)->nullable();
            $table->string('company', 128)->nullable();
            $table->string('monthly_salary', 32)->nullable();
            $table->string('salary_grade', 32)->nullable();
            $table->string('status', 32)->nullable();
            $table->string('movement', 32)->nullable();
            $table->string('govt_service', 32)->nullable();
        });

        $this->createSection('pds_voluntary_works', function (Blueprint $table): void {
            $table->string('name', 128)->nullable();
            $table->string('inclusive_date_from', 32)->nullable();
            $table->string('inclusive_date_to', 32)->nullable();
            $table->string('number_of_hours', 32)->nullable();
            $table->string('position', 128)->nullable();
        });

        $this->createSection('pds_trainings', function (Blueprint $table): void {
            $table->string('name', 128)->nullable();
            $table->string('date_from', 32)->nullable();
            $table->string('date_to', 32)->nullable();
            $table->string('number_hours', 32)->nullable();
            $table->string('conducted_by', 128)->nullable();
            $table->string('location', 128)->nullable();
        });

        $this->createSection('pds_other_info', function (Blueprint $table): void {
            $table->text('special_skills')->nullable();
            $table->text('recognition')->nullable();
            $table->text('membership_organization')->nullable();
        });

        $this->createSection('pds_questions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('question_no');
            $table->boolean('answer')->nullable();
            $table->text('details')->nullable();

            $table->unique(['company_id', 'employee_id', 'question_no'], 'pds_questions_employee_number_unique');
        });

        $this->createSection('pds_references', function (Blueprint $table): void {
            $table->string('name', 128)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('tel_no', 32)->nullable();
            $table->string('ctc_no', 32)->nullable();
            $table->string('issue_at', 32)->nullable();
            $table->string('issue_on', 32)->nullable();
        });
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables()) as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Creates one section table keyed on the tenant and the employee.
     *
     * @param  callable(Blueprint): void  $columns
     */
    private function createSection(string $name, callable $columns): void
    {
        if (Schema::hasTable($name)) {
            return;
        }

        Schema::create($name, function (Blueprint $table) use ($name, $columns): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $columns($table);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'employee_id'], $name.'_company_employee_index');
        });
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return [
            'pds_personal_info',
            'pds_family_background',
            'pds_children',
            'pds_education_background',
            'pds_eligibility',
            'pds_work_experience',
            'pds_voluntary_works',
            'pds_trainings',
            'pds_other_info',
            'pds_questions',
            'pds_references',
        ];
    }
};
