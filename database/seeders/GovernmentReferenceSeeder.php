<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Government\SalaryGrade;
use App\Models\Leave;
use Database\Seeders\Concerns\SeedsDemoTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class GovernmentReferenceSeeder extends Seeder
{
    use SeedsDemoTenant;

    /**
     * The 2016 National Budget Circular salary schedule (SG 1-30, steps 1-8).
     *
     * @var array<int, array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int, 8: int}>
     */
    private const SALARY_GRADES = [
        [1, 9478, 9568, 9660, 9753, 9846, 9949, 10036, 10132],
        [2, 10159, 10225, 10351, 10449, 10547, 10647, 10747, 10848],
        [3, 10883, 10985, 11089, 11193, 11298, 11405, 11512, 11621],
        [4, 11658, 11767, 11878, 11990, 12103, 12217, 12333, 12448],
        [5, 12488, 12644, 12725, 12844, 12965, 13087, 13211, 13335],
        [6, 13378, 13504, 13630, 13759, 13889, 14020, 14152, 14285],
        [7, 14331, 14466, 14602, 14740, 14878, 15018, 15159, 15303],
        [8, 15368, 15519, 15670, 15823, 15978, 16133, 16291, 16450],
        [9, 16512, 16671, 16830, 16992, 17155, 17319, 17485, 17653],
        [10, 17730, 17900, 18071, 18245, 18420, 18634, 18775, 18955],
        [11, 19077, 19286, 19496, 19709, 19925, 20142, 20362, 20585],
        [12, 20651, 20870, 21091, 21315, 21540, 21769, 21999, 22232],
        [13, 22328, 22564, 22804, 23045, 23289, 23536, 23786, 24037],
        [14, 24141, 24396, 24655, 24916, 25180, 25447, 25717, 25989],
        [15, 26192, 26489, 26790, 27094, 27401, 27712, 28027, 28344],
        [16, 28417, 28740, 29066, 29396, 29729, 30066, 30408, 30752],
        [17, 30831, 31183, 31536, 31893, 32255, 32622, 32991, 33366],
        [18, 33452, 33831, 34215, 34603, 34996, 35393, 35795, 36201],
        [19, 36409, 36857, 37312, 37771, 38237, 38709, 39186, 39670],
        [20, 39768, 40259, 40755, 41258, 41766, 42281, 42802, 43330],
        [21, 43439, 43974, 44517, 45066, 45621, 46183, 46753, 47329],
        [22, 47448, 48032, 48625, 49224, 49831, 50445, 51067, 51697],
        [23, 51826, 52466, 53112, 53767, 54430, 55101, 55781, 56468],
        [24, 56610, 57308, 58014, 58730, 59453, 60187, 60928, 61679],
        [25, 61971, 62735, 63508, 64291, 65083, 65885, 66698, 67520],
        [26, 67690, 68524, 69369, 70224, 71090, 71967, 72855, 73751],
        [27, 73937, 74849, 75771, 76705, 77651, 78608, 79577, 80567],
        [28, 80760, 81756, 82764, 83784, 84817, 85862, 86921, 87993],
        [29, 88214, 89301, 90402, 91516, 92644, 93786, 94943, 96113],
        [30, 96354, 97543, 98745, 99962, 101195, 102442, 103705, 104984],
    ];

    public function run(): void
    {
        $this->runForDemoTenant(function (Company $company): void {
            $this->seedLeaveTypes($company);
            $this->seedSalaryGrades($company);

            Artisan::call('payroll:holidays:import', [
                '--year' => 2026,
                '--company' => $company->getKey(),
            ]);
        });
    }

    private function seedLeaveTypes(Company $company): void
    {
        $leaveTypes = [
            ['VL', 'Vacation Leave', false, 0, 0],
            ['SL', 'Sick Leave', false, 0, 0],
            ['SPL', 'Special Privilege Leave', false, 0, 3],
            ['PL', 'Paternity Leave', false, 0, 7],
            ['ML', 'Maternity Leave', false, 0, 105],
            ['SPL-SP', 'Solo Parent Leave', false, 0, 7],
            ['FL', 'Forced Leave/Mandatory Leave', false, 1, 5],
            ['MON', 'Monetization Leave', false, 0, 0],
            ['STUDY', 'Study Leave', false, 0, 0],
            ['CAL', 'Calamity Leave', false, 0, 0],
            ['REHAB', 'Rehabilitation Leave', false, 0, 0],
            ['SLWOP', 'Sick Leave Without Pay', false, 0, 0],
            ['VLWOP', 'Vacation Leave Without Pay', false, 0, 0],
        ];

        foreach ($leaveTypes as [$code, $name, $deductible, $deductTo, $allowPerYear]) {
            $this->upsertModel(
                Leave::class,
                ['company_id' => $company->getKey(), 'name' => $name],
                [
                    'code' => $code,
                    'deductable' => $deductible,
                    'deduct_to' => $deductTo,
                    'allow_per_year' => $allowPerYear,
                    'is_active' => true,
                    'description' => null,
                ],
            );
        }
    }

    private function seedSalaryGrades(Company $company): void
    {
        foreach (self::SALARY_GRADES as [$sg, $step1, $step2, $step3, $step4, $step5, $step6, $step7, $step8]) {
            $this->upsertModel(
                SalaryGrade::class,
                [
                    'company_id' => $company->getKey(),
                    'year' => '2016',
                    'salary_grade_type' => 'NBC',
                    'sg' => $sg,
                ],
                [
                    'step1' => $step1,
                    'step2' => $step2,
                    'step3' => $step3,
                    'step4' => $step4,
                    'step5' => $step5,
                    'step6' => $step6,
                    'step7' => $step7,
                    'step8' => $step8,
                ],
            );
        }
    }
}
