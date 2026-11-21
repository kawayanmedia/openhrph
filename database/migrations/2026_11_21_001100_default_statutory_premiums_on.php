<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statutory premiums are not optional for covered rank-and-file employees.
 * Default them on so a new employee is not silently excluded from rest-day,
 * holiday, overtime and night-differential pay.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            $table->boolean('rest_day_pay')->default(true)->change();
            $table->boolean('regular_holiday_pay')->default(true)->change();
            $table->boolean('special_holiday_pay')->default(true)->change();
            $table->boolean('premium_holiday_pay')->default(true)->change();
            $table->boolean('overtime_pay')->default(true)->change();
            $table->boolean('night_differential')->default(true)->change();
            $table->boolean('deminimis')->default(true)->change();
        });

        // Rows created under the old false default were never a deliberate
        // exclusion, so enable the statutory premiums for them too.
        DB::table('employees')->update([
            'rest_day_pay' => true,
            'regular_holiday_pay' => true,
            'special_holiday_pay' => true,
            'premium_holiday_pay' => true,
            'overtime_pay' => true,
            'night_differential' => true,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            $table->boolean('rest_day_pay')->default(false)->change();
            $table->boolean('regular_holiday_pay')->default(false)->change();
            $table->boolean('special_holiday_pay')->default(false)->change();
            $table->boolean('premium_holiday_pay')->default(false)->change();
            $table->boolean('overtime_pay')->default(false)->change();
            $table->boolean('night_differential')->default(false)->change();
            $table->boolean('deminimis')->default(false)->change();
        });
    }
};
