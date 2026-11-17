<?php

use App\Models\Rate;
use App\Support\Tenancy\TenantCache;
use Illuminate\Support\Facades\Cache;

// We use this to cache data.
// We separate this file for caching so that we know where to check
// and change easily for all cache data.

if (! function_exists('cache_rates_options')) {
    function cache_rates_options()
    {
        // remember for 1 day
        return TenantCache::remember('rates_options', 86400, function () {
            $rates = Rate::all();

            $ratesOptions = [];

            foreach ($rates as $rate) {
                $ratesOptions[$rate->code] = [
                    'is_rest_day' => $rate->is_rest_day,
                    'day_type' => $rate->day_type,
                    'options' => [
                        'regular' => 'REG ('.$rate->regular.')',
                        'ot' => 'OT ('.$rate->ot.')',
                        'nt' => 'ND ('.$rate->nt.')',
                        'nt_ot' => 'ND-OT ('.$rate->nt_ot.')',
                    ],
                    'options_labels' => [
                        $rate->regular => $rate->regular,
                        $rate->ot => $rate->ot,
                        $rate->nt => $rate->nt,
                        $rate->nt_ot => $rate->nt_ot,
                    ],
                    'regular' => $rate->regular,
                    'ot' => $rate->ot,
                    'nt' => $rate->nt,
                    'nt_ot' => $rate->nt_ot,
                ];
            }

            return $ratesOptions;
        });
    }
}
