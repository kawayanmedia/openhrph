<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A one-time employee deduction has no pay frequency. Storing the old
 * 'every_pay' default made it look recurring to payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees_deductions') || ! Schema::hasColumn('employees_deductions', 'frequency')) {
            return;
        }

        Schema::table('employees_deductions', function (Blueprint $table): void {
            $table->string('frequency', 16)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees_deductions') || ! Schema::hasColumn('employees_deductions', 'frequency')) {
            return;
        }

        Schema::table('employees_deductions', function (Blueprint $table): void {
            $table->string('frequency', 16)->nullable()->default('every_pay')->change();
        });
    }
};
