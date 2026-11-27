<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\AppointmentDocument as AppointmentDocumentModel;
use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord as AppointmentRecordModel;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Document;
use Illuminate\Http\Request;

class AppointmentDocument extends Resource
{
    public static string $model = AppointmentDocumentModel::class;

    public static string $title = 'document_type';

    public static int $priority = 23;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $search = ['document_type'];

    public function title(): string
    {
        return (string) ($this->resource?->document_type ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Appointment', 'appointment')
                ->options(AppointmentRecordModel::query()->orderByDesc('id')->limit(200)->pluck('reference_number', 'id')->toArray())
                ->rules('required'),
            BelongsTo::make('Document')
                ->options(Document::query()->orderBy('title')->pluck('title', 'id')->toArray())
                ->nullable(),
            Text::make('Document Type', 'document_type')->rules('required', 'max:64'),
            Number::make('Version')->min(1)->default(1),
            Textarea::make('Notes')->hideFromIndex(),
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
        return [];
    }
}
