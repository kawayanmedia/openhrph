<?php

namespace App\Livewire\Admin\Actions;

use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Services\Payroll\PayrollAudit;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Creates a 13th-month, final-pay or annual-adjustment run from the admin UI.
 *
 * The run is created in the open state; the operator previews and processes it
 * from the payroll run page, where the usual audit trail is written.
 */
class CreateSpecialPayroll extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public string $name = 'Create special run';

    public string $confirmText = 'Create this special payroll run?';

    public string $confirmButtonText = 'Create run';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $companyId = Auth::user()?->company_id;
        $group = PayrollGroup::query()->find($fields['payroll_group_id'] ?? null);

        if ($companyId === null || $group === null) {
            return self::message('Choose a payroll group first.');
        }

        $type = (string) ($fields['type'] ?? Payroll::TYPE_THIRTEENTH_MONTH);
        $payrollDate = CarbonImmutable::parse($fields['payroll_date'] ?? now());

        [$from, $to] = match ($type) {
            Payroll::TYPE_THIRTEENTH_MONTH => [$payrollDate->startOfYear(), $payrollDate],
            Payroll::TYPE_ANNUAL_ADJUSTMENT => [$payrollDate->startOfYear(), $payrollDate->endOfYear()],
            default => [$payrollDate->startOfMonth(), $payrollDate],
        };

        $meta = array_filter([
            'employee_ids' => (string) ($fields['employee_ids'] ?? ''),
            'separation_reason' => (string) ($fields['separation_reason'] ?? ''),
            'years_of_service' => (string) ($fields['years_of_service'] ?? ''),
            'leave_days' => (string) ($fields['leave_days'] ?? ''),
        ], fn (string $value): bool => trim($value) !== '');

        $payroll = Payroll::query()->forceCreate([
            'company_id' => $companyId,
            'payroll_group_id' => $group->getKey(),
            'payroll_date' => $payrollDate->toDateString(),
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'cut_off' => null,
            'status' => Payroll::STATUS_OPEN,
            'type' => $type,
            'meta' => $meta,
        ]);

        PayrollAudit::record($payroll, 'created', null, ['type' => $type]);

        return self::message("Payroll #{$payroll->getKey()} created. Open it to preview and process.");
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Payroll Group', 'payroll_group_id')
                ->options(PayrollGroup::query()->pluck('name', 'id')->all())
                ->rules('required'),

            Select::make('Run Type', 'type')
                ->options([
                    Payroll::TYPE_THIRTEENTH_MONTH => '13th month',
                    Payroll::TYPE_FINAL_PAY => 'Final pay',
                    Payroll::TYPE_ANNUAL_ADJUSTMENT => 'Annual adjustment',
                ])
                ->default(Payroll::TYPE_THIRTEENTH_MONTH)
                ->rules('required'),

            Date::make('Payroll Date', 'payroll_date')
                ->rules('required'),

            Select::make('Employee (optional)', 'employee_ids')
                ->options(Employee::employeeList())
                ->nullable(),

            Select::make('Separation reason (final pay)', 'separation_reason')
                ->options([
                    '' => 'None',
                    'just_cause' => 'Just cause (no separation pay)',
                    'authorised_cause' => 'Authorized cause',
                    'retrenchment' => 'Retrenchment',
                    'redundancy' => 'Redundancy',
                    'closure' => 'Closure',
                    'illness' => 'Disease',
                    'retirement' => 'Retirement (RA 7641)',
                ])
                ->nullable(),

            Text::make('Years of service (optional)', 'years_of_service'),

            Text::make('Leave days to convert (optional)', 'leave_days'),
        ];
    }
}
