<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\HrmpsbCommittee as HrmpsbCommitteeModel;
use App\Jurisdictions\Philippines\Government\Models\RecruitmentDeliberation as RecruitmentDeliberationModel;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Document;
use Illuminate\Http\Request;
use KawayanMedia\Government\Models\JobVacancy;

class RecruitmentDeliberation extends Resource
{
    public static string $model = RecruitmentDeliberationModel::class;

    public static string $title = 'reference_number';

    public static int $priority = 29;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $with = ['committee', 'minutes'];

    public static array $search = ['reference_number', 'recommendation'];

    public function title(): string
    {
        return (string) ($this->resource?->reference_number ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Vacancy', 'vacancy')
                ->options(JobVacancy::query()->orderByDesc('id')->limit(200)->pluck('title', 'id')->toArray())
                ->nullable(),
            BelongsTo::make('Committee')
                ->options(HrmpsbCommitteeModel::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable(),
            Date::make('Meeting Date', 'meeting_date')->rules('required')->sortable(),
            Text::make('Reference Number', 'reference_number')->rules('nullable', 'max:96'),
            BelongsTo::make('Minutes', 'minutes')
                ->options(Document::query()->orderBy('title')->pluck('title', 'id')->toArray())
                ->nullable(),
            Textarea::make('Recommendation')->hideFromIndex(),
            Text::make('Status')->hideFromIndex(),
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
