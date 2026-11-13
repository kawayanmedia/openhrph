<?php

namespace App\Livewire\Admin\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserType extends Filter
{
    public function apply(Request $request, Builder $query, mixed $value): Builder
    {
        return $query;
    }

    public function options(Request $request): array
    {
        return [];
    }
}
