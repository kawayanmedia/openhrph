<?php

namespace App\Livewire\Admin\Actions\Government;

use App\Jurisdictions\Philippines\Government\Services\AppointmentService;
use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RecordAppointmentAssumption extends Action
{
    public string $name = 'Record Assumption';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Date::make('Oath Date', 'oath_date'),
            Date::make('Assumption Date', 'assumption_date')->rules('required'),
            Text::make('Witnessed By', 'witnessed_by'),
            Textarea::make('Remarks', 'remarks'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(AppointmentService::class);

        $attributes = array_filter([
            'oath_date' => $fields['oath_date'] ?? null,
            'assumption_date' => $fields['assumption_date'] ?? null,
            'witnessed_by' => $fields['witnessed_by'] ?? null,
            'remarks' => $fields['remarks'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        foreach ($models as $model) {
            $service->recordAssumption($model, $attributes, $request->user());
        }

        return self::message('Assumption recorded.');
    }
}
