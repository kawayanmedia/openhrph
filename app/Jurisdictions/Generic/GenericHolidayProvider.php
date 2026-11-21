<?php

namespace App\Jurisdictions\Generic;

use App\Jurisdictions\Contracts\HolidayProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Holidays supplied by the country profile, or an empty calendar.
 */
final class GenericHolidayProvider implements HolidayProvider
{
    /**
     * @param  array<string, mixed>  $profile
     */
    public function __construct(
        private readonly string $countryCode,
        private readonly array $profile = [],
    ) {}

    public function calendar(int $year): array
    {
        $rows = (array) ($this->profile['holidays'][(string) $year] ?? $this->profile['holidays'][$year] ?? []);

        return array_values(array_filter(array_map(function (mixed $row): ?array {
            if (! is_array($row) || empty($row['date'])) {
                return null;
            }

            return [
                'date' => (string) $row['date'],
                'name' => (string) ($row['name'] ?? 'Holiday'),
                'type' => (string) ($row['type'] ?? $row['holiday_type'] ?? 'public'),
                'code' => (string) ($row['code'] ?? 'rh'),
            ];
        }, $rows)));
    }

    public function types(): array
    {
        return [
            'regular' => 'Regular holiday',
            'special_non_working' => 'Special non-working day',
            'special_working' => 'Special working day',
            'regional' => 'Regional holiday',
            'company' => 'Company holiday',
        ];
    }

    public function import(int $companyId, int $year, bool $fresh = false): int
    {
        if (! Schema::hasTable('holidays')) {
            return 0;
        }

        if ($fresh) {
            DB::table('holidays')->where('company_id', $companyId)->where('year', $year)->delete();
        }

        $now = now();
        $count = 0;

        foreach ($this->calendar($year) as $row) {
            DB::table('holidays')->updateOrInsert(
                ['company_id' => $companyId, 'date' => $row['date']],
                [
                    'country_code' => $this->countryCode,
                    'year' => $year,
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'holiday_type' => $row['type'],
                    'code' => $row['code'],
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $count++;
        }

        return $count;
    }
}
