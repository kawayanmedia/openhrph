<?php

namespace App\Nova;

use Illuminate\Database\Eloquent\Model;
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

class EmployeeDeduction extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\EmployeeDeduction::class;

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
    public static $group = 'Admin';

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
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Deduction')
                ->rules('required')
                ->withoutTrashed()
                ->hideFromIndex()
                ->onlyOnForms(),

            Text::make('Deduction', function () {
                if ($this->deduction == null) {
                    return;
                }

                return $this->deduction->name;
            })
                ->onlyOnIndex(),

            Currency::make('Amount')
                ->rules('required')
                ->sortable(),

            new Panel('Recurring', $this->recurringFields()),
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
                ->options(payment_schemes())
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
                    ['is_recurring'],
                    function (Date $field, NovaRequest $request, FormData $formData) {
                        if ($formData->is_recurring === true) {
                            $field->readonly(false)->rules(['required']);
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
        // $model->sendEmailVerificationNotification();
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
        return __('Create Deduction', ['resource' => static::singularLabel()]);
    }
}
