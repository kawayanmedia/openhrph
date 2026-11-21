<?php

namespace App\Livewire\Admin\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DateRangeTo extends Filter
{
    public string $type = 'date';

    public string $name = 'Date To';

    public function apply(Request $request, Builder $query, mixed $value): Builder
    {
        if ($value == null) {
            return $query;
        }

        // Include the whole selected day even when the column stores a timestamp.
        $endExclusive = CarbonImmutable::parse($value)->addDay()->toDateString();

        return $query->where('log_date', '<', $endExclusive);
    }

    // Behaviour approximated: the Nova DateFilter received a date string; the
    // Livewire base has no dedicated date filter, so options() returns [].
    public function options(Request $request): array
    {
        return [];
    }
}
