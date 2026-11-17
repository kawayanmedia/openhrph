<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForTenants;
use App\Models\Government\SalaryGrade;
use Illuminate\Console\Command;

class GenerateSalaryGradeCommand extends Command
{
    use RunsForTenants;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salary_grade:generate {--company= : Company id to run for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->forTenantOption(function (): void {
            $salaryGrades = SalaryGrade::where('year', 2023)->get();

            foreach ($salaryGrades as $salaryGrade) {
                $sg = SalaryGrade::firstOrNew(['year' => 2025, 'sg' => $salaryGrade->sg, 'salary_grade_type' => $salaryGrade->salary_grade_type]);
                $sg->step1 = $salaryGrade->step1 + 4500;
                $sg->step2 = $salaryGrade->step2 + 4500;
                $sg->step3 = $salaryGrade->step3 + 4500;
                $sg->step4 = $salaryGrade->step4 + 4500;
                $sg->step5 = $salaryGrade->step5 + 4500;
                $sg->step6 = $salaryGrade->step6 + 4500;
                $sg->step7 = $salaryGrade->step7 + 4500;
                $sg->step8 = $salaryGrade->step8 + 4500;
                $sg->save();
            }
        });

        return Command::SUCCESS;
    }
}
