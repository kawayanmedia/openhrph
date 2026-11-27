<?php

namespace App\Livewire\Admin\Actions\Government;

use App\Jurisdictions\Philippines\Government\Models\AppointmentRecord;
use App\Jurisdictions\Philippines\Government\Services\AppointmentService;
use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Select;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TransitionAppointment extends Action
{
    public string $name = 'Change Status';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        $current = $this->model?->status;
        $options = collect(AppointmentRecord::TRANSITIONS[$current] ?? [])
            ->mapWithKeys(fn (string $status): array => [$status => $status])
            ->all();

        return [
            Select::make('New Status', 'status')
                ->options($options)
                ->rules('required'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(AppointmentService::class);

        foreach ($models as $model) {
            $service->transition($model, (string) $fields['status'], $request->user());
        }

        return self::message('Appointment status updated.');
    }
}
