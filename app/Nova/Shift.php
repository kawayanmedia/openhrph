<?php

namespace App\Nova;

use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Shift extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Shift::class;

    // public static $orderBy = ['date' => 'asc'];

    // protected static function applyOrderings($query, array $orderings)
    // {
    //     if (empty($orderings) && property_exists(static::class, 'orderBy')) {
    //         $orderings = static::$orderBy;
    //     }

    //     return parent::applyOrderings($query, $orderings);
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
        return $this->name;
    }

    public static function label()
    {
        return 'Shifts';
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
        // 'id',
        // 'name',
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
            // ID::make()->sortable(),

            Text::make('Name', 'name')
                ->rules('required')
                ->sortable(),

            Number::make('Grace Period', 'grace_period')
                ->rules('required')
                ->help('Minutes')
                ->sortable(),

            Number::make('Start Deducting', 'start_deducting')
                // ->rules('required')
                ->sortable(),

            // MultiSelect::make('Sizes')
            //     ->options([
            //         'S' => 'Small',
            //         'M' => 'Medium',
            //         'L' => 'Large',
            //     ]),
            //     // ->searchable(),

            // MultiSelect::make('Size')->options([
            //     'S' => 'Small',
            //     'M' => 'Medium',
            //     'L' => 'Large',
            // ])->displayUsingLabels(),
            // BelongsTo::make('Office')
            //     ->rules('required'),
            // ->hideFromIndex(),

            // Boolean::make('Requires leave credit?', 'is_leave_credits')->default(false),

            // Boolean::make('Payable?', 'is_payable')->default(false),

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

    public static function afterUpdate(NovaRequest $request, Model $model)
    {
    }

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
        return '/resources/'.static::uriKey();
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
        return '/resources/'.static::uriKey();
        // return '/resources/'.Employee::uriKey().'/'.$request->viaResourceId;
    }
}
