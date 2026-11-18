<?php

namespace App\Sectors\Catalogues;

use App\Enums\Sector;
use App\Sectors\SectorManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The leave type catalogue every company shares.
 *
 * A row with a NULL company_id is a system row. A row with a company_id is that
 * company's override or a leave type only that company uses. System rows are
 * keyed by name: the legacy codes repeat (ML is both Maternity and Monetization),
 * so the name is the only stable identity. A type both sectors use is left
 * untagged and stays visible to both.
 */
final class LeaveTypeCatalogue
{
    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     deductable: bool,
     *     deduct_to: int,
     *     allow_per_year: int,
     *     is_active: bool,
     *     description: ?string,
     *     sector: ?string
     * }>
     */
    public static function rows(?Sector $sector = null): array
    {
        $sectors = $sector === null ? Sector::cases() : [$sector];
        $rows = [];

        foreach ($sectors as $case) {
            $policy = app(SectorManager::class)->for($case);

            foreach ($policy->leaveTypes() as $row) {
                $code = trim((string) ($row['code'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));

                if ($code === '' || $name === '') {
                    continue;
                }

                $key = self::normalise($name);

                if (isset($rows[$key])) {
                    if ($rows[$key]['sector'] !== $case->value) {
                        $rows[$key]['sector'] = null;
                    }

                    continue;
                }

                $rows[$key] = [
                    'code' => $code,
                    'name' => $name,
                    'deductable' => (bool) ($row['deductible'] ?? false),
                    'deduct_to' => 0,
                    'allow_per_year' => (int) ($row['allow_per_year'] ?? 0),
                    'is_active' => true,
                    'description' => isset($row['source']) ? (string) $row['source'] : null,
                    'sector' => $case->value,
                ];
            }
        }

        return array_values($rows);
    }

    /**
     * Create the missing system rows.
     *
     * A null sector seeds the whole default catalogue. Once a sector is locked,
     * only the rows both sectors share plus that sector's own rows are seeded.
     * Existing system rows keep the allowance, deductibility and sector a
     * company already set; only the code is refreshed, so a legacy row does not
     * disappear from the other sector. Company-owned rows are never touched.
     */
    public static function sync(?Carbon $now = null, ?Sector $sector = null): int
    {
        if ($sector === null) {
            return self::syncRows(self::rows(), $now);
        }

        return self::syncRows(array_values(array_filter(
            self::rows(),
            static fn (array $row): bool => $row['sector'] === null || $row['sector'] === $sector->value,
        )), $now);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private static function syncRows(array $rows, ?Carbon $now): int
    {
        if (! Schema::hasTable('leave_types')) {
            return 0;
        }

        $now ??= now();
        $hasSector = Schema::hasColumn('leave_types', 'sector');

        $system = DB::table('leave_types')
            ->whereNull('company_id')
            ->get(['id', 'name'])
            ->keyBy(fn (object $row): string => self::matchKey((string) $row->name));

        $synced = 0;

        foreach ($rows as $row) {
            $existing = $system->get(self::matchKey($row['name']));

            if ($existing !== null) {
                DB::table('leave_types')->where('id', $existing->id)->update([
                    'code' => $row['code'],
                    'deleted_at' => null,
                    'updated_at' => $now,
                ]);

                $synced++;

                continue;
            }

            $insert = $row + [
                'company_id' => null,
                'deduct_to' => (int) ($row['deduct_to'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (! $hasSector) {
                unset($insert['sector']);
            }

            DB::table('leave_types')->insert($insert);

            $synced++;
        }

        return $synced;
    }

    /**
     * Identity used to merge company copies and to shadow a system row with an
     * override.
     */
    public static function normalise(?string $name): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $name)) ?? '');
    }

    /**
     * Looser identity used to match catalogue rows against legacy names, so
     * "Special Privilege Leave" still matches "Special Privilege Leave(MC#6)".
     */
    public static function matchKey(?string $name): string
    {
        $name = preg_replace('/\([^)]*\)/', ' ', (string) $name);

        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', (string) $name) ?? '');
    }
}
