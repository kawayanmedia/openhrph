<?php

namespace App\Support\Payroll;

use App\Enums\Sector;
use App\Support\Installation\Installation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the loan catalogue from the country profile.
 *
 * GSIS loans are salary deductions, not statutory contributions: they belong
 * here with the SSS, Pag-IBIG and company loans, and payroll reads them through
 * `employees_loans` exactly like any other amortization.
 */
final class LoanCatalogue
{
    public static function seedDefaults(int $companyId, ?string $countryCode = null, Sector|string|null $sector = null): int
    {
        if (! Schema::hasTable('loans') || ! Schema::hasColumn('loans', 'code')) {
            return 0;
        }

        $countryCode = (string) ($countryCode ?? Installation::countryCode()) |> trim(...) |> strtoupper(...);
        $profile = Installation::countryProfile($countryCode);
        $sector = $sector === null ? null : Sector::fromValue($sector instanceof Sector ? $sector->value : $sector);
        $created = 0;

        foreach ((array) ($profile['loans'] ?? []) as $row) {
            if (! is_array($row) || empty($row['code'])) {
                continue;
            }

            $appliesTo = Sector::tryFromValue(is_string($row['sector'] ?? null) ? $row['sector'] : null);

            if ($sector !== null && $appliesTo !== null && $appliesTo !== $sector) {
                continue;
            }

            $exists = DB::table('loans')
                ->where('company_id', $companyId)
                ->where('code', (string) $row['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('loans')->insert([
                'company_id' => $companyId,
                'loan_agency' => (string) ($row['agency'] ?? 'Company'),
                'code' => (string) $row['code'],
                'name' => (string) ($row['name'] ?? $row['code']),
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $created++;
        }

        return $created;
    }
}
