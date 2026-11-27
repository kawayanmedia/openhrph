<?php

namespace App\Livewire\Admin\Actions\Retirement;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use App\Models\ExternalClaimTracking;
use App\Services\Retirement\RetirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ManageExternalClaim extends Action
{
    public string $name = 'Update Claim';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Status')
                ->options(array_combine(ExternalClaimTracking::STATUSES, ExternalClaimTracking::STATUSES))
                ->rules('required'),
            Text::make('Reference')->rules('nullable', 'max:128'),
            Textarea::make('Notes', 'notes'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(RetirementService::class);

        foreach ($models as $model) {
            $service->trackExternalClaim($model->retirementCase, [
                'agency' => $model->agency,
                'status' => $fields['status'],
                'reference' => $fields['reference'] ?? $model->reference,
                'notes' => $fields['notes'] ?? $model->notes,
            ], $request->user());
        }

        return self::message('External claim updated.');
    }
}
