<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ScheduleAdjustmentRequest;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleAdjustmentRequest>
 */
class ScheduleAdjustmentRequestFactory extends Factory
{
    use ResolvesTenant;

    protected $model = ScheduleAdjustmentRequest::class;

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
            'date_filed' => now()->toDateString(),
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'time_from' => '09:00:00',
            'time_to' => '18:00:00',
            'is_rd' => false,
            'status' => 'Pending',
        ];
    }
}
