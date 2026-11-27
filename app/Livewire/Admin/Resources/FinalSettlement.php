<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\Badge;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Currency;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\FinalSettlement as FinalSettlementModel;
use App\Models\SeparationCase as SeparationCaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class FinalSettlement extends Resource
{
    public static string $model = FinalSettlementModel::class;

    public static string $title = 'id';

    public static int $priority = 73;

    public static string $group = 'Separation';

    public static ?string $sector = 'Private';

    public static array $with = ['separationCase'];

    public static array $search = ['id', 'calculation_reference'];

    public function title(): string
    {
        return 'Settlement #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Separation Case', 'separationCase')
                ->options(SeparationCaseModel::query()->orderByDesc('id')->limit(300)->pluck('id', 'id')->toArray()),
            Text::make('Calculation Reference', 'calculation_reference'),
            Currency::make('Total Amount', 'total_amount'),
            Badge::make('Status')->map([
                FinalSettlementModel::STATUS_DRAFT => 'default',
                FinalSettlementModel::STATUS_REVIEW => 'warning',
                FinalSettlementModel::STATUS_APPROVED => 'success',
                FinalSettlementModel::STATUS_PAID => 'success',
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
