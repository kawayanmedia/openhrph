<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Government appointments, their documents, CSC actions and assumption.
 *
 * Issuance, service, assumption and CSC action are distinct events. The
 * appointment's `applied_at` records when it last updated the employee
 * placement and service record, so a validation/resubmission cycle cannot
 * apply the same appointment twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointment_records')) {
            Schema::create('appointment_records', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('plantilla_item_id')->nullable();
                $table->unsignedBigInteger('plantilla_version_id')->nullable();
                $table->unsignedBigInteger('office_id')->nullable();
                $table->unsignedBigInteger('position_id')->nullable();
                $table->string('appointment_type', 32)->default('original');
                $table->string('nature', 32)->default('permanent');
                $table->string('legal_basis', 191)->nullable();
                $table->string('reference_number', 96)->nullable();
                $table->date('effectivity_date');
                $table->date('assumption_date')->nullable();
                $table->string('status', 32)->default('draft');
                $table->unsignedBigInteger('qualification_standard_id')->nullable();
                $table->unsignedBigInteger('qualification_screening_id')->nullable();
                $table->unsignedBigInteger('workflow_instance_id')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('served_at')->nullable();
                $table->timestamp('submitted_to_csc_at')->nullable();
                $table->timestamp('csc_action_at')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id', 'effectivity_date'], 'appointment_records_employee_date_index');
                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'plantilla_item_id']);
            });
        }

        if (! Schema::hasTable('appointment_documents')) {
            Schema::create('appointment_documents', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('appointment_id');
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('document_type', 64);
                $table->unsignedInteger('version')->default(1);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'appointment_id']);
                $table->index(['company_id', 'document_type']);
            });
        }

        if (! Schema::hasTable('appointment_actions')) {
            Schema::create('appointment_actions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('appointment_id');
                $table->string('action_type', 32);
                $table->string('reference_number', 96)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->date('action_date')->nullable();
                $table->string('status', 32)->default('pending');
                $table->text('remarks')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'appointment_id', 'action_type']);
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('assumption_records')) {
            Schema::create('assumption_records', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('appointment_id');
                $table->date('oath_date')->nullable();
                $table->date('assumption_date');
                $table->unsignedBigInteger('document_id')->nullable();
                $table->unsignedBigInteger('witnessed_by')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'appointment_id']);
                $table->index(['company_id', 'assumption_date']);
            });
        }

        if (! Schema::hasTable('personnel_actions')) {
            Schema::create('personnel_actions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('action_type', 32);
                $table->unsignedBigInteger('current_appointment_id')->nullable();
                $table->unsignedBigInteger('target_plantilla_item_id')->nullable();
                $table->unsignedBigInteger('target_position_id')->nullable();
                $table->unsignedBigInteger('target_office_id')->nullable();
                $table->text('reason')->nullable();
                $table->date('proposed_effective_date')->nullable();
                $table->date('effective_date')->nullable();
                $table->string('status', 32)->default('draft');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('workflow_instance_id')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id', 'action_type']);
                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_actions');
        Schema::dropIfExists('assumption_records');
        Schema::dropIfExists('appointment_actions');
        Schema::dropIfExists('appointment_documents');
        Schema::dropIfExists('appointment_records');
    }
};
