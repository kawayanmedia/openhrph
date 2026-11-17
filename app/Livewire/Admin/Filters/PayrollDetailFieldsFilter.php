<?php

namespace App\Livewire\Admin\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PayrollDetailFieldsFilter extends Filter
{
    public string $type = 'multi';

    public string $name = 'Columns';

    public function apply(Request $request, Builder $query, mixed $value): Builder
    {
        return $query;
    }

    // Behaviour approximated: this was a Nova BooleanFilter with no options.
    public function options(Request $request): array
    {
        return [];
    }
}
