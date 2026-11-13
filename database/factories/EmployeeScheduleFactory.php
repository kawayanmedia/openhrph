<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\Schedule;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSchedule>
 */
class EmployeeScheduleFactory extends Factory
{
    use ResolvesTenant;

    protected $model = EmployeeSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'employee_id' => fn (array $attributes) => (string) Employee::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'schedule_id' => fn (array $attributes) => Schedule::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'from_date' => now()->startOfYear()->toDateString(),
            'to_date' => now()->endOfYear()->toDateString(),
        ];
    }
}
