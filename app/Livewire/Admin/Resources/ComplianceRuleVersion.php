<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Actions\Compliance\ManageComplianceRuleVersion;
use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Livewire\Admin\Resource;
use App\Models\ComplianceRule as ComplianceRuleModel;
use App\Models\ComplianceRuleVersion as ComplianceRuleVersionModel;
use Illuminate\Http\Request;

class ComplianceRuleVersion extends Resource
{
    public static string $model = ComplianceRuleVersionModel::class;

    public static string $title = 'version';

    public static int $priority = 97;

    public static string $group = 'Compliance';

    public static array $with = ['rule'];

    public static array $search = ['version', 'status'];

    public function title(): string
    {
        return (string) ($this->resource?->version ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Rule')
                ->options(ComplianceRuleModel::query()->orderBy('code')->pluck('code', 'id')->toArray()),
            Text::make('Version')->sortable(),
            Badge::make('Status')->map([
                ComplianceRuleVersionModel::STATUS_DRAFT => 'default',
                ComplianceRuleVersionModel::STATUS_REVIEWED => 'warning',
                ComplianceRuleVersionModel::STATUS_APPROVED => 'success',
                ComplianceRuleVersionModel::STATUS_REJECTED => 'danger',
            ])->sortable(),
            DateTime::make('Reviewed At', 'reviewed_at')->hideFromIndex(),
            DateTime::make('Approved At', 'approved_at')->hideFromIndex(),
            Textarea::make('Change Summary', 'change_summary')->hideFromIndex(),
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
        return [(new ManageComplianceRuleVersion)->onlyOnTableRow()];
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
