<?php

namespace Tests\Fixtures;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Text;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ModelAwareOfficeAction extends Action
{
    public static ?int $lastModelId = null;

    public string $name = 'Inspect';

    public function fields(Request $request): array
    {
        return [
            Text::make('Model Id', 'model_id')->default($this->model?->getKey()),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        static::$lastModelId = $fields['model_id'] ?? null;

        return self::message('Inspected.');
    }
}
