<?php

namespace App\Nova\Metrics;

use App\Models\Employee;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class EmploymentType extends Partition
{
    /**
     * Calculate the value of the metric.
     *
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        return $this->count($request, Employee::class, 'employment_status')
            ->label(fn ($value) => match ($value) {
                null => 'None',
                1 => 'Regular',
                2 => 'Probationary',
                3 => 'Contractual',
                4 => 'Consultant',
                default => ucfirst($value)
            });

        return $this->count($request, Employee::class, 'employment_status');
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
        // return now()->addMinutes(5);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'employment-type';
    }
}
