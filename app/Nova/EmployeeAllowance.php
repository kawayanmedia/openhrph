<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use SLASH2NL\NovaBackButton\NovaBackButton;

class EmployeeAllowance extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\EmployeeAllowance::class;

    /**
     * Indicates if the resource should be displayed in the sidebar.
     *
     * @var bool
     */
    public static $displayInNavigation = false;

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

            BelongsTo::make('Allowance')
                ->rules('required')
                // ->searchable()
                ->withoutTrashed()
                ->hideFromIndex()
                ->onlyOnForms(),

            Text::make('Allowance', function () {
                return $this->allowance->name;
            })
                ->onlyOnIndex(),

            Boolean::make('Special Pay Run?', 'is_special')
                ->showOnPreview()
                ->hideFromIndex()
                ->default(false),

            Date::make('Release date', 'release_date')
                ->readonly()
                ->dependsOn(
                    ['is_special'],
                    function (Date $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->is_special === true) {
                            $field->readonly(false)->rules(['required']);
                        }
                    }
                ),

            Currency::make('Amount')
                ->rules('required')
                ->sortable(),

            new Panel('Recurring', $this->recurringFields()),

            new Panel('Prorated', $this->proratedFields()),

        ];
    }

    /**
     * Get the address fields for the resource.
     *
     * @return array
     */
    protected function recurringFields()
    {
        return [
            Boolean::make('Recurring', 'is_recurring')
                ->default(false)
                ->dependsOn(
                    ['is_special'],
                    function (Boolean $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->is_special === true) {
                            $field->readonly(true);
                        }
                    }
                ),

            Select::make('Frequency', 'frequency')
                ->readonly()
                ->options(frequency())
                ->sortable()
                ->dependsOn(
                    ['is_recurring'],
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        if ($formData->is_recurring === true) {
                            $field->readonly(false)->rules(['required']);
                        }
                    }
                )
                ->hideFromIndex()
                ->onlyOnForms(),

            Date::make('Valid from Date', 'valid_date_from')
                // ->readonly()
                ->dependsOn(
                    ['is_recurring', 'is_special'],
                    function (Date $field, NovaRequest $request, FormData $formData) {
                        if ($formData->is_recurring === true) {
                            $field->readonly(false)->rules(['required']);
                        }

                        if ($formData->is_special === true) {
                            $field->readonly(true);
                        }
                    }
                ),

            Date::make('Valid until Date', 'valid_date_to')
                ->readonly()
                ->dependsOn(
                    ['is_recurring'],
                    function (Date $field, NovaRequest $request, FormData $formData) {
                        if ($formData->is_recurring === true) {
                            $field->readonly(false)->rules(['required']);
                        }
                    }
                ),

            Boolean::make('Never expires', 'is_never_expires')
                ->readonly()
                ->showOnPreview()
                ->hideFromIndex()
                ->default(false)
                ->dependsOn(
                    ['is_recurring'],
                    function (Boolean $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->is_recurring === true) {
                            $field->readonly(false);
                        }
                    }
                ),
        ];
    }

    /**
     * Get the address fields for the resource.
     *
     * @return array
     */
    protected function proratedFields()
    {
        return [
            Boolean::make('Prorated', 'is_prorated')
                ->readonly()
                ->showOnPreview()
                ->hideFromIndex()
                ->default(false)
                ->dependsOn(
                    ['is_recurring'],
                    function (Boolean $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->is_recurring === true) {
                            $field->readonly(false);
                        }
                    }
                ),
            Select::make('Prorated by', 'prorated_by')
                ->readonly()
                ->options(prorated_by())
                ->sortable()
                ->dependsOn(
                    ['is_prorated'],
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        if ($formData->is_prorated === true) {
                            $field->readonly(false);
                        }
                    }
                )
                ->hideFromIndex()
                ->onlyOnForms(),

            Boolean::make('On paid leave', 'on_paid_leave')
                ->showOnPreview()
                ->hideFromIndex()
                ->default(false)
                ->readonly()
                ->dependsOn(
                    ['is_prorated'],
                    function (Boolean $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->is_prorated === true) {
                            $field->default(false);
                            $field->readonly(false);
                        }
                    }
                ),

            Boolean::make('On unpaid leave', 'on_unpaid_leave')
                ->showOnPreview()
                ->hideFromIndex()
                ->default(false)
                ->readonly()
                ->dependsOn(
                    ['is_prorated'],
                    function (Boolean $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->is_prorated === true) {
                            $field->default(false);
                            $field->readonly(false);
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
        return [
            (new NovaBackButton())
                ->onlyOnDetail(),
        ];
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
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    // public static function afterCreate(NovaRequest $request, Model $model)
    // {
    //     $model->maximum_non_tax = $request->maximum_non_tax;
    //     $model->save();
    // }

    // public static function afterUpdate(NovaRequest $request, Model $model)
    // {
    //     $model->maximum_non_tax = $request->maximum_non_tax;
    //     $model->save();
    // }

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
        return '/resources/'.Employee::uriKey().'/'.$request->viaResourceId;

        return '/resources/'.static::uriKey();
    }

    /**
     * Get the text for the create resource button.
     *
     * @return string|null
     */
    public static function createButtonLabel()
    {
        // return __('Create :resource', ['resource' => static::singularLabel()]);
        return __('Create Allowance', ['resource' => static::singularLabel()]);
    }
}
