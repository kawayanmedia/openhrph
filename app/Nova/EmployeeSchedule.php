<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class EmployeeSchedule extends Resource
{
    /**
     * Indicates if the resource should be displayed in the sidebar.
     *
     * @var bool
     */
    public static $displayInNavigation = false;

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\EmployeeSchedule::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * @var array
     */
    public static $with = ['employee', 'employee.office'];

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Attendance';

    public static function label()
    {
        return 'Employee Schedule';
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    // public static $search = [
    //     'id',
    //     'employee.first_name',
    // ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            //Text::make('Employee', 'employee_id')->rules('required')->onlyOnForms(),
            Text::make('Employee', function () {
                return $this->employee?->fullname;
            })->onlyOnIndex(),

            Text::make('Office', function () {
                if ($this->employee) {
                    return $this->employee->office?->name;
                }
            })->onlyOnIndex(),

            //Text::make('From', 'from_date')->rules('required')->onlyOnForms(),

            BelongsTo::make('Employee')
                ->creationRules('required', 'unique:employees_schedules,employee_id')
                ->updateRules('required', 'unique:employees_schedules,employee_id,{{resourceId}}')
                ->searchable()
                ->withoutTrashed()
                ->hideFromIndex(),

            Date::make('From Date', 'from_date')
                // ->format('MMM DD YYYY') //  https://momentjs.com/docs/#/parsing/string-format
                //->rules('required')
                ->help(
                    'Leave Blank if regular working hours'
                ),

            Date::make('To Date', 'to_date')
                // ->format('MMM DD YYYY') //  https://momentjs.com/docs/#/parsing/string-format
                //->rules('required')
                ->nullable()
                ->help(
                    'Leave Blank if regular working hours'
                ),

        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
