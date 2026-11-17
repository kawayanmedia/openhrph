<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Government contribution tables (SSS, PhilHealth, Pag-IBIG).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pagibig_contributions')) {
            Schema::create('pagibig_contributions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('min_salary');
                $table->unsignedBigInteger('max_salary')->nullable();
                $table->unsignedInteger('employee_rate');
                $table->unsignedInteger('employer_rate');
                $table->unsignedBigInteger('max_contribution')->nullable();
                $table->date('effective_date');
                $table->date('expiry_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['effective_date', 'is_active']);
            });
        }

        if (! Schema::hasTable('philhealth')) {
            Schema::create('philhealth', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('salary_bracket')->default(0);
                $table->date('effectivity_date')->nullable();
                $table->date('effectivity_date_to')->nullable();
                $table->decimal('start_range', 10, 2);
                $table->decimal('end_range', 10, 2);
                $table->string('salary_based', 16);
                $table->string('monthly_share', 16);
                $table->string('employee_share', 16);
                $table->string('employer_share', 16);
                $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('deleted_at')->nullable();
            });
        }

        if (! Schema::hasTable('philhealth_contributions')) {
            Schema::create('philhealth_contributions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('min_salary');
                $table->unsignedBigInteger('max_salary')->nullable();
                $table->unsignedInteger('premium_rate');
                $table->unsignedBigInteger('min_premium')->nullable();
                $table->unsignedBigInteger('max_premium')->nullable();
                $table->date('effective_date');
                $table->date('expiry_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['effective_date', 'is_active']);
            });
        }

        if (! Schema::hasTable('sss')) {
            Schema::create('sss', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('from', 9)->nullable();
                $table->string('to', 9)->nullable();
                $table->string('regular_ec', 9)->nullable();
                $table->string('wisp', 9)->nullable();
                $table->string('regular_er', 8)->nullable();
                $table->decimal('regular_ee', 4, 1)->nullable();
                $table->integer('ecc')->nullable();
                $table->decimal('wisp_er', 4, 1)->nullable();
                $table->decimal('wisp_ee', 4, 1)->nullable();
            });
        }

        if (! Schema::hasTable('sss_contributions')) {
            Schema::create('sss_contributions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('min_salary');
                $table->unsignedBigInteger('max_salary')->nullable();
                $table->unsignedBigInteger('monthly_salary_credit');
                $table->unsignedBigInteger('employee_share');
                $table->unsignedBigInteger('employer_share');
                $table->unsignedBigInteger('ec_contribution')->default(0);
                $table->unsignedBigInteger('mpf_employee')->default(0);
                $table->unsignedBigInteger('mpf_employer')->default(0);
                $table->date('effective_date');
                $table->date('expiry_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['effective_date', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        foreach (['sss_contributions', 'sss', 'philhealth_contributions', 'philhealth', 'pagibig_contributions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
