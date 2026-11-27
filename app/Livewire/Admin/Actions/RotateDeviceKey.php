<?php

namespace App\Livewire\Admin\Actions;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Issues a new device API key for the JSON ingestion endpoint.
 *
 * Only the hash is stored, so the plain key is shown once in the response.
 */
class RotateDeviceKey extends Action
{
    public string $name = 'Rotate API Key';

    public string $confirmText = 'Issue a new API key? The old key stops working immediately.';

    public string $confirmButtonText = 'Rotate';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $device = $models->first();

        if (! $device instanceof Device) {
            return self::message('Select a device first.');
        }

        $plain = Str::random(48);

        $device->forceFill([
            'api_key' => hash('sha256', $plain),
            'is_active' => true,
        ])->save();

        return self::message("New API key for {$device->name} (copy it now, it is not shown again): {$plain}");
    }
}
