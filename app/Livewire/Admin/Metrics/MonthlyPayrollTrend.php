<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\Payroll;
use Illuminate\Http\Request;

class MonthlyPayrollTrend extends Metric
{
    public string $name = 'Monthly Payroll Trend';

    public function calculate(Request $request): mixed
    {
        $counts = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $label = $date->format('Y-m-d');

            $counts[$label] = Payroll::query()
                ->whereDate('created_at', $date->toDateString())
                ->count();
        }

        return $counts;
    }

    public function component(): string
    {
        return 'trend';
    }
}
