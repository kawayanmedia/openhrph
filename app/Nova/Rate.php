<?php

namespace App\Nova;

use App\Nova\Lenses\OrderAscending;
use App\Support\Tenancy\TenantCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use Laravel\Nova\URL;

// use Illuminate\Database\Query\Builder;
class Rate extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Rate::class;

    /**
     * Build an "index" query for the given resource.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // Get the groups by company id
        // return $query->where('company_id', $request->user()->company_id);
    }

    // https://github.com/laravel/nova-issues/issues/156

    public static $orderBy = ['id' => 'asc'];

    /**
     * The pagination per-page options configured for this resource.
     *
     * @return array
     */
    public static $perPageOptions = [50, 100, 150];

    // protected static function applyOrderings($query, array $orderings)
    // {
    //     if (empty($orderings) && property_exists(static::class, 'orderBy')) {
    //         $orderings = static::$orderBy;
    //     }

    //     return parent::applyOrderings($query, $orderings);
    // }

    // public static $defaultSort = 'id';

    /**
     * Build an "index" query for the given resource.
     *
     * @param  NovaRequest  $request
     * @param  Builder  $query
     * @return Builder
     */
    // public static function indexQuery(NovaRequest $request, $query)
    // {
    //     if (static::$defaultSort && empty($request->get('orderBy'))) {
    //         $query->getQuery()->orders = [];
    //         return $query->orderBy(static::$defaultSort);
    //     }
    //     return $query;
    // }

    /**
     * Indicates if the resource should be displayed in the sidebar.
     *
     * @var bool
     */
    // public static $displayInNavigation = false;

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
        return $this->day_type;
    }

    public static function authorizedToSearch(Request $request)
    {
        return false;
    }

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

    public function authorizedToView(Request $request)
    {
        return false;
    }

    public function authorizedToReplicate(Request $request)
    {
        return false;
    }

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
        'day_type',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  Request  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [

            // ID::make()->sortable(),

            Text::make('', 'day_type')->hideWhenUpdating(),
            // ->sortable(),

            Text::make('Regular', 'regular'),

            Text::make('Overtime', 'ot'),

            Text::make('Night Differential', 'nt'),

            Text::make('Night Differential + Overtime', 'nt_ot'),

            // Text::make('Holiday Type', 'holiday_type')
            //     ->sortable(),

            // Text::make('Time Type', 'time_type')
            //     ->sortable(),

            // Text::make('Code', 'code')
            //     ->sortable(),

            // Text::make('Rate', 'rate')
            //     ->sortable(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  Request  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  Request  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  Request  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [
            // OrderAscending::make(),
        ];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  Request  $request
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
        TenantCache::forget('rates_options');
    }

    public static function afterUpdate(NovaRequest $request, Model $model)
    {
        TenantCache::forget('rates_options');
    }

    // We can also use other method like above
    // afterUpdate
    // afterDelete
    // afterForceDelete

    /**
     * Return the location to redirect the user after creation.
     *
     * @param  NovaRequest  $request
     * @param  \Laravel\Nova\Resource  $resource
     * @return URL|string
     */
    // public static function redirectAfterCreate(NovaRequest $request, $resource)
    // {
    //     return '/resources/'.Employee::uriKey().'/'.$request->viaResourceId;
    // }

    // /**
    //  * Return the location to redirect the user after update.
    //  *
    //  * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
    //  * @param  \Laravel\Nova\Resource  $resource
    //  * @return \Laravel\Nova\URL|string
    //  */
    // public static function redirectAfterUpdate(NovaRequest $request, $resource)
    // {
    //     return '/resources/'.Employee::uriKey().'/'.$request->viaResourceId;
    // }
}
