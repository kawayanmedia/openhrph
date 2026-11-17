<?php

namespace App\Nova;

use App\Models\Payroll as PayrollModel;
use App\Models\PayrollDate;
use App\Models\PayrollGroup as PayrollGroupModel;
use App\Nova\Actions\ProcessPayroll;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use SLASH2NL\NovaBackButton\NovaBackButton;

class PayRoll extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Payroll::class;

    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // Get the payroll by company id
        // return $query->where('company_id', $request->user()->company_id);
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
     * Indicates whether the resource should automatically poll for new resources.
     *
     * @var bool
     */
    // public static $polling = true;

    /**
     * The interval at which Nova should poll for new resources.
     *
     * @var int
     */
    // public static $pollingInterval = 5;

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
    public static $priority = 1;

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
    public static $with = ['payrollGroup.employees'];

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

    public function authorizedToReplicate(Request $request)
    {
        return false;
    }

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Payroll';

    public static function label()
    {
        return 'Payroll';
    }

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
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            // BelongsTo::make('PayrollGroup')
            //     ->withoutTrashed(),

            Text::make('Group', function () {
                return $this->payrollGroup?->name ?? '(Payroll Group Deleted)';
            }),

            Text::make('Pay Schedule', function () {
                if ($this->schedule == 1) {
                    return PayrollGroupModel::PAY_SCHEDULE_FIRSTPAY_TEXT;
                }

                if ($this->schedule == 2) {
                    return PayrollGroupModel::PAY_SCHEDULE_LASTPAY_TEXT;
                }

                return $this->schedule;
            })->hideFromIndex(),

            Text::make('Payroll Date', function () {
                return $this->payroll_date?->format('M d, Y');
            }),

            Text::make('Payroll Period', function () {
                if ($this->date_from == null) {
                    return;
                }

                return $this->date_from->format('M d, Y') . ' - ' . $this->date_to->format('M d, Y');
            }),

            Select::make('Group', 'payroll_group_id')
                ->options(
                    PayrollGroupModel::pluck('name', 'id')->toArray()
                )->sortable()
                ->default(function () {
                    return PayrollGroupModel::where('is_default', true)->first()->id;
                })
                ->rules('required')
                ->onlyOnForms(),

            Select::make('Payroll Date', 'payroll_date')
                ->sortable()
                ->rules('required')
                ->hideFromIndex()
                ->dependsOn(
                    ['payroll_group_id'],
                    function (Select $field, NovaRequest $request, FormData $formData) {
                        $payrollGroup = PayrollGroupModel::find($formData->payroll_group_id);

                        if ($payrollGroup) {
                            $field->options(monthly_periods($payrollGroup));
                        }
                    }
                )
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    unset($model->{$attribute});
                })
                ->onlyOnForms(),

            Text::make('No. of Employees', function () {
                return $this->payrollDetails?->count();
            }),

            Currency::make('Gross', function () {
                return $this->gross_amount;
            }),

            Currency::make('Net', function () {
                return $this->net_amount;
            }),

            // Badge::make('Status')->map([
            //     PayrollModel::STATUS_OPEN => 'warning',
            //     PayrollModel::STATUS_CLOSED => 'danger',
            //     PayrollModel::STATUS_RUNNING => 'success',
            //     PayrollModel::STATUS_PROCESSED => 'warning',
            // ]),

            Status::make('Status')
                ->loadingWhen(['waiting', PayrollModel::STATUS_RUNNING])
                ->failedWhen([PayrollModel::STATUS_CLOSED]),

            HasMany::make('Employees', 'PayrollDetails', 'App\Nova\PayrollDetail')->nullable()->readonly(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
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
        return [
            // (new ProcessPayroll)->showInline(),
            // (new ProcessPayroll)->standalone(), // can run even no selected row/resource
            (new ProcessPayroll())
                ->showInline()
                ->confirmText('Are you sure you want to process Payroll?')
                ->confirmButtonText('Yes')
                ->cancelButtonText('No'),
            // ->canRun(function ($request, $user) {
            //     // Show if payroll is open.
            //     return $this->model()->is_open;
            // }),

        ];
    }

    /**
     * Register a callback to be called after the resource is created.
     *
     * @return void
     */
    public static function afterCreate(NovaRequest $request, Model $payroll)
    {
        $payroll->company_id = $request->user()->company_id;
        $payroll->save();

        $schedule = substr($request->payroll_date, 11, 1); // Get 1 or 2

        $payrollDateId = substr($request->payroll_date, 13); // Get payroll date id

        $payrollDate = PayrollDate::find($payrollDateId);

        // Check if monthly pay
        $payrollGroup = PayrollGroupModel::find($request->payroll_group_id);

        if ($payrollGroup->frequency == PayrollGroupModel::FREQUENCY_MONTHLY) {

            $payrollDateId = substr($request->payroll_date, 11); // Get payroll date id

            $payrollDate = PayrollDate::find($payrollDateId);

            $schedule = null;
        }

        if ($payrollDate) {
            $payroll->date_from = $payrollDate->attendance_date_start;
            $payroll->date_to = $payrollDate->attendance_date_end;
            $payroll->payroll_date = $payrollDate->payroll_date;
            $payroll->save();
        }

        if ($schedule != null) {
            $payroll->schedule = $schedule;
            if ($schedule == 1) {
                $payroll->cut_off = PayrollGroupModel::PAY_SCHEDULE_FIRSTPAY;
            }
            if ($schedule == 2) {
                $payroll->cut_off = PayrollGroupModel::PAY_SCHEDULE_LASTPAY;
            }
            $payroll->save();
        }

        // Process the payroll here
        // We may need to queue the processing of payroll
        $payrollGroup = PayrollGroupModel::find($payroll->payroll_group_id);

        if ($payrollGroup->frequency == PayrollGroupModel::FREQUENCY_MONTHLY) {
            $payroll->cut_off = null;
            $payroll->save();
        }
    }

    public static function afterUpdate(NovaRequest $request, Model $payroll)
    {
        $payroll->company_id = $request->user()->company_id;
        $payroll->save();

        $schedule = substr($request->payroll_date, 11, 1); // Get 1 or 2

        $payrollDateId = substr($request->payroll_date, 13); // Get payroll date id

        $payrollDate = PayrollDate::find($payrollDateId);

        // Check if monthly pay
        $payrollGroup = PayrollGroupModel::find($request->payroll_group_id);

        if ($payrollGroup->frequency == PayrollGroupModel::FREQUENCY_MONTHLY) {

            $payrollDateId = substr($request->payroll_date, 11); // Get payroll date id

            $payrollDate = PayrollDate::find($payrollDateId);

            $schedule = null;
        }

        if ($payrollDate) {
            $payroll->date_from = $payrollDate->attendance_date_start;
            $payroll->date_to = $payrollDate->attendance_date_end;
            $payroll->payroll_date = $payrollDate->payroll_date;
            $payroll->save();
        }

        if ($schedule != null) {
            $payroll->schedule = $schedule;
            if ($schedule == 1) {
                $payroll->cut_off = PayrollGroupModel::PAY_SCHEDULE_FIRSTPAY;
            }
            if ($schedule == 2) {
                $payroll->cut_off = PayrollGroupModel::PAY_SCHEDULE_LASTPAY;
            }
            $payroll->save();
        }

        // Process the payroll here
        // We may need to queue the processing of payroll
        $payrollGroup = PayrollGroupModel::find($payroll->payroll_group_id);

        if ($payrollGroup->frequency == PayrollGroupModel::FREQUENCY_MONTHLY) {
            $payroll->cut_off = null;
            $payroll->save();
        }
    }
}
