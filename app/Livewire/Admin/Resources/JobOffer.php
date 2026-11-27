<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\PrivateSector\RespondToJobOffer;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Application as ApplicationModel;
use App\Models\JobOffer as JobOfferModel;
use Illuminate\Http\Request;

class JobOffer extends Resource
{
    public static string $model = JobOfferModel::class;

    public static string $title = 'id';

    public static int $priority = 57;

    public static string $group = 'Recruitment';

    public static ?string $sector = 'Private';

    public static array $with = ['application'];

    public static array $search = ['id', 'terms_reference'];

    public function title(): string
    {
        return 'Offer #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Application')
                ->options(ApplicationModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray())
                ->rules('required'),
            Number::make('Offer Version', 'offer_version')->readonly()->default(1),
            Number::make('Salary')->min(0),
            Date::make('Start Date', 'start_date'),
            DateTime::make('Expires At', 'expires_at'),
            Badge::make('Status')->map([
                JobOfferModel::STATUS_DRAFT => 'default',
                JobOfferModel::STATUS_ISSUED => 'info',
                JobOfferModel::STATUS_ACCEPTED => 'success',
                JobOfferModel::STATUS_DECLINED => 'danger',
                JobOfferModel::STATUS_EXPIRED => 'danger',
                JobOfferModel::STATUS_WITHDRAWN => 'default',
            ])->sortable(),
            Text::make('Terms Reference', 'terms_reference')->rules('nullable', 'max:191'),
        ];
    }

    public function actions(Request $request): array
    {
        return [new RespondToJobOffer];
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
