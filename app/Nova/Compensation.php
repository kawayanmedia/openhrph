<?php

namespace App\Nova;

use App\Nova\Actions\OverrideCompensation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use Laravel\Nova\URL;

class Compensation extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Compensation::class;

    /**
     * The pagination per-page options configured for this resource.
     *
     * @return array
     */
    public static $perPageOptions = [100, 200, 300];

    /**
     * Build an "index" query for the given resource.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->effective();
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    public function authorizedToUpdate(Request $request)
    {
        return $this->ownsOverride($request);
    }

    public function authorizedToDelete(Request $request)
    {
        return $this->ownsOverride($request);
    }

    /**
     * A company may only change the amount and ceiling of its own override.
     */
    private function ownsOverride(Request $request): bool
    {
        $companyId = $this->resource->getAttribute('company_id');
        $userCompanyId = $request->user()?->company_id;

        return $companyId !== null
            && $userCompanyId !== null
            && (int) $companyId === (int) $userCompanyId;
    }

    public static $orderBy = ['id' => 'asc'];

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

    public static function label()
    {
        return 'Compensations';
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'code',
        'name',
        'tax_class',
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
            ID::make()->sortable(),

            Text::make('Code', 'code')
                ->sortable()
                ->rules('required'),

            Text::make('Name', 'name')
                ->sortable()
                ->rules('required'),

            Select::make('Tax Class', 'tax_class_code')
                ->options(\App\Models\Compensation::TAX_CLASS_OPTIONS)
                ->sortable()
                ->rules('required')
                ->help('BIR tax class. It decides which payroll detail column the amount is reported in.'),

            Boolean::make('Taxable', 'is_taxable')
                ->onlyOnIndex()
                ->default(true),

            Select::make('Tax Option', 'tax_option')
                ->options(
                    [
                        'fully_taxable' => 'Fully Taxable',
                        'partially_nontaxable' => 'Partially Non-Taxable',
                        'fully_nontaxable' => 'Fully Non-Taxable',
                        // 'reduce_taxable_income' => 'Reduce to taxable income',
                    ]
                )->sortable()
                ->rules('required')
                ->hideFromIndex()
                ->onlyOnForms(),

            Currency::make('Amount', 'amount')
                ->rules('required')
                ->sortable()
                ->symbol(currency_symbol())
                ->readonly()
                ->onlyOnForms()
                ->help('Partially Non Taxable Ceiling Amount (Per Annum)')
                ->dependsOn(
                    ['is_taxable', 'tax_option'],
                    function (Text $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        // if ($formData->is_taxable === false) {
                        //     $field->readonly(false)->rules(['required']);
                        // }
                        $field->hide();
                        if ($formData->tax_option == 'partially_nontaxable') {
                            $field->readonly(false)->rules(['required']);
                            $field->show();
                        }
                    }
                ),
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
        return [
            new OverrideCompensation,
        ];
    }

    // We can also use other method like above
    // afterUpdate
    // afterDelete
    // afterForceDelete

    /**
     * Return the location to redirect the user after creation.
     *
     * @param  \Laravel\Nova\Resource  $resource
     * @return URL|string
     */
    public static function redirectAfterCreate(NovaRequest $request, $resource)
    {
        return '/resources/'.static::uriKey();
    }

    /**
     * Return the location to redirect the user after update.
     *
     * @param  \Laravel\Nova\Resource  $resource
     * @return URL|string
     */
    public static function redirectAfterUpdate(NovaRequest $request, $resource)
    {
        return '/resources/'.static::uriKey();
    }
}
