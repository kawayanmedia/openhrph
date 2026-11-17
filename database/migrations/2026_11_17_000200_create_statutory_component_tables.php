<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic, effective-dated statutory components.
 *
 * A `PH` installation keeps using the agency-specific tables and drivers. Any
 * other country is described by rows in these two tables, so a jurisdiction can
 * be added without changing the payroll engine: each component declares how it
 * is calculated (percentage, bracket or fixed), which base it uses and which
 * payroll period it is taken from.
 *
 * Amounts are centavos (BIGINT) to stay centavo-exact on both the PHP and Go
 * side, matching the existing statutory tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('statutory_components')) {
            Schema::create('statutory_components', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('country_code', 2)->default('PH')->index();
                $table->string('sector', 16)->default('any');
                $table->string('code', 32);
                $table->string('name', 128);
                $table->string('category', 24)->default('contribution');
                $table->string('applies_to', 16)->default('both');
                $table->string('calculation', 16)->default('percentage');
                $table->string('base', 24)->default('monthly_basic');
                $table->decimal('employee_rate', 10, 6)->nullable();
                $table->decimal('employer_rate', 10, 6)->nullable();
                $table->bigInteger('employee_amount_centavos')->nullable();
                $table->bigInteger('employer_amount_centavos')->nullable();
                $table->bigInteger('min_base_centavos')->nullable();
                $table->bigInteger('max_base_centavos')->nullable();
                $table->bigInteger('max_employee_centavos')->nullable();
                $table->bigInteger('max_employer_centavos')->nullable();
                $table->string('rounding', 16)->default('half_up');
                $table->string('schedule', 16)->default('every_pay');
                $table->boolean('reduces_taxable_income')->default(false);
                $table->boolean('employer_tax_deductible')->default(true);
                $table->boolean('mandatory')->default(true);
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(100);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('source_url', 512)->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['country_code', 'code']);
                $table->index(['country_code', 'effective_from']);
            });
        }

        if (! Schema::hasTable('statutory_component_bands')) {
            Schema::create('statutory_component_bands', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('statutory_component_id')->index();
                $table->bigInteger('from_centavos')->default(0);
                $table->bigInteger('to_centavos')->nullable();
                $table->bigInteger('base_centavos')->default(0);
                $table->decimal('employee_rate', 10, 6)->nullable();
                $table->decimal('employer_rate', 10, 6)->nullable();
                $table->bigInteger('employee_amount_centavos')->default(0);
                $table->bigInteger('employer_amount_centavos')->default(0);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index(['statutory_component_id', 'from_centavos']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_component_bands');
        Schema::dropIfExists('statutory_components');
    }
};
