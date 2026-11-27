<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Models\SeparationCase;
use App\Services\Separation\SeparationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TransitionSeparation extends Action
{
    public string $name = 'Advance Case';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        $options = collect(SeparationCase::TRANSITIONS[$this->model?->status] ?? [])
            ->mapWithKeys(fn (string $status): array => [$status => $status])
            ->all();

        return [
            Select::make('New Status', 'status')->options($options)->rules('required'),
            Date::make('Effective Date', 'effective_date'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(SeparationService::class);

        foreach ($models as $model) {
            $attributes = [];

            if (! empty($fields['effective_date'])) {
                $attributes['effective_date'] = $fields['effective_date'];
            }

            $service->transition($model, (string) $fields['status'], $request->user(), $attributes);
        }

        return self::message('Separation case updated.');
    }
}
