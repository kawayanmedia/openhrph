<?php

namespace App\Sectors\Catalogues;

use App\Enums\Sector;
use App\Sectors\SectorManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds a company's employment types from its sector profile.
 */
final class EmploymentTypeCatalogue
{
    public static function seedDefaults(int $companyId, Sector|string|null $sector = null): int
    {
        if (! Schema::hasTable('employment_types')) {
            return 0;
        }

        $policy = app(SectorManager::class)->for($sector);
        $created = 0;

        foreach ($policy->employmentTypes() as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($code === '' || $name === '') {
                continue;
            }

            $exists = DB::table('employment_types')
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('employment_types')->insert([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'is_payroll' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $created++;
        }

        return $created;
    }
}
