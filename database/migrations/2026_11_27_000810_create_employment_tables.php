<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private employment periods, contracts, probation and regularization.
 *
 * An employment period is the legal engagement; position placement stays in
 * `position_assignments`. Regularization changes the employment classification
 * and effective-dated service history without rewriting the original contract.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employments')) {
            Schema::create('employments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('employment_type_id')->nullable();
                $table->string('classification', 32)->default('regular');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->date('probation_start')->nullable();
                $table->date('probation_end')->nullable();
                $table->date('regularization_date')->nullable();
                $table->string('status', 32)->default('active');
                $table->string('contract_reference', 96)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id', 'start_date']);
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'classification']);
            });
        }

        if (! Schema::hasTable('employment_contracts')) {
            Schema::create('employment_contracts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employment_id');
                $table->string('template_name', 128)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->string('signed_by_employee', 128)->nullable();
                $table->string('signed_by_company', 128)->nullable();
                $table->string('status', 32)->default('draft');
                $table->unsignedBigInteger('document_id')->nullable();
                $table->json('terms')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employment_id']);
                $table->unique(['employment_id', 'version'], 'employment_contracts_version_unique');
            });
        }

        if (! Schema::hasTable('probationary_reviews')) {
            Schema::create('probationary_reviews', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employment_id');
                $table->date('review_date');
                $table->string('standards_reference', 191)->nullable();
                $table->unsignedBigInteger('evaluator_id')->nullable();
                $table->string('outcome', 32)->nullable();
                $table->text('employee_comments')->nullable();
                $table->text('supervisor_comments')->nullable();
                $table->unsignedBigInteger('evidence_document_id')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employment_id', 'review_date']);
            });
        }

        if (! Schema::hasTable('regularization_decisions')) {
            Schema::create('regularization_decisions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employment_id');
                $table->string('decision', 32);
                $table->date('effective_date');
                $table->string('approval_reference', 128)->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employment_id']);
            });
        }

        if (! Schema::hasTable('onboarding_checklists')) {
            Schema::create('onboarding_checklists', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employment_id')->nullable();
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->string('name', 128)->default('Onboarding');
                $table->string('status', 32)->default('open');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('onboarding_tasks')) {
            Schema::create('onboarding_tasks', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('checklist_id');
                $table->string('task', 191);
                $table->string('owner', 64)->nullable();
                $table->date('due_at')->nullable();
                $table->string('status', 32)->default('pending');
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('evidence_document_id')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'checklist_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('onboarding_checklists');
        Schema::dropIfExists('regularization_decisions');
        Schema::dropIfExists('probationary_reviews');
        Schema::dropIfExists('employment_contracts');
        Schema::dropIfExists('employments');
    }
};
