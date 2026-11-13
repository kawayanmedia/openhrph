<?php

namespace App\Nova;

use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Schedule extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Schedule::class;

    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // Get the groups by company id
        // return $query->where('company_id', $request->user()->company_id);
    }

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Attendance';

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
    public function fieldsForIndex(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->sortable(),

            Text::make('Time In', 'time_in_format'),

            Text::make('Break Time', 'break_time'),

            Date::make('Effective Date Start')->hideFromIndex(),

            Date::make('Effective Date End')->hideFromIndex(),

            Boolean::make('Default Schedule', 'is_default'),

            Boolean::make('Active', 'is_active')
                ->default(true)
                ->sortable(),
        ];
    }

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

            Text::make('Name')
                ->rules('required')
                ->sortable(),

            Text::make('Time In', 'time_in_format')
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    unset($model->{$attribute});
                })
                ->hideWhenCreating(),

            Text::make('Time In', 'time_in')
                ->rules('required')
                ->hideFromDetail()
                // ->default('08:00')
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    unset($model->{$attribute});
                })
                ->withMeta(['extraAttributes' => [
                    'placeholder' => '08:00',
                    'type' => 'time',
                    //'class' => '13:00'
                ],
                ]),

            Text::make('Break Time Start', 'break_time_start')
                ->rules('date_format:H:i', 'nullable')
                ->withMeta(['extraAttributes' => [
                    'placeholder' => '12:00',
                    'type' => 'time',
                ],
                ]),

            Text::make('Break Time End', 'break_time_end')
                ->rules('date_format:H:i', 'nullable')
                ->withMeta(['extraAttributes' => [
                    'placeholder' => '12:00',
                    'type' => 'time',
                ],
                ]),

            Text::make('Time out', 'time_out')
                ->rules('required')
                ->hideFromDetail()
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    unset($model->{$attribute});
                })
                ->withMeta(['extraAttributes' => [
                    'placeholder' => '12:00',
                    'type' => 'time',
                ],
                ]),

            // Text::make('PM IN')
            //     ->rules('date_format:H:i', 'nullable')
            //     ->withMeta(['extraAttributes' => [
            //         'placeholder' => '13:00',
            //         'type' => 'time',
            //     ],
            //     ]),

            // Text::make('PM OUT')
            //     ->rules('date_format:H:i', 'nullable')
            //     // ->default('17:00')
            //     ->withMeta(['extraAttributes' => [
            //         'placeholder' => '17:00',
            //         'type' => 'time',
            //     ],
            //     ]),

            // Boolean::make('Out in the morning?', 'out_morning')
            //     // ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
            //     //     unset($model->{$attribute});
            //     // })
            //     ->dependsOn(
            //         ['time_out', 'time_in'],
            //         function (Boolean $field, NovaRequest $request, FormData $formData) {

            //             if ($formData->time_out <= '12:00') {
            //                 $field->show();
            //                 if ($formData->time_in >= '12:01') {
            //                     $field->withMeta(['value' => true]);
            //                 } else {
            //                     $field->withMeta(['value' => false]);
            //                 }
            //             }
            //         }
            //     ),
            // ->default(true),

            // Text::make('OUT IN THE MORNING', 'am_out_morning')
            //     ->rules('date_format:H:i', 'nullable')
            //     ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
            //         unset($model->{$attribute});
            //     })
            //     ->readonly()
            //     ->hide()
            //     ->help('If the schedule is something like in factory if there are night shift')
            //     // ->dependsOn(
            //     //     ['out_morning'],
            //     //     function (Text $field, NovaRequest $request, FormData $formData) {

            //     //         if ($formData->out_morning == true) {
            //     //             $field->show();
            //     //             $field->readonly(false)->rules(['required']);
            //     //         }
            //     //     }
            //     // )
            //     ->withMeta(['extraAttributes' => [
            //         'placeholder' => '06:00',
            //         'type' => 'time',
            //     ],
            //     ]),

            // Date::make('Effective Date Start')->hideFromIndex(),

            // Date::make('Effective Date End')->hideFromIndex(),

            Boolean::make('Default Schedule', 'is_default')
                ->default(false)
                ->sortable(),

            Boolean::make('Active', 'is_active')
                // ->rules('required')
                ->default(true)
                ->sortable(),

            // HasMany::make('EmployeeSchedules'),
            // Argument 1. Name to display (e.g. Empoyees)
            // Argument 2. Name of the relationship as used in the model (e.g. employeeSchedules)
            // Argument 3. The Nova Resource (e.g. App\Nova\EmployeeSchedule)
            HasMany::make('Employees', 'employeeSchedules', EmployeeSchedule::class),
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
        $model->updateTime($request, $model);
    }

    public static function afterUpdate(NovaRequest $request, Model $model)
    {
        $model->updateTime($request, $model);
    }
}
