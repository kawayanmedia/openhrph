<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Services\Payroll\Engine\EngineManager;
use App\Services\Payroll\Engine\PayrollEngineException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class PayrollPreview extends Command
{
    protected $signature = 'payroll:preview {payroll : Payroll id}';

    protected $description = 'Compute a payroll without saving anything (engine dry run)';

    public function handle(EngineManager $manager): int
    {
        $payroll = Payroll::withoutTenant()->find($this->argument('payroll'));

        if (! $payroll) {
            $this->error('Payroll not found.');

            return self::FAILURE;
        }

        app(TenantContext::class)->set($payroll->company_id);

        try {
            $result = $manager->engine()->preview($payroll);
        } catch (PayrollEngineException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Payroll #{$result->payrollId} — {$result->employees} employees in {$result->durationMs} ms");

        $this->table(
            ['Employee', 'Name', 'Gross', 'Deductions', 'Net'],
            collect($result->payslips)->map(fn (array $slip) => [
                $slip['employee_number'] ?? '',
                $slip['name'] ?? '',
                $slip['gross'] ?? '',
                $slip['total_deductions'] ?? $slip['deductions'] ?? '',
                $slip['net_pay'] ?? '',
            ])->all()
        );

        foreach ($result->warnings as $warning) {
            $this->warn('• '.$warning);
        }

        return self::SUCCESS;
    }
}
