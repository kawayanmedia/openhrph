<?php

namespace App\Nova\Filters;

use App\Models\Employee;
use App\Models\EmploymentType;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class EmploymentStatus extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Employment Type';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->where('employment_type_id', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return EmploymentType::orderBy('name')->pluck('name', 'id')->toArray();

        return app(Employee::class)->statuses(null, true)->toArray();
    }
}
