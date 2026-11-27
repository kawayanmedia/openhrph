<?php

namespace App\Livewire\Admin\Actions\Workforce;

use App\Livewire\Admin\Actions\Action;
use App\Services\Performance\PerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ActivatePerformanceCycle extends Action
{
    public string $name = 'Activate Cycle';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(PerformanceService::class);

        foreach ($models as $model) {
            $service->activateCycle($model, $request->user());
        }

        return self::message('Performance cycle activated.');
    }
}
