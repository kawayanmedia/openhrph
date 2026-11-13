<?php

namespace App\Nova;

use App\Models\PayrollGroup as PayrollGroupModel;
use App\Nova\Filters\PayrollDetailFieldsFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Nova\Actions\ExportAsCsv;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use NumberFormatter;

class PayrollDetail extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\PayrollDetail::class;

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
     * The number of resources to show per page via relationships.
     *
     * @var int
     */
    public static $perPageViaRelationship = 100;

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
    public static $with = ['employee'];

    // public static function authorizedToSearch(Request $request)
    // {
    //     return false;
    // }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    public function authorizedToUpdate(Request $request)
    {
        return false;
    }

    public function authorizedToDelete(Request $request)
    {
        return false;
    }

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
        return 'Payroll Details';
    }

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
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $filterFields = filter_fields($request, '\PayrollDetailFieldsFilter');

        return [
            // ID::make()->sortable(),
            Text::make('', function () {
                return "<a class='flex-shrink-0 shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm' href='" . route('payslip.show', $this->id) . "' target='_new'>Pay Slip</a>";
            })
                ->onlyOnDetail()
                ->asHtml(),

            Text::make('Employee Number', function () {
                return $this->employee?->employee_number ?? 'Deleted';
            })->textAlign('right'),

            BelongsTo::make('Employee', 'Employee', 'App\Nova\Employee'),

            // Text::make('Employee', function () {
            //     return $this->employee->last_name . ', ' . $this->employee->first_name;
            // })->hideWhenCreating(),

            Heading::make('income'),

            Currency::make('Monthly / Daily Basic', function () {
                return $this->basic_monthly_salary;
            })
                // ->hideFromIndex($this->employee?->payment_type == Employee::PAYMENT_TYPE_DAILY)
                // ->hideFromDetail($this->employee?->payment_type == Employee::PAYMENT_TYPE_DAILY)
                ->symbol(currency_symbol())
                ->textAlign('right'),

            Currency::make('Daily Basic', function () {
                return $this->employee?->daily_rate_format;
            })
                ->symbol(currency_symbol())
                // ->onlyOnDetail()
                // ->hideFromDetail($this->employee?->payment_type == Employee::PAYMENT_TYPE_MONTHLY)
                ->textAlign('right'),

            Currency::make('Regular Pay', function () {
                return $this->basic_salary;
            })
                ->symbol(currency_symbol())
                ->textAlign('right'),

            Currency::make('Representation Allowance', function () {
                return $this->representation_allowance;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->representation_allowance))
                ->textAlign('right'),

            Currency::make('Transportation Allowance', function () {
                return $this->transportation_allowance;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->transportation_allowance))
                ->textAlign('right'),

            Currency::make('Cost of Living Allowance', function () {
                return $this->cola;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->cola))
                ->textAlign('right'),

            Currency::make('Fixed Housing Allowance', function () {
                return $this->fixed_housing_allowance;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->fixed_housing_allowance))
                ->textAlign('right'),

            Currency::make('Other Taxable Regular Compensation', function () {
                return $this->other_taxable_regular;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->other_taxable_regular))
                ->textAlign('right'),

            Currency::make('Commission', function () {
                return $this->commission;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->commission))
                ->textAlign('right'),

            Currency::make('Profit Sharing', function () {
                return $this->profit_sharing;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->profit_sharing))
                ->textAlign('right'),

            Currency::make('Fees', function () {
                return $this->fees;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->fees))
                ->textAlign('right'),

            Currency::make('Hazard Pay', function () {
                return $this->hazard_pay;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->hazard_pay))
                ->textAlign('right'),

            Currency::make('Other Taxable Supplementary', function () {
                return $this->other_taxable_supplementary;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->other_taxable_supplementary))
                ->textAlign('right'),

            Currency::make('Overtime Pay', function () {
                return $this->overtime_pay;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->overtime_pay))
                ->textAlign('right'),

            Currency::make('Night Differential Pay', function () {
                return $this->night_pay;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->night_pay))
                ->textAlign('right'),

            Currency::make('Rest Day Pay', function () {
                return $this->rest_day_pay;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->rest_day_pay))
                ->textAlign('right'),

            Currency::make('Holiday Pay', function () {
                return $this->holiday_pay;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->hideFromDetail(is_empty($this->holiday_pay))
                ->textAlign('right'),

            Currency::make('Other Compensations', function () {
                return $this->compensations_format;
            })
                ->symbol(currency_symbol())
                ->textAlign('right'),

            Currency::make('Gross', function () {
                return $this->gross_income;
            })
                ->symbol(currency_symbol())
                ->textAlign('right'),

            Currency::make('Mandatory Deductions', function () {
                return $this->total_employee;
            })
                ->onlyOnIndex()
                ->symbol(currency_symbol())
                ->textAlign('right'),

            Heading::make('deductions'),

            Currency::make('SSS', function () {
                return $this->sss_employee + $this->sss_provident_employee;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->textAlign('right'),

            Currency::make('Philhealth', function () {
                return $this->philhealth_employee;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->textAlign('right'),

            Currency::make('HDMF', function () {
                return $this->hdmf_employee;
            })
                ->symbol(currency_symbol())
                ->onlyOnDetail()
                ->textAlign('right'),

            Text::make('Absent', function () {
                return number_format($this->absent_days_amount, 2) . '(' . $this->absent_days . ' x ' . $this->employee?->daily_rate . ')';
            })
                ->onlyOnDetail()
                ->hideFromDetail($this->absent_days == null or $this->absent_days == 0)
                ->textAlign('right'),

            Currency::make('Taxable Income', function () {
                return $this->taxable_income;
            })
                ->symbol(currency_symbol())
                ->textAlign('right'),

            Currency::make('Withholding tax', function () {
                return $this->withholding_tax;
            })
                ->symbol(currency_symbol())
                ->textAlign('right'),

            Currency::make('Total Deductions', function () {
                return $this->total_deductions;
            })
                ->symbol(currency_symbol())
                ->textAlign('right'),

            Heading::make('net'),

            Currency::make('Net Pay', function () {
                return $this->net_pay;
            })
                ->symbol(currency_symbol())
                // ->showOnIndex(show_on_index($filterFields, 'net_pay'))
                ->textAlign('right'),

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
        return [
            new PayrollDetailFieldsFilter,
        ];
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
            ExportAsCsv::make()->withFormat(function ($model) {
                return [
                    // 'ID' => $model->getKey(),

                    'Employee' => $model->employee->fullname,
                    'Position' => $model->employee->position,
                    'Salary' => $model->employee->basic_salary,

                    'Night Differential' => $model->night_pay,
                    'Overtime Pay' => $model->overtime_pay,
                    'Rest Day Pay' => $model->rest_day_pay,
                    'Total Earnings' => $model->gross_income,

                    'SSS' => $model->sss_employee_total,
                    'PhilHealth' => $model->philhealth_employee,
                    'HDMF' => $model->hdmf_employee,

                    'Late' => $model->late ?? 0,
                    'Absent' => $model->absent_days_amount,
                    'Total Deductions' => $model->total_deductions,
                    'Net Pay' => $model->net_pay,
                ];
            })
                ->onlyOnIndex(),
        ];
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
        if ($request->is_default == true) {
            $model->is_default = true;
            $model->save();

            PayrollGroupModel::query()
                ->where('company_id', $request->user()->company_id)
                ->where('id', '!=', $model->id)
                ->update(['is_default' => false]);
        }
    }
}
