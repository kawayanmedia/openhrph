<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private-sector recruitment: requisitions, vacancies, applications and the
 * selection pipeline. The legacy `job_vacancies` and `applicants` tables are
 * extended rather than replaced so existing screens keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('job_requisitions')) {
            Schema::create('job_requisitions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('position_id')->nullable();
                $table->unsignedBigInteger('organization_unit_id')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('employment_type_id')->nullable();
                $table->string('reason', 64)->default('new_headcount');
                $table->unsignedSmallInteger('headcount')->default(1);
                $table->date('target_date')->nullable();
                $table->unsignedInteger('salary_min')->nullable();
                $table->unsignedInteger('salary_max')->nullable();
                $table->text('justification')->nullable();
                $table->string('status', 32)->default('draft');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('workflow_instance_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'position_id']);
            });
        }

        if (Schema::hasTable('job_vacancies')) {
            $columns = [
                'requisition_id' => fn (Blueprint $table) => $table->unsignedBigInteger('requisition_id')->nullable()->index(),
                'position_id' => fn (Blueprint $table) => $table->unsignedBigInteger('position_id')->nullable()->index(),
                'employment_type_id' => fn (Blueprint $table) => $table->unsignedBigInteger('employment_type_id')->nullable(),
                'location_id' => fn (Blueprint $table) => $table->unsignedBigInteger('location_id')->nullable(),
                'hiring_manager_employee_id' => fn (Blueprint $table) => $table->unsignedBigInteger('hiring_manager_employee_id')->nullable(),
                'salary_min' => fn (Blueprint $table) => $table->unsignedInteger('salary_min')->nullable(),
                'salary_max' => fn (Blueprint $table) => $table->unsignedInteger('salary_max')->nullable(),
                'description' => fn (Blueprint $table) => $table->text('description')->nullable(),
                'requirements' => fn (Blueprint $table) => $table->text('requirements')->nullable(),
                'published_at' => fn (Blueprint $table) => $table->timestamp('published_at')->nullable(),
                'closes_at' => fn (Blueprint $table) => $table->date('closes_at')->nullable(),
                'status' => fn (Blueprint $table) => $table->string('status', 32)->default('draft')->index(),
                'channels' => fn (Blueprint $table) => $table->json('channels')->nullable(),
                'criteria' => fn (Blueprint $table) => $table->json('criteria')->nullable(),
            ];

            foreach ($columns as $column => $definition) {
                if (Schema::hasColumn('job_vacancies', $column)) {
                    continue;
                }

                Schema::table('job_vacancies', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }

        if (! Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('job_vacancy_id');
                $table->unsignedBigInteger('applicant_id');
                $table->string('source', 64)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->string('status', 32)->default('received');
                $table->string('current_stage', 64)->nullable();
                $table->string('disposition_reason', 255)->nullable();
                $table->timestamp('consent_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['job_vacancy_id', 'applicant_id'], 'applications_vacancy_applicant_unique');
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('application_documents')) {
            Schema::create('application_documents', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('application_id');
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('document_type', 64);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'application_id']);
            });
        }

        if (! Schema::hasTable('screening_results')) {
            Schema::create('screening_results', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('application_id');
                $table->string('criteria_version', 64)->nullable();
                $table->string('result', 32);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('reviewer_id')->nullable();
                $table->timestamp('reviewed_at');
                $table->timestamps();

                $table->index(['company_id', 'application_id']);
            });
        }

        if (! Schema::hasTable('interviews')) {
            Schema::create('interviews', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('application_id');
                $table->timestamp('scheduled_at');
                $table->string('stage', 64)->default('interview');
                $table->string('status', 32)->default('scheduled');
                $table->string('mode', 32)->default('onsite');
                $table->string('location', 191)->nullable();
                $table->json('panel')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'application_id']);
                $table->index(['company_id', 'status', 'scheduled_at']);
            });
        }

        if (! Schema::hasTable('interview_evaluations')) {
            Schema::create('interview_evaluations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('interview_id');
                $table->unsignedBigInteger('reviewer_id')->nullable();
                $table->json('scores')->nullable();
                $table->text('comments')->nullable();
                $table->string('recommendation', 32)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'interview_id']);
            });
        }

        if (! Schema::hasTable('assessments')) {
            Schema::create('assessments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('application_id');
                $table->string('assessment_type', 64);
                $table->decimal('score', 8, 2)->nullable();
                $table->string('result', 32)->nullable();
                $table->text('evidence')->nullable();
                $table->unsignedBigInteger('assessed_by')->nullable();
                $table->timestamp('assessed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'application_id']);
            });
        }

        if (! Schema::hasTable('selection_recommendations')) {
            Schema::create('selection_recommendations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('job_vacancy_id');
                $table->unsignedBigInteger('application_id');
                $table->unsignedBigInteger('recommended_by')->nullable();
                $table->string('decision_reference', 96)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('recommended_at');
                $table->timestamps();

                $table->index(['company_id', 'job_vacancy_id']);
            });
        }

        if (! Schema::hasTable('job_offers')) {
            Schema::create('job_offers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('application_id');
                $table->unsignedInteger('offer_version')->default(1);
                $table->string('terms_reference', 191)->nullable();
                $table->unsignedInteger('salary')->nullable();
                $table->date('start_date')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('status', 32)->default('draft');
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->json('terms')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'application_id']);
                $table->unique(['application_id', 'offer_version'], 'job_offers_application_version_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
        Schema::dropIfExists('selection_recommendations');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('interview_evaluations');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('screening_results');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('job_requisitions');

        if (Schema::hasTable('job_vacancies')) {
            foreach ([
                'requisition_id', 'position_id', 'employment_type_id', 'location_id',
                'hiring_manager_employee_id', 'salary_min', 'salary_max', 'description',
                'requirements', 'published_at', 'closes_at', 'status', 'channels', 'criteria',
            ] as $column) {
                if (Schema::hasColumn('job_vacancies', $column)) {
                    Schema::table('job_vacancies', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
