<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\AppointmentAction as AppointmentActionModel;
use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord as AppointmentRecordModel;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AppointmentAction extends Resource
{
    public static string $model = AppointmentActionModel::class;

    public static string $title = 'action_type';

    public static int $priority = 24;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $with = ['appointment', 'recorder'];

    public static array $search = ['action_type', 'reference_number'];

    public function title(): string
    {
        return (string) ($this->resource?->action_type ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Appointment', 'appointment')
                ->options(AppointmentRecordModel::query()->orderByDesc('id')->limit(200)->pluck('reference_number', 'id')->toArray()),
            Text::make('Action Type', 'action_type')->sortable(),
            Text::make('Reference Number', 'reference_number'),
            Date::make('Action Date', 'action_date'),
            DateTime::make('Submitted At', 'submitted_at')->hideFromIndex(),
            Text::make('Status')->hideFromIndex(),
            Textarea::make('Remarks')->hideFromIndex(),
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
}
