<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\Department;
use Illuminate\Http\Request;

class TotalDepartments extends Metric
{
    public string $name = 'Total Departments';

    public function calculate(Request $request): mixed
    {
        return Department::count();
    }
}
