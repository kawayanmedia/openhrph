<?php

namespace App\Nova\Government\Deductions;

use App\Nova\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class PhilHealth extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\DeductionPhilHealth::class;

    public static function uriKey()
    {
        return 'philhealth';
    }

    /**
     * Indicates if the resource should be displayed in the sidebar.
     *
     * @var bool
     */
    // public static $displayInNavigation = false;

    public static $orderBy = ['id' => 'asc'];

    // protected static function applyOrderings($query, array $orderings)
    // {
    //     if (empty($orderings) && property_exists(static::class, 'orderBy')) {
    //         $orderings = static::$orderBy;
    //     }

    //     return parent::applyOrderings($query, $orderings);
    // }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    public function authorizedToUpdate(Request $request)
    {
        return true;
    }

    public function authorizedToDelete(Request $request)
    {
        return false;
    }

    public function authorizedToReplicate(Request $request)
    {
        return false;
    }

    /**
     * Custom priority level of the resource.
     *
     * @var int
     */
    public static $priority = 6;

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
        return $this->monthl_basic;
    }

    public static function label()
    {
        return 'PhilHealth Contribution';
    }

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Government';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        // 'name',
    ];

    public function fieldsForIndex(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Name', 'monthly_basic')
                // ->rules('required')
                ->sortable(),

            Text::make('premium Rate', function () {
                if ($this->premium_rate) {
                    return number_format($this->premium_rate, 2) . '%';
                }
            }),

            Text::make('Monthly Share', function () {

                if ($this->monthly_share == null) {
                    return '₱' . number_format(500, 2) . ' to ' . '₱' . number_format(5000, 2);
                }

                return '₱' . number_format($this->monthly_share, 2);
            }),

            Text::make('Employee Share', function () {

                if ($this->employee_share == null) {
                    return '₱' . number_format(250, 2) . ' to ' . '₱' . number_format(2500, 2);
                }

                return '₱' . number_format($this->employee_share, 2);
            }),

            Text::make('Employer Share', function () {

                if ($this->employer_share == null) {
                    return '₱' . number_format(250, 2) . ' to ' . '₱' . number_format(2500, 2);
                }

                return '₱' . number_format($this->employer_share, 2);
            }),

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

            Text::make('Name', 'monthly_basic')
                // ->rules('required')
                ->sortable(),

            Currency::make('Salary Start', 'basic_salary_start')
                ->sortable(),

            Currency::make('Salary End', 'basic_salary_end')
                ->sortable(),

            Number::make('Premium Rate', 'premium_rate')
                ->rules('required')
                ->sortable(),

            Currency::make('Employee Share', 'employee_share')
                ->rules('required')
                ->sortable(),

            Currency::make('Employer Share', 'employer_share')
                ->rules('required')
                ->sortable(),
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
        $model->company_id = $request->user()->company_id;
        $model->save();
    }

    public static function afterUpdate(NovaRequest $request, Model $model) {}

    // We can also use other method like above
    // afterUpdate
    // afterDelete
    // afterForceDelete

    /**
     * Return the location to redirect the user after creation.
     *
     * @param  \Laravel\Nova\Resource  $resource
     * @return \Laravel\Nova\URL|string
     */
    public static function redirectAfterCreate(NovaRequest $request, $resource)
    {
        return '/resources/' . static::uriKey();
        // return '/resources/'.Employee::uriKey().'/'.$request->viaResourceId;
    }

    /**
     * Return the location to redirect the user after update.
     *
     * @param  \Laravel\Nova\Resource  $resource
     * @return \Laravel\Nova\URL|string
     */
    public static function redirectAfterUpdate(NovaRequest $request, $resource)
    {
        return '/resources/' . static::uriKey();
        // return '/resources/'.Employee::uriKey().'/'.$request->viaResourceId;
    }
}
