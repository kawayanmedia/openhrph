<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Fields\Trix;
use App\Livewire\Admin\Resource;
use App\Models\EmploymentType;
use App\Models\JobRequisition;
use App\Models\Location;
use App\Models\Position;
use Illuminate\Http\Request;

class JobVacancy extends Resource
{
    public static string $model = \App\Models\JobVacancy::class;

    public static int $priority = 4;

    public static string $title = 'title';

    public static string $group = 'Company';

    public static array $search = ['id'];

    public function title(): string
    {
        return (string) ($this->resource?->title ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Job Title', 'title')
                ->rules('required'),

            Text::make('Job Type', 'type')
                ->rules('required'),

            Trix::make('Position Summary', 'summary')
                ->rules('required'),

            Text::make('Salary', 'salary')
                ->rules('required'),

            Text::make('Shift and Schedule', 'shift')
                ->rules('required'),

            Boolean::make('Active', 'is_active')->default(true),

            BelongsTo::make('Requisition')
                ->options(JobRequisition::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            BelongsTo::make('Position')
                ->options(Position::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            BelongsTo::make('Employment Type', 'employmentType')
                ->options(EmploymentType::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            BelongsTo::make('Location')
                ->options(Location::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            Number::make('Salary Min', 'salary_min')->min(0)->hideFromIndex(),
            Number::make('Salary Max', 'salary_max')->min(0)->hideFromIndex(),
            Date::make('Published At', 'published_at')->hideFromIndex(),
            Date::make('Closes At', 'closes_at'),

            Badge::make('Status')->map([
                \App\Models\JobVacancy::STATUS_DRAFT => 'default',
                \App\Models\JobVacancy::STATUS_OPEN => 'success',
                \App\Models\JobVacancy::STATUS_ON_HOLD => 'warning',
                \App\Models\JobVacancy::STATUS_CLOSED => 'default',
                \App\Models\JobVacancy::STATUS_FILLED => 'success',
                \App\Models\JobVacancy::STATUS_CANCELLED => 'danger',
            ])->sortable(),

            Textarea::make('Requirements')->hideFromIndex(),
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
