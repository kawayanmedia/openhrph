<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\ProcessPayroll;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\HasMany;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Status;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Livewire\Admin\Support\FormData;
use App\Models\Payroll as PayrollModel;
use App\Models\PayrollDate;
use App\Models\PayrollGroup as PayrollGroupModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PayRoll extends Resource
{
    public static string $model = PayrollModel::class;

    public static string $title = 'name';

    public static array $with = ['payrollGroup.employees', 'payrollDetails'];

    public static int $priority = 1;

    public static string $group = 'Payroll';

    public static array $search = ['id'];

    public static function label(): string
    {
        return 'Payroll';
    }

    public function title(): string
    {
        $date = $this->resource?->payroll_date;

        return (string) ($date?->format('M d, Y') ?? $this->resource?->getKey() ?? '');
    }

    public static function indexQuery(Builder $query): Builder
    {
        // Company scoping is handled by the model's global CompanyScope.
        return $query;
    }

    public function fields(Request $request): array
    {
        $defaultGroup = PayrollGroupModel::where('is_default', true)->first();

        return [
            ID::make()->sortable(),

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

                return $this->date_from->format('M d, Y').' - '.$this->date_to->format('M d, Y');
            }),

            Select::make('Group', 'payroll_group_id')
                ->options(PayrollGroupModel::pluck('name', 'id')->toArray())
                ->sortable()
                ->default(PayrollGroupModel::where('is_default', true)->first()?->id)
                ->rules('required')
                ->onlyOnForms(),

            Select::make('Payroll Date', 'payroll_date')
                ->options($defaultGroup ? monthly_periods($defaultGroup) : [])
                ->sortable()
                ->rules('required')
                ->hideFromIndex()
                ->fillUsing(function ($model, $attribute) {
                    unset($model->{$attribute});
                })
                ->onlyOnForms()
                ->dependsOn(['payroll_group_id'], function (Select $field, Request $request, FormData $formData) {
                    $payrollGroup = PayrollGroupModel::find($formData->payroll_group_id);

                    if ($payrollGroup) {
                        $field->options(monthly_periods($payrollGroup));
                    }
                }),

            Text::make('No. of Employees', function () {
                return $this->payrollDetails?->count();
            }),

            Currency::make('Gross', function () {
                return $this->gross_amount;
            })->currency(currency_symbol()),

            Currency::make('Net', function () {
                return $this->net_amount;
            })->currency(currency_symbol()),

            Status::make('Status')
                ->loadingWhen(['waiting', PayrollModel::STATUS_RUNNING])
                ->failedWhen([PayrollModel::STATUS_CLOSED]),

            Text::make('', function () {
                return "<a class='font-semibold text-teal-700' href='".route('admin.payroll.run', $this->id)."'>Run payroll</a>";
            })
                ->asHtml()
                ->onlyOnIndex(),

            HasMany::make('Employees', 'PayrollDetails')->nullable()->readonly(),
        ];
    }

    public function cards(Request $request): array
    {
        // NovaBackButton is not applicable to the Livewire admin engine.
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
        $processPayroll = new ProcessPayroll;
        $processPayroll->confirmText = 'Are you sure you want to process Payroll?';
        $processPayroll->confirmButtonText = 'Yes';

        return [
            $processPayroll->onlyOnIndex(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->company_id = $request->user()->company_id;
        $model->save();

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
            $model->date_from = $payrollDate->attendance_date_start;
            $model->date_to = $payrollDate->attendance_date_end;
            $model->payroll_date = $payrollDate->payroll_date;
            $model->save();
        }

        if ($schedule != null) {
            $model->schedule = $schedule;

            if ($schedule == 1) {
                $model->cut_off = PayrollGroupModel::PAY_SCHEDULE_FIRSTPAY;
            }

            if ($schedule == 2) {
                $model->cut_off = PayrollGroupModel::PAY_SCHEDULE_LASTPAY;
            }

            $model->save();
        }

        // Process the payroll here
        // We may need to queue the processing of payroll
        $payrollGroup = PayrollGroupModel::find($model->payroll_group_id);

        if ($payrollGroup->frequency == PayrollGroupModel::FREQUENCY_MONTHLY) {
            $model->cut_off = null;
            $model->save();
        }
    }

    public static function afterUpdate(Request $request, Model $model): void
    {
        $model->company_id = $request->user()->company_id;
        $model->save();

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
            $model->date_from = $payrollDate->attendance_date_start;
            $model->date_to = $payrollDate->attendance_date_end;
            $model->payroll_date = $payrollDate->payroll_date;
            $model->save();
        }

        if ($schedule != null) {
            $model->schedule = $schedule;

            if ($schedule == 1) {
                $model->cut_off = PayrollGroupModel::PAY_SCHEDULE_FIRSTPAY;
            }

            if ($schedule == 2) {
                $model->cut_off = PayrollGroupModel::PAY_SCHEDULE_LASTPAY;
            }

            $model->save();
        }

        // Process the payroll here
        // We may need to queue the processing of payroll
        $payrollGroup = PayrollGroupModel::find($model->payroll_group_id);

        if ($payrollGroup->frequency == PayrollGroupModel::FREQUENCY_MONTHLY) {
            $model->cut_off = null;
            $model->save();
        }
    }
}
