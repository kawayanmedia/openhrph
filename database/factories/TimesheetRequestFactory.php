<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\TimesheetRequest;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimesheetRequest>
 */
class TimesheetRequestFactory extends Factory
{
    use ResolvesTenant;

    protected $model = TimesheetRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'employee_id' => fn (array $attributes) => Employee::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'type' => 'attendance',
            'reason' => $this->faker->sentence(3),
            'log_date' => now()->toDateString(),
            'time' => '08:00:00',
            'log_type' => 0,
            'status' => 'Pending',
            'date_filed' => now()->toDateString(),
        ];
    }
}
