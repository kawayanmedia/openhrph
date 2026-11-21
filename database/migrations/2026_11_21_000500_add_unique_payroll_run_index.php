<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One payroll run per company, group, payroll date and type.
 *
 * Without this key two operators can process the same period twice, which
 * double-posts the journal and pays the same period twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payrolls')) {
            return;
        }

        Schema::table('payrolls', function (Blueprint $table): void {
            $table->unique(
                ['company_id', 'payroll_group_id', 'payroll_date', 'type'],
                'payrolls_company_group_date_type_unique',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payrolls')) {
            return;
        }

        Schema::table('payrolls', function (Blueprint $table): void {
            $table->dropUnique('payrolls_company_group_date_type_unique');
        });
    }
};
