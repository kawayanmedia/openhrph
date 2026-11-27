<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes duplicate holiday and setting rows and prevents them coming back.
 *
 * The payroll engine treats every holiday row in a period as one payable
 * holiday, so duplicated reference rows multiplied unworked regular-holiday
 * pay (the development database held four rows per date for one company and
 * forty for another). Settings were duplicated the same way, and one company
 * held two different six-day divisors (312 and 313).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('holidays')) {
            // Keep the oldest row of each exact duplicate.
            $groups = DB::table('holidays')
                ->select(['company_id', 'date', 'code', DB::raw('MIN(id) as keep_id')])
                ->groupBy(['company_id', 'date', 'code'])
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($groups as $group) {
                DB::table('holidays')
                    ->where('company_id', $group->company_id)
                    ->where('date', $group->date)
                    ->where('code', $group->code)
                    ->where('id', '>', (int) $group->keep_id)
                    ->delete();
            }

            if (! Schema::hasIndex('holidays', 'holidays_company_date_code_unique')) {
                Schema::table('holidays', function (Blueprint $table) {
                    $table->unique(['company_id', 'date', 'code'], 'holidays_company_date_code_unique');
                });
            }
        }

        if (Schema::hasTable('settings')) {
            // Keep the most recently created row, which is what the settings
            // screen reads back (it orders by id and keeps the last value).
            $groups = DB::table('settings')
                ->select(['company_id', 'key', DB::raw('MAX(id) as keep_id')])
                ->groupBy(['company_id', 'key'])
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($groups as $group) {
                DB::table('settings')
                    ->where('company_id', $group->company_id)
                    ->where('key', $group->key)
                    ->where('id', '<', (int) $group->keep_id)
                    ->delete();
            }

            if (! Schema::hasIndex('settings', 'settings_company_key_unique')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->unique(['company_id', 'key'], 'settings_company_key_unique');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('holidays') && Schema::hasIndex('holidays', 'holidays_company_date_code_unique')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->dropUnique('holidays_company_date_code_unique');
            });
        }

        if (Schema::hasTable('settings') && Schema::hasIndex('settings', 'settings_company_key_unique')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropUnique('settings_company_key_unique');
            });
        }
    }
};
