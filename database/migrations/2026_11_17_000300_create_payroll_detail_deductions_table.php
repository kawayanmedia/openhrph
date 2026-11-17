<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itemised deduction lines written by the payroll engine.
 *
 * The legacy `payroll_details` columns stay authoritative for Philippine
 * payrolls so existing reports keep working. These lines add a country-neutral
 * ledger: the payslip, remittance reports and any future country can read the
 * same rows without knowing SSS, PhilHealth, Pag-IBIG or GSIS by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_detail_deductions')) {
            Schema::create('payroll_detail_deductions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('payroll_id')->index();
                $table->unsignedBigInteger('payroll_detail_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('code', 32);
                $table->string('name', 128);
                $table->string('category', 24)->default('contribution');
                $table->bigInteger('base_centavos')->default(0);
                $table->bigInteger('employee_centavos')->default(0);
                $table->bigInteger('employer_centavos')->default(0);
                $table->boolean('reduces_taxable_income')->default(false);
                $table->string('source', 16)->default('statutory');
                $table->integer('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['payroll_detail_id', 'code', 'source'], 'payroll_detail_deductions_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_detail_deductions');
    }
};
