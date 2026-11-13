<?php

namespace App\Nova\Metrics;

use App\Models\Office;
use Illuminate\Http\Request;
use Laravel\Nova\Metrics\Value;

class TotalOffices extends Value
{
    /**
     * Calculate the value of the metric.
     *
     * @return mixed
     */
    public function calculate(Request $request)
    {
        // return $this->result(Office::count())->previous(1);
        return $this->result(Office::count());
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'total-offices';
    }
}
