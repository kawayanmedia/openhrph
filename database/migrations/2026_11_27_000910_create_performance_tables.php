<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable performance cycles, goals, reviews, ratings and development
 * plans. Rating scales and templates are versioned per cycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('performance_cycles')) {
            Schema::create('performance_cycles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('name', 128);
                $table->string('sector', 32)->default('Private');
                $table->string('template_version', 64)->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 32)->default('draft');
                $table->json('rating_scale')->nullable();
                $table->json('eligibility')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('performance_goals')) {
            Schema::create('performance_goals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('performance_cycle_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('parent_goal_id')->nullable();
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->text('measures')->nullable();
                $table->decimal('weight', 5, 2)->nullable();
                $table->string('target', 191)->nullable();
                $table->string('status', 32)->default('draft');
                $table->date('due_date')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'performance_cycle_id', 'employee_id'], 'performance_goals_cycle_employee_index');
            });
        }

        if (! Schema::hasTable('performance_reviews')) {
            Schema::create('performance_reviews', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('performance_cycle_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('reviewer_employee_id')->nullable();
                $table->string('status', 32)->default('draft');
                $table->text('self_assessment')->nullable();
                $table->text('supervisor_comments')->nullable();
                $table->decimal('overall_rating', 5, 2)->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['performance_cycle_id', 'employee_id'], 'performance_reviews_cycle_employee_unique');
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('performance_ratings')) {
            Schema::create('performance_ratings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('performance_review_id');
                $table->string('criterion', 128);
                $table->decimal('score', 5, 2);
                $table->decimal('weight', 5, 2)->nullable();
                $table->text('comments')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'performance_review_id']);
            });
        }

        if (! Schema::hasTable('development_plans')) {
            Schema::create('development_plans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('performance_cycle_id')->nullable();
                $table->string('title', 191);
                $table->text('goals')->nullable();
                $table->string('status', 32)->default('draft');
                $table->date('due_date')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('development_plans');
        Schema::dropIfExists('performance_ratings');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_goals');
        Schema::dropIfExists('performance_cycles');
    }
};
