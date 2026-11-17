<?php

namespace App\Livewire\Admin\Metrics;

use Illuminate\Http\Request;

class NewScheduleApplications extends Metric
{
    public string $name = 'New Schedule Applications';

    public function calculate(Request $request): mixed
    {
        return [
            [
                'icon' => 'check-circle',
                'iconClass' => 'text-green-500',
                'title' => 'Tricia Gonzales',
                'subtitle' => '',
            ],
            [
                'icon' => 'check-circle',
                'iconClass' => 'text-green-500',
                'title' => 'Trudis Santos',
                'subtitle' => '',
            ],
        ];
    }

    public function component(): string
    {
        return 'table';
    }
}
