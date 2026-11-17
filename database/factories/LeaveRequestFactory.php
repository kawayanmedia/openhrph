<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    use ResolvesTenant;

    protected $model = LeaveRequest::class;

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
            'leave_type_id' => 1,
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'is_whole_day' => true,
            'status' => LeaveRequest::PENDING,
        ];
    }
}
