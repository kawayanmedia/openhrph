<?php

namespace App\Livewire\Admin\Metrics;

use Illuminate\Http\Request;

abstract class Metric
{
    public string $name = '';

    public function name(): string
    {
        return $this->name ?: class_basename(static::class);
    }

    public function uriKey(): string
    {
        return \Illuminate\Support\Str::kebab(class_basename(static::class));
    }

    /**
     * Calculate the metric and return a value to display.
     */
    abstract public function calculate(Request $request): mixed;

    /**
     * The Blade component used to render this metric.
     */
    public function component(): string
    {
        return 'value';
    }
}
