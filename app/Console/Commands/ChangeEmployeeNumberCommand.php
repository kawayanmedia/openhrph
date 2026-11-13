<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForTenants;
use App\Models\Employee;
use App\Models\Government\LeaveApplication;
use App\Models\Timesheet;
use Illuminate\Console\Command;

class ChangeEmployeeNumberCommand extends Command
{
    use RunsForTenants;

    /**
     * Generate blank timesheet so that we can identify if the day is regular day,
     * rest day or holiday.
     *
     * @var string
     */
    protected $signature = 'employee_id:change {--company= : Company id to run for}';

    /**
     * Generate blank timesheet.
     *
     * @var string
     */
    protected $description = 'Change the employee_id for all database tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->forTenantOption(function (): void {
            $this->changeEmployeeNumbers();
        });

        return Command::SUCCESS;
    }

    private function changeEmployeeNumbers(): void
    {
        // TODO update me on real migration
        // foreach (Employee::where('employee_number', '41A')->where('status', 1)->lazy() as $employee) {
        //     Timesheet::where('employee_number', $employee->employee_number)
        //         ->where('log_date', '>=', '2023-07-01')
        //         ->update(['employee_id' => $employee->id]);
        //     $this->comment($employee->lname);
        // }

        // Uncomment me on real migration
        // foreach (Employee::where('status', 1)->lazy() as $employee) {
        //     Timesheet::where('employee_number', $employee->employee_number)
        //         // ->where('log_date', '>=', '2023-07-01')
        //         ->update(['employee_id' => $employee->id]);
        //     $this->comment($employee->lname);
        // }

        foreach (Employee::where('status', 1)->lazy() as $employee) {
            LeaveApplication::where('employee_number', $employee->employee_number)
                // ->where('log_date', '>=', '2023-07-01')
                ->update(['employee_id' => $employee->id]);
            $this->comment($employee->lname);
        }

        $this->comment('okay na');
    }
}
