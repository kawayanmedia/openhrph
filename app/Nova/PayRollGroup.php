<?php

namespace App\Nova;

use App\Actions\GeneratePayrollDateAction;
use App\Models\PayrollGroup as PayrollGroupModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use NumberFormatter;

class PayRollGroup extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\PayrollGroup::class;

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
    public static $priority = 0;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * @var array
     */
    //public static $with = ['model', 'model.relationship'];
    public static $with = ['employees'];

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

    public static function label()
    {
        return 'Payroll Groups';
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
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

            Number::make('Employees', function () {
                return $this->employees->count();
            })->hideWhenCreating(),

            Number::make('days/year', 'days_year')
                // ->rules('required')
                ->help('Number of days per year. Mon-Fri is 261. Mon-Sat is 313')
                ->sortable(),

            Number::make('No. of hours', 'hours_day')
                // ->rules('required')
                ->hideFromIndex()
                ->help('The required number of hours per day')
                ->sortable(),

            Select::make('Period', 'frequency')
                ->options(
                    [
                        PayrollGroupModel::FREQUENCY_MONTHLY => 'Monthly',
                        PayrollGroupModel::FREQUENCY_SEMI_MONTHLY => 'Semi-Monthly',
                        // PayrollGroupModel::FREQUENCY_WEEKLY => 'Weekly',
                        // PayrollGroupModel::FREQUENCY_DAILY => 'Daily',
                    ]
                )->sortable()
                ->rules('required')
                ->hideFromIndex()
                ->onlyOnForms(),

            Boolean::make('Default', 'is_default')
                ->showOnPreview()
                // ->hideFromIndex()
                ->default(false),

            new Panel('Cut-off', $this->cutoffFields()),

            new Panel('Deductions', $this->deductionsFields()),

            new Panel('Attendance', $this->attendanceFields()),

            HasMany::make('Employees', 'Employees', 'App\Nova\Employee')->nullable()->readonly(),
        ];
    }

    /**
     * Get the address fields for the resource.
     *
     * @return array
     */
    protected function cutoffFields()
    {
        $days = [];

        for ($i = 1; $i != 32; $i++) {
            // $days[$i] = $this->ordinal($i).' day of the month';
            $days[$i] = 'Day ' . $i;
        }

        return [

            Select::make('First Timesheet Cut-off', 'first_cut_off')
                ->options($days)
                ->readonly()
                ->dependsOn(
                    ['frequency'],
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                            $field->readonly(false)->rules(['required']);
                            $field->default(25);
                            $field->name = 'Timesheet Cut-off';
                        }
                        if ($formData->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                            $field->readonly(false)->rules(['required']);
                            $field->default(10);
                        }
                    }
                )->onlyOnForms(),

            Select::make('First Pay date', 'first_pay_date')
                ->options($days)
                ->readonly()
                ->dependsOn(
                    ['frequency'],
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                            $field->readonly(false)->rules(['required']);
                            $field->default(30);
                            $field->name = 'Pay Date';
                        }

                        if ($formData->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                            $field->readonly(false)->rules(['required']);
                            $field->default(15);
                        }
                    }
                )->onlyOnForms(),

            Select::make('Second Timesheet Cut-off', 'second_cut_off')
                ->options($days)
                ->readonly()
                ->dependsOn(
                    ['frequency'],
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                            $field->readonly(false)->rules(['required']);
                            $field->default(25);
                        }
                        if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                            $field->hide();
                        }
                    }
                )->onlyOnForms(),

            Select::make('Second Pay date', 'second_pay_date')
                ->options($days)
                ->readonly()
                ->dependsOn(
                    ['frequency'],
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                            $field->readonly(false)->rules(['required']);
                            $field->default(30);
                        }
                        if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                            $field->hide();
                        }
                    }
                )->onlyOnForms(),

            Select::make('Pay Date on non-working day?', 'pay_date_non_working')
                ->options([
                    PayrollGroupModel::NON_WORKING_DATE_AFTER => 'Pay date after non working day',
                    PayrollGroupModel::NON_WORKING_DATE_BEFORE => 'Pay date before non working day',
                    PayrollGroupModel::NON_WORKING_DATE_DATE => 'Remain as schedule pay date',
                ])
                ->rules('required')
                ->onlyOnForms(),

            Text::make('Cut-off Date', function ($model) {
                if ($this->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                    return $this->ordinal($this->first_cut_off) . ' & ' . $this->ordinal($this->second_cut_off) . ' of the month';
                }
                if ($this->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                    return $this->ordinal($this->first_cut_off) . ' of the month';
                }

                return $model->first_name . ' ' . $model->last_name;
            }),

            Text::make('Pay Date', function ($model) {
                if ($this->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                    return $this->ordinal($this->first_pay_date) . ' & ' . $this->ordinal($this->second_pay_date) . ' of the month';
                }
                if ($this->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                    return $this->ordinal($this->first_pay_date) . ' of the month';
                }

                return $model->first_name . ' ' . $model->last_name;
            }),
        ];
    }

    protected function deductionsFields()
    {
        return [

            Select::make('SSS payment schedule', 'sss_schedule')
                ->rules('required')
                ->options(payment_schedules())
                ->hideFromIndex()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                // ->readonly()
                ->dependsOn(
                    ['frequency'],
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                            $field->readonly(true)->rules(['required']);
                            // $field->default('every_pay');
                            $field->value = PayrollGroupModel::PAY_SCHEDULE_EVERYPAY;
                        }
                    }
                )->displayUsingLabels(),

            Select::make('Philhealth payment schedule', 'phil_health_schedule')
                ->rules('required')
                ->options(payment_schedules())
                ->hideFromIndex()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                // ->readonly()
                ->dependsOn(
                    ['frequency'],
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                            $field->readonly(true)->rules(['required']);
                            // $field->default('every_pay');
                            $field->value = PayrollGroupModel::PAY_SCHEDULE_EVERYPAY;
                        }
                    }
                )->displayUsingLabels(),

            Select::make('HDMF payment schedule', 'hdmf_schedule')
                ->rules('required')
                ->options(payment_schedules())
                ->hideFromIndex()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                // ->readonly()
                ->dependsOn(
                    ['frequency'],
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                            $field->readonly(true)->rules(['required']);
                            // $field->default('every_pay');
                            $field->value = PayrollGroupModel::PAY_SCHEDULE_EVERYPAY;
                        }
                    }
                )->displayUsingLabels(),

            Text::make('Tax payment schedule', function () {
                return PayrollGroupModel::PAY_SCHEDULE_LASTPAY_TEXT;
            })
                ->hideFromIndex()
                ->showOnDetail(),
        ];
    }

    protected function attendanceFields()
    {
        return [
            Boolean::make('Computer Tardiness', 'compute_tardiness')
                ->showOnPreview()
                ->hideFromIndex()
                ->default(false),
            Boolean::make('Computer Overbreak', 'compute_overbreak')
                ->showOnPreview()
                ->hideFromIndex()
                ->default(false),
            Boolean::make('Computer Undertime', 'compute_undertime')
                ->showOnPreview()
                ->hideFromIndex()
                ->default(false),
        ];
    }

    public function ordinal($number)
    {
        $locale = 'en_US';
        $nf = new NumberFormatter($locale, NumberFormatter::ORDINAL);

        return $nf->format($number);
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
        $model->save();

        $model->updateData($model, $request);

        GeneratePayrollDateAction::run($model);
    }

    public static function afterUpdate(NovaRequest $request, Model $model)
    {
        if ($request->is_default == true) {
            $model->is_default = true;
            $model->save();

            PayrollGroupModel::query()
                ->where('company_id', $request->user()->company_id)
                ->where('id', '!=', $model->id)
                ->update(['is_default' => false]);
        }

        $model->updateData($model, $request);

        GeneratePayrollDateAction::run($model, true);
    }
}
