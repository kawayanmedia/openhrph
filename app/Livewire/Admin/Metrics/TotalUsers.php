<?php

namespace App\Livewire\Admin\Metrics;

use App\Models\User;
use Illuminate\Http\Request;

class TotalUsers extends Metric
{
    public string $name = 'Total Users';

    public function calculate(Request $request): mixed
    {
        return User::count();
    }
}
