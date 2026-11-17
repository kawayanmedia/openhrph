<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForTenants;
use App\Gateways\GenerateTimesheetGateway;
use App\Models\Employee;
use App\Models\Timesheet;
use Illuminate\Console\Command;

class GenerateBlankTimesheetCommand extends Command
{
    use RunsForTenants;

    /**
     * Generate blank timesheet so that we can identify if the day is regular day,
     * rest day or holiday.
     *
     * @var string
     */
    protected $signature = 'blank_timesheet:generate {from_date?} {to_date?} {--company= : Company id to run for}';

    /**
     * Generate blank timesheet.
     *
     * @var string
     */
    protected $description = 'Generate blank timesheet';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->forTenantOption(function (): void {
            $gateway = new GenerateTimesheetGateway;

            foreach (Employee::with('payrollGroup')->lazy() as $employee) {
                $gateway->addDays($employee);
            }
        });

        return Command::SUCCESS;
    }
}
