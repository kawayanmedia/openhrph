<?php

namespace App\Livewire\Admin\Filters;

use App\Models\EmploymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EmploymentStatus extends Filter
{
    public string $name = 'Employment Type';

    public function apply(Request $request, Builder $query, mixed $value): Builder
    {
        return $query->where('employment_type_id', $value);
    }

    public function options(Request $request): array
    {
        return EmploymentType::orderBy('name')->pluck('name', 'id')->toArray();
    }
}
