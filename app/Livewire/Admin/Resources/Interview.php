<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Application as ApplicationModel;
use App\Models\Interview as InterviewModel;
use Illuminate\Http\Request;

class Interview extends Resource
{
    public static string $model = InterviewModel::class;

    public static string $title = 'id';

    public static int $priority = 54;

    public static string $group = 'Recruitment';

    public static ?string $sector = 'Private';

    public static array $with = ['application'];

    public static array $search = ['id', 'stage'];

    public function title(): string
    {
        return 'Interview #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Application')
                ->options(ApplicationModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray())
                ->rules('required'),
            DateTime::make('Scheduled At', 'scheduled_at')->rules('required')->sortable(),
            Text::make('Stage')->default('interview')->rules('required', 'max:64'),
            Select::make('Status')
                ->options([
                    InterviewModel::STATUS_SCHEDULED => InterviewModel::STATUS_SCHEDULED,
                    InterviewModel::STATUS_COMPLETED => InterviewModel::STATUS_COMPLETED,
                    InterviewModel::STATUS_CANCELLED => InterviewModel::STATUS_CANCELLED,
                    InterviewModel::STATUS_NO_SHOW => InterviewModel::STATUS_NO_SHOW,
                ])
                ->default(InterviewModel::STATUS_SCHEDULED),
            Select::make('Mode')
                ->options(['onsite' => 'onsite', 'virtual' => 'virtual', 'phone' => 'phone'])
                ->default('onsite'),
            Text::make('Location')->rules('nullable', 'max:191'),
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
