<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\DateTime;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Text;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class IssueJobOffer extends Action
{
    public string $name = 'Issue Offer';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Number::make('Salary', 'salary')->min(0),
            Date::make('Start Date', 'start_date'),
            DateTime::make('Expires At', 'expires_at'),
            Text::make('Terms Reference', 'terms_reference')->rules('nullable', 'max:191'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(RecruitmentService::class);

        $attributes = array_filter([
            'salary' => $fields['salary'] ?? null,
            'start_date' => $fields['start_date'] ?? null,
            'expires_at' => $fields['expires_at'] ?? null,
            'terms_reference' => $fields['terms_reference'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        foreach ($models as $model) {
            $service->issueOffer($model, $attributes, $request->user());
        }

        return self::message('Offer issued.');
    }
}
