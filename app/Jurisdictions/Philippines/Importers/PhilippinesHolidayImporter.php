<?php

namespace App\Jurisdictions\Philippines\Importers;

use App\Jurisdictions\Contracts\StatutoryImporter;
use App\Jurisdictions\Philippines\Holidays\PhilippinesHolidayProvider;
use Illuminate\Support\Facades\DB;

/**
 * Imports the Philippine holiday calendar for one company or all companies.
 */
final class PhilippinesHolidayImporter implements StatutoryImporter
{
    public function code(): string
    {
        return 'ph.holidays';
    }

    public function supports(string $countryCode): bool
    {
        return ($countryCode |> trim(...) |> strtoupper(...)) === 'PH';
    }

    public function import(string $countryCode, array $options = []): array
    {
        $year = (int) ($options['year'] ?? now()->year);
        $companyId = isset($options['company_id']) ? (int) $options['company_id'] : null;
        $fresh = (bool) ($options['fresh'] ?? false);

        $provider = new PhilippinesHolidayProvider;
        $companies = $companyId === null
            ? DB::table('companies')->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : [$companyId];

        $imported = [];

        foreach ($companies as $id) {
            $imported[$id] = $provider->import($id, $year, $fresh);
        }

        return ['year' => $year, 'companies' => $imported];
    }
}
