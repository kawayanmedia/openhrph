<?php

namespace App\Livewire\Admin\Actions;

use App\Models\Device;
use App\Services\Attendance\Devices\EloquentDeviceRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use KawayanMedia\Attendance\Devices\DriverManager;

class TestDeviceConnection extends Action
{
    public string $name = 'Test Connection';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $device = $models->first();

        if (! $device instanceof Device) {
            return self::message('Select a device first.');
        }

        $descriptor = app(EloquentDeviceRepository::class)->descriptorFor($device);
        $probe = app(DriverManager::class)->driverFor($descriptor->protocol)->probe($descriptor);

        $details = collect($probe->details)
            ->filter(fn ($value) => is_scalar($value) && $value !== null && $value !== '')
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->implode(', ');

        $prefix = $probe->ok ? 'OK — ' : 'Failed — ';

        return self::message($prefix.$probe->message.($details !== '' ? " ({$details})" : ''));
    }
}
