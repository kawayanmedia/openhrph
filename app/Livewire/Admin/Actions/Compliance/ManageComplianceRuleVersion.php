<?php

namespace App\Livewire\Admin\Actions\Compliance;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Select;
use App\Services\Compliance\ComplianceRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ManageComplianceRuleVersion extends Action
{
    public string $name = 'Review Version';

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
                    'submit' => 'submit for review',
                    'approve' => 'approve',
                ])
                ->rules('required'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $registry = app(ComplianceRegistry::class);

        foreach ($models as $model) {
            match ($fields['action']) {
                'submit' => $registry->submitForReview($model, $request->user()),
                'approve' => $registry->approveVersion($model, $request->user()),
            };
        }

        return self::message('Compliance rule version updated.');
    }
}
