<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups the day classification columns together.
 *
 * `day_rate_code` was appended to the end of `timesheets` when it replaced
 * `rate_code`; it is read alongside `biometric_id` and `day_type`, so it is
 * moved next to them. Column order is cosmetic and no index covers it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasColumns()) {
            return;
        }

        Schema::table('timesheets', function (Blueprint $table): void {
            // change() drops unspecified attributes, so restate the current
            // definition: varchar(32) NULL.
            $table->string('day_rate_code', 32)->nullable()->after('biometric_id')->change();
        });
    }

    public function down(): void
    {
        if (! $this->hasColumns() || ! Schema::hasColumn('timesheets', 'company_id')) {
            return;
        }

        Schema::table('timesheets', function (Blueprint $table): void {
            // Restore the position the add-column migration produced.
            $table->string('day_rate_code', 32)->nullable()->after('company_id')->change();
        });
    }

    private function hasColumns(): bool
    {
        return Schema::hasTable('timesheets')
            && Schema::hasColumn('timesheets', 'day_rate_code')
            && Schema::hasColumn('timesheets', 'biometric_id');
    }
};
