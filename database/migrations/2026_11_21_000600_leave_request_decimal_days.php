<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave is measured in days, not whole integers: a half-day is 0.5.
 * Also indexes the employee/date lookups used by approval and payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_requests')) {
            return;
        }

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->decimal('days_with_pay', 6, 2)->nullable()->change();
            $table->decimal('days_without_pay', 6, 2)->nullable()->change();
            $table->index(
                ['company_id', 'employee_id', 'date_from', 'date_to'],
                'leave_requests_company_employee_dates_index',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_requests')) {
            return;
        }

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropIndex('leave_requests_company_employee_dates_index');
            $table->integer('days_with_pay')->nullable()->change();
            $table->integer('days_without_pay')->nullable()->change();
        });
    }
};
