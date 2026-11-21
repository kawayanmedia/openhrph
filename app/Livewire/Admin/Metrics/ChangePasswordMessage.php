<?php

namespace App\Livewire\Admin\Metrics;

use Illuminate\Http\Request;

class ChangePasswordMessage extends Metric
{
    public string $name = 'Account security';

    public function calculate(Request $request): mixed
    {
        return [];
    }

    public function component(): string
    {
        return 'table';
    }
}
