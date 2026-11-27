<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separation cases, clearance and final-settlement inputs, plus the private
 * leave policy rule dimensions layered on the existing leave ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('separation_cases')) {
            Schema::create('separation_cases', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('separation_type', 32);
                $table->unsignedBigInteger('initiated_by')->nullable();
                $table->date('proposed_effective_date')->nullable();
                $table->date('effective_date')->nullable();
                $table->timestamp('notice_served_at')->nullable();
                $table->string('status', 32)->default('initiated');
                $table->text('reason')->nullable();
                $table->string('legal_review_reference', 128)->nullable();
                $table->unsignedBigInteger('workflow_instance_id')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id']);
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('clearance_templates')) {
            Schema::create('clearance_templates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('name', 128);
                $table->unsignedInteger('version')->default(1);
                $table->json('tasks');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('clearance_tasks')) {
            Schema::create('clearance_tasks', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('separation_case_id');
                $table->string('unit', 96);
                $table->string('task', 191);
                $table->unsignedBigInteger('assignee_user_id')->nullable();
                $table->string('status', 32)->default('pending');
                $table->unsignedBigInteger('evidence_document_id')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'separation_case_id', 'status']);
            });
        }

        if (! Schema::hasTable('final_settlements')) {
            Schema::create('final_settlements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('separation_case_id');
                $table->string('calculation_reference', 128)->nullable();
                $table->json('earnings')->nullable();
                $table->json('deductions')->nullable();
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('status', 32)->default('draft');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'separation_case_id']);
            });
        }

        if (! Schema::hasTable('leave_policy_rules')) {
            Schema::create('leave_policy_rules', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('leave_policy_id');
                $table->string('employment_classification', 32)->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->unsignedSmallInteger('min_service_months')->nullable();
                $table->unsignedSmallInteger('notice_days')->nullable();
                $table->unsignedSmallInteger('max_consecutive_days')->nullable();
                $table->boolean('requires_evidence')->default(false);
                $table->boolean('allow_half_day')->default(true);
                $table->boolean('is_paid')->default(true);
                $table->json('eligibility')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'leave_policy_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policy_rules');
        Schema::dropIfExists('final_settlements');
        Schema::dropIfExists('clearance_tasks');
        Schema::dropIfExists('clearance_templates');
        Schema::dropIfExists('separation_cases');
    }
};
