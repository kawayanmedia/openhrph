<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\Office;
use Illuminate\Http\Request;

class TotalOffices extends Metric
{
    public string $name = 'Total Offices';

    public function calculate(Request $request): mixed
    {
        return Office::count();
    }
}
