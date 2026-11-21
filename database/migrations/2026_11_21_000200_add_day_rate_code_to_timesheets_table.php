<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the payable day classification an explicit name.
 *
 * `timesheets.day_type` is the schedule category (regular/rest/absent) and
 * `rate_code` is the premium classification from `rates.code` (rh, sh,
 * rd-sh, pl, ...). `rate_code` reads like the pay component (regular/ot/nt)
 * and caused worked holidays to be paid as ordinary days.
 *
 * This migration backfills the new column; `2026_11_21_000300` removes
 * `rate_code` once every reader uses `day_rate_code`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('timesheets')
            || ! Schema::hasColumn('timesheets', 'rate_code')) {
            return;
        }

        if (! Schema::hasColumn('timesheets', 'day_rate_code')) {
            Schema::table('timesheets', function (Blueprint $table): void {
                $table->string('day_rate_code', 32)->nullable();
            });
        }

        $max = (int) DB::table('timesheets')->max('id');

        // Backfill in id ranges so a large timesheet table is not locked by a
        // single update.
        for ($start = 1; $start <= $max; $start += 10000) {
            DB::table('timesheets')
                ->whereBetween('id', [$start, $start + 9999])
                ->where(function ($query): void {
                    $query->whereNull('day_rate_code')->orWhere('day_rate_code', '');
                })
                ->whereNotNull('rate_code')
                ->update(['day_rate_code' => DB::raw('rate_code')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('timesheets') && Schema::hasColumn('timesheets', 'day_rate_code')) {
            Schema::table('timesheets', function (Blueprint $table): void {
                $table->dropColumn('day_rate_code');
            });
        }
    }
};
