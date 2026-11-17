<?php

namespace Database\Factories;

use App\Services\Government\Models\PhilHealth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhilHealth>
 */
class PhilHealthFactory extends Factory
{
    protected $model = PhilHealth::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->numberBetween(0, 90000);
        $end = $start + 9999.99;

        return [
            'salary_bracket' => $this->faker->numberBetween(1, 100),
            'effectivity_date' => now()->startOfYear()->toDateString(),
            'start_range' => $start,
            'end_range' => $end,
            'salary_based' => (string) $start,
            'monthly_share' => '500.00',
            'employee_share' => '250.00',
            'employer_share' => '250.00',
        ];
    }
}
