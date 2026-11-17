<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The GSIS Business Partner (BP) number identifies a government member on the
 * GSIS remittance working paper, the same way sss_number does for SSS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees') || Schema::hasColumn('employees', 'gsis_number')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            $table->string('gsis_number', 32)->nullable()->after('sss_number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'gsis_number')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('gsis_number');
        });
    }
};
