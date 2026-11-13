<?php

namespace App\Nova;

use App\Models\Employee as EmployeeModel;
use App\Models\Schedule;
use App\Models\User;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
use Eminiarts\Tabs\Tab;
use Eminiarts\Tabs\Tabs;
use Eminiarts\Tabs\Traits\HasTabs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Laravel\Nova\Actions\ExportAsCsv;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Line;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Stack;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use SLASH2NL\NovaBackButton\NovaBackButton;

class Employee extends Resource
{
    // use HasTabs;

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Employee::class;

    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // Get the groups by company id
        // return $query->where('company_id', $request->user()->company_id);
    }

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
        return $this->last_name . ', ' . $this->first_name;
    }

    /**
     * Get the search result subtitle for the resource.
     *
     * @return string
     */
    public function subtitle()
    {
        return $this->office->name;
    }

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * @var array
     */
    public static $with = ['office', 'department', 'office', 'allowances'];

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
        'last_name',
        'first_name',
        'middle_name',
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

            Text::make('Employee Number')
                // ->default(generate_employee_number())
                ->rules(
                    'required',
                    Rule::unique('employees', 'employee_number')
                        ->where(fn(Builder $query) => $query->where('company_id', $request->user()->company_id))
                        ->ignore($this->id)
                )
                ->sortable(),

            Text::make('Full Name', function () {

                return $this->last_name . ', ' . $this->first_name;
            })->sortable(),

            Text::make('Last Name')
                ->rules('required')
                ->onlyOnForms()
                ->sortable(),

            Text::make('First Name')
                ->rules('required')
                ->onlyOnForms()
                ->sortable(),

            Text::make('Middle Name')
                //->rules('required')
                ->sortable()->hideFromIndex(),

            // Select::make('Office', 'office_id')->options(
            //     Office::orderBy('name')->pluck('name', 'id')
            // )->sortable()
            // ->rules('required')
            // ->hideFromIndex()
            // ->onlyOnForms(),

            BelongsTo::make('Office')
                ->rules('required')
                // ->searchable()
                ->withoutTrashed()
                ->hideFromIndex()
                ->help('Click + button on the right to add Office')
                ->showCreateRelationButton()
                ->onlyOnForms(),

            BelongsTo::make('Department')
                ->rules('required')
                // ->searchable()
                ->filterable()
                ->withoutTrashed()
                ->showCreateRelationButton()
                ->help('Click + button on the right to add Department')
                ->hideFromIndex()
                ->onlyOnForms(),

            Text::make('Position')
                //->rules('required')
                ->hideFromIndex(),

            BelongsTo::make('Immediate Head', 'ImmediateHead')
                // ->rules('required')
                ->searchable()
                ->withoutTrashed()
                ->hideFromIndex()
                ->nullable()
                ->onlyOnForms(),

            BelongsTo::make('Employment Type', 'EmploymentType')
                ->rules('required')
                // ->searchable()
                ->filterable()
                ->withoutTrashed()
                ->hideFromIndex()
                ->onlyOnForms(),

            Date::make('Date hired', 'date_hired'),

            Date::make('Date Resigned', 'date_resigned')
                ->hide()
                ->hideFromIndex()
                ->dependsOn(
                    ['EmploymentType'],
                    function (Date $field, NovaRequest $request, FormData $formData) {

                        // 16 is resigned, 17 terminated
                        if ($formData->EmploymentType == 16 or $formData->EmploymentType == 17) {
                            $field->show();
                        }
                    }
                ),

            Text::make('Other Remarks')
                ->hide()
                ->dependsOn(
                    ['EmploymentType'],
                    function (Text $field, NovaRequest $request, FormData $formData) {

                        // 16 is resigned, 17 terminated
                        if ($formData->EmploymentType == 16 or $formData->EmploymentType == 17) {
                            $field->show();
                        }
                    }
                )
                ->hideFromIndex(),

            Text::make('Biometric ID', 'biometric_id')
                // ->rules('required')
                ->help('The ID used in biometric machine if any')
                ->onlyOnForms()
                ->sortable(),

            // Files::make('Single file', 'one_file'),
            Files::make('Resume/Requirements', 'resume_requirements')
                ->customPropertiesFields([
                    // Boolean::make('Active'),
                    Text::make('Description'),
                ]),

            // Text::make('Office', function () {
            //     return $this->office?->name;
            // }),

            Stack::make('Office', [
                Line::make('Office', function () {
                    return $this->office?->name;
                })
                    ->asHeading(),

                Line::make('Department', function () {
                    return $this->department?->name;
                })
                    ->asHeading(),
            ]),

            Text::make('Department', function () {
                return $this->department?->name;
            })->hideFromIndex(),

            Text::make('Employment Status', function () {
                return $this->employment_status_format;
            })->hideFromIndex(),

            // new Panel('Payroll', $this->earningData()),

            Tabs::make('Some Title', [
                Tab::make('Payroll', $this->earningData()),

                Tab::make('Leave', $this->leaveData()),

                Tab::make('Portal Login', $this->loginData()),

                HasMany::make('Compensations', 'Compensations', 'App\Nova\EmployeeCompensation')->nullable()->readonly(),
                // HasMany::make('Allowances', 'Allowances', 'App\Nova\EmployeeAllowance')->nullable()->readonly(),
                // HasMany::make('Commissions', 'commissions', 'App\Nova\EmployeeCommission')->nullable()->readonly(),
                // HasMany::make('Bonus', 'Bonuses', 'App\Nova\EmployeeBonus')->nullable()->readonly(),
                HasMany::make('Deductions', 'Deductions', 'App\Nova\EmployeeDeduction')->nullable()->readonly(),
                HasMany::make('Loans', 'Loans', 'App\Nova\EmployeeLoan')->nullable()->readonly(),
                // HasMany::make('Progression', 'Loans', 'App\Nova\Employee\EmployeeProgression')->nullable()->readonly(),
                // HasMany::make('Certificates', 'Certificates', 'App\Nova\EmployeeCertificate')->nullable()->readonly(),
                // HasMany::make('Trainings', 'Trainings', 'App\Nova\EmployeeTraining')->nullable()->readonly(),
            ]),

            Tabs::make('Some Title1', [
                // HasMany::make('Progression', 'Loans', 'App\Nova\Employee\EmployeeProgression')->nullable()->readonly(),
                HasMany::make('Certificates', 'Certificates', 'App\Nova\EmployeeCertificate')->nullable()->readonly(),
                HasMany::make('Trainings', 'Trainings', 'App\Nova\EmployeeTraining')->nullable()->readonly(),
            ]),

        ];
        // https://github.com/eminiarts/nova-tabs/pull/182/files
        // return [

        //     (new Tabs($this->fullname . ' Details', [
        //         'Basic'    => [
        //             ID::make()->sortable(),
        //             Text::make('Employee Number')
        //                 ->rules('required')
        //                 ->sortable(),
        //             Text::make('Last Name')
        //                 ->rules('required')
        //                 // ->help(
        //                 //     'The tax rate to be applied to the sale'
        //                 // )
        //                 ->sortable(),

        //             Text::make('First Name')
        //                 ->rules('required')
        //                 ->sortable(),

        //             Text::make('Middle Name')
        //                 //->rules('required')
        //                 ->sortable(),

        //             // Select::make('Office', 'office_id')->options(
        //             //     Office::orderBy('name')->pluck('name', 'id')
        //             // )->sortable()
        //             // ->rules('required')
        //             // ->hideFromIndex()
        //             // ->onlyOnForms(),

        //             BelongsTo::make('Office')
        //                 ->rules('required')
        //                 // ->searchable()
        //                 ->hideFromIndex()
        //                 ->onlyOnForms(),

        //             BelongsTo::make('Department')
        //                 ->rules('required')
        //                 // ->searchable()
        //                 ->hideFromIndex()
        //                 ->onlyOnForms(),

        //             // BelongsTo::make('Office')
        //             //     ->rules('required')
        //             //     // ->searchable()
        //             //     ->hideFromIndex()
        //             //     ->onlyOnForms(),

        //             Select::make('Employment Status')
        //             ->options(
        //                 $this->statuses()
        //             )->sortable()
        //             ->rules('required')
        //             ->hideFromIndex()
        //             ->onlyOnForms(),

        //             // Text::make('Office', function () {
        //             //     return $this->office?->name;
        //             // }),

        //             Text::make('Office', function () {
        //                 return $this->office?->name;
        //             }),

        //             Text::make('Department', function () {
        //                 return $this->department?->name;
        //             }),

        //             Text::make('Employment Status', function () {
        //                 return $this->employment_status_format;
        //             }),
        //         ],

        //         // 'Personal Data' => [
        //         //     new Panel('Personal Data', $this->personalData()),
        //         // ],

        //         // 'Service Records' => [
        //         //     new Panel('Service Recods', $this->serviceRecods()),
        //         // ],

        //         // 'Trainings' => [
        //         //     new Panel('Trainings', $this->trainings()),
        //         // ],

        //     ]))->withToolbar(),

        //     // Other Data
        //     // PDS
        //     // new Panel('Personal Data', $this->personalData()),

        //     // Service Records
        //     // new Panel('Service Recods', $this->serviceRecods()),

        //     // // Trainings
        //     // new Panel('Trainings', $this->trainings()),

        // ];
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
        return [
            new Filters\EmploymentStatus,
            new Filters\OfficeFilter,
        ];
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
        return [
            // new Actions\AssignEmployeeSchedule,
            // (new Actions\AssignEmployeeSchedule($this->model()))->onlyOnTableRow(),
            (new Actions\AssignEmployeeSchedule($this->model()))->onlyOnDetail(),
            // new Actions\AssignEmployeeBonus,
            // new Actions\AssignEmployeeCompensations,
            (new Actions\ImportEmployees)->onlyOnIndexToolbar(),
            ExportAsCsv::make()->onlyOnIndex(),
        ];
    }

    /**
     * Register a callback to be called after the resource is created.
     *
     * @return void
     */
    public static function afterCreate(NovaRequest $request, Model $model)
    {
        $model->schedule_id = Schedule::where('is_default', true)->first()?->id;
        $model->save();

        if ($request->login_email != null) {
            // Create login credentials
            $user = User::firstOrNew(['employee_id' => $model->id]);
            $user->first_name = $model->first_name;
            $user->last_name = $model->last_name;
            $user->email = $request->login_email;
            $user->password = bcrypt($request->login_password);
            $user->save();
        }

        // // Also we should include the sss, philhealth and HDMF since we
        // // based those values in basic salary.
        // $model->company_id = $request->user()->company_id;
        // $model->save();
    }

    /**
     * 
     *
     * @return void
     */
    public static function afterUpdate(NovaRequest $request, Model $model)
    {
        if ($request->login_email != null) {
            // Create login credentials
            $user = User::firstOrNew(['employee_id' => $model->id]);
            $user->first_name = $model->first_name;
            $user->last_name = $model->last_name;
            $user->email = $request->login_email;
            $user->password = bcrypt($request->login_password);
            $user->save();
        }
    }

    /**
     * @return array
     */
    protected function earningData()
    {
        return [

            Select::make('Payment Type', 'payment_type')
                ->options(payment_types())
                ->sortable()
                ->default(EmployeeModel::PAYMENT_TYPE_MONTHLY)
                ->rules('required')
                ->displayUsingLabels()
                ->hideFromIndex(),

            Currency::make('Basic Salary', 'basic_salary')
                ->dependsOn(
                    ['payment_type'],
                    function (Currency $field, NovaRequest $request, FormData $formData) {

                        $field->name = 'Daily Rate';
                        // $field->name = 'Monthly Basic Salary';
                        // If non taxable then enable the field. Pretty good right?!
                        if ($formData->payment_type === EmployeeModel::PAYMENT_TYPE_MONTHLY) {
                            // $field->readonly(false)->rules(['required']);
                            // $field->default(25);
                            $field->name = 'Monthly Basic Salary';
                        }
                    }
                )
                ->hideFromIndex(),

            Currency::make('Daily Rate', 'daily_rate_format')
                ->onlyOnDetail(),

            Boolean::make('Monday to Friday only?', 'is_monday_friday')
                ->default(true)
                ->withMeta(['value' => $this->is_monday_friday ?? true])
                ->help('Check if Monday to Friday. Uncheck if weekends are included.')
                ->showOnDetail($this->is_monday_friday)
                ->hideFromIndex(),

            Boolean::make('Minimum wage', 'is_minimum_wage')
                ->withMeta(['value' => $this->is_minimum_wage ?? false])
                ->showOnDetail($this->is_minimum_wage)
                ->hideFromIndex(),

            BelongsTo::make('PayrollGroup')
                ->filterable()
                ->showCreateRelationButton()
                ->help('Click + button on the right to add Payroll Group')
                ->withoutTrashed(),

            Heading::make('Government'),

            Text::make('TIN Number', 'tin_number')
                ->withMeta([
                    'extraAttributes' => [
                        'placeholder' => '000-000-000-000',
                    ],
                ])
                ->hideFromIndex(),

            Text::make('SSS Number', 'sss_number')
                ->withMeta([
                    'extraAttributes' => [
                        'placeholder' => '00-0000000-0',
                    ],
                ])
                ->hideFromIndex(),
            Text::make('PhilHealth Number', 'philhealth_number')
                ->withMeta([
                    'extraAttributes' => [
                        'placeholder' => '00-000000000-0',
                    ],
                ])
                ->hideFromIndex(),
            Text::make('HDMF', 'hdmf_number')
                ->withMeta([
                    'extraAttributes' => [
                        'placeholder' => '0000-0000-0000',
                    ],
                ])
                ->hideFromIndex(),

            Heading::make('Benefits'),

            Boolean::make('Timesheet required', 'timesheet')->withMeta(['value' => $this->timesheet ?? true])->hideFromIndex(),
            Boolean::make('Regular Holiday Pay', 'regular_holiday_pay')->withMeta(['value' => $this->regular_holiday_pay ?? true])->hideFromIndex(),
            Boolean::make('Special Holiday Pay', 'special_holiday_pay')->withMeta(['value' => $this->special_holiday_pay ?? true])->hideFromIndex(),
            Boolean::make('Premium Holiday Pay (Double Regular Holiday)', 'premium_holiday_pay')->withMeta(['value' => $this->premium_holiday_pay ?? true])->hideFromIndex(),
            Boolean::make('Regular Holiday, Special Holiday Pay', 'regular_holiday_special_holiday_pay')->withMeta(['value' => $this->regular_holiday_special_holiday_pay ?? true])->hideFromIndex(),
            Boolean::make('Rest Day Pay', 'rest_day_pay')->withMeta(['value' => $this->rest_day_pay ?? true])->hideFromIndex(),
            Boolean::make('Overtime', 'overtime_pay')->withMeta(['value' => $this->overtime_pay ?? true])->hideFromIndex(),
            Boolean::make('De Minimis', 'deminimis')->withMeta(['value' => $this->deminimis ?? true])->hideFromIndex(),
            Boolean::make('Night Differential', 'night_differential')->withMeta(['value' => $this->night_differential ?? true])->hideFromIndex(),
        ];
    }

    /**
     * @return array
     */
    protected function leaveData()
    {
        return [

            Text::make('Total vacation leave', 'total_vacation_leave')
                ->withMeta([
                    'extraAttributes' => [
                        'type' => 'number',
                    ],
                ])
                ->hideFromIndex(),

            Text::make('Total sick leave', 'total_sick_leave')
                ->withMeta([
                    'extraAttributes' => [
                        'type' => 'number',
                    ],
                ])
                ->hideFromIndex(),

            Text::make('Total personal leave', 'total_personal_leave')
                ->withMeta([
                    'extraAttributes' => [
                        'type' => 'number',
                    ],
                ])
                ->hideFromIndex(),
        ];
    }

    /**
     * @return array
     */
    protected function loginData()
    {
        return [

            Text::make('Email', 'login_email')
                ->withMeta([
                    'extraAttributes' => [
                        'type' => 'email',
                    ],
                ])
                ->hideFromIndex()
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    unset($model->{$attribute});
                }),

            Text::make('Password', 'login_password')
                ->withMeta([
                    'extraAttributes' => [
                        'type' => 'password',
                    ],
                ])
                ->hideFromIndex()
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    unset($model->{$attribute});
                }),
        ];
    }

    /**
     * @return array
     */
    protected function allowanceData()
    {
        return [
            Currency::make('Basic Pay'),
        ];
    }

    /**
     * @return array
     */
    protected function personalData()
    {
        return [
            // Text::make('PDS')->onlyOnDetail(),
            Text::make('Employee Number')
                ->rules('required')
                ->sortable(),
            Text::make('Last Name')
                ->rules('required')
                // ->help(
                //     'The tax rate to be applied to the sale'
                // )
                ->sortable(),

            Text::make('First Name')
                ->rules('required')
                ->sortable(),

            Text::make('Middle Name')
                ->hideFromIndex()
                //->rules('required')
                ->sortable(),

        ];
    }

    /**
     * @return array
     */
    protected function serviceRecods()
    {
        return [
            Text::make('serviceRecods')->onlyOnDetail(),
        ];
    }

    /**
     * @return array
     */
    protected function trainings()
    {
        return [
            Text::make('Trainings')->onlyOnDetail(),
        ];
    }
}
