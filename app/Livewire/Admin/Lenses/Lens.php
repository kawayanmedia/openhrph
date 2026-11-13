<?php

namespace App\Livewire\Admin\Lenses;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class Lens
{
    public string $name = '';

    public function name(): string
    {
        return $this->name ?: class_basename(static::class);
    }

    public function uriKey(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    abstract public function query(Request $request, Builder $query): Builder;
}
