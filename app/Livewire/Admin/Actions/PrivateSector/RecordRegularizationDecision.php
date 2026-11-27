<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Models\Employment;
use App\Models\RegularizationDecision;
use App\Services\Employment\EmploymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RecordRegularizationDecision extends Action
{
    public string $name = 'Regularization Decision';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Decision', 'decision')
                ->options([
                    RegularizationDecision::DECISION_REGULARIZE => 'regularize',
                    RegularizationDecision::DECISION_EXTEND => 'extend',
                    RegularizationDecision::DECISION_SEPARATE => 'separate',
                ])
                ->rules('required'),
            Date::make('Effective Date', 'effective_date')->rules('required'),
            Date::make('New Probation End', 'probation_end'),
            Textarea::make('Notes', 'notes')->rules('nullable'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(EmploymentService::class);

        foreach ($models as $model) {
            if (! $model instanceof Employment) {
                continue;
            }

            $service->decideRegularization($model, array_filter([
                'decision' => $fields['decision'] ?? null,
                'effective_date' => $fields['effective_date'] ?? null,
                'probation_end' => $fields['probation_end'] ?? null,
                'notes' => $fields['notes'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''), $request->user());
        }

        return self::message('Regularization decision recorded.');
    }
}
