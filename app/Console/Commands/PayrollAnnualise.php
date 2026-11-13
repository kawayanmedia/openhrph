<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollGroup;
use App\Services\Payroll\Engine\EngineManager;
use App\Services\Payroll\PayrollAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Year-end annualisation: compares the tax withheld during the year with the
 * tax on the annualised taxable income and posts the difference as an
 * annual_adjustment payroll.
 */
class PayrollAnnualise extends Command
{
    protected $signature = 'payroll:annualise
        {group : Payroll group id}
        {--year= : Calendar year (defaults to the current year)}
        {--process : Run the payroll engine immediately}';

    protected $description = 'Create the year-end annual tax adjustment payroll';

    public function handle(): int
    {
        $group = PayrollGroup::withoutTenant()->find($this->argument('group'));

        if (! $group) {
            $this->error('Payroll group not found.');

            return self::FAILURE;
        }

        $year = (int) ($this->option('year') ?: now()->year);
        $date = Carbon::create($year, 12, 31)->startOfDay();

        $payroll = Payroll::withoutTenant()->forceCreate([
            'company_id' => $group->company_id,
            'payroll_group_id' => $group->getKey(),
            'payroll_date' => $date->toDateString(),
            'date_from' => Carbon::create($year, 1, 1)->toDateString(),
            'date_to' => $date->toDateString(),
            'cut_off' => 'last_pay',
            'status' => Payroll::STATUS_OPEN,
            'type' => Payroll::TYPE_ANNUAL_ADJUSTMENT,
            'meta' => ['year' => $year],
        ]);

        PayrollAudit::record($payroll, 'created', null, ['type' => Payroll::TYPE_ANNUAL_ADJUSTMENT, 'year' => $year]);

        $this->info(sprintf('Annual adjustment payroll #%d created for %d.', $payroll->getKey(), $year));

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

        $this->info(sprintf('Processed %d employee(s), net adjustment ₱%s.', $result->employees, $result->net));

        return self::SUCCESS;
    }
}
