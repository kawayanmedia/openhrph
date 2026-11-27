<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General append-only audit trail for HR actions outside payroll runs.
 *
 * The older `hr_audit_events` table stays for compatibility; new features
 * write here with actor, subject, before/after and request context.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_events')) {
            return;
        }

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 64);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'occurred_at']);
            $table->index(['company_id', 'action']);
            $table->index(['company_id', 'subject_type', 'subject_id']);
            $table->index(['company_id', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
