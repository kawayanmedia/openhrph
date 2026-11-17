<?php

namespace App\Services\Payroll\Statutory;

use App\Enums\Sector;
use App\Support\Installation\Installation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the deduction catalogue with the country profile's suggested
 * voluntary deductions.
 *
 * Rows are only created when the company/code pair is missing, so an
 * administrator's edits are never overwritten. A row may carry a `sector`
 * key; rows without one apply to both sectors.
 */
final class DeductionCatalogue
{
    public static function seedDefaults(int $companyId, ?string $countryCode = null, Sector|string|null $sector = null): int
    {
        if (! Schema::hasTable('deductions') || ! Schema::hasColumn('deductions', 'code')) {
            return 0;
        }

        $countryCode = strtoupper(trim((string) ($countryCode ?? Installation::countryCode())));
        $profile = Installation::countryProfile($countryCode);
        $sector = $sector === null ? null : Sector::fromValue($sector instanceof Sector ? $sector->value : $sector);
        $created = 0;

        foreach ((array) ($profile['deductions'] ?? []) as $row) {
            if (! is_array($row) || empty($row['code'])) {
                continue;
            }

            $appliesTo = Sector::tryFromValue(is_string($row['sector'] ?? null) ? $row['sector'] : null);

            if ($sector !== null && $appliesTo !== null && $appliesTo !== $sector) {
                continue;
            }

            $exists = DB::table('deductions')
                ->where('company_id', $companyId)
                ->where('code', (string) $row['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('deductions')->insert([
                'company_id' => $companyId,
                'code' => (string) $row['code'],
                'name' => (string) ($row['name'] ?? $row['code']),
                'country_code' => $countryCode,
                'type' => (string) ($row['type'] ?? 'voluntary'),
                'calculation' => (string) ($row['calculation'] ?? 'fixed'),
                'amount_centavos' => 0,
                'rate' => $row['rate'] ?? null,
                'base' => $row['base'] ?? null,
                'reduces_taxable_income' => (bool) ($row['reduces_taxable_income'] ?? false),
                'is_active' => true,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $created++;
        }

        return $created;
    }
}
