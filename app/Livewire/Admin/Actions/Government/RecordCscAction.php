<?php

namespace App\Livewire\Admin\Actions\Government;

use App\Jurisdictions\Philippines\Government\Models\AppointmentAction;
use App\Jurisdictions\Philippines\Government\Services\AppointmentService;
use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Livewire\Admin\Fields\Textarea;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RecordCscAction extends Action
{
    public string $name = 'Record CSC Action';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Action', 'action_type')
                ->options(array_combine(AppointmentAction::TYPES, AppointmentAction::TYPES))
                ->rules('required'),
            Text::make('Reference Number', 'reference_number')->rules('nullable', 'max:96'),
            Date::make('Action Date', 'action_date'),
            Textarea::make('Remarks', 'remarks'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(AppointmentService::class);

        foreach ($models as $model) {
            $service->recordAction($model, (string) $fields['action_type'], [
                'reference_number' => $fields['reference_number'] ?? null,
                'action_date' => $fields['action_date'] ?? null,
                'remarks' => $fields['remarks'] ?? null,
                'submitted_at' => now(),
                'status' => 'recorded',
            ], $request->user());
        }

        return self::message('CSC action recorded.');
    }
}
