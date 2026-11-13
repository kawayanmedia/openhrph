<?php

namespace Database\Factories;

use App\Models\Government\SalaryGrade;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryGrade>
 */
class SalaryGradeFactory extends Factory
{
    use ResolvesTenant;

    protected $model = SalaryGrade::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $step = fn (): float => $this->faker->randomFloat(2, 10000, 90000);

        return [
            'company_id' => $this->tenantId(),
            'sg' => $this->faker->numberBetween(1, 33),
            'year' => (string) now()->year,
            'salary_grade_type' => 'NBC',
            'step1' => $step(),
            'step2' => $step(),
            'step3' => $step(),
            'step4' => $step(),
            'step5' => $step(),
            'step6' => $step(),
            'step7' => $step(),
            'step8' => $step(),
        ];
    }
}
