<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Models\ClearanceTask;
use App\Services\Separation\SeparationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CompleteClearanceTask extends Action
{
    public string $name = 'Close Clearance Task';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Outcome', 'status')
                ->options([
                    ClearanceTask::STATUS_COMPLETED => 'completed',
                    ClearanceTask::STATUS_WAIVED => 'waived',
                ])
                ->rules('required'),
            Textarea::make('Notes', 'notes')->rules('nullable'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(SeparationService::class);

        foreach ($models as $model) {
            $service->completeClearanceTask($model, (string) $fields['status'], $request->user(), array_filter([
                'notes' => $fields['notes'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));
        }

        return self::message('Clearance task closed.');
    }
}
