<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Models\Employment;
use App\Models\EmploymentType;
use App\Services\Employment\EmploymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StartEmployment extends Action
{
    public string $name = 'Start Employment';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Employment Type', 'employment_type_id')
                ->options(EmploymentType::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->nullable(),
            Select::make('Classification', 'classification')
                ->options(array_combine(Employment::CLASSIFICATIONS, Employment::CLASSIFICATIONS))
                ->default(Employment::CLASSIFICATION_REGULAR)
                ->rules('required'),
            Date::make('Start Date', 'start_date')->rules('required'),
            Date::make('Probation End', 'probation_end'),
            Date::make('End Date', 'end_date'),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(EmploymentService::class);

        foreach ($models as $model) {
            $service->start($model, array_filter([
                'employment_type_id' => $fields['employment_type_id'] ?? null,
                'classification' => $fields['classification'] ?? null,
                'start_date' => $fields['start_date'] ?? null,
                'probation_end' => $fields['probation_end'] ?? null,
                'end_date' => $fields['end_date'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''), $request->user());
        }

        return self::message('Employment started.');
    }
}
