<?php

namespace App\Nova\Metrics;

use App\Models\Department;
use Illuminate\Http\Request;
use Laravel\Nova\Metrics\Value;

class TotalDepartments extends Value
{
    /**
     * Calculate the value of the metric.
     *
     * @return mixed
     */
    public function calculate(Request $request)
    {
        return $this->result(Department::count());
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'total-departments';
    }
}
