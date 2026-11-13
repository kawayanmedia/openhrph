<?php

namespace App\Nova;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Commission extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Commission::class;

    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // Get the groups by company id
        return $query->where('company_id', $request->user()->company_id);
    }

    /**
     * Build a "relatable" query for the given resource.
     *
     * This query determines which instances of the model may be attached to other resources.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Laravel\Nova\Fields\Field  $field
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function relatableQuery(NovaRequest $request, $query)
    {
        //return $query->where('user_id', $request->user()->id);
    }

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
    public static $priority = 3;

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
    //public static $with = ['model', 'model.relationship'];
    public static $with = [];

    // public static function authorizedToSearch(Request $request)
    // {
    //     return false;
    // }

    // public static function authorizedToCreate(Request $request)
    // {
    //     return false;
    // }

    // public function authorizedToDelete(Request $request)
    // {
    //     return false;
    // }

    // public function authorizedToView(Request $request)
    // {
    //     return false;
    // }

    // public function authorizedToReplicate(Request $request)
    // {
    //     return false;
    // }

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Payroll';

    // public static function label()
    // {
    //     return 'Payroll Groups - Todo';
    // }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Name', 'name')
                ->rules('required')
                ->sortable(),

            Select::make('Amount Basis', 'amount_basis')
                ->options(
                    ['Fixed' => 'Fixed']
                )
                ->default('Fixed')
                ->rules('required'),

            Boolean::make('Taxable?', 'is_taxable')
                ->default(true)
                ->rules('required'),

            Number::make('Maximum non-taxable', 'maximum_non_tax')
                ->rules('required')
                ->readonly()
                ->dependsOn(
                    ['is_taxable'],
                    function (Text $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->is_taxable === false) {
                            $field->readonly(false)->rules(['required']);
                        }
                    }
                ),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
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
        $model->maximum_non_tax = $request->maximum_non_tax;
        $model->save();
    }

    public static function afterUpdate(NovaRequest $request, Model $model)
    {
        // if ($request->is_taxable === false) {
        $model->maximum_non_tax = $request->maximum_non_tax;
        // }

        $model->save();
    }

    /**
     * Return the location to redirect the user after creation.
     *
     * @param  \Laravel\Nova\Resource  $resource
     * @return \Laravel\Nova\URL|string
     */
    public static function redirectAfterCreate(NovaRequest $request, $resource)
    {
        return '/resources/'.static::uriKey();
    }

    /**
     * Return the location to redirect the user after creation.
     *
     * @param  \Laravel\Nova\Resource  $resource
     * @return \Laravel\Nova\URL|string
     */
    public static function redirectAfterUpdate(NovaRequest $request, $resource)
    {
        return '/resources/'.static::uriKey();
    }
}
