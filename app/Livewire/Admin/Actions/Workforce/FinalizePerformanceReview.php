<?php

namespace App\Livewire\Admin\Actions\Workforce;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Number;
use App\Services\Performance\PerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FinalizePerformanceReview extends Action
{
    public string $name = 'Finalize Review';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Number::make('Overall Rating Override', 'overall_rating')
                ->min(0)
                ->help('Leave blank to compute the weighted average of criterion ratings.'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(PerformanceService::class);
        $overall = $fields['overall_rating'] ?? null;

        foreach ($models as $model) {
            $service->finalize($model, $request->user(), $overall === null || $overall === '' ? null : (float) $overall);
        }

        return self::message('Performance review finalized.');
    }
}
