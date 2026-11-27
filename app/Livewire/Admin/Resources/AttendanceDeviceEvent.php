<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\AttendanceDeviceEvent as AttendanceDeviceEventModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Read-only audit of device protocol traffic and sync runs.
 *
 * Keepalive polling is not recorded, so a row here is either a push, an import,
 * a sync summary or a failure worth investigating.
 */
class AttendanceDeviceEvent extends Resource
{
    public static string $model = AttendanceDeviceEventModel::class;

    public static int $priority = 7;

    public static string $title = 'id';

    public static string $group = 'Attendance';

    public static array $with = ['device'];

    public static array $search = ['id', 'serial_number', 'kind', 'message'];

    public static function label(): string
    {
        return 'Device Events';
    }

    public function title(): string
    {
        return 'Device event #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            DateTime::make('Occurred At', 'occurred_at')->sortable(),

            Text::make('Device', fn () => $this->resource?->device?->name ?? '—'),

            Text::make('Serial Number', 'serial_number')->nullable()->sortable(),

            Text::make('Protocol', 'protocol')->nullable(),

            Badge::make('Kind', 'kind')->map([
                AttendanceDeviceEventModel::KIND_PUSH => 'info',
                AttendanceDeviceEventModel::KIND_PULL => 'success',
                AttendanceDeviceEventModel::KIND_IMPORT => 'info',
                AttendanceDeviceEventModel::KIND_TEST => 'warning',
                AttendanceDeviceEventModel::KIND_ERROR => 'danger',
            ]),

            Text::make('Endpoint', 'endpoint')->nullable()->hideFromIndex(),

            Number::make('HTTP', 'http_status')->nullable()->sortable(),

            Number::make('Records', 'records')->nullable(),
            Number::make('Accepted', 'accepted')->nullable(),
            Number::make('Duplicates', 'duplicates')->nullable(),
            Number::make('Unmatched', 'unmatched')->nullable(),

            Text::make('Message', 'message')->nullable(),

            Text::make('Payload', 'payload')
                ->displayUsing(fn ($value) => $this->formatPayload($value))
                ->asHtml()
                ->onlyOnDetail(),
        ];
    }

    public static function canCreateResource(): bool
    {
        return false;
    }

    public static function authorizedToCreate(): ?bool
    {
        return false;
    }

    public static function authorizedToUpdate(?Model $model = null): ?bool
    {
        return false;
    }

    public static function authorizedToDelete(?Model $model = null): ?bool
    {
        return false;
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
        return [];
    }

    private function formatPayload(mixed $payload): string
    {
        if ($payload === null || $payload === []) {
            return '—';
        }

        return '<pre class="overflow-x-auto rounded bg-slate-50 p-3 text-xs dark:bg-navy-900">'
            .e(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            .'</pre>';
    }
}
