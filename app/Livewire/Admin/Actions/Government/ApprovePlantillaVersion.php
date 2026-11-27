<?php

namespace App\Livewire\Admin\Actions\Government;

use App\Jurisdictions\Philippines\Government\Services\PlantillaService;
use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Text;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ApprovePlantillaVersion extends Action
{
    public string $name = 'Approve Version';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Text::make('Approval Reference', 'approval_reference')
                ->rules('nullable', 'max:128'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(PlantillaService::class);

        foreach ($models as $model) {
            $service->approveVersion($model, $request->user(), $fields['approval_reference'] ?? null);
        }

        return self::message('Plantilla version approved.');
    }
}
