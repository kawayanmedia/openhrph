<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy catalogue already had a "Special Leave for Women" row with a
 * blank code and a zero allowance. Promote it to the RA 9710 Sec. 18
 * entitlement instead of creating a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        DB::table('leave_types')
            ->whereNull('company_id')
            ->whereRaw('UPPER(TRIM(name)) = ?', ['SPECIAL LEAVE FOR WOMEN'])
            ->update([
                'code' => 'SLW',
                'allow_per_year' => 60,
                'is_active' => true,
                'description' => 'RA 9710 Sec. 18',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        DB::table('leave_types')
            ->whereNull('company_id')
            ->where('code', 'SLW')
            ->update(['allow_per_year' => 0, 'updated_at' => now()]);
    }
};
