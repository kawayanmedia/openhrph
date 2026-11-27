<?php

namespace App\Livewire\Admin\Actions;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Issues a push key for ADMS devices, which cannot send HTTP headers.
 *
 * The key is appended to the server address as `?key=` (or sent as the
 * X-Push-Key header) and is stored hashed.
 */
class RotatePushKey extends Action
{
    public string $name = 'Rotate Push Key';

    public string $confirmText = 'Issue a new ADMS push key? The device must be reconfigured with it.';

    public string $confirmButtonText = 'Rotate';

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $device = $models->first();

        if (! $device instanceof Device) {
            return self::message('Select a device first.');
        }

        $plain = Str::random(32);

        $device->forceFill(['push_key' => hash('sha256', $plain)])->save();

        return self::message(
            "New push key for {$device->name} (copy it now, it is not shown again): {$plain}"
        );
    }
}
