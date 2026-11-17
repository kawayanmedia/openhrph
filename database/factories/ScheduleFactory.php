<?php

namespace Database\Factories;

use App\Models\Schedule;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    use ResolvesTenant;

    protected $model = Schedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'name' => 'Regular 8-5',
            'am_in' => '08:00',
            'am_out' => '12:00',
            'pm_in' => '13:00',
            'pm_out' => '17:00',
            'break_time_start' => '12:00',
            'break_time_end' => '13:00',
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
