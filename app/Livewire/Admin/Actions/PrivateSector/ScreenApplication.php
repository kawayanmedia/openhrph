<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Models\ScreeningResult;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ScreenApplication extends Action
{
    public string $name = 'Screen';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Result', 'result')
                ->options([
                    ScreeningResult::RESULT_QUALIFIED => ScreeningResult::RESULT_QUALIFIED,
                    ScreeningResult::RESULT_DISQUALIFIED => ScreeningResult::RESULT_DISQUALIFIED,
                ])
                ->rules('required'),
            Textarea::make('Notes', 'notes')->rules('nullable'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(RecruitmentService::class);

        foreach ($models as $model) {
            $service->screen($model, (string) $fields['result'], $fields['notes'] ?? null, $request->user());
        }

        return self::message('Screening recorded.');
    }
}
