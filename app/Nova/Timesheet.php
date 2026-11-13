<?php

namespace App\Nova;

use App\Enums\Timesheet\DayType;
use App\Nova\Actions\EditViewTimeSheet;
use App\Nova\Actions\GenerateTimeSheet;
use App\Nova\Filters\FieldsFilter;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Timesheet extends Resource
{
    /**
     * Indicates if the resource should be displayed in the sidebar.
     *
     * @var bool
     */
    public static $displayInNavigation = true;

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Timesheet::class;

    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // Get only timesheet with employee
        return $query->has('employee');
    }

    public static $orderBy = ['log_date' => 'asc'];

    // protected static function applyOrderings($query, array $orderings)
    // {
    //     if (empty($orderings) && property_exists(static::class, 'orderBy')) {
    //         $orderings = static::$orderBy;
    //     }

    //     return parent::applyOrderings($query, $orderings);
    // }

    public function authorizedToReplicate(Request $request)
    {
        return false;
    }

    public function authorizedToUpdate(Request $request)
    {
        return false;
    }

    public function authorizedToDelete(Request $request)
    {
        return false;
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    public function authorizedToView(Request $request)
    {
        return true;
    }

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
    public static $with = ['employee'];

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Attendance';

    public static function label()
    {
        return 'Timesheet';
    }

    /**
     * The click action to use when clicking on the resource in the table.
     *
     * Can be one of: 'detail' (default), 'edit', 'select', 'preview', or 'ignore'.
     *
     * @var string
     */
    // public static $clickAction = 'edit';
    public static $clickAction = 'preview';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'employee.first_name',
        'employee.last_name',
    ];

    public function fieldsForIndex(NovaRequest $request)
    {
        $filterFields = filter_fields($request);

        return [
            ID::make()->sortable(),
            Text::make('Employee Number', 'employee_number')
                ->sortable()
                ->showOnPreview(),

            BelongsTo::make('Employee')->filterable()->searchable()->sortable(),

            Date::make('Date', 'log_date')
                ->sortable()
                ->displayUsing(function ($logDate) {
                    return $logDate->format('M d, Y');
                })
                ->showOnPreview(),

            BelongsTo::make('Schedule'),
            Text::make('Day Type', function () {

                return $this->day_type_format;
            })->asHtml()
                ->showOnIndex(show_on_index($filterFields, 'day_type')),

            Text::make('TimeIn', 'am_login')
                ->showOnIndex(show_on_index($filterFields, 'am_login')),

            // Text::make('AM Logout', 'am_logout'),

            // Text::make('PM Login', 'pm_login'),

            // Text::make('TimeOut', 'pm_logout'),

            Heading::make($this->resource->logs_display)->asHtml()->onlyOnPreview(),

            // Text::make('Logs', function () {
            //     return json_encode($this->logs_rates);
            // })
            //     ->onlyOnPreview(),
            // ->showOnPreview(),

            Text::make('Hours', function () {
                return number_format($this->total_hours, 2);
            })
                ->showOnIndex(show_on_index($filterFields, 'hours'))
                ->showOnPreview(),
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

            Select::make('Day Type', 'day_type')
                ->options(
                    DayType::options()
                )->sortable()
                ->onlyOnForms()
                ->rules('required'),
            // Text::make('Employee Number', 'employee_number')
            //     ->sortable()
            //     ->showOnPreview(),

            // Date::make('Date', 'log_date')
            //     ->sortable()
            //     ->displayUsing(function ($logDate) {
            //         return $logDate->format('M d, Y');
            //     })
            //     ->showOnPreview(),

            // Text::make('date', function () {
            //     return $this->log_date->format('M d, Y');
            // })
            //     ->onlyOnIndex()
            //     ->sortable()
            //     ->showOnPreview(),

            // BelongsTo::make('Schedule'),

            // Text::make('Name', function () {
            //     return $this->rate?->name;
            // })
            // ->onlyOnIndex()
            // ->showOnPreview(),

            // BelongsTo::make('Employee')->filterable()->searchable()->sortable(),

            // Text::make('TimeIn', 'am_login'),

            // Text::make('AM Logout', 'am_logout'),

            // Text::make('PM Login', 'pm_login'),

            // Text::make('TimeOut', 'pm_logout'),

            // Text::make('Hours', function () {
            //     return $this->total_hours;
            // })
            //     ->onlyOnIndex()
            //     ->showOnPreview(),

            // Text::make('Hours', function () {
            //     return $this->hours;
            // })->onlyOnIndex()->showOnPreview(),

            // InlineText::make('Hours'),
            // TimeField::make('Hours', 'hours2'),
            // DateTime::make('time_Enable')->,

            // Text::make('Office', function () {
            //     if ($this->employee) {
            //         return $this->employee->office?->name;
            //     }
            // })->onlyOnIndex(),

            //Text::make('From', 'from_date')->rules('required')->onlyOnForms(),

        ];
    }

    /**
     * Get the fields displayed by the resource on preview page.
     *
     * @return array
     */
    // public function fieldsForPreview(NovaRequest $request)
    // {
    //     return [
    //         Number::make('hours'),
    //     ];
    // }

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
        return [
            // new Filters\AttendanceOffice,
            new Filters\DateRangeFrom,
            new Filters\DateRangeTo,
            new FieldsFilter,
        ];
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
        return [
            // new Actions\ImportTimeSheet,
            (new Actions\ImportTimeSheet)->standalone(),
            // (new EditViewTimeSheet)->onlyOnTableRow()->showInline(),
            (new EditViewTimeSheet($this->model()))->onlyOnTableRow($this->model()->employee?->allow_timesheet_edit),
            // (new GenerateTimeSheet)->standalone()->withoutConfirmation()

            // I think this can be automatic
            (new GenerateTimeSheet)->standalone(),
        ];
    }

    public static function searchable()
    {
        return true;
    }
}
