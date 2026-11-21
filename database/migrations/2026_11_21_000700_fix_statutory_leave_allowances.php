<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correct the maternity allowance and add the missing statutory leave types.
 *
 * RA 11210 grants 105 days (120 for solo mothers) and 60 days for a
 * miscarriage; the legacy row carried 60 for every case. RA 9710 Sec. 18
 * grants two months for surgery caused by a gynecological disorder, and
 * RA 11642 Sec. 36 extends the maternity/paternity entitlement to adoptive
 * parents within one year of the adoption order.
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
            ->where('code', 'ML')
            ->where('allow_per_year', 60)
            ->update(['allow_per_year' => 105, 'updated_at' => now()]);

        $rows = [
            ['code' => 'SLW', 'name' => 'Special Leave for Women', 'allow_per_year' => 60, 'source' => 'RA 9710 Sec. 18'],
            ['code' => 'ADOPT', 'name' => 'Adoption Leave', 'allow_per_year' => 0, 'source' => 'RA 11642 Sec. 36'],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('leave_types')
                ->whereNull('company_id')
                ->whereRaw('UPPER(TRIM(name)) = ?', [strtoupper(trim($row['name']))])
                ->exists();

            if ($exists) {
                continue;
            }

            $insert = [
                'code' => $row['code'],
                'name' => $row['name'],
                'deductable' => false,
                'deduct_to' => 0,
                'allow_per_year' => $row['allow_per_year'],
                'is_active' => true,
                'description' => $row['source'],
                'company_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('leave_types', 'sector')) {
                $insert['sector'] = null;
            }

            DB::table('leave_types')->insert($insert);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        DB::table('leave_types')
            ->whereNull('company_id')
            ->where('code', 'ML')
            ->where('allow_per_year', 105)
            ->update(['allow_per_year' => 60, 'updated_at' => now()]);

        DB::table('leave_types')
            ->whereNull('company_id')
            ->whereIn('code', ['SLW', 'ADOPT'])
            ->delete();
    }
};
