<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\DateFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class DateRangeTo extends DateFilter
{
    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Date To';

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
        if ($value == null) {
            return $query;
        }

        return $query->where('log_date', '<=', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return [
            'dateFormat' => 'Y-m-d', // default Y-m-d H:i:S
            //'placeholder' => 'Date To', // default __('Pick a date')
            'date_to' => true,
            //'disabled' => true, // default false
            // 'twelveHourTime' => true, // default false
            // 'enableTime' => true, // default false
            // 'enableSeconds' => true, // default false
        ];
    }
}
