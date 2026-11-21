<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\Payroll;
use Illuminate\Http\Request;

class NewPayroll extends Metric
{
    public string $name = 'Latest Payroll Transactions';

    public function calculate(Request $request): mixed
    {
        return Payroll::query()
            ->latest('payroll_date')
            ->limit(5)
            ->get()
            ->map(fn (Payroll $payroll): array => [
                'title' => '#'.$payroll->getKey().' · '.optional($payroll->payroll_date)->format('M d, Y'),
                'subtitle' => ucfirst((string) $payroll->status),
            ])
            ->all();
    }

    public function component(): string
    {
        return 'table';
    }
}
