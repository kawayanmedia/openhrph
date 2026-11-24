<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee bank/payment details used by the payroll bank file.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'bank_id')) {
                $table->unsignedBigInteger('bank_id')->nullable()->index()->after('payroll_group_id');
            }

            if (! Schema::hasColumn('employees', 'bank_account_name')) {
                $table->string('bank_account_name', 128)->nullable()->after('bank_id');
            }

            if (! Schema::hasColumn('employees', 'bank_account_number')) {
                $table->string('bank_account_number', 64)->nullable()->after('bank_account_name');
            }

            if (! Schema::hasColumn('employees', 'bank_branch')) {
                $table->string('bank_branch', 128)->nullable()->after('bank_account_number');
            }

            if (! Schema::hasColumn('employees', 'pay_mode')) {
                $table->string('pay_mode', 16)->default('bank')->after('bank_branch');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            foreach (['bank_id', 'bank_account_name', 'bank_account_number', 'bank_branch', 'pay_mode'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
