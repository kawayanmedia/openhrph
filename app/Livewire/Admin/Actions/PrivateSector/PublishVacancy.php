<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Text;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PublishVacancy extends Action
{
    public string $name = 'Publish Vacancy';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Text::make('Job Title', 'title')->rules('nullable', 'max:255'),
            Date::make('Closes At', 'closes_at'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(RecruitmentService::class);

        foreach ($models as $model) {
            $service->publishVacancy($model, array_filter([
                'title' => $fields['title'] ?? null,
                'closes_at' => $fields['closes_at'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''), $request->user());
        }

        return self::message('Vacancy published.');
    }
}
