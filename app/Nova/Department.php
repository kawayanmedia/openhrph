<?php

namespace App\Nova;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Department extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Department::class;

    // public static function label()
    // {
    //     return 'Receipts';
    // }

    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // return $query->orderBy('name', 'asc');
    }

    /**
     * Custom priority level of the resource.
     *
     * @var int
     */
    public static $priority = 4;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * Get the value that should be displayed to represent the resource.
     *
     * @return string
     */
    public function title()
    {
        return $this->name;
    }

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Admin';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
    ];

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

            Text::make('Code')
                //->rules('required')
                //->nullable()
                ->sortable(),

            Text::make('Name')
                ->rules('required')
                // ->help(
                //     'The tax rate to be applied to the sale'
                // )
                ->sortable(),

            Text::make('Address')
                // ->rules('required')
                // ->help(
                //     'The tax rate to be applied to the sale'
                // )
                ->sortable(),

            Select::make('Attendance Approval', 'attendance_approval_employee_id')
                ->options(
                    Employee::employeeList(),
                )->sortable()
                // ->rules('required')
                ->searchable()
                ->displayUsingLabels()
                ->hideFromIndex(),

            Select::make('Overtime Approval', 'attendance_approval_employee_id')
                ->options(
                    Employee::employeeList(),
                )->sortable()
                // ->rules('required')
                ->searchable()
                ->displayUsingLabels()
                ->hideFromIndex(),

            Select::make('Leave Approval', 'leave_approval_employee_id')
                ->options(
                    Employee::employeeList(),
                )->sortable()
                // ->rules('required')
                ->searchable()
                ->displayUsingLabels()
                ->hideFromIndex(),

            HasMany::make('Employees', 'Employees', 'App\Nova\Employee')->nullable()->readonly(),
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

    /**
     * Register a callback to be called after the resource is created.
     *
     * @return void
     */
    public static function afterCreate(NovaRequest $request, Model $model)
    {
        // $model->company_id = $request->user()->company_id;
        // $model->save();
    }
}
