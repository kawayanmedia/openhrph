<?php

namespace App\Nova;

use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Allowance extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Allowance::class;

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
    public static $group = 'Payroll';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
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
            ID::make()->sortable(),

            Text::make('Name', 'name')
                ->sortable()
                ->rules('required'),

            Select::make('Tax Option', 'tax_option')
                ->options(
                    [
                        'fully_taxable' => 'Fully Taxable',
                        'partially_nontaxable' => 'Partially Non-Taxable',
                        'fully_nontaxable' => 'Fully Non-Taxable',
                        'reduce_taxable_income' => 'Reduce to taxable income',
                    ]
                )->sortable()
                ->rules('required')
                ->hideFromIndex()
                ->onlyOnForms(),

            // Currency::make('Amount')
            //     ->rules('required')
            //     ->sortable(),

            // Boolean::make('Taxable', 'is_taxable')->default(true),

            Currency::make('Amount', 'amount')
                ->rules('required')
                ->sortable()
                ->symbol(currency_symbol())
                ->readonly()
                ->help('Partially Non Taxable Ceiling Amount (Per Annum)')
                ->dependsOn(
                    ['is_taxable', 'tax_option'],
                    function (Text $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        // if ($formData->is_taxable === false) {
                        //     $field->readonly(false)->rules(['required']);
                        // }
                        if ($formData->tax_option == 'partially_nontaxable') {
                            $field->readonly(false)->rules(['required']);
                        }
                    }
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

    /**
     * Register a callback to be called after the resource is created.
     *
     * @return void
     */
    public static function afterCreate(NovaRequest $request, Model $model)
    {
        // $model->maximum_non_tax = $request->maximum_non_tax;
        $model->company_id = $request->user()->company_id;
        $model->save();
    }

    public static function afterUpdate(NovaRequest $request, Model $model)
    {
        // $model->maximum_non_tax = $request->maximum_non_tax;
        $model->company_id = $request->user()->company_id;
        $model->save();
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
    }
}
