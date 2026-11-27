<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned approval workflows.
 *
 * Definitions store a validated JSON step configuration; decisions are
 * append-only so an approval history can never be rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_definitions')) {
            Schema::create('workflow_definitions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('code', 64);
                $table->string('name', 128);
                $table->string('subject_type')->nullable();
                $table->string('sector', 32)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->json('configuration');
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'code', 'version']);
                $table->index(['company_id', 'subject_type', 'is_active']);
            });
        }

        if (! Schema::hasTable('workflow_instances')) {
            Schema::create('workflow_instances', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('workflow_definition_id');
                $table->unsignedInteger('definition_version')->default(1);
                $table->json('definition_snapshot');
                $table->string('subject_type');
                $table->unsignedBigInteger('subject_id');
                $table->string('status', 32)->default('under_review');
                $table->string('current_step', 64)->nullable();
                $table->unsignedBigInteger('initiated_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'subject_type', 'subject_id']);
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('workflow_tasks')) {
            Schema::create('workflow_tasks', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('workflow_instance_id');
                $table->string('step_key', 64);
                $table->unsignedInteger('step_order')->default(1);
                $table->string('step_name', 128);
                $table->string('mode', 16)->default('all');
                $table->string('assignee_type', 32);
                $table->unsignedBigInteger('assignee_id')->nullable();
                $table->string('assignee_label', 128)->nullable();
                $table->unsignedBigInteger('assigned_user_id')->nullable();
                $table->string('status', 32)->default('pending');
                $table->timestamp('due_at')->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->unsignedBigInteger('acted_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'workflow_instance_id', 'status']);
                $table->index(['company_id', 'assigned_user_id', 'status']);
                $table->index(['company_id', 'status', 'due_at']);
            });
        }

        if (! Schema::hasTable('workflow_decisions')) {
            Schema::create('workflow_decisions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('workflow_instance_id');
                $table->unsignedBigInteger('workflow_task_id')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('decision', 32);
                $table->text('comments')->nullable();
                $table->unsignedBigInteger('delegated_to_id')->nullable();
                $table->timestamp('decided_at');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['company_id', 'workflow_instance_id', 'decided_at'], 'workflow_decisions_instance_decided_index');
            });
        }

        if (! Schema::hasTable('workflow_delegations')) {
            Schema::create('workflow_delegations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('delegator_id');
                $table->unsignedBigInteger('delegatee_id');
                $table->date('starts_at');
                $table->date('ends_at');
                $table->string('reason', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'delegator_id', 'is_active']);
                $table->index(['company_id', 'delegatee_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_delegations');
        Schema::dropIfExists('workflow_decisions');
        Schema::dropIfExists('workflow_tasks');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_definitions');
    }
};
