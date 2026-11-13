<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\Employee;
use Illuminate\Http\Request;

class TotalEmployees extends Metric
{
    public string $name = 'Total Employees';

    public function calculate(Request $request): mixed
    {
        return Employee::count();
    }
}
