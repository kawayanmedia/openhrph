<?php

namespace App\Livewire\Admin\Actions\Workforce;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\BelongsTo;
use App\Models\Employee;
use App\Services\Performance\PerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StartPerformanceReview extends Action
{
    public string $name = 'Start Review';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            BelongsTo::make('Employee')
                ->options(Employee::employeeList())
                ->rules('required'),
            BelongsTo::make('Reviewer')
                ->options(Employee::employeeList())
                ->nullable(),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(PerformanceService::class);
        $employee = Employee::query()->findOrFail($fields['employee']);
        $reviewer = ! empty($fields['reviewer']) ? Employee::query()->find($fields['reviewer']) : null;

        foreach ($models as $model) {
            $service->startReview($model, $employee, $reviewer, $request->user());
        }

        return self::message('Performance review started.');
    }
}
