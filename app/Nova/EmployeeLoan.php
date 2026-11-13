<?php

namespace App\Nova;

use App\Models\Loan;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class EmployeeLoan extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\EmployeeLoan::class;

    /**
     * Indicates if the resource should be displayed in the sidebar.
     *
     * @var bool
     */
    public static $displayInNavigation = true;

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

            // BelongsTo::make('Loan')
            //     ->withoutTrashed()
            //     ->rules('required'),
            // ->hideFromIndex(),
            // ->onlyOnForms(),

            BelongsTo::make('Employee')
                ->withoutTrashed()
                ->searchable()
                ->rules('required'),

            Select::make('Loan', 'loan_id')
                ->options(Loan::pluck('name', 'id')->toArray())
                ->sortable()
                ->searchable()
                ->rules('required')
                // ->withoutTrashed()
                ->hideFromIndex()
                ->onlyOnForms(),

            // Hide all field from here when not SSS or HDMF
            Text::make('Reference Number', 'reference_number')
                ->rules('required')
                ->sortable(),

            Text::make('Bank Name', 'bank_name')
                // ->rules('required')
                ->hide()
                ->dependsOn(
                    ['loan_id'],
                    function (Text $field, NovaRequest $request, FormData $formData) {

                        if ($formData->loan_id == 11) {
                            $field->show();
                            $field->rules('required');
                        }
                    }
                )
                ->sortable(),

            Date::make('Approved Date'),

            // Select::make('SSS loan type', 'sss_loan_type')
            //     ->options(sss_loan_types())->sortable()
            //     ->readonly()
            //     ->hideFromIndex()
            //     ->dependsOn(
            //         ['Loan'],
            //         function (Select $field, NovaRequest $request, FormData $formData) {
            //             // dd($formData->Loan);
            //             if ($formData->Loan == '1') {
            //                 $field->readonly(false)->rules(['required']);
            //             }

            //             // $field->readonly(false);
            //         }
            //     )
            //     ->onlyOnForms(),

            // Select::make('HDMF loan type', 'hdmf_loan_type')
            //     ->options(hdmf_loan_types())
            //     ->sortable()
            //     ->rules('required')
            //     ->hideFromIndex()
            //     ->onlyOnForms(),
            // Hide all field from here when not SSS or HDMF -- END

            Currency::make('Principal Amount', 'amount')
                ->rules('required')
                ->sortable(),

            Currency::make('Remaining Balance', 'balance')
                ->rules('required')
                ->sortable(),

            Number::make('Loan Term', 'terms')
                ->rules('required')
                ->help('(Months)')
                ->sortable(),

            Date::make('Repayment start date', 'repayment_start_date')->rules('required'),

            Date::make('Repayment end date', 'repayment_end_date'),

            // Hidden::make('Slug', 'employee_id')->default($request->resourceId),

            Select::make('Payment scheme', 'scheme')
                ->options(payment_schemes())->sortable()
                ->rules('required')
                ->hideFromIndex()
                ->onlyOnForms(),

            Currency::make('Monthly amortization', 'monthly_amortization')
                ->rules('required')
                ->sortable(),

            Boolean::make('Active', 'is_active')
                ->default(true),
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
        if ($model->loan->name != 'SSS' or $model->loan->name != 'HDMF') {
            $model->sss_loan_type = null;
            $model->hdmf_loan_type = null;
            $model->save();
        }
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
        return '/resources/'.Employee::uriKey().'/'.$request->viaResourceId;
    }

    /**
     * Return the location to redirect the user after update.
     *
     * @param  \Laravel\Nova\Resource  $resource
     * @return \Laravel\Nova\URL|string
     */
    public static function redirectAfterUpdate(NovaRequest $request, $resource)
    {
        return '/resources/'.Employee::uriKey().'/'.$request->viaResourceId;
    }

    /**
     * Get the text for the create resource button.
     *
     * @return string|null
     */
    public static function createButtonLabel()
    {
        // return __('Create :resource', ['resource' => static::singularLabel()]);
        return __('Create Loan', ['resource' => static::singularLabel()]);
    }
}
