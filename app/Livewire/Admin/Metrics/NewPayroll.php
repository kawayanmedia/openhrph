<?php

namespace App\Livewire\Admin\Metrics;

use Illuminate\Http\Request;

class NewPayroll extends Metric
{
    public string $name = 'Latest Payroll Transactions';

    public function calculate(Request $request): mixed
    {
        return [
            [
                'icon' => 'check-circle',
                'iconClass' => 'text-grey-500',
                'title' => 'Payrol for the month of May 2017',
                'subtitle' => 'The payroll is processing...',
            ],
            [
                'icon' => 'check-circle',
                'iconClass' => 'text-green-500',
                'title' => 'Payrol for the month of April 2017',
                'subtitle' => 'The payroll is already credited to bank account.',
            ],
        ];
    }

    public function component(): string
    {
        return 'table';
    }
}
