<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undertime is a deduction, not just an attendance note. Store the hours and
 * the amount on the payroll detail so the payslip and register can show it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_details')) {
            return;
        }

        Schema::table('payroll_details', function (Blueprint $table): void {
            $table->decimal('undertime_hours', 8, 2)->default(0)->after('absent_days_amount');
            $table->integer('undertime_pay')->default(0)->after('undertime_hours');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_details')) {
            return;
        }

        Schema::table('payroll_details', function (Blueprint $table): void {
            $table->dropColumn(['undertime_hours', 'undertime_pay']);
        });
    }
};
