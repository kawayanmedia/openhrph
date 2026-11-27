<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\User;
use App\Models\WorkflowDelegation as WorkflowDelegationModel;
use Illuminate\Http\Request;

class WorkflowDelegation extends Resource
{
    public static string $model = WorkflowDelegationModel::class;

    public static int $priority = 42;

    public static string $group = 'HR';

    public static array $with = ['delegator', 'delegatee'];

    public static array $search = ['id'];

    public function title(): string
    {
        return (string) ($this->resource?->delegator?->name ?? '');
    }

    /**
     * @return array<int|string, string>
     */
    protected function users(): array
    {
        return User::query()->orderBy('name')->pluck('name', 'id')->toArray();
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Delegator', 'delegator')
                ->options($this->users())
                ->rules('required'),

            BelongsTo::make('Delegatee', 'delegatee')
                ->options($this->users())
                ->rules('required'),

            Date::make('Starts At', 'starts_at')->rules('required')->sortable(),
            Date::make('Ends At', 'ends_at')->rules('required'),

            Text::make('Reason')->hideFromIndex(),

            Boolean::make('Active', 'is_active')->default(true),
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
