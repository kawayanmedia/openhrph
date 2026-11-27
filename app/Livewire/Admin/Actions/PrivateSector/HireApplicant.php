<?php

namespace App\Livewire\Admin\Actions\PrivateSector;

use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Date;
use App\Livewire\Admin\Fields\Select;
use App\Livewire\Admin\Fields\Text;
use App\Models\Employment;
use App\Services\Recruitment\RecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class HireApplicant extends Action
{
    public string $name = 'Hire / Convert';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Date::make('Start Date', 'start_date')->rules('required'),
            Select::make('Classification', 'classification')
                ->options(array_combine(Employment::CLASSIFICATIONS, Employment::CLASSIFICATIONS))
                ->default(Employment::CLASSIFICATION_REGULAR)
                ->rules('required'),
            Date::make('Probation End', 'probation_end'),
            Text::make('Employee Number', 'employee_number')->rules('nullable', 'max:32'),
            Boolean::make('Override accepted offer', 'override_offer')->default(false),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(RecruitmentService::class);

        foreach ($models as $model) {
            $service->hire($model, array_filter([
                'start_date' => $fields['start_date'] ?? null,
                'classification' => $fields['classification'] ?? null,
                'probation_end' => $fields['probation_end'] ?? null,
                'employee_number' => $fields['employee_number'] ?? null,
                'override_offer' => (bool) ($fields['override_offer'] ?? false),
            ], fn ($value) => $value !== null && $value !== ''), $request->user());
        }

        return self::message('Applicant converted to employee.');
    }
}
