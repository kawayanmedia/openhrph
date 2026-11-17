<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_details', 'basic_earned')) {
                $table->bigInteger('basic_earned')->default(0);
            }

            if (! Schema::hasColumn('payroll_details', 'benefit_exempt')) {
                $table->bigInteger('benefit_exempt')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            foreach (['basic_earned', 'benefit_exempt'] as $column) {
                if (Schema::hasColumn('payroll_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
