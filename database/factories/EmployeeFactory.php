<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Office;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    use ResolvesTenant;

    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'office_id' => fn (array $attributes) => Office::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'employee_number' => Str::upper($this->faker->bothify('EMP-####')),
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->lastName(),
            'last_name' => $this->faker->lastName(),
            'sex' => $this->faker->randomElement(['M', 'F']),
            'date_hired' => $this->faker->dateTimeBetween('-5 years')->format('Y-m-d'),
            'employment_status' => Employee::REGULAR_PERMANENT,
        ];
    }
}
