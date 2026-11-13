<?php

namespace App\Livewire\Admin\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HolidayYearFilter extends Filter
{
    public string $name = 'Year';

    public function apply(Request $request, Builder $query, mixed $value): Builder
    {
        return $query->where('year', $value);
    }

    public function options(Request $request): array
    {
        return [2022 => 2022, 2023 => 2023, 2024 => 2024];
    }
}
