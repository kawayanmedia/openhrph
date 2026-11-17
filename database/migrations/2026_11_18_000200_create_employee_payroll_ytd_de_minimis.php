<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Year-to-date de minimis usage per employee and benefit period.
 *
 * De minimis ceilings are monthly, per semester or annual. The ledger records
 * how much of each benefit's ceiling a payroll has already exempted, so a
 * payment that is within the cap for its period is not taxed just because the
 * ceiling was spread over the earlier pay runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_payroll_ytd_de_minimis')) {
            Schema::create('employee_payroll_ytd_de_minimis', function (Blueprint $table): void {
                $table->id();
                $table->integer('company_id');
                $table->integer('employee_id');
                $table->integer('year');
                $table->string('period', 16);
                $table->string('code', 128);
                $table->bigInteger('exempt_centavos')->default(0);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->unique(['company_id', 'employee_id', 'year', 'period', 'code'], 'ytd_de_minimis_unique');
                $table->index(['company_id', 'year']);
            });
        }

        if (Schema::hasTable('payroll_details') && ! Schema::hasColumn('payroll_details', 'de_minimis_exempt')) {
            Schema::table('payroll_details', function (Blueprint $table): void {
                $table->json('de_minimis_exempt')->nullable()->after('benefit_exempt');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_details') && Schema::hasColumn('payroll_details', 'de_minimis_exempt')) {
            Schema::table('payroll_details', function (Blueprint $table): void {
                $table->dropColumn('de_minimis_exempt');
            });
        }

        Schema::dropIfExists('employee_payroll_ytd_de_minimis');
    }
};
