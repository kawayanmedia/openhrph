<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retirement plans, cases, calculations, documents and external claim
 * tracking. A calculation keeps its inputs and rule version and is never
 * treated as an approved entitlement until it has been reviewed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('retirement_plans')) {
            Schema::create('retirement_plans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('name', 128);
                $table->string('sector', 32)->default('Private');
                $table->string('plan_type', 32)->default('defined_benefit');
                $table->unsignedTinyInteger('eligibility_age')->nullable();
                $table->decimal('minimum_service_years', 4, 2)->nullable();
                $table->json('benefit_formula')->nullable();
                $table->decimal('employer_rate', 6, 3)->nullable();
                $table->decimal('employee_rate', 6, 3)->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'is_active'], 'retirement_plans_active_index');
            });
        }

        if (! Schema::hasTable('retirement_cases')) {
            Schema::create('retirement_cases', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('separation_case_id')->nullable();
                $table->unsignedBigInteger('retirement_plan_id')->nullable();
                $table->string('retirement_type', 32)->default('optional');
                $table->string('status', 32)->default('draft');
                $table->date('proposed_retirement_date')->nullable();
                $table->json('eligibility_snapshot')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id', 'status'], 'retirement_cases_employee_status_index');
                $table->index(['company_id', 'status', 'proposed_retirement_date'], 'retirement_cases_status_date_index');
            });
        }

        if (! Schema::hasTable('retirement_calculations')) {
            Schema::create('retirement_calculations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('retirement_case_id');
                $table->string('calculation_type', 32)->default('retirement_benefit');
                $table->string('rule_version', 64);
                $table->json('inputs');
                $table->json('outputs');
                $table->decimal('amount', 14, 2)->nullable();
                $table->string('status', 32)->default('draft');
                $table->unsignedBigInteger('computed_by')->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('reviewer_notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'retirement_case_id'], 'retirement_calculations_case_index');
            });
        }

        if (! Schema::hasTable('retirement_documents')) {
            Schema::create('retirement_documents', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('retirement_case_id');
                $table->unsignedBigInteger('document_id');
                $table->string('document_type', 64);
                $table->timestamps();

                $table->unique(['retirement_case_id', 'document_id'], 'retirement_documents_case_document_unique');
            });
        }

        if (! Schema::hasTable('external_claim_tracking')) {
            Schema::create('external_claim_tracking', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('retirement_case_id');
                $table->string('agency', 64)->default('GSIS');
                $table->string('reference', 128)->nullable();
                $table->string('status', 32)->default('not_submitted');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'external_claims_status_index');
                $table->index(['company_id', 'retirement_case_id'], 'external_claims_case_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('external_claim_tracking');
        Schema::dropIfExists('retirement_documents');
        Schema::dropIfExists('retirement_calculations');
        Schema::dropIfExists('retirement_cases');
        Schema::dropIfExists('retirement_plans');
    }
};
