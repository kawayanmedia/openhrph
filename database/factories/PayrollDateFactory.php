<?php

namespace Database\Factories;

use App\Models\PayrollDate;
use App\Models\PayrollGroup;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollDate>
 */
class PayrollDateFactory extends Factory
{
    use ResolvesTenant;

    protected $model = PayrollDate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return [
            'company_id' => $this->tenantId(),
            'payroll_group_id' => fn (array $attributes) => PayrollGroup::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'payroll_date' => $end->toDateString(),
            'attendance_date_start' => $start->toDateString(),
            'attendance_date_end' => $end->toDateString(),
            'status' => PayrollDate::STATUS_OPEN,
        ];
    }
}
