<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned Philippine statutory tables.
 *
 * Amounts are stored in centavos (BIGINT) so the Go engine can work without
 * floating point drift. Rows are effective-dated: a payroll run uses the rows
 * whose effectivity covers the payroll date, keeping historical runs correct
 * after a rate change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('statutory_sss_brackets')) {
            Schema::create('statutory_sss_brackets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->unsignedBigInteger('msc_from');
                $table->unsignedBigInteger('msc_to')->nullable();
                $table->unsignedBigInteger('msc');
                $table->unsignedBigInteger('ee');
                $table->unsignedBigInteger('er');
                $table->unsignedBigInteger('ec')->default(0);
                $table->unsignedBigInteger('wisp_ee')->default(0);
                $table->unsignedBigInteger('wisp_er')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('statutory_philhealth')) {
            Schema::create('statutory_philhealth', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->decimal('rate', 8, 5);
                $table->unsignedBigInteger('floor_centavos');
                $table->unsignedBigInteger('ceiling_centavos');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('statutory_pagibig')) {
            Schema::create('statutory_pagibig', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->decimal('rate', 8, 5);
                $table->decimal('low_rate', 8, 5)->default(0);
                $table->unsignedBigInteger('low_threshold_centavos')->default(0);
                $table->unsignedBigInteger('max_fund_salary_centavos');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('statutory_tax_brackets')) {
            Schema::create('statutory_tax_brackets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('frequency', 20)->default('semi-monthly');
                $table->unsignedBigInteger('from_centavos');
                $table->unsignedBigInteger('to_centavos')->nullable();
                $table->unsignedBigInteger('base_tax_centavos')->default(0);
                $table->decimal('rate', 8, 5)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('statutory_min_wage')) {
            Schema::create('statutory_min_wage', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('region', 32)->default('NCR');
                $table->date('effective_from')->nullable();
                $table->unsignedBigInteger('amount_centavos');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('statutory_sources')) {
            Schema::create('statutory_sources', function (Blueprint $table) {
                $table->id();
                $table->string('table_name', 64);
                $table->date('effectivity')->nullable();
                $table->string('source_url', 255);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['statutory_sources', 'statutory_min_wage', 'statutory_tax_brackets', 'statutory_pagibig', 'statutory_philhealth', 'statutory_sss_brackets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
