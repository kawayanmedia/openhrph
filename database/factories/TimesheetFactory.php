<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Timesheet;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Timesheet>
 */
class TimesheetFactory extends Factory
{
    use ResolvesTenant;

    protected $model = Timesheet::class;

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
            'employee_number' => fn (array $attributes) => Employee::withoutTenant()
                ->find($attributes['employee_id'])
                ?->employee_number ?? $this->faker->bothify('EMP-####'),
            'log_date' => now()->toDateString(),
            'am_login' => '08:00',
            'am_logout' => '12:00',
            'pm_login' => '13:00',
            'pm_logout' => '17:00',
        ];
    }
}
