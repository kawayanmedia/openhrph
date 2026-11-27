<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Select;
use App\Models\JobRequisition;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TransitionRequisition extends Action
{
    public string $name = 'Change Status';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        $options = collect(JobRequisition::TRANSITIONS[$this->model?->status] ?? [])
            ->mapWithKeys(fn (string $status): array => [$status => $status])
            ->all();

        return [
            Select::make('New Status', 'status')->options($options)->rules('required'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(RecruitmentService::class);

        foreach ($models as $model) {
            $service->transitionRequisition($model, (string) $fields['status'], $request->user());
        }

        return self::message('Requisition status updated.');
    }
}
