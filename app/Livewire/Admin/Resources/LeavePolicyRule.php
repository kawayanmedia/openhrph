<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Resource;
use App\Models\Employment;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyRule as LeavePolicyRuleModel;
use Illuminate\Http\Request;

class LeavePolicyRule extends Resource
{
    public static string $model = LeavePolicyRuleModel::class;

    public static string $title = 'id';

    public static int $priority = 66;

    public static string $group = 'Employment';

    public static ?string $sector = 'Private';

    public static array $with = ['policy'];

    public static array $search = ['id', 'employment_classification'];

    public function title(): string
    {
        return 'Rule #'.($this->resource?->getKey() ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Leave Policy', 'policy')
                ->options(LeavePolicy::query()->orderBy('leave_type_id')->pluck('leave_type_id', 'id')->toArray())
                ->rules('required'),
            Select::make('Employment Classification', 'employment_classification')
                ->options(array_combine(Employment::CLASSIFICATIONS, Employment::CLASSIFICATIONS))
                ->nullable(),
            Date::make('Effective From', 'effective_from'),
            Date::make('Effective Until', 'effective_until'),
            Number::make('Min Service Months', 'min_service_months')->min(0),
            Number::make('Notice Days', 'notice_days')->min(0),
            Number::make('Max Consecutive Days', 'max_consecutive_days')->min(0),
            Boolean::make('Requires Evidence', 'requires_evidence')->default(false),
            Boolean::make('Allow Half Day', 'allow_half_day')->default(true),
            Boolean::make('Paid', 'is_paid')->default(true),
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
