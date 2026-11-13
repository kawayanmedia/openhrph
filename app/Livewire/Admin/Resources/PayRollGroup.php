<?php

namespace App\Livewire\Admin\Resources;

use App\Actions\GeneratePayrollDateAction;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\HasMany;
use App\Livewire\Admin\Fields\Heading;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use App\Models\PayrollGroup as PayrollGroupModel;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use NumberFormatter;

class PayRollGroup extends Resource
{
    public static string $model = PayrollGroupModel::class;

    public static int $priority = 0;

    public static string $title = 'name';

    public static array $with = ['employees'];

    public static string $group = 'Payroll';

    public static array $search = ['id', 'name'];

    public static function label(): string
    {
        return 'Payroll Groups';
    }

    public static function indexQuery(Builder $query): Builder
    {
        $tenantId = app(TenantContext::class)->id();

        return $tenantId === null ? $query : $query->where('company_id', $tenantId);
    }

    public function fields(Request $request): array
    {
        return array_merge([
            ID::make()->sortable(),

            Text::make('Name', 'name')
                ->rules('required')
                ->sortable(),

            Number::make('Employees', function () {
                return $this->employees->count();
            })->hideWhenCreating(),

            Number::make('days/year', 'days_year')
                ->help('Number of days per year. Mon-Fri is 261. Mon-Sat is 313')
                ->sortable(),

            Number::make('No. of hours', 'hours_day')
                ->hideFromIndex()
                ->help('The required number of hours per day')
                ->sortable(),

            Select::make('Period', 'frequency')
                ->options([
                    PayrollGroupModel::FREQUENCY_MONTHLY => 'Monthly',
                    PayrollGroupModel::FREQUENCY_SEMI_MONTHLY => 'Semi-Monthly',
                ])
                ->sortable()
                ->rules('required')
                ->hideFromIndex()
                ->onlyOnForms(),

            Boolean::make('Default', 'is_default')
                ->default(false),
        ], [
            Heading::make('Cut-off'),
        ], $this->cutoffFields(), [
            Heading::make('Deductions'),
        ], $this->deductionsFields(), [
            Heading::make('Attendance'),
        ], $this->attendanceFields(), [
            HasMany::make('Employees', 'Employees')->nullable()->readonly(),
        ]);
    }

    protected function cutoffFields(): array
    {
        $days = [];

        for ($i = 1; $i != 32; $i++) {
            $days[$i] = 'Day '.$i;
        }

        return [
            Select::make('First Timesheet Cut-off', 'first_cut_off')
                ->options($days)
                ->readonly()
                ->onlyOnForms()
                ->dependsOn(['frequency'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                        $field->readonly = false;
                        $field->rules('required');
                        $field->default = 25;
                        $field->name = 'Timesheet Cut-off';
                        $formData->fill('first_cut_off', 25);
                    }

                    if ($formData->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                        $field->readonly = false;
                        $field->rules('required');
                        $field->default = 10;
                        $formData->fill('first_cut_off', 10);
                    }
                }),

            Select::make('First Pay date', 'first_pay_date')
                ->options($days)
                ->readonly()
                ->onlyOnForms()
                ->dependsOn(['frequency'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                        $field->readonly = false;
                        $field->rules('required');
                        $field->default = 30;
                        $field->name = 'Pay Date';
                        $formData->fill('first_pay_date', 30);
                    }

                    if ($formData->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                        $field->readonly = false;
                        $field->rules('required');
                        $field->default = 15;
                        $formData->fill('first_pay_date', 15);
                    }
                }),

            Select::make('Second Timesheet Cut-off', 'second_cut_off')
                ->options($days)
                ->readonly()
                ->onlyOnForms()
                ->dependsOn(['frequency'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                        $field->readonly = false;
                        $field->rules('required');
                        $field->default = 25;
                        $formData->fill('second_cut_off', 25);
                    }

                    if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                        $field->hide();
                    }
                }),

            Select::make('Second Pay date', 'second_pay_date')
                ->options($days)
                ->readonly()
                ->onlyOnForms()
                ->dependsOn(['frequency'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                        $field->readonly = false;
                        $field->rules('required');
                        $field->default = 30;
                        $formData->fill('second_pay_date', 30);
                    }

                    if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                        $field->hide();
                    }
                }),

            Select::make('Pay Date on non-working day?', 'pay_date_non_working')
                ->options([
                    PayrollGroupModel::NON_WORKING_DATE_AFTER => 'Pay date after non working day',
                    PayrollGroupModel::NON_WORKING_DATE_BEFORE => 'Pay date before non working day',
                    PayrollGroupModel::NON_WORKING_DATE_DATE => 'Remain as schedule pay date',
                ])
                ->rules('required')
                ->onlyOnForms(),

            Text::make('Cut-off Date', function () {
                if ($this->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                    return self::ordinal($this->first_cut_off).' & '.self::ordinal($this->second_cut_off).' of the month';
                }

                if ($this->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                    return self::ordinal($this->first_cut_off).' of the month';
                }

                return '';
            }),

            Text::make('Pay Date', function () {
                if ($this->frequency === PayrollGroupModel::FREQUENCY_SEMI_MONTHLY) {
                    return self::ordinal($this->first_pay_date).' & '.self::ordinal($this->second_pay_date).' of the month';
                }

                if ($this->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                    return self::ordinal($this->first_pay_date).' of the month';
                }

                return '';
            }),
        ];
    }

    protected function deductionsFields(): array
    {
        return [
            Select::make('SSS payment schedule', 'sss_schedule')
                ->rules('required')
                ->options(payment_schedules())
                ->hideFromIndex()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->displayUsingLabels()
                ->dependsOn(['frequency'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                        $field->readonly = true;
                        $field->rules('required');
                        $formData->set('sss_schedule', PayrollGroupModel::PAY_SCHEDULE_EVERYPAY);
                    }
                }),

            Select::make('Philhealth payment schedule', 'phil_health_schedule')
                ->rules('required')
                ->options(payment_schedules())
                ->hideFromIndex()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->displayUsingLabels()
                ->dependsOn(['frequency'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                        $field->readonly = true;
                        $field->rules('required');
                        $formData->set('phil_health_schedule', PayrollGroupModel::PAY_SCHEDULE_EVERYPAY);
                    }
                }),

            Select::make('HDMF payment schedule', 'hdmf_schedule')
                ->rules('required')
                ->options(payment_schedules())
                ->hideFromIndex()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->displayUsingLabels()
                ->dependsOn(['frequency'], function (Select $field, Request $request, FormData $formData) {
                    if ($formData->frequency === PayrollGroupModel::FREQUENCY_MONTHLY) {
                        $field->readonly = true;
                        $field->rules('required');
                        $formData->set('hdmf_schedule', PayrollGroupModel::PAY_SCHEDULE_EVERYPAY);
                    }
                }),

            Text::make('Tax payment schedule', function () {
                return PayrollGroupModel::PAY_SCHEDULE_LASTPAY_TEXT;
            })
                ->hideFromIndex(),
        ];
    }

    protected function attendanceFields(): array
    {
        return [
            Boolean::make('Computer Tardiness', 'compute_tardiness')
                ->hideFromIndex()
                ->default(false),
            Boolean::make('Computer Overbreak', 'compute_overbreak')
                ->hideFromIndex()
                ->default(false),
            Boolean::make('Computer Undertime', 'compute_undertime')
                ->hideFromIndex()
                ->default(false),
        ];
    }

    public static function ordinal($number)
    {
        $locale = 'en_US';
        $nf = new NumberFormatter($locale, NumberFormatter::ORDINAL);

        return $nf->format($number);
    }

    public function cards(Request $request): array
    {
        return [];
    }

    public function filters(Request $request): array
    {
        return [];
    }

    public function lenses(Request $request): array
    {
        return [];
    }

    public function actions(Request $request): array
    {
        return [];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->company_id = $request->user()->company_id;
        $model->save();

        $model->updateData($model, $request);

        GeneratePayrollDateAction::run($model);
    }

    public static function afterUpdate(Request $request, Model $model): void
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
