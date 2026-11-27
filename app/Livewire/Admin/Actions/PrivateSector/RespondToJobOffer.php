<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Select;
use App\Models\JobOffer;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RespondToJobOffer extends Action
{
    public string $name = 'Record Offer Response';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Response', 'status')
                ->options([
                    JobOffer::STATUS_ACCEPTED => 'accepted',
                    JobOffer::STATUS_DECLINED => 'declined',
                    JobOffer::STATUS_WITHDRAWN => 'withdrawn',
                ])
                ->rules('required'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(RecruitmentService::class);

        foreach ($models as $model) {
            $service->respondToOffer($model, (string) $fields['status'], $request->user());
        }

        return self::message('Offer response recorded.');
    }
}
