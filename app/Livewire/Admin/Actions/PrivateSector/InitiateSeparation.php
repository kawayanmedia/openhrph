<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Models\ClearanceTemplate;
use App\Models\SeparationCase;
use App\Services\Separation\SeparationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InitiateSeparation extends Action
{
    public string $name = 'Initiate Separation';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Separation Type', 'separation_type')
                ->options(array_combine(SeparationCase::TYPES, SeparationCase::TYPES))
                ->rules('required'),
            Date::make('Proposed Effective Date', 'proposed_effective_date')->rules('required'),
            Textarea::make('Reason', 'reason')->rules('nullable'),
            Select::make('Clearance Template', 'template_id')
                ->options(ClearanceTemplate::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable(),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(SeparationService::class);

        $attributes = array_filter([
            'separation_type' => $fields['separation_type'] ?? null,
            'proposed_effective_date' => $fields['proposed_effective_date'] ?? null,
            'reason' => $fields['reason'] ?? null,
            'template_id' => $fields['template_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        foreach ($models as $model) {
            $service->initiate($model, $attributes, $request->user());
        }

        return self::message('Separation case initiated.');
    }
}
