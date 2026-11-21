<?php

namespace App\Livewire\Admin\Filters;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TimesheetEmployee extends Filter
{
    public string $name = 'Filter By Employee';

    public function apply(Request $request, Builder $query, mixed $value): Builder
    {
        return $query->where('employee_id', $value);
    }

    public function options(Request $request): array
    {
        return Employee::employeeList();
    }
}
