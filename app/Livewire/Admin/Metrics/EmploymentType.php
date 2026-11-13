<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmploymentType extends Metric
{
    public string $name = 'Employment Type';

    public function calculate(Request $request): mixed
    {
        $counts = Employee::query()
            ->selectRaw('employment_status, COUNT(*) as total')
            ->groupBy('employment_status')
            ->pluck('total', 'employment_status');

        $result = [];

        foreach ($counts as $value => $total) {
            $result[$this->employmentLabel($value)] = $total;
        }

        return $result;
    }

    public function component(): string
    {
        return 'partition';
    }

    protected function employmentLabel($value): string
    {
        return match (true) {
            $value === null || $value === '' => 'None',
            (int) $value === 1 => 'Regular',
            (int) $value === 2 => 'Probationary',
            (int) $value === 3 => 'Contractual',
            (int) $value === 4 => 'Consultant',
            default => ucfirst((string) $value),
        };
    }
}
