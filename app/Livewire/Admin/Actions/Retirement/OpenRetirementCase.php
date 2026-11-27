<?php

namespace App\Livewire\Admin\Actions\Retirement;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Models\Employee;
use App\Models\RetirementCase;
use App\Models\RetirementPlan;
use App\Services\Retirement\RetirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OpenRetirementCase extends Action
{
    public string $name = 'Open Retirement Case';

    public function fields(Request $request): array
    {
        return [
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->rules('required'),
            BelongsTo::make('Plan')
                ->options(RetirementPlan::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable(),
            Select::make('Retirement Type', 'retirement_type')
                ->options(array_combine(RetirementCase::TYPES, RetirementCase::TYPES))
                ->default(RetirementCase::TYPE_OPTIONAL),
            Date::make('Proposed Retirement Date', 'proposed_retirement_date'),
            Textarea::make('Notes', 'notes'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $employee = Employee::query()->findOrFail($fields['employee']);
        $plan = ! empty($fields['plan']) ? RetirementPlan::query()->find($fields['plan']) : null;

        app(RetirementService::class)->openCase($employee, $plan, [
            'retirement_type' => $fields['retirement_type'] ?? RetirementCase::TYPE_OPTIONAL,
            'proposed_retirement_date' => $fields['proposed_retirement_date'] ?? null,
            'notes' => $fields['notes'] ?? null,
        ], $request->user());

        return self::message('Retirement case opened.');
    }
}
