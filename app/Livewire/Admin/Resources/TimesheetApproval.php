<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Timesheet;
use App\Models\TimesheetApproval as TimesheetApprovalModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TimesheetApproval extends Resource
{
    public static string $model = TimesheetApprovalModel::class;

    public static string $title = 'action';

    public static int $priority = 81;

    public static string $group = 'Workforce';

    public static array $with = ['timesheet', 'actor'];

    public static array $search = ['action'];

    public function title(): string
    {
        return (string) ($this->resource?->action ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Timesheet')
                ->options(Timesheet::query()->orderByDesc('log_date')->limit(300)->pluck('id', 'id')->toArray()),
            BelongsTo::make('Actor')
                ->options(User::query()->orderBy('name')->pluck('name', 'id')->toArray()),
            Text::make('Action')->sortable(),
            Text::make('Comments')->hideFromIndex(),
            DateTime::make('Decided At', 'decided_at')->sortable(),
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

    public static function authorizedToUpdate(Model $model): ?bool
    {
        return false;
    }

    public static function authorizedToDelete(Model $model): ?bool
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
