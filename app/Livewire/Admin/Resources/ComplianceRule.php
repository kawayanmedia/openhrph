<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\Compliance\RegisterComplianceRule;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\ComplianceRule as ComplianceRuleModel;
use App\Models\ComplianceSource as ComplianceSourceModel;
use Illuminate\Http\Request;

class ComplianceRule extends Resource
{
    public static string $model = ComplianceRuleModel::class;

    public static string $title = 'name';

    public static int $priority = 96;

    public static string $group = 'Compliance';

    public static array $with = ['source'];

    public static array $search = ['code', 'name', 'sector', 'status'];

    public function title(): string
    {
        return (string) ($this->resource?->name ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Source')
                ->options(ComplianceSourceModel::query()->orderBy('title')->pluck('title', 'id')->toArray()),
            Text::make('Code')->sortable(),
            Text::make('Name')->sortable(),
            Text::make('Sector')->rules('nullable', 'max:32'),
            Text::make('Classification', 'classification_code')->hideFromIndex(),
            Text::make('Version')->sortable(),
            Date::make('Effective From', 'effective_from')->sortable(),
            Date::make('Effective Until', 'effective_until'),
            Badge::make('Status')->map([
                ComplianceRuleModel::STATUS_DRAFT => 'default',
                ComplianceRuleModel::STATUS_IN_REVIEW => 'warning',
                ComplianceRuleModel::STATUS_APPROVED => 'success',
                ComplianceRuleModel::STATUS_RETIRED => 'danger',
            ])->sortable(),
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
        return [(new RegisterComplianceRule)->onlyOnIndexToolbar()];
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
