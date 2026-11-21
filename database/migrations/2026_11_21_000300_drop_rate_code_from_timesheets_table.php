<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completes the rename of `timesheets.rate_code` to `day_rate_code`.
 *
 * Every reader now uses `day_rate_code`. The previous migration backfilled it;
 * this one copies any row written by an older client since then and removes
 * the ambiguous column.
 *
 * Rebuild the Go payroll binary in the same release: older binaries still
 * select `rate_code`. The legacy CodeIgniter schema in
 * `priv/sql-codeigniter.sql` must not share this database any more.
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

        // Copy any row written by an older client since the previous
        // migration backfilled. Chunk by id so the table is not locked by a
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

        Schema::table('timesheets', function (Blueprint $table): void {
            $table->dropColumn('rate_code');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('timesheets')
            || ! Schema::hasColumn('timesheets', 'day_rate_code')
            || Schema::hasColumn('timesheets', 'rate_code')) {
            return;
        }

        Schema::table('timesheets', function (Blueprint $table): void {
            $table->string('rate_code', 32)->nullable();
        });

        DB::table('timesheets')->update(['rate_code' => DB::raw('day_rate_code')]);
    }
};
