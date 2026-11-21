<?php

namespace App\Services\Payroll;

use App\Models\Rate;
use App\Support\Tenancy\TenantCache;
use App\Support\Tenancy\TenantContext;

/**
 * The statutory day-type rates every company needs to record attendance.
 *
 * A new workspace starts empty, so the timesheet editor and payroll engine had
 * no rate rows to label a day or a premium. They are copied per company because
 * a company may later adjust its own multipliers.
 */
class RateCatalogue
{
    /**
     * Code, label, rest day, holiday and the regular/OT/night/night-OT factors.
     *
     * @return array<int, array{0: string, 1: string, 2: bool, 3: bool, 4: float, 5: float, 6: float, 7: float}>
     */
    public static function defaults(): array
    {
        return [
            ['regular', 'Regular Day', false, false, 1.00, 1.25, 1.10, 1.375],
            ['rd', 'Rest Day', true, false, 1.30, 1.69, 1.43, 1.859],
            ['sh', 'Special Holiday', false, true, 1.30, 1.69, 1.43, 1.859],
            ['rh', 'Regular Holiday', false, true, 2.00, 2.60, 2.20, 2.86],
            ['rd-sh', 'Rest Day - Special Holiday', true, true, 1.50, 1.95, 1.65, 2.145],
            ['rd-rh', 'Rest Day - Regular Holiday', true, true, 2.60, 3.38, 2.86, 3.718],
            ['2rh', 'Double Regular Holiday', false, true, 3.00, 3.90, 3.30, 4.29],
            ['rd-2rh', 'Rest Day - Double Regular Holiday', true, true, 3.90, 5.07, 4.29, 5.577],
            ['rh-sh', 'Regular Holiday, Special Holiday', false, true, 2.60, 3.38, 2.86, 3.718],
            ['rd-rh-sh', 'Rest Day - Regular Holiday, Special Holiday', false, true, 3.00, 3.90, 3.30, 4.29],
            ['pl', 'Paid Leave', false, false, 1.00, 1.00, 1.00, 1.00],
            ['upl', 'Unpaid Leave', false, false, 1.00, 1.00, 1.00, 1.00],
        ];
    }

    /**
     * Create any missing rate rows without overwriting a company's own values.
     */
    public static function sync(int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        return app(TenantContext::class)->run($companyId, function () use ($companyId): int {
            $created = 0;

            foreach (self::defaults() as [$code, $dayType, $restDay, $holiday, $regular, $ot, $nt, $ntOt]) {
                $rate = Rate::query()->where('company_id', $companyId)->where('code', $code)->first();

                if ($rate !== null) {
                    continue;
                }

                $rate = new Rate;
                $rate->forceFill([
                    'company_id' => $companyId,
                    'code' => $code,
                    'day_type' => $dayType,
                    'is_rest_day' => $restDay,
                    'is_holiday' => $holiday,
                    'regular' => $regular,
                    'ot' => $ot,
                    'nt' => $nt,
                    'nt_ot' => $ntOt,
                ])->save();

                $created++;
            }

            if ($created > 0) {
                TenantCache::forgetFor($companyId, 'rates_options');
            }

            return $created;
        });
    }
}
