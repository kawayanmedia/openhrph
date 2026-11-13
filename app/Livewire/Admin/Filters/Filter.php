<?php

namespace App\Livewire\Admin\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class Filter
{
    /**
     * The displayable name of the filter.
     */
    public string $name;

    /**
     * The input type used to render the filter: select, date, boolean or multi.
     */
    public string $type = 'select';

    public function __construct()
    {
        $this->name = $this->name ?? class_basename(static::class);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function uriKey(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    /**
     * Apply the filter to the given query.
     */
    abstract public function apply(Request $request, Builder $query, mixed $value): Builder;

    /**
     * Get the filter's available options.
     */
    abstract public function options(Request $request): array;
}
