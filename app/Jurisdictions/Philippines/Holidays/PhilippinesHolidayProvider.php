<?php

namespace App\Jurisdictions\Philippines\Holidays;

use App\Jurisdictions\Contracts\HolidayProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Philippine holiday calendar published by proclamation.
 */
final class PhilippinesHolidayProvider implements HolidayProvider
{
    /**
     * @var array<string, string>
     */
    private const TYPE_MAP = [
        'regular' => 'regular',
        'regular holiday' => 'regular',
        'special non-working holiday' => 'special_non_working',
        'special non-working day' => 'special_non_working',
        'additional_special_non_working' => 'special_non_working',
        'special working holiday' => 'special_working',
        'special working day' => 'special_working',
        'regional' => 'regional',
        'company' => 'company',
    ];

    public function calendar(int $year): array
    {
        $calendar = require __DIR__.'/data/calendar.php';
        $rows = (array) ($calendar[(string) $year] ?? []);

        return array_values(array_map(fn (array $row): array => [
            'date' => (string) $row['date'],
            'name' => (string) $row['name'],
            'type' => self::normalizeType((string) ($row['holiday_type'] ?? 'regular')),
            'code' => (string) ($row['code'] ?? 'rh'),
        ], $rows));
    }

    public function types(): array
    {
        return [
            'regular' => 'Regular holiday',
            'special_non_working' => 'Special non-working holiday',
            'special_working' => 'Special working holiday',
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
                    'country_code' => 'PH',
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

    private static function normalizeType(string $type): string
    {
        $key = $type |> trim(...) |> strtolower(...);

        return self::TYPE_MAP[$key] ?? $key;
    }
}
