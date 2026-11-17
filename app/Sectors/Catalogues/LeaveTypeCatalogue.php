<?php

namespace App\Sectors\Catalogues;

use App\Enums\Sector;
use App\Sectors\SectorManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds leave types from the sector profile.
 *
 * The `source` note is written to the description so an administrator can see
 * which legal basis a row came from. Amounts and accrual rules are company
 * policy once seeded; the profile only chooses the starting set.
 */
final class LeaveTypeCatalogue
{
    public static function seedDefaults(int $companyId, Sector|string|null $sector = null): int
    {
        if (! Schema::hasTable('leave_types')) {
            return 0;
        }

        $policy = app(SectorManager::class)->for($sector);
        $created = 0;

        foreach ($policy->leaveTypes() as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($code === '' || $name === '') {
                continue;
            }

            $exists = DB::table('leave_types')
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('leave_types')->insert([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'deductable' => (bool) ($row['deductible'] ?? false),
                'deduct_to' => 0,
                'allow_per_year' => (int) ($row['allow_per_year'] ?? 0),
                'is_active' => true,
                'description' => isset($row['source']) ? (string) $row['source'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $created++;
        }

        return $created;
    }
}
