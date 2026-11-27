<?php

namespace App\Livewire\Admin\Actions\Workforce;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Textarea;
use App\Models\TrainingEnrollment;
use App\Services\Learning\LearningService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CompleteTrainingEnrollment extends Action
{
    public string $name = 'Record Completion';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Status', 'status')
                ->options([
                    TrainingEnrollment::STATUS_ATTENDED => 'attended',
                    TrainingEnrollment::STATUS_COMPLETED => 'completed',
                    TrainingEnrollment::STATUS_FAILED => 'failed',
                    TrainingEnrollment::STATUS_WITHDRAWN => 'withdrawn',
                ])
                ->rules('required'),
            Number::make('Score')->min(0),
            Textarea::make('Evaluation', 'evaluation'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(LearningService::class);
        $score = $fields['score'] ?? null;

        foreach ($models as $model) {
            $service->recordCompletion(
                $model,
                (string) $fields['status'],
                $score === null || $score === '' ? null : (float) $score,
                $fields['evaluation'] ?? null,
                $request->user(),
            );
        }

        return self::message('Training completion recorded.');
    }
}
