<?php

namespace App\Livewire\Admin\Actions\Government;

use App\Jurisdictions\Philippines\Government\Services\QualificationScreeningService;
use App\Livewire\Admin\Actions\Action;
use App\Livewire\Admin\Fields\Boolean;
use App\Livewire\Admin\Fields\Number;
use App\Livewire\Admin\Fields\Select;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ScreenQualification extends Action
{
    public string $name = 'Screen Employee';

    public function onlyOnTableRow(): static
    {
        parent::onlyOnTableRow();

        return $this;
    }

    public function fields(Request $request): array
    {
        return [
            Select::make('Employee', 'employee_id')
                ->options(Employee::employeeList())
                ->rules('required'),
            Boolean::make('Education Met', 'education_met')->default(false),
            Number::make('Training Hours', 'training_hours')->min(0),
            Number::make('Experience Years', 'experience_years')->min(0),
            Boolean::make('Eligibility Evidenced', 'eligibility_provided')->default(false),
            Boolean::make('License Evidenced', 'license_provided')->default(false),
        ];
    }

    public function handle(Request $request, array $fields, Collection $models): mixed
    {
        $service = app(QualificationScreeningService::class);
        $employee = Employee::query()->findOrFail($fields['employee_id']);

        foreach ($models as $standard) {
            $service->screen($employee, $standard, [
                'education_met' => (bool) ($fields['education_met'] ?? false),
                'training_hours' => (float) ($fields['training_hours'] ?? 0),
                'experience_years' => (float) ($fields['experience_years'] ?? 0),
                'eligibility_provided' => (bool) ($fields['eligibility_provided'] ?? false),
                'license_provided' => (bool) ($fields['license_provided'] ?? false),
            ], $request->user());
        }

        return self::message('Qualification screening recorded.');
    }
}
