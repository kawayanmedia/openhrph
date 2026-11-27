<?php

namespace App\Livewire\Admin\Actions\Retirement;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Services\Retirement\RetirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReviewRetirementCalculation extends Action
{
    public string $name = 'Review Calculation';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Decision', 'decision')
                ->options(['approve' => 'approve', 'reject' => 'reject'])
                ->rules('required'),
            Textarea::make('Reviewer Notes', 'reviewer_notes'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(RetirementService::class);

        foreach ($models as $model) {
            $service->reviewCalculation(
                $model,
                $request->user(),
                $fields['decision'] === 'approve',
                $fields['reviewer_notes'] ?? null,
            );
        }

        return self::message('Calculation reviewed.');
    }
}
