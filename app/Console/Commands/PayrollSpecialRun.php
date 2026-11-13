<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Services\Payroll\Engine\EngineManager;
use App\Services\Payroll\PayrollAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Creates a non-regular payroll run: 13th month, final pay or the year-end
 * annual tax adjustment.
 */
class PayrollSpecialRun extends Command
{
    protected $signature = 'payroll:special
        {group : Payroll group id}
        {type : thirteenth_month|final_pay|annual_adjustment}
        {--date= : Payroll date (defaults by run type)}
        {--from= : Period start (defaults by run type)}
        {--to= : Period end (defaults by run type)}
        {--employee=* : Restrict the run to these employee ids}
        {--separation-reason= : just_cause|authorised_cause|retrenchment|redundancy|closure|illness|death|disability}
        {--years-of-service= : Override the computed years of service}
        {--leave-days= : Override the leave balance converted to cash}
        {--process : Run the payroll engine immediately}';

    protected $description = 'Create a 13th-month, final-pay or annual-adjustment payroll run';

    public function handle(): int
    {
        $type = (string) $this->argument('type');

        if (! in_array($type, [Payroll::TYPE_THIRTEENTH_MONTH, Payroll::TYPE_FINAL_PAY, Payroll::TYPE_ANNUAL_ADJUSTMENT], true)) {
            $this->error('Type must be thirteenth_month, final_pay or annual_adjustment.');

            return self::FAILURE;
        }

        $group = PayrollGroup::withoutTenant()->find($this->argument('group'));

        if (! $group) {
            $this->error('Payroll group not found.');

            return self::FAILURE;
        }

        $payrollDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : $this->defaultPayrollDate($type);

        [$from, $to] = $this->defaultPeriod($type, $payrollDate);

        $meta = array_filter([
            'employee_ids' => implode(',', (array) $this->option('employee')),
            'separation_reason' => $this->option('separation-reason'),
            'years_of_service' => $this->option('years-of-service'),
            'leave_days' => $this->option('leave-days'),
        ], fn ($value) => $value !== null && $value !== '');

        $payroll = Payroll::withoutTenant()->forceCreate([
            'company_id' => $group->company_id,
            'payroll_group_id' => $group->getKey(),
            'payroll_date' => $payrollDate->toDateString(),
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'cut_off' => $payrollDate->day <= 15 ? 'first_pay' : 'last_pay',
            'status' => Payroll::STATUS_OPEN,
            'type' => $type,
            'meta' => $meta,
        ]);

        PayrollAudit::record($payroll, 'created', null, ['type' => $type]);

        $this->info(sprintf('Payroll #%d created (%s).', $payroll->getKey(), $type));

        if (! $this->option('process')) {
            return self::SUCCESS;
        }

        $result = app(EngineManager::class)->engine()->process($payroll);

        $payroll->refresh()->forceFill([
            'status' => Payroll::STATUS_PROCESSED,
            'engine_version' => $result->engineVersion,
            'processed_at' => now(),
            'processed_employees' => $result->employees,
        ])->save();

        $this->info(sprintf(
            'Processed %d employee(s): gross ₱%s, deductions ₱%s, net ₱%s.',
            $result->employees, $result->gross, $result->deductions, $result->net
        ));

        foreach (array_slice(array_unique($result->warnings), 0, 10) as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }

    private function defaultPayrollDate(string $type): Carbon
    {
        return match ($type) {
            Payroll::TYPE_THIRTEENTH_MONTH => Carbon::create(null, 12, 24)->startOfDay(),
            Payroll::TYPE_ANNUAL_ADJUSTMENT => Carbon::create(null, 12, 31)->startOfDay(),
            default => now()->startOfDay(),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function defaultPeriod(string $type, Carbon $payrollDate): array
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : match ($type) {
            Payroll::TYPE_THIRTEENTH_MONTH, Payroll::TYPE_ANNUAL_ADJUSTMENT => $payrollDate->copy()->startOfYear(),
            default => $payrollDate->copy()->startOfMonth(),
        };

        $to = $this->option('to') ? Carbon::parse($this->option('to')) : $payrollDate->copy();

        return [$from, $to];
    }
}
