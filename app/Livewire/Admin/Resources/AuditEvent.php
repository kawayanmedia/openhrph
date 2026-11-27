<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\AuditEvent as AuditEventModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Read-only audit trail. Rows are written by services, never edited here.
 */
class AuditEvent extends Resource
{
    public static string $model = AuditEventModel::class;

    public static int $priority = 43;

    public static string $group = 'HR';

    public static array $with = ['actor'];

    public static array $search = ['id', 'action', 'description'];

    public function title(): string
    {
        return (string) ($this->resource?->action ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            DateTime::make('Occurred At', 'occurred_at')->sortable(),

            Text::make('Actor', fn () => $this->actor?->name ?? 'System')
                ->onlyOnIndex(),

            Text::make('Action')->sortable(),
            Text::make('Description'),

            Text::make('Subject Type', 'subject_type')->hideFromIndex(),
            Text::make('Subject ID', 'subject_id')->hideFromIndex(),

            Text::make('Before', 'before')
                ->displayUsing(fn ($value) => $this->formatPayload($value))
                ->asHtml()
                ->onlyOnDetail(),

            Text::make('After', 'after')
                ->displayUsing(fn ($value) => $this->formatPayload($value))
                ->asHtml()
                ->onlyOnDetail(),

            Text::make('Metadata', 'metadata')
                ->displayUsing(fn ($value) => $this->formatPayload($value))
                ->asHtml()
                ->onlyOnDetail(),
        ];
    }

    protected function formatPayload(mixed $payload): string
    {
        if ($payload === null || $payload === []) {
            return '—';
        }

        return '<pre class="overflow-x-auto rounded bg-slate-50 p-3 text-xs dark:bg-navy-900">'
            .e(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            .'</pre>';
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
}
