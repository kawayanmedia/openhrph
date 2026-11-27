<?php

namespace App\Livewire\Admin\Resources;

use App\Enums\DeviceProtocol;
use App\Livewire\Admin\Actions\ImportDeviceLogFile;
use App\Livewire\Admin\Actions\RotateDeviceKey;
use App\Livewire\Admin\Actions\RotatePushKey;
use App\Livewire\Admin\Actions\SyncDeviceNow;
use App\Livewire\Admin\Actions\TestDeviceConnection;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Device as DeviceModel;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class Device extends Resource
{
    public static string $model = DeviceModel::class;

    public static int $priority = 6;

    public static string $title = 'name';

    public static string $group = 'Attendance';

    public static array $search = ['id', 'name', 'serial_number', 'ip_address'];

    public static function label(): string
    {
        return 'Devices';
    }

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Name', 'name')
                ->rules('required')
                ->sortable(),

            Select::make('Protocol', 'protocol')
                ->options(DeviceProtocol::options())
                ->default(DeviceProtocol::GenericJson->value)
                ->displayUsingLabels()
                ->rules('required')
                ->sortable(),

            Text::make('Serial Number', 'serial_number')
                ->nullable()
                ->sortable()
                ->help('The SN the device reports to the ADMS endpoint; required for ZKTeco/eSSL push devices.'),

            Text::make('IP Address', 'ip_address')
                ->nullable()
                ->sortable()
                ->help('LAN address for pull protocols; empty for devices that only push.'),

            Number::make('Port', 'port')
                ->nullable()
                ->min(1)
                ->max(65535)
                ->hideFromIndex()
                ->help('Defaults to 4370 for ZKTeco UDP, 80 for HTTP APIs, 443 with HTTPS.'),

            Text::make('Timezone', 'timezone')
                ->nullable()
                ->hideFromIndex()
                ->default(config('app.timezone'))
                ->help('Applied to timestamps the device reports without an offset.'),

            BelongsTo::make('Office', 'office')
                ->options(Office::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable(),

            Boolean::make('Active', 'is_active')
                ->default(true),

            Boolean::make('Sync Enabled', 'sync_enabled')
                ->help('Pull protocols only: allow the scheduler or agent to read this device.'),

            Number::make('Sync Interval (minutes)', 'sync_interval_minutes')
                ->min(1)
                ->max(1440)
                ->default(5)
                ->hideFromIndex(),

            Badge::make('Last Sync', 'last_sync_status')
                ->map([
                    DeviceModel::SYNC_OK => 'success',
                    DeviceModel::SYNC_FAILED => 'danger',
                    DeviceModel::SYNC_SKIPPED => 'warning',
                ])
                ->sortable(),

            DateTime::make('Last Seen', 'last_seen_at')
                ->sortable()
                ->hideWhenCreating()
                ->hideWhenUpdating(),

            DateTime::make('Last Synced', 'last_synced_at')
                ->hideFromIndex()
                ->hideWhenCreating()
                ->hideWhenUpdating(),

            Textarea::make('Last Sync Message', 'last_sync_message')
                ->readonly()
                ->onlyOnDetail(),

            Textarea::make('Driver Config (JSON)', 'config')
                ->hideFromIndex()
                ->resolveUsing(fn ($value) => is_array($value) ? $this->encodeJson($value) : $value)
                ->fillUsing(fn (DeviceModel $model, string $attribute, mixed $value) => $this->fillJson($model, $attribute, $value, false))
                ->help('Driver options such as direction maps or vendor field names. Leave blank for the defaults.'),

            Textarea::make('Credentials (JSON)', 'credentials')
                ->onlyOnForms()
                ->resolveUsing(fn () => '')
                ->fillUsing(fn (DeviceModel $model, string $attribute, mixed $value) => $this->fillJson($model, $attribute, $value, true))
                ->help('e.g. {"username":"admin","password":"secret"} or {"login_id":"admin","password":"secret"} for BioStar 2. Stored encrypted and never shown; leave blank to keep the current value.'),

            Text::make('API Key', fn () => $this->api_key !== null ? 'Configured — use Rotate API Key to replace it' : 'Not set')
                ->onlyOnDetail(),
        ];
    }

    public function cards(Request $request): array
    {
        return [];
    }

    public function filters(Request $request): array
    {
        return [];
    }

    public function lenses(Request $request): array
    {
        return [];
    }

    public function actions(Request $request): array
    {
        return [
            (new SyncDeviceNow)->onlyOnTableRow(),
            (new TestDeviceConnection)->onlyOnTableRow(),
            (new ImportDeviceLogFile)->onlyOnIndexToolbar(),
            (new RotateDeviceKey)->onlyOnTableRow(),
            (new RotatePushKey)->onlyOnTableRow(),
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encodeJson(array $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function fillJson(DeviceModel $model, string $attribute, mixed $value, bool $keepWhenBlank): void
    {
        $value = is_string($value) ? trim($value) : $value;

        if ($value === null || $value === '') {
            if (! $keepWhenBlank) {
                $model->setAttribute($attribute, null);
            }

            return;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $attribute => 'Enter a valid JSON object.',
            ]);
        }

        $model->setAttribute($attribute, $decoded);
    }
}
