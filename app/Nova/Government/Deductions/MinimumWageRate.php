<?php

namespace App\Nova\Government\Deductions;

use App\Nova\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class MinimumWageRate extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\MinimumWageRate::class;

    /**
     * Indicates if the resource should be displayed in the sidebar.
     *
     * @var bool
     */
    // public static $displayInNavigation = false;

    /**
     * The pagination per-page options configured for this resource.
     *
     * @return array
     */
    public static $perPageOptions = [100, 200, 300];

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
    public static $title = 'region';

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
        return 'Minimum Wage Rates';
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
        'region',
        'industry',
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

            Text::make('Region', 'region')->filterable(),
            Text::make('Industry', 'industry'),
            Currency::make('Wage', 'wage_start')->symbol(''),
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
    }
}
