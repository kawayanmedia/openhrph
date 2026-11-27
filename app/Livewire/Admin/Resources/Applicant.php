<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\JobVacancy;
use Illuminate\Http\Request;
use KawayanMedia\Government\Models\Applicant as ApplicantModel;

class Applicant extends Resource
{
    public static string $model = ApplicantModel::class;

    public static string $title = 'name';

    public static int $priority = 51;

    public static string $group = 'Recruitment';

    public static ?string $sector = 'Private';

    public static array $with = ['jobVacancy'];

    public static array $search = ['name', 'email', 'telephone'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Vacancy', 'jobVacancy')
                ->options(JobVacancy::query()->orderByDesc('id')->limit(300)->pluck('title', 'id')->toArray())
                ->nullable(),

            Text::make('Name')->rules('required', 'max:128')->sortable(),
            Select::make('Gender')
                ->options(['male' => 'male', 'female' => 'female'])
                ->rules('required'),
            Text::make('Telephone')->rules('required', 'max:128'),
            Text::make('Email')->rules('nullable', 'email', 'max:128')->sortable(),
            Text::make('Address')->hideFromIndex(),
            Date::make('Birth Date', 'birth_date')->hideFromIndex(),
            Text::make('Educational Attainment', 'educational_attainment')->hideFromIndex(),
            Text::make('Eligibility')->rules('required', 'max:128')->hideFromIndex(),
            Textarea::make('Training')->hideFromIndex(),
            Textarea::make('Experience')->hideFromIndex(),
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
