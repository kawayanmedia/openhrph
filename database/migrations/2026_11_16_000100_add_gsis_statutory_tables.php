<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSIS statutory premium (RA 8291).
 *
 * A government company is identified by the existing companies.type column, so
 * only the versioned schedule and the payroll detail columns are added here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('statutory_gsis')) {
            Schema::create('statutory_gsis', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->decimal('employee_rate', 8, 5);
                $table->decimal('employer_rate', 8, 5);
                $table->unsignedBigInteger('max_amc_limit_centavos')->default(0);
                $table->decimal('above_limit_employee_rate', 8, 5)->default(0.02);
                $table->timestamps();
            });
        }

        foreach (['payroll_details', 'payroll_detail_revisions'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach (['gsis_employee', 'gsis_employer', 'gsis_total'] as $column) {
                    if (! Schema::hasColumn($tableName, $column)) {
                        $table->bigInteger($column)->default(0);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['payroll_details', 'payroll_detail_revisions'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach (['gsis_employee', 'gsis_employer', 'gsis_total'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('statutory_gsis');
    }
};
