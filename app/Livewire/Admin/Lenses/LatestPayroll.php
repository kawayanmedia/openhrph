<?php

namespace App\Livewire\Admin\Lenses;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LatestPayroll extends Lens
{
    public string $name = 'Latest Payroll';

    public function query(Request $request, Builder $query): Builder
    {
        return $query;
    }
}
