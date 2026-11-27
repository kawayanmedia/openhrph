<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Training catalogue, sessions, enrollments and employee certifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_courses')) {
            Schema::create('training_courses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('code', 32)->nullable();
                $table->string('title', 191);
                $table->string('provider', 128)->nullable();
                $table->string('category', 64)->nullable();
                $table->text('description')->nullable();
                $table->decimal('duration_hours', 6, 2)->nullable();
                $table->decimal('cost', 12, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('training_sessions')) {
            Schema::create('training_sessions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('training_course_id');
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->string('location', 191)->nullable();
                $table->unsignedSmallInteger('capacity')->nullable();
                $table->decimal('cost', 12, 2)->nullable();
                $table->string('status', 32)->default('scheduled');
                $table->timestamps();

                $table->index(['company_id', 'training_course_id']);
                $table->index(['company_id', 'status', 'starts_at']);
            });
        }

        if (! Schema::hasTable('training_enrollments')) {
            Schema::create('training_enrollments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('training_session_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('status', 32)->default('enrolled');
                $table->date('completion_date')->nullable();
                $table->decimal('score', 5, 2)->nullable();
                $table->text('evaluation')->nullable();
                $table->unsignedBigInteger('certificate_document_id')->nullable();
                $table->timestamps();

                $table->unique(['training_session_id', 'employee_id'], 'training_enrollments_session_employee_unique');
                $table->index(['company_id', 'employee_id', 'status']);
            });
        }

        if (! Schema::hasTable('employee_certifications')) {
            Schema::create('employee_certifications', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('certification_type', 64);
                $table->string('title', 191);
                $table->string('issuer', 128)->nullable();
                $table->string('certification_number', 96)->nullable();
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->unsignedBigInteger('document_id')->nullable();
                $table->string('status', 32)->default('active');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id', 'expires_at'], 'employee_certifications_employee_expiry_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_certifications');
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_courses');
    }
};
