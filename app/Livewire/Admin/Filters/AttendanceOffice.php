<?php

namespace App\Livewire\Admin\Filters;

use App\Models\Office;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AttendanceOffice extends Filter
{
    public string $name = 'Filter By Office';

    public function apply(Request $request, Builder $query, mixed $value): Builder
    {
        return $query->where('office_id', $value);
    }

    public function options(Request $request): array
    {
        return Office::orderBy('name')->pluck('id', 'name')->toArray();
    }
}
