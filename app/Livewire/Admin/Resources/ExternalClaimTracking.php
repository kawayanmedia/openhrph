<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\Retirement\ManageExternalClaim;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\ExternalClaimTracking as ExternalClaimTrackingModel;
use App\Models\RetirementCase as RetirementCaseModel;
use Illuminate\Http\Request;

class ExternalClaimTracking extends Resource
{
    public static string $model = ExternalClaimTrackingModel::class;

    public static string $title = 'reference';

    public static int $priority = 94;

    public static string $group = 'Retirement';

    public static array $with = ['retirementCase.employee'];

    public static array $search = ['agency', 'reference', 'status'];

    public function title(): string
    {
        return (string) ($this->resource?->reference ?? 'Claim #'.($this->resource?->getKey() ?? ''));
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Retirement Case', 'retirementCase')
                ->options(RetirementCaseModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray()),
            Text::make('Agency')->sortable(),
            Text::make('Reference')->rules('nullable', 'max:128'),
            Badge::make('Status')->map([
                ExternalClaimTrackingModel::STATUS_NOT_SUBMITTED => 'default',
                ExternalClaimTrackingModel::STATUS_SUBMITTED => 'info',
                ExternalClaimTrackingModel::STATUS_IN_PROCESS => 'warning',
                ExternalClaimTrackingModel::STATUS_APPROVED => 'success',
                ExternalClaimTrackingModel::STATUS_DENIED => 'danger',
                ExternalClaimTrackingModel::STATUS_RELEASED => 'success',
            ])->sortable(),
            DateTime::make('Submitted At', 'submitted_at'),
            DateTime::make('Last Checked At', 'last_checked_at'),
            DateTime::make('Released At', 'released_at')->hideFromIndex(),
            Textarea::make('Notes')->hideFromIndex(),
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

    public function actions(Request $request): array
    {
        return [(new ManageExternalClaim)->onlyOnTableRow()];
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
