<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\ExportCsv;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\Heading;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Filters\PayrollDetailFieldsFilter;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\PayrollGroup as PayrollGroupModel;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PayrollDetail extends Resource
{
    public static string $model = \App\Models\PayrollDetail::class;

    public static int $priority = 0;

    public static string $title = 'name';

    public static array $with = ['employee'];

    public static string $group = 'Payroll';

    public static array $search = [];

    public static function label(): string
    {
        return 'Payroll Details';
    }

    public function title(): string
    {
        return (string) ($this->resource?->employee?->fullname ?? $this->resource?->getKey() ?? '');
    }

    public static function indexQuery(Builder $query): Builder
    {
        $tenantId = app(TenantContext::class)->id();

        return $tenantId === null ? $query : $query->where('company_id', $tenantId);
    }

    public function fields(Request $request): array
    {
        return [
            Text::make('', function () {
                return "<a class='flex-shrink-0 shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm' href='".route('payslip.show', $this->id)."' target='_new'>Pay Slip</a>";
            })
                ->onlyOnDetail()
                ->asHtml(),

            Text::make('Employee Number', function () {
                return $this->employee?->employee_number ?? 'Deleted';
            }),

            BelongsTo::make('Employee', 'Employee')
                ->options(Employee::employeeList()),

            Heading::make('income'),

            // NOTE: Nova's ->hideFromDetail(is_empty(...)) conditionals were dropped; the
            // engine has no conditional visibility. All detail-only fields render on detail.
            Currency::make('Monthly / Daily Basic', function () {
                return $this->basic_monthly_salary;
            })->currency(currency_symbol()),

            Currency::make('Daily Basic', function () {
                return $this->employee?->daily_rate_format;
            })->currency(currency_symbol()),

            Currency::make('Regular Pay', function () {
                return $this->basic_salary;
            })->currency(currency_symbol()),

            Currency::make('Representation Allowance', function () {
                return $this->representation_allowance;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Transportation Allowance', function () {
                return $this->transportation_allowance;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Cost of Living Allowance', function () {
                return $this->cola;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Fixed Housing Allowance', function () {
                return $this->fixed_housing_allowance;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Other Taxable Regular Compensation', function () {
                return $this->other_taxable_regular;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Commission', function () {
                return $this->commission;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Profit Sharing', function () {
                return $this->profit_sharing;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Fees', function () {
                return $this->fees;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Hazard Pay', function () {
                return $this->hazard_pay;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Other Taxable Supplementary', function () {
                return $this->other_taxable_supplementary;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Overtime Pay', function () {
                return $this->overtime_pay;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Night Differential Pay', function () {
                return $this->night_pay;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Rest Day Pay', function () {
                return $this->rest_day_pay;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Holiday Pay', function () {
                return $this->holiday_pay;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Other Compensations', function () {
                return $this->compensations_format;
            })->currency(currency_symbol()),

            Currency::make('Gross', function () {
                return $this->gross_income;
            })->currency(currency_symbol()),

            Currency::make('Mandatory Deductions', function () {
                return $this->total_employee;
            })->onlyOnIndex()->currency(currency_symbol()),

            Heading::make('deductions'),

            Currency::make('SSS', function () {
                return $this->sss_employee + $this->sss_provident_employee;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('Philhealth', function () {
                return $this->philhealth_employee;
            })->currency(currency_symbol())->onlyOnDetail(),

            Currency::make('HDMF', function () {
                return $this->hdmf_employee;
            })->currency(currency_symbol())->onlyOnDetail(),

            Text::make('Absent', function () {
                return number_format($this->absent_days_amount ?? 0, 2).'('.$this->absent_days.' x '.$this->employee?->daily_rate.')';
            })->onlyOnDetail(),

            Currency::make('Taxable Income', function () {
                return $this->taxable_income;
            })->currency(currency_symbol()),

            Currency::make('Withholding tax', function () {
                return $this->withholding_tax;
            })->currency(currency_symbol()),

            Currency::make('Total Deductions', function () {
                return $this->total_deductions;
            })->currency(currency_symbol()),

            Heading::make('net'),

            Currency::make('Net Pay', function () {
                return $this->net_pay;
            })->currency(currency_symbol()),
        ];
    }

    public function cards(Request $request): array
    {
        return [];
    }

    public function filters(Request $request): array
    {
        return [
            new PayrollDetailFieldsFilter,
        ];
    }

    public function lenses(Request $request): array
    {
        return [];
    }

    public function actions(Request $request): array
    {
        return [
            ExportCsv::make()->withFormat(function ($model) {
                return [
                    'Employee' => $model->employee?->fullname,
                    'Position' => $model->employee?->position,
                    'Salary' => $model->employee?->basic_salary,

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
            })->onlyOnIndex(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        $model->company_id = $request->user()->company_id;
        $model->save();
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
    }

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return false;
    }

    public static function authorizedToDelete(Model $model): ?bool
    {
        return false;
    }
}
