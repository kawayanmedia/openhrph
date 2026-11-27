<?php

namespace App\Livewire\Admin\Actions\Government;

use App\Jurisdictions\Philippines\Government\Models\PersonnelAction;
use App\Jurisdictions\Philippines\Government\Services\PersonnelActionService;
use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdvancePersonnelAction extends Action
{
    public string $name = 'Advance Action';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        $current = $this->model?->status;
        $options = collect(PersonnelAction::TRANSITIONS[$current] ?? [])
            ->mapWithKeys(fn (string $status): array => [$status => $status])
            ->all();

        return [
            Select::make('New Status', 'status')
                ->options($options)
                ->rules('required'),
            Date::make('Effective Date', 'effective_date'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(PersonnelActionService::class);

        foreach ($models as $model) {
            $target = (string) $fields['status'];
            $attributes = [];

            if (! empty($fields['effective_date'])) {
                $attributes['effective_date'] = $fields['effective_date'];
            }

            if ($target === PersonnelAction::STATUS_COMPLETED) {
                if ($attributes !== []) {
                    $model->forceFill($attributes)->save();
                }

                $service->complete($model->refresh(), $request->user());
            } else {
                $service->transition($model, $target, $request->user(), $attributes);
            }
        }

        return self::message('Personnel action updated.');
    }
}
