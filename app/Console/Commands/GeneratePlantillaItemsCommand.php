<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForTenants;
use App\Models\Employee;
use App\Models\PlantillaItem;
use Illuminate\Console\Command;

class GeneratePlantillaItemsCommand extends Command
{
    use RunsForTenants;

    /**
     * Genarate a plantilla items from employee.
     * This is just a temporary items
     *
     * @var string
     */
    protected $signature = 'plantilla:generate {--company= : Company id to run for}';

    /**
     * Generate blank timesheet.
     *
     * @var string
     */
    protected $description = 'Genarate a plantilla items from employee tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->forTenantOption(function (): void {
            $defaultYear = 2024;

            foreach (Employee::where('status', 1)->lazy() as $employee) {
                $this->comment($employee->id);
                $plantilla = PlantillaItem::firstOrNew(['employee_id' => $employee->id, 'year' => $defaultYear]);
                $plantilla->office_id = $employee->office_id;
                $plantilla->item_number = $employee->employee_number;
                $plantilla->position_title = $employee->position;

                $plantilla->salary_grade = 0;
                if (is_numeric($employee->salary_grade)) {
                    $plantilla->salary_grade = $employee->salary_grade;
                }

                $plantilla->step = $employee->step;
                $plantilla->save();
            }
        });

        $this->comment('okay na');

        return Command::SUCCESS;
    }
}
