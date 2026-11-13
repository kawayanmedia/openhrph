<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\AssignEmployeeSchedule;
use App\Livewire\Admin\Actions\ExportCsv;
use App\Livewire\Admin\Actions\ImportEmployees;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Field;
use App\Livewire\Admin\Fields\Files;
use App\Livewire\Admin\Fields\HasMany;
use App\Livewire\Admin\Fields\Heading;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Filters\EmploymentStatus;
use App\Livewire\Admin\Filters\OfficeFilter;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use App\Models\Department;
use App\Models\Employee as EmployeeModel;
use App\Models\EmploymentType;
use App\Models\Office;
use App\Models\PayrollGroup;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class Employee extends Resource
{
    public static string $model = EmployeeModel::class;

    public static string $title = 'id';

    public static array $search = [
        'id',
        'last_name',
        'first_name',
        'middle_name',
    ];

    public static array $with = ['office', 'department', 'allowances'];

    public static string $group = 'Admin';

    public function title(): string
    {
        if (! $this->resource) {
            return '';
        }

        return $this->resource->last_name.', '.$this->resource->first_name;
    }

    public function fields(Request $request): array
    {
        return array_merge([
            ID::make()->sortable(),

            Text::make('Employee Number')
                ->rules(
                    'required',
                    Rule::unique('employees', 'employee_number')
                        ->where(fn (QueryBuilder $query) => $query->where('company_id', $request->user()->company_id))
                        ->ignore($this->resource?->getKey())
                )
                ->sortable(),

            Text::make('Full Name', function () {
                return $this->last_name.', '.$this->first_name;
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
                ->sortable()
                ->hideFromIndex(),

            BelongsTo::make('Office')
                ->rules('required')
                ->withoutTrashed()
                ->hideFromIndex()
                ->help('Click + button on the right to add Office')
                ->showCreateRelationButton()
                ->onlyOnForms()
                ->options(Office::orderBy('name')->pluck('name', 'id')->toArray()),

            Select::make('Region override', 'region')
                ->options(region_options())
                ->nullable()
                ->hideFromIndex()
                ->onlyOnForms()
                ->help('Leave blank to use the office region.'),

            Select::make('Industry override', 'industry')
                ->options(industry_options())
                ->nullable()
                ->hideFromIndex()
                ->onlyOnForms()
                ->help('Leave blank to use the office industry.'),

            BelongsTo::make('Department')
                ->rules('required')
                ->filterable()
                ->withoutTrashed()
                ->showCreateRelationButton()
                ->help('Click + button on the right to add Department')
                ->hideFromIndex()
                ->onlyOnForms()
                ->options(Department::orderBy('name')->pluck('name', 'id')->toArray()),

            Text::make('Position')
                ->hideFromIndex(),

            BelongsTo::make('Immediate Head', 'ImmediateHead')
                ->searchable()
                ->withoutTrashed()
                ->hideFromIndex()
                ->nullable()
                ->onlyOnForms()
                ->options(EmployeeModel::employeeList()),

            BelongsTo::make('Employment Type', 'EmploymentType')
                ->rules('required')
                ->filterable()
                ->withoutTrashed()
                ->hideFromIndex()
                ->onlyOnForms()
                ->options(EmploymentType::orderBy('name')->pluck('name', 'id')->toArray()),

            Date::make('Date hired', 'date_hired'),

            Date::make('Date Resigned', 'date_resigned')
                ->hide()
                ->hideFromIndex()
                ->dependsOn(['EmploymentType'], function (Date $field, Request $request, FormData $formData) {
                    // 16 is resigned, 17 terminated
                    if ($formData->EmploymentType == 16 || $formData->EmploymentType == 17) {
                        $field->show();
                    }
                }),

            Text::make('Other Remarks')
                ->hide()
                ->hideFromIndex()
                ->dependsOn(['EmploymentType'], function (Text $field, Request $request, FormData $formData) {
                    // 16 is resigned, 17 terminated
                    if ($formData->EmploymentType == 16 || $formData->EmploymentType == 17) {
                        $field->show();
                    }
                }),

            Text::make('Biometric ID', 'biometric_id')
                ->help('The ID used in biometric machine if any')
                ->onlyOnForms()
                ->sortable(),

            Files::make('Resume/Requirements', 'resume_requirements')
                ->customPropertiesFields([
                    Text::make('Description'),
                ]),

            // NOTE: Nova used a Stack of Line fields for the index Office/Department
            // column. The engine's Stack::make() has an incompatible signature with
            // Field::make() (pre-existing bug), so plain computed Text fields are used.
            Text::make('Office', function () {
                return $this->office?->name;
            }),

            Text::make('Department', function () {
                return $this->department?->name;
            })->hideFromIndex(),

            Text::make('Employment Status', function () {
                return $this->employment_status_format;
            })->hideFromIndex(),
        ], [
            Heading::make('Payroll'),
        ], $this->earningData(), [
            Heading::make('Leave'),
        ], $this->leaveData(), [
            Heading::make('Portal Login'),
        ], $this->loginData(), [
            Heading::make('Related Records'),

            HasMany::make('Compensations', 'Compensations')->nullable()->readonly(),
            HasMany::make('Deductions', 'Deductions')->nullable()->readonly(),
            HasMany::make('Loans', 'Loans')->nullable()->readonly(),
            HasMany::make('Certificates', 'Certificates')->nullable()->readonly(),
            HasMany::make('Trainings', 'Trainings')->nullable()->readonly(),
        ]);
    }

    protected function earningData(): array
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
                ->hideFromIndex()
                ->currency(currency_symbol())
                ->dependsOn(['payment_type'], function (Currency $field, Request $request, FormData $formData) {
                    $field->name = 'Daily Rate';

                    if ($formData->payment_type === EmployeeModel::PAYMENT_TYPE_MONTHLY) {
                        $field->name = 'Monthly Basic Salary';
                    }
                }),

            Currency::make('Daily Rate', 'daily_rate_format')
                ->onlyOnDetail()
                ->currency(currency_symbol()),

            Boolean::make('Monday to Friday only?', 'is_monday_friday')
                ->default(true)
                ->withMeta(['value' => $this->is_monday_friday ?? true])
                ->help('Check if Monday to Friday. Uncheck if weekends are included.')
                ->hideFromIndex(),

            Boolean::make('Minimum wage', 'is_minimum_wage')
                ->withMeta(['value' => $this->is_minimum_wage ?? false])
                ->hideFromIndex(),

            BelongsTo::make('PayrollGroup')
                ->filterable()
                ->showCreateRelationButton()
                ->help('Click + button on the right to add Payroll Group')
                ->withoutTrashed()
                ->options(PayrollGroup::orderBy('name')->pluck('name', 'id')->toArray()),

            Heading::make('Government'),

            Text::make('TIN Number', 'tin_number')
                ->rules('nullable', 'regex:/^\d{3}-?\d{3}-?\d{3}(-?\d{3,5})?$/')
                ->withMeta([
                    'extraAttributes' => [
                        'placeholder' => '000-000-000-000',
                    ],
                ])
                ->hideFromIndex(),

            Text::make('SSS Number', 'sss_number')
                ->rules('nullable', 'regex:/^\d{2}-?\d{7}-?\d$/')
                ->withMeta([
                    'extraAttributes' => [
                        'placeholder' => '00-0000000-0',
                    ],
                ])
                ->hideFromIndex(),

            Text::make('PhilHealth Number', 'philhealth_number')
                ->rules('nullable', 'regex:/^\d{2}-?\d{9}-?\d$/')
                ->withMeta([
                    'extraAttributes' => [
                        'placeholder' => '00-000000000-0',
                    ],
                ])
                ->hideFromIndex(),

            Text::make('HDMF', 'hdmf_number')
                ->rules('nullable', 'regex:/^\d{4}-?\d{4}-?\d{4}$/')
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

    protected function leaveData(): array
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

    protected function loginData(): array
    {
        return [
            Text::make('Email', 'login_email')
                ->withMeta([
                    'extraAttributes' => [
                        'type' => 'email',
                    ],
                ])
                ->hideFromIndex()
                ->fillUsing(function ($model, $attribute) {
                    unset($model->{$attribute});
                }),

            Text::make('Password', 'login_password')
                ->withMeta([
                    'extraAttributes' => [
                        'type' => 'password',
                    ],
                ])
                ->hideFromIndex()
                ->fillUsing(function ($model, $attribute) {
                    unset($model->{$attribute});
                }),
        ];
    }

    public function detailFields(Request $request): array
    {
        return array_values(array_filter(parent::detailFields($request), function (Field $field) {
            if ($field->attribute === 'is_monday_friday') {
                return (bool) $this->resource?->is_monday_friday;
            }

            if ($field->attribute === 'is_minimum_wage') {
                return (bool) $this->resource?->is_minimum_wage;
            }

            return true;
        }));
    }

    public function cards(Request $request): array
    {
        // NovaBackButton is not applicable to the Livewire admin engine.
        return [];
    }

    public function filters(Request $request): array
    {
        return [
            new EmploymentStatus,
            new OfficeFilter,
        ];
    }

    public function lenses(Request $request): array
    {
        return [];
    }

    public function actions(Request $request): array
    {
        return [
            (new AssignEmployeeSchedule($this->resource))->onlyOnDetail(),
            (new ImportEmployees)->onlyOnIndexToolbar(),
            ExportCsv::make()->onlyOnIndex(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->schedule_id = Schedule::where('is_default', true)->first()?->id;
        $model->save();

        if ($request->login_email != null) {
            $user = User::firstOrNew(['employee_id' => $model->id]);
            $user->first_name = $model->first_name;
            $user->last_name = $model->last_name;
            $user->email = $request->login_email;
            $user->password = bcrypt($request->login_password);
            $user->save();
        }
    }

    public static function afterUpdate(Request $request, Model $model): void
    {
        if ($request->login_email != null) {
            $user = User::firstOrNew(['employee_id' => $model->id]);
            $user->first_name = $model->first_name;
            $user->last_name = $model->last_name;
            $user->email = $request->login_email;
            $user->password = bcrypt($request->login_password);
            $user->save();
        }
    }
}
