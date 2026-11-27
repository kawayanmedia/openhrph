<?php

namespace App\Livewire\Admin\Actions\Retirement;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Models\RetirementCase;
use App\Services\Retirement\RetirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ManageRetirementCase extends Action
{
    public string $name = 'Update Case';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Action', 'action')
                ->options([
                    'assess' => 'assess eligibility',
                    'submit' => 'submit application',
                    'approve' => 'approve application',
                    'complete' => 'complete retirement',
                    'cancel' => 'cancel case',
                ])
                ->rules('required'),
            Date::make('Proposed Retirement Date', 'proposed_retirement_date'),
            Textarea::make('Notes', 'notes'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(RetirementService::class);

        foreach ($models as $model) {
            $attributes = array_filter([
                'proposed_retirement_date' => $fields['proposed_retirement_date'] ?? null,
                'notes' => $fields['notes'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            match ($fields['action']) {
                'assess' => $service->assessEligibility($model, $request->user()),
                'submit' => $service->transition($model, RetirementCase::STATUS_APPLIED, $request->user(), $attributes),
                'approve' => $service->transition($model, RetirementCase::STATUS_APPROVED, $request->user(), $attributes),
                'complete' => $service->transition($model, RetirementCase::STATUS_COMPLETED, $request->user(), $attributes),
                'cancel' => $service->transition($model, RetirementCase::STATUS_CANCELLED, $request->user(), $attributes),
            };
        }

        return self::message('Retirement case updated.');
    }
}
