<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class FieldsFilter extends BooleanFilter
{
    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Columns';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        return $query;
    }

    /**
     * Get the filter's available options.
     *
     * @return array
     */
    public function options(NovaRequest $request)
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

    /**
     * Set the default options for the filter.
     *
     * @return array
     */
    public function default()
    {
        return [
            'employee_number' => true,
            'log_date' => true,
            'day_type' => true,
            'hours' => true,
            'time_in' => true,
            'time_out' => true,
        ];
    }
}
