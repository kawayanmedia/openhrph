<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employment as EmploymentModel;
use App\Models\RegularizationDecision as RegularizationDecisionModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RegularizationDecision extends Resource
{
    public static string $model = RegularizationDecisionModel::class;

    public static string $title = 'decision';

    public static int $priority = 63;

    public static string $group = 'Employment';

    public static ?string $sector = 'Private';

    public static array $with = ['employment', 'approver'];

    public static array $search = ['decision', 'approval_reference'];

    public function title(): string
    {
        return (string) ($this->resource?->decision ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Employment')
                ->options(EmploymentModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray()),
            Badge::make('Decision')->map([
                RegularizationDecisionModel::DECISION_REGULARIZE => 'success',
                RegularizationDecisionModel::DECISION_EXTEND => 'warning',
                RegularizationDecisionModel::DECISION_SEPARATE => 'danger',
            ])->sortable(),
            Date::make('Effective Date', 'effective_date')->sortable(),
            Text::make('Approval Reference', 'approval_reference'),
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
