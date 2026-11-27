<?php

namespace Database\Factories;

use App\Models\AttendanceDeviceEvent;
use Database\Factories\Concerns\ResolvesTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceDeviceEvent>
 */
class AttendanceDeviceEventFactory extends Factory
{
    use ResolvesTenant;

    protected $model = AttendanceDeviceEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => $this->tenantId(),
            'device_id' => null,
            'serial_number' => $this->faker->bothify('ADMS-####'),
            'protocol' => 'zkteco_adms',
            'kind' => AttendanceDeviceEvent::KIND_PUSH,
            'endpoint' => 'iclock/cdata',
            'http_status' => 200,
            'records' => 0,
            'accepted' => 0,
            'duplicates' => 0,
            'unmatched' => 0,
            'message' => null,
            'payload' => null,
            'occurred_at' => now(),
        ];
    }
}
