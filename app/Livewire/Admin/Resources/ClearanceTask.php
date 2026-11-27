<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\PrivateSector\CompleteClearanceTask;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\ClearanceTask as ClearanceTaskModel;
use App\Models\SeparationCase as SeparationCaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ClearanceTask extends Resource
{
    public static string $model = ClearanceTaskModel::class;

    public static string $title = 'task';

    public static int $priority = 72;

    public static string $group = 'Separation';

    public static ?string $sector = 'Private';

    public static array $with = ['separationCase', 'assignee'];

    public static array $search = ['task', 'unit'];

    public function title(): string
    {
        return (string) ($this->resource?->task ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Separation Case', 'separationCase')
                ->options(SeparationCaseModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray())
                ->rules('required'),
            Text::make('Unit')->rules('required', 'max:96'),
            Text::make('Task')->rules('required', 'max:191'),
            BelongsTo::make('Assignee')
                ->options(User::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable(),
            Badge::make('Status')->map([
                ClearanceTaskModel::STATUS_PENDING => 'warning',
                ClearanceTaskModel::STATUS_COMPLETED => 'success',
                ClearanceTaskModel::STATUS_WAIVED => 'default',
            ])->sortable(),
            DateTime::make('Completed At', 'completed_at')->hideFromIndex(),
            Textarea::make('Notes')->hideFromIndex(),
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

    public function actions(Request $request): array
    {
        return [new CompleteClearanceTask];
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
}
