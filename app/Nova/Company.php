<?php

namespace App\Nova;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Company extends Resource
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
    public static $model = \App\Models\Company::class;

    public static function label()
    {
        return 'Company Settings';
    }

    /**
     * Custom priority level of the resource.
     *
     * @var int
     */
    public static $priority = 1;

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

    public static $with = [
        'employees',
    ];

    public static function indexQuery(NovaRequest $request, $query)
    {
        return static::scopeToUser($request, $query);
    }

    public static function detailQuery(NovaRequest $request, $query)
    {
        return static::scopeToUser($request, $query);
    }

    protected static function scopeToUser(Request $request, $query)
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if (static::isPlatformAdmin($user)) {
            return $query;
        }

        $ids = $user->companyMemberships()->pluck('id')->all();

        return $query->whereIn('id', $ids ?: [0]);
    }

    protected static function isPlatformAdmin(?User $user): bool
    {
        return $user !== null && in_array($user->email, config('tenancy.platform_admin_emails', []), true);
    }

    public function authorizedToView(Request $request)
    {
        $user = $request->user();

        if (static::isPlatformAdmin($user)) {
            return true;
        }

        return $user !== null
            && $this->resource !== null
            && $user->belongsToCompany((int) $this->resource->getKey());
    }

    public static function authorizedToSearch(Request $request)
    {
        return false;
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    // public function authorizedToDelete(Request $request)
    // {
    //     return true;
    // }

    public function authorizedToReplicate(Request $request)
    {
        return false;
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  Request  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            // Text::make('Code')
            //     //->rules('required')
            //     //->nullable()
            //     ->sortable(),

            Text::make('Name')
                // ->size('w-1/3')
                ->rules('required')
                // ->help(
                //     'The tax rate to be applied to the sale'
                // )
                ->sortable(),

            Text::make('Employees', function () {
                return $this->employees->count();
            })->sortable(),

            // Text::make('Trade Name', 'trade_name')
            //     ->rules('required')
            //     // ->help(
            //     //     'The tax rate to be applied to the sale'
            //     // )
            //     ->sortable(),

            Select::make('Type')
                ->options(
                    [
                        'Private' => 'Private',
                        'Government' => 'Government',
                        'Non-Profit' => 'Non-Profit',
                    ]
                )->sortable()
                ->rules('required'),

            Text::make('TIN', 'tin')
                ->placeholder('000-000-000-000')
                ->rules('required'),

            Text::make('RDO', 'rdo')
                ->placeholder('049')
                ->rules('required'),

            Text::make('SSS', 'sss')
                ->placeholder('00-0000000-0')
                ->rules('required'),

            Text::make('HDMF', 'hdmf')
                ->placeholder('0000-0000-0000')
                ->rules('required'),

            Text::make('PhilHealth', 'phil_health')
                ->placeholder('00-000000000-0')
                ->rules('required'),
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
        return [];
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
}
