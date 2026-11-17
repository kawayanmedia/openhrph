<?php

namespace App\Livewire\Admin\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FieldsFilter extends Filter
{
    public string $type = 'multi';

    public string $name = 'Columns';

    public function apply(Request $request, Builder $query, mixed $value): Builder
    {
        return $query;
    }

    // Behaviour approximated: this was a Nova BooleanFilter. $value is the
    // selected option key; the original apply() was a no-op, so it is kept as-is.
    public function options(Request $request): array
    {
        return [
            'Employee Number' => 'employee_number',
            'Log Date' => 'log_date',
            'Day Type' => 'day_type',
            'Hours' => 'hours',
            'Time In' => 'time_in',
            'Time Out' => 'time_out',
        ];
    }
}
