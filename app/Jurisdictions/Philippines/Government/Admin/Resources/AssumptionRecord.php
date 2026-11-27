<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord as AppointmentRecordModel;
use App\Jurisdictions\Philippines\Government\Models\AssumptionRecord as AssumptionRecordModel;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AssumptionRecord extends Resource
{
    public static string $model = AssumptionRecordModel::class;

    public static string $title = 'assumption_date';

    public static int $priority = 25;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $with = ['appointment'];

    public static array $search = ['remarks'];

    public function title(): string
    {
        return (string) ($this->resource?->assumption_date?->toDateString() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Appointment', 'appointment')
                ->options(AppointmentRecordModel::query()->orderByDesc('id')->limit(200)->pluck('reference_number', 'id')->toArray()),
            Date::make('Oath Date', 'oath_date'),
            Date::make('Assumption Date', 'assumption_date')->sortable(),
            BelongsTo::make('Document')
                ->options(Document::query()->orderBy('title')->pluck('title', 'id')->toArray())
                ->nullable(),
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
