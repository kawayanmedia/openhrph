<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Punch lookup and period locking both filter timesheets by employee and a
 * date range, which is the hottest attendance query path.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('timesheets')) {
            return;
        }

        Schema::table('timesheets', function (Blueprint $table): void {
            $table->index(['company_id', 'employee_id', 'log_date'], 'timesheets_company_employee_log_date_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('timesheets')) {
            return;
        }

        Schema::table('timesheets', function (Blueprint $table): void {
            $table->dropIndex('timesheets_company_employee_log_date_index');
        });
    }
};
