<?php

namespace App\Livewire\Admin\Resources;

use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\ID;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Resource;
use App\Models\Employee;
use App\Models\ReportingRelationship as ReportingRelationshipModel;
use App\Services\Organization\PositionAssignmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ReportingRelationship extends Resource
{
    public static string $model = ReportingRelationshipModel::class;

    public static int $priority = 37;

    public static string $group = 'HR';

    public static array $with = ['employee', 'supervisor'];

    public static array $search = ['id'];

    public function title(): string
    {
        return (string) ($this->resource?->employee?->fullname ?? '');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->rules('required')
                ->sortable(),

            BelongsTo::make('Supervisor')
                ->options(Employee::employeeList())
                ->rules('required'),

            Select::make('Relationship Type', 'relationship_type')
                ->options([
                    ReportingRelationshipModel::TYPE_SUPERVISOR => ReportingRelationshipModel::TYPE_SUPERVISOR,
                    ReportingRelationshipModel::TYPE_MANAGER => ReportingRelationshipModel::TYPE_MANAGER,
                    ReportingRelationshipModel::TYPE_HEAD => ReportingRelationshipModel::TYPE_HEAD,
                ])
                ->default(ReportingRelationshipModel::TYPE_SUPERVISOR),

            Date::make('Effective From', 'effective_from')->rules('required')->sortable(),
            Date::make('Effective Until', 'effective_until'),

            Text::make('Notes')->hideFromIndex(),
        ];
    }

    public static function afterCreate(Request $request, Model $model): void
    {
        app(PositionAssignmentService::class)->applyReportingRelationship($model);
    }

    public static function afterUpdate(Request $request, Model $model): void
    {
        app(PositionAssignmentService::class)->applyReportingRelationship($model);
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
