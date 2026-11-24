<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Services\Payroll\PayrollRegisterReport;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Exports the payroll register: every employee's gross, each deduction, the
 * employer share and net pay, suitable for reconciliation to the journal.
 */
class PayrollRegister extends Command
{
    protected $signature = 'payroll:register
        {payroll : Payroll id}
        {--path= : Write the CSV to this path instead of stdout}';

    protected $description = 'Export the payroll register as CSV';

    public function handle(PayrollRegisterReport $report): int
    {
        $payroll = Payroll::withoutTenant()->find($this->argument('payroll'));

        if (! $payroll) {
            $this->error('Payroll not found.');

            return self::FAILURE;
        }

        app(TenantContext::class)->set((int) $payroll->company_id);

        if (! $payroll->payrollDetails()->exists()) {
            $this->error('This payroll has no details to export.');

            return self::FAILURE;
        }

        $csv = $report->csv($payroll);
        $path = $this->option('path');

        if ($path) {
            file_put_contents($path, $csv);
            $this->info('Payroll register written to '.$path);

            return self::SUCCESS;
        }

        $this->line($csv);

        return self::SUCCESS;
    }
}
