<?php

namespace App\Jurisdictions\Philippines\Government\Admin\Resources;

use App\Jurisdictions\Philippines\Government\Models\QualificationStandard as QualificationStandardModel;
use App\Livewire\Admin\Actions\Government\ScreenQualification;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\Position;
use Illuminate\Http\Request;

class QualificationStandard extends Resource
{
    public static string $model = QualificationStandardModel::class;

    public static string $title = 'position_title';

    public static int $priority = 21;

    public static string $group = 'Government';

    public static ?string $sector = 'Government';

    public static ?string $jurisdiction = 'PH';

    public static array $search = ['position_title', 'source_reference'];

    public function title(): string
    {
        return (string) ($this->resource?->position_title ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Position')
                ->options(Position::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable()
                ->hideFromIndex(),

            Text::make('Position Title', 'position_title')->rules('nullable', 'max:128')->sortable(),

            Number::make('Version')->min(1)->default(1)->sortable(),

            Textarea::make('Education')->hideFromIndex(),
            Textarea::make('Training')->hideFromIndex(),
            Textarea::make('Experience')->hideFromIndex(),
            Textarea::make('Eligibility')->hideFromIndex(),
            Textarea::make('License')->hideFromIndex(),

            Number::make('Training Hours', 'training_hours')->min(0)->hideFromIndex(),
            Number::make('Experience Years', 'experience_years')->min(0)->hideFromIndex(),

            Boolean::make('Requires Eligibility', 'requires_eligibility'),
            Boolean::make('Requires License', 'requires_license'),

            Date::make('Effective From', 'effective_from'),
            Date::make('Effective Until', 'effective_until'),

            Select::make('Status')
                ->options([
                    QualificationStandardModel::STATUS_DRAFT => QualificationStandardModel::STATUS_DRAFT,
                    QualificationStandardModel::STATUS_APPROVED => QualificationStandardModel::STATUS_APPROVED,
                    QualificationStandardModel::STATUS_RETIRED => QualificationStandardModel::STATUS_RETIRED,
                ])
                ->default(QualificationStandardModel::STATUS_DRAFT),

            Text::make('Source Reference', 'source_reference')
                ->rules('nullable', 'max:191')
                ->hideFromIndex(),
        ];
    }

    public function actions(Request $request): array
    {
        return [new ScreenQualification];
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
