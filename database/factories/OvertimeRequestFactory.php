<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OvertimeRequest;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeRequest>
 */
class OvertimeRequestFactory extends Factory
{
    use ResolvesTenant;

    protected $model = OvertimeRequest::class;

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
            'type' => OvertimeRequest::TYPE_OVERTIME,
            'date' => now()->toDateString(),
            'time_from' => '18:00:00',
            'time_to' => '20:00:00',
            'hours' => 2,
            'status' => 'Pending',
        ];
    }
}
